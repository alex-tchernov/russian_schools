<?
define('PATH','/home/DOCS/ogrn/fns');
require_once '/var/www/shared/unecon/db/db_class.php';
require_once __DIR__ . '/fns_api_key.php';
eqr_ogrns();

function eqr_ogrns() {
	$ogrns = ['1022400760458'];
	foreach ($ogrns as $ogrn) {
		//echo "$ogrn\n";
		req('egr',$ogrn);
	}
}

function req($method, $ogrn) {
	$fname = PATH."/{$method}_{$ogrn}.json";
	if ( file_exists($fname) ) {
		echo "EXISTS $fname\n";
		$str = file_get_contents($fname);
		$data = json_decode($str,true);		
		if (!$data) {
			echo "bad file $fname!\n";
			unlink($fname);
		}
	} else {
		$result = fns_req($method,['req'=>$ogrn]);		
		echo "$fname\n";
		file_put_contents($fname, $result);
		$result = file_get_contents($fname);
		if (!$result) {
			die("Error saving file!");
		}
	}
}

function fns_level($level  = 'school') {
	$page = 0;
	$region = '78';
	while (true) {
		$ogrns = orgns($level, $page);
		if (count($ogrns) < 50) {
			echo "too few orgns".count($ogrns)."\n";
			break;
		}
		$fname = PATH."/{$level}_{$region}_{$page}.json";
		$str_ogrns = implode(',', $ogrns);
		echo "$fname\n";
		$result = fns_req('multinfo',['req'=>$str_ogrns]);
		file_put_contents($fname, $result);
		$page++;
	}
}


function fns_req($method, $params) {
	$data = http_build_query($params+['key'=>API_KEY]);
	$url = 'https://api-fns.ru/api/'.$method.'?'.$data;
	
	//var_dump($url);

	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
//	curl_setopt($curl, CURLOPT_POST, true);
//	curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

	//curl_setopt($curl, CURLOPT_HEADER, 0);
	// grab URL and pass it to the browser
	$str = curl_exec($curl);
	$info = curl_getinfo($curl);
	$code = $info['http_code'];	
	if ($code <> 200) {
		var_dump($code);
		var_dump($info);
	}
	curl_close($curl);
	return $str;
}

function orgns(string $level, $page= 0, $region = null) {
	$db = \Unecon\DB::connect('abit2014_pk');
	$sql = "select distinct ogrn from school where true ";
	
	if ($level=='vpo') {
		$sql .= " and has_vpo = 1";
	} else if ($level=='spo') {
		$sql .= " and has_vpo = 0 and has_spo = 1 ";
	} else if ($level=='school') {
		$sql .= " and has_vpo = 0 and has_spo = 0 and has_school=1 ";
	}
	if ($region) {
		$sql .= " and kladr like '$region%' ";
	}
	$sql .= " ORDER BY ogrn ";
	$sql .= " LIMIT " .$page*100 . ",100";
	return $db->to_array($sql);
}