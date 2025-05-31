<?
require_once '/var/www/shared/dadata/dadata.php';
require_once '/var/www/shared/unecon/db/db_class.php';

$db = \Unecon\DB::connect('frdo');

$db->query("update ro_org set kladr = null, addr_md5=null, geo_lat=null, geo_lon=null,
city = null, region = null, area = null, city_district = null, settlement=null");
fill_suggest($db);
fill_clean_address($db);


// 
function fill_clean_address($db, $repeat = false) {
	$rows = $db->rows("select * from ro_org where Address<>'' and kladr is null ");
	echo count($rows) . "\n";
	foreach ($rows as $row) {
		$md5 = md5( trim($row['Address']) );
		$id = $row['id'];
		$data = [ $row['Address'] ];
		$url = "https://cleaner.dadata.ru/api/v1/clean/address";
		$file = __DIR__ . "/addr/clean_$md5.json";
		if ( file_exists($file) ) {
			$result = json_decode(file_get_contents($file), true);
			if ( $repeat && isset($result['error']) && $result['error'] == 'Forbidden' ) {
				$result = null;
				echo "was forbidden\n";
			}
			
		}
		if (!$result) {
			usleep(100*1000);
			echo ".";
			$response = dadata_query(json_encode($data), $url);
			//var_dump($response);
			file_put_contents($file, $response);
			$result = json_decode($response, true);
		}
		if ( isset($result['error']) && $result['error'] == 'Forbidden' ) {
			die("{$result['error']} Проверьте счет или связь!");
		}
		if ( isset($result[0]) ) {
			upd_kladr($db, $id, $md5, $result[0] ?? null);
		}
	}
}

function upd_kladr($db, int $id, string $md5, ?array $data) {
	if (!$data) return;
	$p = ['id'=>$id, 'md5'=>$md5];
	
	$p['kladr'] = $data['kladr_id'];
	$p['geo_lat'] = $data['geo_lat'];
	$p['geo_lon'] = $data['geo_lon'];
	$p['region'] = $data['region_with_type'];
	$p['area'] = $data['area_with_type'];
	$p['city'] = $data['city_with_type'];
	$p['city_district'] = $data['city_district_with_type'];
	$p['settlement'] = $data['settlement_with_type'];
		
	//echo "{$p['kladr']}\n";
	$db->query("update ro_org set kladr=:kladr, addr_md5=:md5, geo_lat=:geo_lat, geo_lon=:geo_lon, 
	region=:region, area=:area, city=:city, city_district = :city_district, settlement=:settlement
	where id = :id", 
		$p
	);
}

function fill_suggest($db) {
	$rows = $db->rows("select * from ro_org where Address<>'' and addr_md5 is null");
	foreach ($rows as $row) {
		$md5 = md5( trim($row['Address']) );
		$id = $row['id'];
		$data = [ 'query'=> $row['Address'] ];
		$url = "http://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address";
		$file = __DIR__ . "/addr/$md5.json";
		if ( file_exists($file) ) {
			//echo "$file\n";
			$result = json_decode(file_get_contents($file), true);
		} else {
			usleep(2*1000);
			$response = dadata_query(json_encode($data), $url);
			file_put_contents($file, $response);
			$result = json_decode($response, true);
		}
		$data = $result['suggestions'][0]['data'] ?? null;
		if ($data) {
			upd_kladr($db, $id, $md5, $data);
		}
	}
}
