<?
require_once '/var/www/shared/unecon/db/db_class.php';
require_once __DIR__ .'/parser_short_name_class.php';

define('PATH','/home/DOCS/ogrn/fns');
$files = scandir(PATH);
$db = \Unecon\DB::connect('frdo');
$parser = new ParserShortName();
foreach ($files as $file) {
//	if ( preg_match('/^egr_/' ,$file) ) {
	if ( preg_match('/^egr_1037700123152/' ,$file) ) {
		$file = PATH . '/'.$file;
		//echo "$file\n";
		$str = file_get_contents($file);
		$data = json_decode($str,true);		
		if (!$data) {
			echo "bad file $file!\n";
			unlink($file);
			continue ;
		}
		$cnt = count($data['items']);
		if ($cnt <> 1) {
			//echo "$file count $cnt\n";
			continue;
		}
		process($db, $parser, $data['items'][0]);
//		exit();
	}
}

function process($db, $parser, $item) {
	$ogrn = $item['ЮЛ']['ОГРН'];
	$has = $db->fetch_value("select count(*) from school where ogrn=:ogrn and status='HISTORY'",['ogrn'=>$ogrn]);
	if ($has) return;

//	echo "$ogrn\n";
	$history = [];
	$item_history = $item['ЮЛ']['История'];
	$first_date_from = null;
	if (isset($item_history['НаимЮЛПолн'])) {
		foreach ($item_history['НаимЮЛПолн'] as $key=>$full) {
			$short = $item_history['НаимЮЛСокр'][$key] ?? '';
			[$date_from, $date_to] = explode(' ~ ', $key);
			if (!$first_date_from) {
				$first_date_from = $date_from;
			}
			$history[$key] = ['date_from_name'=>$date_from, 'date_to_name'=>$date_to, 'full_name'=>$full, 'short_name'=>$short];
			//echo("$key $short $full\n");
		}
	}
	/*
	if (isset($item_history['НаимЮЛСокр'])) {
		foreach ($item_history['НаимЮЛСокр'] as $key=>$short) {
			if (!isset($history[$key])) {
				echo "No Full Name $ogrn!\n";
				$history[$key] = ['period'=>$key, 'full'=>null, 'short'=>$short];
			}
		}
	}
	*/
	/*
	$full = $short = null;
	foreach ($history as $elm) {
		if (empty($elm['full'])) {
			$elm['full'] = $full;
		}
		if (empty($elm['short'])) {
			$elm['short'] = $short;
		}
		$full = $elm['full'];
		$short = $elm['short'];
	}
	*/

	
	$row = $db->fetch_array("select * from school where ogrn=:ogrn and branch='MAIN'",['ogrn'=>$ogrn]);
	$history = reprocess_history($history, $row, $parser);
	
	$row['branch'] = 'MAIN_HISTORY';
	$row['status'] = 'HISTORY';
	$main_id = $row['id'];
	unset($row['id']);
	unset($row['dadata_hid']);
	unset($row['ts']);
	unset($row['actuality_date']);
	unset($row['is_new']);
	foreach ($row as $key=>$dummy) {
		if ( preg_match('/^has_/', $key) || preg_match('/_name/', $key) ) {
			unset($row[$key]);
		}
	}
	
	foreach ($history as $elm) {
		$p = $elm + $row;
		$flds = array_keys($p);
		$sql = "INSERT INTO school (".implode(',' ,$flds).") VALUES(:".implode(',:' ,$flds).")";
		$db->query($sql,$p);
	}
	
	if ($history) {
		$stop_date = new DateTime($p['date_to_name']);
		$stop_date->modify('+1 day');
		$d_from = $stop_date->format('Y-m-d');
		$db->query("UPDATE school set date_from_name=:date_from where id=:id", ['date_from'=>$d_from,'id'=>$main_id]);
	} else if ($first_date_from) {
		$db->query("UPDATE school set date_from_name=:date_from where id=:id", ['date_from'=>$first_date_from,'id'=>$main_id]);
	}
}

function reprocess_history($history, $row, ParserShortName $parser):array {
	ksort($history);
	
	$last_parsed = $last_key = $last_full_name = null;
	$current_parsed = _normalize($parser->parse($row['full_name'], $row));;
	$current_full_name = str_replace(['«','»'], ['"','"'], mb_strtoupper($row['full_name']));
	
	$array_parsed = [];
	
	// уберем повторяющиеся
	foreach ($history as $key=>$elm) {
		$array_parsed[$key] = $parsed = _normalize($parser->parse($elm['full_name'], $row));
		// К школам могли добавить что-то вообще несущественное в адресе или пробелы кавычки
		$diff = $last_parsed ? array_diff_assoc($parsed, $last_parsed) : null;
		// $skip = ($last_parsed && !$diff);
		if ($diff) {
			$history[$last_key]['date_to_name'] = $elm['date_to_name'];
			unset($history[$key]);
			continue;			
		} else {
			var_dump($diff);
		}
		$last_full_name = $elm['full_name'];
		$last_parsed = $parsed;
		$last_key = $key;
	}
	
	// уберем повторяющие текущее ниаменование
	$keys = array_keys($history);
	rsort($keys);
	foreach ($keys as $key) {
		$diff = array_diff_assoc($array_parsed[$key], $current_parsed);
		var_dump($diff);
		if ( array_diff_assoc($array_parsed[$key], $current_parsed) ) break;
		unset($history[$key]);
	}
	return $history;
}

function _normalize($elm) {
	foreach ($elm as $key=>$val) {
		if ($elm[$key])
			$elm[$key] = mb_strtolower($elm[$key]);
	}
	return $elm;
}