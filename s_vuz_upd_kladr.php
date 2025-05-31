<?
require_once '/var/www/shared/dadata/dadata.php';
require_once '/var/www/shared/unecon/db/db_class.php';

$db = \Unecon\DB::connect('dekanat');


fill_vuz_suggest($db);
//fill_clean_address($db);


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

function upd_vuz_kladr($db, int $id, ?array $data) {
	if (!$data) return;
	$p = [];
	
	$p['id'] = $id;
	$p['kladr_full'] = $data['kladr_id'];
	$p['geo_lat'] = $data['geo_lat'];
	$p['geo_lon'] = $data['geo_lon'];
		
	//echo "{$p['kladr']}\n";
	$db->query("update s_vuz set kladr_full=:kladr_full, geo_lat=:geo_lat, geo_lon=:geo_lon	where vuz_kod = :id", 
		$p
	);
}

function fill_vuz_suggest($db) {
	$rows = $db->rows("select * from s_vuz where vuz_adr is not null");
	foreach ($rows as $row) {
		$id = $row['vuz_kod'];
		$addr = $row['vuz_adr'];
		$addr = preg_replace('/ тел\..*$/ui', '', $addr);
		$addr = preg_replace('/^[0-9]+,? ?/ui', '', $addr);
		echo "$id $addr\n";
		$data = [ 'query'=> $addr];
		$url = "http://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address";
		usleep(20*1000);
		$response = dadata_query(json_encode($data), $url);
//			file_put_contents($file, $response);
		$result = json_decode($response, true);
		$count = count($result['suggestions']);
		if ($count>1) {
			echo "$id count: $count\n";
		//	continue;
		}
		$data = $result['suggestions'][0]['data'] ?? null;
		if ($data) {
			upd_vuz_kladr($db, $id,  $data);
		}
	}
}
