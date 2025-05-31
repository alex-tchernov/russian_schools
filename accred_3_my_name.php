<?
error_reporting(E_ALL);
require_once '/var/www/shared/unecon/db/db_class.php';
require_once __DIR__ .'/parser_short_name_class.php';
require_once __DIR__ .'/short_name_fn.php';

$db = \Unecon\DB::connect('frdo');
$rows = $db->rows("select * from ro_org where FullName is not null");
$parser = new ParserShortName();
$db->query("SET autocommit=0");
foreach ($rows as $row) {
	parser_set_need_remove($parser, $row['FullName']);
	$id = $row['id'];
	if ($row['Address']) {
		$addr = $row;
	} else {
		$ogrn = $row['my_ogrn'] ;
		$addr = $db->fetch_array("select * from school where ogrn=:ogrn and branch='MAIN'", ['ogrn'=>$ogrn]);
	}
	$params = $parser->parse($row['FullName'], $addr);
	$p = ['id'=>$id, 'name'=>$params['name'], 'MBOU'=>$params['MBOU'], 'fio_full'=>$params['fio_full'], 'fio_short'=>$params['fio_short']];
	$db->query("update ro_org 
		set 
		new_my_name=:name,
		_name_MBOU=:MBOU,
		_name_fio_full=:fio_full,
		_name_fio_short=:fio_short 
		where id=:id",
		$p);
}
$db->query("COMMIT");
unset($rows);

$rows = $db->rows("select * from ro_cert where FullName is not null");
foreach ($rows as $row) {
	$id = $row['id'];
	$org_row = $db->fetch_array("select * from ro_org where uid=:uid", ['uid'=>$row['OrgUid']]);
	parser_set_need_remove($parser, $row['FullName']);
	$addr = $org_row;
	
	$p = $parser->parse($row['FullName'], $addr);
	$db->query("update ro_cert SET 
		my_name=:name,
		my_name_MBOU=:MBOU,
		my_name_fio_short=:fio_short
		where id=:id",
		[ 'id'=>$id, 'name'=>$p['name'],'MBOU'=>$p['MBOU'], 'fio_short'=>$p['fio_short'] ]
	);
}
$db->query("COMMIT");
unset($rows);

$rows = $db->rows("select * from ro_cert_suppl where FullName is not null");
foreach ($rows as $row) {
	$id = $row['id'];
	$org_row = $db->fetch_array("select * from ro_org where uid=:uid", ['uid'=>$row['OrgUid']]);
	parser_set_need_remove($parser, $row['FullName']);

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

