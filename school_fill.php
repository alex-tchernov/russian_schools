<?
error_reporting(E_ALL);

require_once '/var/www/shared/dadata/dadata.php';
require_once '/var/www/shared/unecon/db/db_class.php';
require_once __DIR__ . '/short_name_fn.php';
//require_once '/var/www/shared/sql/pathc_sql.php';


$db = \Unecon\DB::connect("frdo");
fetch_ogrn('1030900915660');
exit();
//$rows = $db->rows("select distinct ogrn from  school_cert where has_frdo = 0");
$rows = $db->rows("SELECT distinct my_ogrn FROM `ro_org` WHERE my_ogrn not in (select ogrn from school);
and my_ogrn is not null");

$i = 0;
foreach($rows as $row) {
	$ogrn = $row['my_ogrn'];
	echo "$ogrn\n";
	//$ogrn = $row['ogrn'];
	fetch_ogrn($ogrn);
	usleep(10*1000);
	//if ($i++ > 1000) break;
}

/*
$ogrns = ["1037739877295"];

foreach ($ogrns as $ogrn) {
	fetch_ogrn($ogrn, $requery = true);
}
*/

function fetch_ogrn(string $ogrn, $requery = false) {
	$PATH = '/home/DOCS/ogrn';
	$url = 'http://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/party';
	$file_path = $PATH.'/'.$ogrn;
	
	if (!$requery && file_exists($file_path)) {
		$result = json_decode(file_get_contents($file_path), true);
		if (!isset($result['suggestions'])) {
			echo "BAD $ogrn\n";
			unlink($file_path);
		}
	}

	if ($requery || !file_exists($file_path)) {
		$data = [ 'query'=> $ogrn, 'type' => 'LEGAL', 'count'=>250];
		$response = dadata_query(json_encode($data), $url);
		usleep(100);
		file_put_contents($file_path, $response);
		$result = json_decode($response, true);
	}
	if (!$result['suggestions']) {
		echo "$ogrn empty \n";
		return;
	}
	$main_okogu = null;
	foreach ($result['suggestions'] as $org) {
		if ($org['data']['branch_type'] == 'MAIN') {
			$main_okogu = $org['data']['okogu'];
		}
		if (!$org['data']['okogu']) {
			$org['data']['okogu'] = $main_okogu;
		}
		parse_result($org);
	}	
}



function parse_result($org) {
	$db = \Unecon\DB::connect('frdo');

	$row = [
		'short_name' => $org['value']
		,'inn' => $org['data']['inn']
		,'ogrn' => $org['data']['ogrn']
		,'kpp' => $org['data']['kpp']
		,'dadata_hid' => $org['data']['hid']
		,'full_name' => $org['data']['name']['full_with_opf']
		,'main_okved' => $org['data']['okved']
		,'okogu' => $org['data']['okogu']
		,'opf_full' => $org['data']['opf']['full'] ?? null
		,'opf_short' => $org['data']['opf']['short'] ?? null
		,'branch' => $org['data']['branch_type']
//		,'has_okved_85_13' => null
//		,'has_okved_85_14' => null
		,'geo_lat' => $org['data']['address']['data']['geo_lat']
		,'geo_lon' => $org['data']['address']['data']['geo_lon']
		,'address' => $org['data']['address']['value']
		,'region' => $org['data']['address']['data']['region_with_type']
		,'area' => $org['data']['address']['data']['area_with_type']
		,'city' => $org['data']['address']['data']['city_with_type']
		,'city_district' => $org['data']['address']['data']['city_district_with_type']
		,'settlement' => $org['data']['address']['data']['settlement_with_type']
		,'kladr' => $org['data']['address']['data']['kladr_id']
		,'status' => $org['data']['state']['status']
		,'dadata_actuality_date' => unix_ts_to_str_date($org['data']['state']['actuality_date'])
		,'registration_date' => unix_ts_to_str_date($org['data']['state']['registration_date'])
		,'liquidation_date' => unix_ts_to_str_date($org['data']['state']['liquidation_date'])
		,'phones' => $org['data']['phones']
		,'emails' => $org['data']['emails']
	];
	// ОКВЭДы не возвращает на моем тарифе
	if ($org['data']['okveds']) {
		$row['has_okved_85_13'] = 0;
		$row['has_okved_85_14'] = 0;
		foreach ($org['data']['okveds'] as $okv) {
			echo $okv['code'] ."\n";
			if ($okv['code'] == '85.13') {
				$row['has_okved_85_13'] = 1;
			} else if ($okv['code'] == '85.14') {
				$row['has_okved_85_14'] = 1;
			}
		}
	}
	$hid = $org['data']['hid'];
	$has = $db->fetch_value("select count(*) from school where dadata_hid = :hid", ['hid'=>$hid]);
	if (!$has) {
		echo "{$row['ogrn']} {$row['short_name']} \n";
		$sql = "INSERT INTO school (". implode(", ", array_keys($row)) .") VALUE ( :".  implode(", :", array_keys($row)). ")";
		$db->query($sql, $row); 
	}
}