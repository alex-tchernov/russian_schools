<?
error_reporting(E_ALL);
require_once '/var/www/shared/unecon/db/db_class.php';
require_once __DIR__ .'/parser_short_name_class.php';

$db = \Unecon\DB::connect('frdo');
foreach (['school','spo','vpo', 'dpo', 'po'] as $level) {
	$parser = new ParserShortName();

	$db->query("SET autocommit=0");
	echo "$level\n";
	$rows = $db->rows("select * from frdo_school where level = :level", ['level'=>$level]);
	foreach ($rows as $row) {
		parser_set_need_remove($parser, $row['full_name']);

		$id = $row['id'];
		$ogrn = $row['ogrn'];
	
		$regexp = '/Филиал|отделение/ui';
		$parent_school = $db->fetch_array("select * from school where  ogrn=:ogrn and branch='MAIN'", ['ogrn'=>$ogrn]);
		
		$is_branch = preg_match($regexp, $row['full_name'] ?? '') || preg_match($regexp, $row['short_name'] ?? '');
		$parent_name = $is_branch ? $parent_school['full_name'] ?? null : null;
		$addr = null;
		if ($school_id = $row['school_id']) {
			$addr = $db->fetch_array("select * from school where id=:id", ['id'=>$school_id]);
		}
		if (!$addr) {
			$addr = $parent_school;
		}
		$p = $parser->parse($row['full_name'], $addr, $parent_name);
		$db->query("update frdo_school set 
			_my_name=:name,
			_my_name_MBOU=:MBOU,
			_my_name_fio_short=:fio_short
			where id=:id",
			[ 'id'=>$id, 'name'=>$p['name'],'MBOU'=>$p['MBOU'], 'fio_short'=>$p['fio_short'] ]
		);
	}
	$db->query("COMMIT");
}