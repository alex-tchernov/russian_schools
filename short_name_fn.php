<?
require_once __DIR__ .'/parser_short_name_class.php';

function unix_ts_to_str_date($ts) {
	if ($ts) {
		return date('Y-m-d', $ts/1000);
	}
	return null;
}

function mb_upper_case_first(string $str): string {
	//if ($str == '-') return $str;
	$parts = explode('-', $str);
	foreach ($parts as &$part) {
		 $part = mb_strtoupper(mb_substr($part,0,1)) . mb_strtolower(mb_substr($part,1));
	}
	$str =  implode('-', $parts);
	// Ростов-на-Дону в адресе
	$str = preg_replace('/-на-/ui', '-на-', $str);
	return $str;
}

function load_dict(string $fname) {
	$words = explode("\n", file_get_contents($fname));
	$dic = [];
	foreach ($words as $word) {
		$dic[mb_strtoupper($word)] = $word;
	}
	return $dic;	
}

function file_to_assoc(string $fname) {
	$lines = explode("\n", file_get_contents($fname));
	$dict = [];
	foreach ($lines as $line) {
		$words = explode("\t", $line);
		$key = $words[0];
		$val = $words[1] ?? $key;
		$dict[mb_strtoupper($key)] = $val;
	}
	return $dict;
}

function dash_regexp() {
	return "(-|–|—)";
}

function load_rus_surnames() {
	$words = explode("\n", file_get_contents(__DIR__ . '/russian-words/russian_surnames.utf-8'));
	$dic = [];
	foreach ($words as $word) {
		$dic[mb_strtoupper($word)] = $word;
	}
	return $dic;	
}

function load_my_words(string $level='') {
	// Разделил на 3 для читаемости
	$types = ['','_spo','_vpo'];
	// Порядок важен ВО в VPO и во в обчныом
	if ($level == 'spo') {
		$types = ['_spo',''];
	} else if ($level == 'vpo') {
		$types = ['_vpo',''];
	}
	$dict = [];
	foreach ($types as $type) {
		$dict += load_dict(__DIR__ . "/dict/my_words{$type}.txt");
	}

	return $dict;
}

function load_ngodata_names($type = null) {
	$dic = [];
	if (!$type) {
		$types = ['names', 'midnames', 'surnames'];
	} else {
		$types = is_array($type) ? $type : [$type];
	}
	
	foreach ($types as $type) {
		$strings = explode("\n", file_get_contents(__DIR__ . '/ngodata.ru/'.$type.'_table.jsonl'));
		foreach ($strings as $string) {
			$row = json_decode($string, true);
			$name = $row['text'] ?? '';
			$gender = $row['gender'] ?? '';
			if ($name) {
				$name = mb_strtoupper(morpher_inflect($name, 'rod'));
				$dic[$name] = $name;
			}
		}
	}
	return $dic;	
}

function parser_set_need_remove(ParserShortName $parser, string $full_name) {
	$need_remove = preg_match('/\b(школа|школы|детский сад|центр образования|учебно-воспитательный комплекс|образовательный центр|образовательный комплекс|Гимназия|Лицей|СШ|СОШ|ООШ|НОШ)\b/ui', $full_name);
	//echo "$need_remove $full_name\n";
	$parser->set_need_remove_fio($need_remove);
	$parser->set_need_remove_addr($need_remove);
	if (!$need_remove) {
		// Администрация р-на или Местное отделение ДОСААФ оставим
		$need_remove_area = !preg_match('/^(Администрация|Управление|Местное отделение|ФИЛИАЛ ГБУ РС \(Я\) ЦСППСИМ)\b/ui',$full_name);			
		$parser->set_need_remove_area($need_remove_area);
	}
}

function mask_reg(string $str): string {
	$masked = $str;
	$masked = preg_replace('#\.#u','\.', $masked);
	$masked = preg_replace('#\(#u','\(', $masked);
	$masked = preg_replace('#\)#u','\)', $masked);
	$masked = preg_replace('#/#u','\/', $masked);
	return $masked;
}
	
function reg_word_start() {
	// Следующий символ -- не словарный или граница
	return "((?=\W)|\b)";
}

function reg_word_end() {
	// Последний символ -- не словарный или граница
	return "((?<=\W)|\b)";
}



function preg_word_match($regexp, $string, &$matches=[]) {
	$start = reg_word_start();
	$end = reg_word_end();
	$r = "/{$start}{$regexp}{$end}/ui";
	$result =  preg_match($r, $string, $matches);
	//echo "$result $r '$string' \n";
	return $result;
}

function preg_word_replace($regexp, $replace, $string) {
	$start = reg_word_start();
	$end = reg_word_end();
	return preg_replace("/{$start}{$regexp}{$end}/ui", $replace, $string);
}