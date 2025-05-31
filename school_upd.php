<?
error_reporting(E_ALL);

require_once '/var/www/shared/dadata/dadata.php';
require_once '/var/www/shared/unecon/db/db_class.php';
require_once __DIR__ . '/short_name_fn.php';

//require_once '/var/www/shared/sql/pathc_sql.php';
$db = \Unecon\DB::connect('frdo');

$PATH = '/home/DOCS/ogrn';

$rows = $db->rows("select distinct ogrn from school where okogu is null");
$url = 'http://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/party';
$i = 0;
$db->query("SET autocommit=0");
foreach ($rows as $row) {
	$ogrn = $row['ogrn'];
	// Иностранные организации
	if ( preg_match('/^id-/', $ogrn) ) continue;
	$file_path = $PATH.'/'.$ogrn;
	if (file_exists($file_path)) {
		$result = json_decode(file_get_contents($file_path), true);
		if (!isset($result['suggestions'])) {
			echo "BAD $ogrn\n";
			continue;
		}
	} else {
		echo "NO FILE $ogrn\n";
		$data = [ 'query'=> $ogrn, 'type' => 'LEGAL', 'count'=>250];
		$response = dadata_query(json_encode($data), $url);
		usleep(100);
		file_put_contents($file_path, $response);
		$result = json_decode($response, true);
	}

	if (!$result['suggestions']) {
		echo "$ogrn empty \n";
		continue;
	}
	$main_okogu = null;
	foreach ($result['suggestions'] as $org) {
		$p = [];
		
		if ($org['data']['branch_type'] == 'MAIN') {
			$main_okogu = $org['data']['okogu'];
		}
//		$okogu = $org['data']['okogu'];
		$p['dadata_hid'] = $org['data']['hid'];
//		$p['okogu'] = $org['data']['okogu'] ?? $main_okogu;
		$p['registration_date'] = unix_ts_to_str_date($org['data']['state']['registration_date']);
		$sql = "update school set registration_date = :registration_date where dadata_hid=:dadata_hid";
		$db->query($sql, $p);
		//parse_result($db, $org);
	}
	//if ($i++ > 1000) break;
}
$db->query("COMMIT");
