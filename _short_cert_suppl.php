<?
error_reporting(E_ALL);
require_once '/var/www/shared/unecon/db/db_class.php';
require_once __DIR__ .'/parser_short_name_class.php';
require_once __DIR__ .'/short_name_fn.php';

$db = \Unecon\DB::connect('frdo');
$parser = new ParserShortName();

$db->query("SET autocommit=0");
$rows = $db->rows("select * from ro_cert_suppl where FullName is not null");
foreach ($rows as $row) {
	$id = $row['id'];
	$org_row = $db->fetch_array("select * from ro_org where uid=:uid", ['uid'=>$row['OrgUid']]);
	parser_set_need_remove($parser, $row['FullName']);
	/*

	$regexp = '/Филиал|отделение/ui';
	$parent_school = $db->fetch_array("select * from school where ogrn=:ogrn and branch='MAIN'", ['ogrn'=>$ogrn]);
	
	$is_branch = preg_match($regexp, $row['full_name'] ?? '') || preg_match($regexp, $row['short_name'] ?? '');
	$parent_name = $is_branch ? $parent_school['full_name'] ?? null : null;
	if ($school_id = $row['school_id']) {
		$addr = $db->fetch_array("select * from school where id=:id", ['id'=>$school_id]);
	} else {
		$addr = $parent_school;
	}
	*/
	$addr = $org_row;
	
	$p = $parser->parse($row['FullName'], $addr);
	$db->query("update ro_cert_suppl SET 
		my_name=:name,
		my_name_MBOU=:MBOU,
		my_name_fio_short=:fio_short
		where id=:id",
		[ 'id'=>$id, 'name'=>$p['name'],'MBOU'=>$p['MBOU'], 'fio_short'=>$p['fio_short'] ]
	);
}
$db->query("COMMIT");
