<?
error_reporting(E_ALL);
require_once '/var/www/shared/unecon/db/db_class.php';
require_once __DIR__ .'/parser_short_name_class.php';
require_once __DIR__ .'/short_name_fn.php';

$db = \Unecon\DB::connect('frdo');
$parser = new ParserShortName();
$db->query("UPDATE ro_cert_suppl set school_id = null");

$db->query("UPDATE ro_cert_suppl suppl inner join hand_cert_suppl_school hand 
on suppl.Uid = hand.SupplUid set suppl.school_id = hand.school_id");




$regexp = '[^А-ЯЁа-яё0-9]';
$join_cert = " ro_cert_suppl suppl 
inner join ro_cert cert ON suppl.CertUid = cert.Uid 
inner join school s ON cert.OGRN = s.ogrn ";

$join_org = " ro_cert_suppl suppl 
inner join ro_cert cert ON suppl.CertUid = cert.Uid 
inner join ro_org o ON suppl.OrgUid = o.Uid 
inner join school s ON o.my_ogrn = s.ogrn ";


// По FullName и ShortName
$join_on = "
AND REGEXP_REPLACE(suppl.FullName, '$regexp','') = REGEXP_REPLACE(s.full_name, '$regexp','')
AND REGEXP_REPLACE(suppl.ShortName, '$regexp','') = REGEXP_REPLACE(s.short_name, '$regexp','')
";
suppl_set_school_id($db, $join_cert.$join_on);
suppl_set_school_id($db, $join_org.$join_on);

// По FullName
$join_on = "
AND REGEXP_REPLACE(suppl.FullName, '$regexp','') = REGEXP_REPLACE(s.full_name, '$regexp','')
";
suppl_set_school_id($db, $join_cert.$join_on);
suppl_set_school_id($db, $join_org.$join_on);

$join_my_name = "AND REGEXP_REPLACE(suppl.my_name, '$regexp','') = REGEXP_REPLACE(s.new_my_name, '$regexp','')";

// my_name, MOBU, fio
$join_on = "
$join_my_name
AND IFNULL(suppl.my_name_MBOU,'') = IFNULL(s._name_MBOU,'')
AND IFNULL(suppl.my_name_fio_short,'') = IFNULL(s._name_fio_short,'')
";

suppl_set_school_id($db, $join_cert.$join_on);
suppl_set_school_id($db, $join_org.$join_on);
// my_name, fio

$join_on = "
$join_my_name
AND IFNULL(suppl.my_name_fio_short,'') = IFNULL(s._name_fio_short,'')
";
suppl_set_school_id($db, $join_cert.$join_on);
suppl_set_school_id($db, $join_org.$join_on);

// my_name
suppl_set_school_id($db, $join_cert.$join_my_name);
suppl_set_school_id($db, $join_org.$join_my_name);


$rows = $db->rows("select suppl.id suppl_id, suppl.my_name, s.new_my_name as school_my_name, s.id as school_id 
FROM ro_cert_suppl suppl 
inner join ro_org o ON suppl.OrgUid = o.Uid 
inner join school s ON o.my_ogrn = s.ogrn 
WHERE suppl.school_id is null AND suppl.my_name IS NOT NULL
ORDER BY suppl.id, branch desc, date_from_name desc ");

$suppl_id = 0;
$found = false;
foreach ($rows as $row) {
	if ($row['suppl_id'] <> $suppl_id) {
		$suppl_id = $row['suppl_id'];

		$suppl_hash = my_hash($row['my_name']);
		//echo "$suppl_hash\n";
		$found = false;
	}
	if (!$found && my_hash($row['school_my_name']) == $suppl_hash ) {
		$found = true;
		echo ".";
		$db->query("update ro_cert_suppl set school_id = :school_id where id = :id", 
			['school_id'=>$row['school_id'], 'id'=>$suppl_id]
		);
	}
}

/*
// Переставим действующие свидетельства со ссылками ведующие на историю, где название в заголовке сертификата не совпадает с приложением 
// (и приложении ссылается на историческое название).
// в pdf название берется из cert не из suppl ( https://islod.obrnadzor.gov.ru/accredreestr/details/8575cc10-5427-93ad-80be-da2f1242fdf8/
// Сюда могут попасть и лишние СОШ ставшие ООШ, для которых эти п..ры не не ввели новую запись

// ЗЫ всего 56 действующих и большая часть не адекватна ((
$y = date('Y');		

$sql = "UPDATE ro_cert_suppl suppl 
INNER JOIN ro_cert cert ON suppl.CertUid = cert.Uid
INNER JOIN school s ON suppl.school_id = s.id
INNER JOIN school AS s1 ON s.ogrn = s1.ogrn and s.branch='MAIN_HISTORY' AND s_1.branch = 'MAIN'
WHERE suppl.my_name<>cert.my_name AND cert.my_name=s_1.my_name AND suppl.StatusName='Действующее'
			AND cert.StatusName = 'Действующее' AND (cert.EndDate is null or cert.EndDate>'$y-01-01')
SET suppl.school_id = s_1.id";
$db->query($sql);
*/


function my_hash($name) {
	$name = preg_replace('/\b(СОШ|Средняя образовательная школа)\b/ui', 'СШ', $name);
	$name = preg_replace('/\b(ст-ца|ст-цы)\b/ui', 'ст', $name);
	$name = preg_replace('/ё/ui', 'е', $name);
	$name = preg_replace('/[^А-ЯA-Z0-9]/ui', '', $name);
	$name = mb_strtoupper($name);
	return $name;
}

function suppl_set_school_id($db, $pre_sql) {
	$db->query("DROP TEMPORARY TABLE IF EXISTS tmp_school_id");
	
	$sql = "CREATE TEMPORARY TABLE tmp_school_id AS SELECT suppl.id as suppl_id, max(s.id) school_id 
		FROM $pre_sql 
		WHERE suppl.school_id is NULL AND s.status <> 'HISTORY'
		GROUP BY suppl.id
		HAVING COUNT(s.id)=1";
	$db->query($sql);
	
	$db->query("UPDATE ro_cert_suppl suppl 
	INNER JOIN  tmp_school_id t ON suppl.id = t.suppl_id
	SET suppl.school_id = t.school_id
	WHERE suppl.school_id is NULL ");

	$db->query("DROP TEMPORARY TABLE IF EXISTS tmp_school_id");
	
	$sql = "CREATE TEMPORARY TABLE tmp_school_id AS SELECT suppl.id as suppl_id, s.ogrn, s.new_my_name, MAX(date_from_name)  d_from
		FROM $pre_sql 
		WHERE suppl.school_id is NULL AND s.status = 'HISTORY' AND cert.IssueDate >= s.date_from_name AND cert.IssueDate <= s.date_to_name
		GROUP BY suppl.id
		";
	$db->query($sql);
	
	$db->query("UPDATE ro_cert_suppl suppl 
	INNER JOIN  tmp_school_id t ON suppl.id = t.suppl_id
	INNER JOIN school s ON t.ogrn = s.ogrn AND t.new_my_name = s.new_my_name AND t.d_from = s.date_from_name
	SET suppl.school_id = s.id
	WHERE suppl.school_id is NULL ");

}