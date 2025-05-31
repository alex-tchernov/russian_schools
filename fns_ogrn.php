<?
define('API_KEY', 'b35f9844eac8bbb4e48582021dde669978c65721');
define('PATH','/home/DOCS/ogrn/fns');
require_once '/var/www/shared/unecon/db/db_class.php';
$page = 1;
$level  = 'school';
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