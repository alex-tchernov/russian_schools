<?
error_reporting(E_ALL);
require_once '/var/www/shared/unecon/db/db_class.php';
require_once __DIR__ .'/fio_parser_class.php';
require_once __DIR__ .'/short_name_fn.php';
$db = \Unecon\DB::connect('frdo');

$parser = new FioParser();
$db->query("SET autocommit=0");
$db->query("update school set _name_fio_short = null where _name_fio_short is not null");

$rows = $db->rows("select * from school where _name_fio_full is not null and _name_fio_short is null");
foreach ($rows as $row) {
	$id = $row['id'];
	$result = $parser->parse('имени ' .$row['_name_fio_full']);
	if ($result['short']) {
		$short = $result['short'];
		echo "$id\t $short\n";
		$db->query(
			"UPDATE school SET _name_fio_short = :short WHERE id=:id",
			['id'=>$id, 'short'=>$short,'end'=>$end]
		);
	}
}
$db->query("COMMIT");