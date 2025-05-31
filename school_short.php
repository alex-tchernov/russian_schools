<?
ini_set('memory_limit', '2048M'); // 1 GBs minus 1 MB

error_reporting(E_ALL);
require_once '/var/www/shared/unecon/db/db_class.php';
require_once __DIR__ .'/parser_short_name_class.php';
require_once __DIR__ .'/short_name_fn.php';

// Число сколько раз должна встретиться аббревиатура чтобы её счиать стандратной
$start_ts = microtime(true);
//update_my_name_new(null);
//exit();
foreach (['school'=>25,'spo'=>10,'vpo'=>4,'dpo_po'=>10] as $level=>$count) {
	//if ($level == 'spo' || $level == 'spo' || $level == 'school') continue;

	//if ($level <> 'spo') continue;
	//create_dicts($level, $count);
	update_my_name_new($level);
	//update_my_name2($level);
	//}

	echo "$level time secs ". (int) (microtime(true)-$start_ts) . "\n";
	$start_ts = microtime(true);}

function create_heroes_dict() {
	$db = \Unecon\DB::connect('frdo');
	$rows = $db->rows("select _name_fio_full, _name_fio_short from school where _name_fio_full<>''");
	foreach ($rows as $row) {
		$search = $row['_name_fio_full'];
		$short = $row['_name_fio_short'];
		
		
		$search = preg_replace('/[^А-ЯЁ-]/ui', ' ', $search);
		$search = preg_replace('/\s\s+/u', ' ', $search);
		$name_words = explode(' ', $search);
		foreach ($name_words as $word) {
			if (mb_strlen($word)<2) continue;
			if ($short && preg_match("/\b$word\b/ui", $short, $matches)) {
				$letter = mb_substr($matches[0],0,1);
				if ($letter == mb_strtoupper($letter)) {
					continue;
				}
			}
			if (!isset($words[$word])) {
				$words[$word] = ['cnt'=>0, 'ids'=>[], 'socr'=>[] ];
			}
			$words[$word]['cnt']++;
		}
	}
	
	list($dict_words, $not_found_words) = find_in_dict($words, []);
	$dict = [];
	
	$my_words = load_my_words();
	foreach ($dict_words as $k=>$v) {
		if ( $v !== mb_upper_case_first($v) && !isset($my_words[mb_strtoupper($k)]) ) {
			$export[] = "$v";
		}
	}
	
	foreach ($not_found_words as $word=>$cnt) {
		// Не найденные будут с Заглавной для читаемости приводим в такой формат
		if ( !isset($my_words[mb_strtoupper($word)]) ) {
			$export_not[] = mb_upper_case_first($word)."\t".$cnt;
		}
	}
	
	file_put_contents(__DIR__ . "/dict/tmp_not_found_words_heroes.txt", implode("\n", $export_not));
	file_put_contents(__DIR__ . "/dict/tmp_words_heroes.txt", implode("\n", $export));
}

function school_rows(?string $level=null) {
	$db = \Unecon\DB::connect('frdo');
	
	$vpo_ogrns = $db->to_array("select ogrn from frdo_school where level ='vpo'");
	$spo_ogrns = $db->to_array("select ogrn from frdo_school where  level ='spo'");
	$school_ogrns = $db->to_array("select ogrn from frdo_school where level ='school'");
	$all_ogrns = array_merge($school_ogrns, $spo_ogrns, $vpo_ogrns);
	if ($level == 'school') {
		//
		//$sql = "select * from school where  has_frdo_school<>0 and has_frdo_vpo=0 and has_frdo_spo=0  ";
		
		
		$sql = "select * from school where ogrn not in ('".implode("','", $vpo_ogrns)."') 
			and ogrn not in ('".implode("','", $spo_ogrns)."') and 
			 ogrn in ('".implode("','", $school_ogrns). "')  ";
			
	} else if ($level == 'spo') {
		$sql = "select * from school where ogrn not in ('".implode("','", $vpo_ogrns)."') 
				and  ogrn in ('".implode("','", $spo_ogrns)."') ";

	} else if ($level == 'vpo') {
		$sql = "select * from school where  ogrn in ('".implode("','", $vpo_ogrns)."')  ";
	} else if ($level == 'dpo_po') {
		$sql = "select * from school where 
				 ogrn not in ('".implode("','", $all_ogrns)."') ";
	} else {
		$sql = "select * from school where new_my_name is null";
	}
	$rows = $db->rows($sql);	
	return $rows;
}

function create_dicts(string $level, int $MIN_ABBR_COUNT=30) {
	$rows = school_rows($level);
	$i = 0;
	$words = [];
	foreach($rows as $row) {
		$parent_row = ($row['branch'] == 'MAIN') ? null : parent_org($row['ogrn']);
		$id = $row['id'];
		if ( $row['branch'] == 'MAIN' && in_array($level, ['spo','vpo']) ) {
			$search = $name = $row['full_name'];
		} else {
			$search = $name = $row['name'];
		}
		
		// Поменять!!!
		$search = $name = $row['new_my_name'];
		
		$full_name = $row['full_name'];
		$surname = find_fio($name);
		// Убираем скобки если в скобках больще 2-х символов
		
		$search = preg_replace('/[^А-ЯЁ-]/ui', ' ', $search);
		$search = preg_replace('/\s\s+/u', ' ', $search);
		$name_words = explode(' ', $search);
		foreach ($name_words as $word) {
		
			if ( mb_strlen($word)<2 ) continue;
			
			// Фамилия
			if ( in_array($word, $surname) ) continue;
			if ( word_in_address($word, $row) ) {
				continue;
			}
			
			//if ( preg_match("/\"$word\"/ui", $name) ) continue;
			if (!isset($words[$word])) {
				$words[$word] = ['cnt'=>0, 'ids'=>[], 'socr'=>[] ];
			}
			$words[$word]['cnt']++;
			$words[$word]['ids'][] = $id;

			$socr = find_abbr($word, $full_name);
			if (!$socr && $parent_row) {
				$socr = find_abbr($word, $parent_row['full_name']);
			}

			if ( $socr ) {
				if (!isset($words[$word]['socr'][$socr])) {
					$words[$word]['socr'][$socr] = ['cnt'=>0, 'ids'=>[]];
				}
				$words[$word]['socr'][$socr]['cnt']++;
				$words[$word]['socr'][$socr]['ids'][] = $id;
			}
			// Название в кавычках
		}
	}
	// Сократим данные для читаемости json
	foreach ($words as &$w) {
		if ($w['cnt'] > 10) unset($w['ids']);
		foreach ($w['socr'] as &$socr) {
			if ($socr['cnt'] > 10) unset($socr['ids']);
		}
		unset($socr);
	}
	unset($w);
	
	uksort($words, fn($a, $b) => $words[$b]['cnt']<=>$words[$a]['cnt']);

	$json = json_encode($words, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
	//echo $json;
	file_put_contents(__DIR__ ."/dict/tmp_words_{$level}.json", $json);

	$abbrs = []; // аббревиатуры
	$export_abbr = [];
	$export_all_abbr = [];
	$export = [];
	$export_not = [];
	$my_not_abbr = load_abbr_not();
	foreach ($words as $word=>$data) {
		if ($data['socr']) $abbrs[] = mb_strtoupper($word);
		
		foreach ($data['socr'] as $full=>$data) {
			$word =  mb_strtoupper($word);
			$export_all_abbr[] = "$word\t$full\t{$data['cnt']}";
			if ( $data['cnt']>$MIN_ABBR_COUNT && 
				!preg_match('/[А-Я]+ МУНИЦИПАЛЬНОГО РАЙОНА$/ui', $full) &&
				!isset($my_not_abbr[$full])
			) {
				if ( !in_array($word, $export) ) {
					$export[] = $word;
				}
				$export_abbr[] = "$word\t$full";
			}
		}
	}

	list($dict_words, $not_found_words) = find_in_dict($words, $abbrs);
	$dict = [];
	
	$my_words = load_my_words($level);
	foreach ($dict_words as $k=>$v) {
		if ( $v !== mb_upper_case_first($v) && !isset($my_words[mb_strtoupper($k)]) ) {
			$export[] = "$v";
		}
	}
	
	foreach ($not_found_words as $word=>$cnt) {
		// Не найденные будут с Заглавной для читаемости приводим в такой формат
		if ( !isset($my_words[mb_strtoupper($word)]) ) {
			$export_not[] = mb_upper_case_first($word)."\t".$cnt;
		}
	}
	
	file_put_contents(__DIR__ . "/dict/tmp_not_found_words_{$level}.txt", implode("\n", $export_not));
	file_put_contents(__DIR__ . "/dict/tmp_words_{$level}.txt", implode("\n", $export));
	file_put_contents(__DIR__ . "/dict/tmp_abbr_{$level}.txt", implode("\n", $export_abbr));
	file_put_contents(__DIR__ . "/dict/tmp_all_abbr_{$level}.txt", implode("\n", $export_all_abbr));
}




function load_abbr_not() {
	$strings = explode("\n", file_get_contents(__DIR__ . "/dict/my_not_abbr.txt"));
	$dict = [];
	foreach ($strings as $string) {
		list($short, $full) = explode("\t", $string);
		$dict[$full] = $short;
	}
	return $dict;
}

function load_common_abbr($level) {
	$fname = __DIR__ . "/dict/tmp_abbr_{$level}.txt";
	return load_abbr_file($fname);
}

function load_abbr_file($fname) {
	$strings = explode("\n", file_get_contents($fname));
	$dict = [];
	foreach ($strings as $string) {
		list($short, $full) = explode("\t", $string);
		$dict[$full] = $short;
	}
	foreach ($dict as $key=>$val) {
		$key = morpher_inflect($key, 'rod');
		$dict[$key] = $val;
	}
	// Сортитруем сначала длинные сокращения, чтобы ФГБОУ не заменть на ОУ
	uksort($dict, fn($a,$b) => mb_strlen($dict[$b]) <=> mb_strlen($dict[$a]));	
	
	return $dict;
}

function expand_abbr($level, $row) {
	static $common_abbr = [];
	if (!isset($common_abbr[$level])) {
		$common_abbr[$level] = load_common_abbr($level);
	}
	$expanded = false;
	$callback = function($matches) use($row,$level,$common_abbr,&$expanded) {
		$word = $match = $matches[0];
		$abbr = null;
		if ( mb_strlen($word)>2 ) {
			//echo "$match {$row['full_name']}\n";
			$abbr = find_abbr($match, $row['full_name']);
			
		}
		// && !isset($common_abbr[$level][$abbr]
		if ( $abbr ) {
			//echo "$abbr\n";
			if ( !preg_match('/^(муниципальное|государственное|бюджетное|каз(е|ё)нное|государственное|негосударственное|краевое|областное|автономная)/ui', $abbr) ) {
				$word = $abbr;
				//echo "EXPANDING $match $abbr\n";
				$expanded = true;
			}
		}
		return $word;
	};

	$my_name = preg_replace_callback('/\b[А-ЯЁ-][А-ЯЁ-]+/u', $callback, $row['name']);
	if ($expanded) {
		//echo "\t$my_name {$row['name']}\n";
	}
	return $my_name;
}

// Может быть не менять если результат слишком короткий?
// [13091] "БОУ ОСОШ МО Динской район" => "БОУ ОСОШ"
// 39272 => "МБОУ гимназия"


function update_my_name_new($level) {
	$db = \Unecon\DB::connect('frdo');
	$parser = new ParserShortName();
	
	$rows = school_rows($level);
	$db->query("SET autocommit=0");
	foreach($rows as $row) {
		//if ($row['branch'] <> 'MAIN') continue;
		parser_set_need_remove($parser, $row['full_name']);
		
		$id = $row['id'];
		$parent_name = null;
		if ($row['branch'] <> 'MAIN') {
			$ogrn = $row['ogrn'];
			$parent_name = $db->fetch_value("select full_name from school where ogrn=:ogrn and branch ='MAIN'", ['ogrn'=>$ogrn]);
		}
		//echo "$id {$row['full_name']} $parent_name\n";
		$params = ['id'=>$id];
		$params += $parser->parse($row['full_name'], $row, $parent_name);
		unset($params['city']);

		$db->query("UPDATE school SET _my_name = :name, 
			_name_fio_full=:fio_full, 
			_name_fio_short=:fio_short, 
			_name_fio_removed=:fio_removed, 
			_name_with_discipline=:with_discipline,
			_name_health=:health,
			_name_MBOU=:MBOU,
			_name_orphan=:orphan,
			_name_ministry=:ministry
			WHERE id=:id",
			$params
		);
	}
	$db->query("COMMIT");
}



function update_my_name($level) {
	$rows = school_rows($level);
	$common_abbr = load_common_abbr($level);

	$in_quotes = []; // Для отладки
	$dict_always_lower = load_dict(__DIR__ . '/dict/allways_lower.txt');
	
	$dict_words = load_my_dict($level);
	$i = 0;
	//echo "id\tname\tmy_name\n";
	foreach($rows as $row) {
		$i++;
		$parent_row = ($row['branch'] == 'MAIN') ? null : parent_org($row['ogrn']);
		$id = $row['id'];
		$my_name = $row['name'];
		//if ($level == 'school' || $level == 'spo') {
			// Раскроем неупотребительные аббревиатуры
			$my_name = expand_abbr($level, $row);
		//}
		//echo "Expand $my_name\n";
		$full_name = $row['full_name'];
		$call_back = function($matches) use($dict_words, $row, $parent_row, &$in_quotes, &$dict_always_lower) {
			$word = $match = $matches[0];
			if ( find_abbr($match, $row['full_name']) ) {
				// Аббревиатура исходя из полного названия
				$word =  $match;
				//echo "abbr $word\n";
			} elseif ( $parent_row && find_abbr($match, $parent_row['full_name']) )  {
				// Аббревиатура исходя из полного названия родителя
				$word =  $match;
				//echo "abbrparent $word\n";
			} elseif (false && preg_match("/\"$match\"/ui",$row['name']) )  {
				// Название одно слово в кавычках. Аббревиатура должна быть до, бывает в кавычко у СПО и ВПО
				// Вероятно, этот if больше не нужен. Ниже разбор если несколько слов в кавычках
				// Разница в проверке на dict_always_lower
				$word = mb_upper_case_first($match);
			} else if ( word_in_address($match, $row) || ($parent_row && word_in_address($match, $parent_row) ) ) {
				// Часть названия например Новое
				$word = mb_upper_case_first($match);
				//echo "word_in_address $word\n";
			} elseif ( isset($dict_words[$match]) ) {
				// Словарное слово
				$word = $dict_words[$match];
				//echo "dict_words $word\n";
				// Исключение если короткий текст в кавычках
				if ($word == mb_strtolower($word) && mb_strlen($word)>2 && !isset($dict_always_lower[$match]) ) {
					// В кавычках два или три слова
					if (preg_match("/\"([А-ЯЁ-]+\s){0,1}\b$match\b(\s[А-ЯЁ-]+){0,1}\"/ui", $row['name'])) {
						$in_quotes[$word] = $word;
						$word = mb_upper_case_first($word);
						//echo "in quotes $word\n";
						// echo "$word\n\t{$row['name']}\n";
					}
				}
			} else {
				// Всё что не нашли пишем со строчной буквы
				$word = mb_upper_case_first($match);
			}
			//echo "word '$match' $word\n";
			return $word;
		};

		
		// два и более символа
		$my_name = preg_replace_callback('/\b[А-ЯЁ-][А-ЯЁ-]+\b/u', $call_back, $my_name);
		//echo "callback $my_name\n";
		// однобуквенные с пробелами с двух сторон Д - деревня  П - поселок
		$my_name = preg_replace_callback('/\s[авискоудп]\s\b/ui', 
			fn($matches) => mb_strtolower($matches[0]),
			$my_name
		);
		$my_name = kladr_socr($my_name);
		
		$my_name = remove_area($level, $my_name, $row);
		
		// Замены имен собственных после им.
		$my_name = find_fio($my_name, replace_mode:true);
		// Поправить большие буквы в слове Героев РФ
		$my_name = replace_heroes($my_name);
		
		// Заменить Средняя Образовательная Школа на СОШ
		$my_name = replace_abbr($level, $my_name);
		
		

		// Кавычки на пробел уже после обработки ФИО		
		$my_name = preg_replace('/"/u', ' ', $my_name);
		

		
		// Пробел после номера. И перед ФМЛ№239
		$my_name = preg_replace('/(№|N)([0-9])/u', ' № $2', $my_name);
		// Два пробела на пробел 
		
		$my_name = preg_replace('/\s\s+/u', ' ', $my_name);
		
		if ( preg_match('/[,;]/u',$my_name) ) {
			$my_name = remove_repeat($my_name);
		}
		
		$my_name = remove_abbr($level, $my_name);
		
		// 
		//$my_name = preg_replace('/МБОУ/u', '', $my_name);
		
		
		// Пробел запятая после того как убрали кавычки
		$my_name = preg_replace('/\s,/u', ',', $my_name);
		$my_name = preg_replace('/\s;/u', ';', $my_name);

		
		$my_name = trim($my_name);
		do_update($id, $my_name);
		//echo "$id\t$name\t$my_name\n";
	}
	//echo implode("\n", $in_quotes);
}


// Повторы в сокр именах Колледжи и ВУЗы
function remove_repeat($name) {
	if ( preg_match('/[;]/u',$name) ){
		$glue = ';';
	} else {
		$glue = ',';
	}
	$names = explode($glue, $name);
	$first_name = $names[0];
	
	foreach($names as $i=>$part) {
		if (!$part) continue;
		$part = str_replace(['(',')','/'], ['\(','\)','\/'], trim($part));
		//echo "$part\n$first_name";
		foreach ($names as $j=>$main) {
			if ($i != $j && preg_match("/$part/ui", $main) ) {
				unset($names[$i]);
				break;
			}
			if ( preg_last_error() ) {
				echo "$name\t\n$part\n";
			}
		}
	}
	
	return trim(implode($glue, array_values($names)));
}

function do_update(int $id, string $my_name) {
	$db = \Unecon\DB::connect('abit2014_pk');
	$db->query("update school set my_name = :my_name where id=:id",['id'=>$id, 'my_name'=>$my_name]);
}

function replace_abbr(string $level, string $name): string {
	static $dict = [];
	if (!isset($dict[$level])) {
		$dict[$level] = load_common_abbr($level);
	}
	
	foreach ($dict[$level] as $full=>$short) {
		// Максируем скобки
		$full = str_replace(['(',')'], ['\(','\)'], $full);
	//	$name = preg_replace("/\b{$full}\b([^-]|$)/ui", "{$short}$1", $name);
		$name = preg_replace("/\b{$full}\b/ui", "{$short}", $name);
	}
	

	return $name;
}


function remove_abbr(string $level, string $name): string  {
	$name = preg_replace('/\s\s+/u', ' ', $name);
	$name = trim($name);
	$regexps = [];
	if ($level == 'school') {
		$regexps = [
			'^([А-Я]{1,3}ОУ|МОБУ|МБОО|МОКУ|МОАУ|НОЧУ|ГБУ ОО ЗО|МБУ|КОГОБУ|АНО|АНОО|ОАНО|Смоленское ОГБОУ|Тамбовское ОГБОУ)( (ЛНР|РД|РК|РО|СО|УР|ЯО|ШР|МО|ХО|ТО|ВО|АО|КК|ЛО|ЯО))?\b'
		];
	} else 	if (false && $level == 'spo') {
		$regexps = [
			'^((республиканское|Магаданское|Тамбовское)\s)?(ТОГБПОУ|ГОУНПО|СПб ГБ ПОУ|СОГБПОУ|СПб ГБПОУ|ФГБПОУ|КГБПОУ|КГПОБУ|КГОБУ|ОГАПОУ|ОГА ПОУ||ОАПОУ|ОГБПОУ|КГАПОУ|КГПОАУ|КГА ПОУ|КГБ ПОУ|КОГПОАУ|КОГПОБУ|КГБПОУ|ОГПОБУ|ОГОУ|ГБПОУ|ГАПОУ|ГПОАУ|ГОБПОУ|ОБПОУ|ГПОУ|ГБОУ СПО ЛНР|ГБОУ СПО|ГБОУ НПО|ГБОУ|ГАОУСПО|ГАОУ СПО|БПОУ|ГОУ НПО|ГОУ СПО|КГОУ НПО|ГОБУ НПО|ГБ ПОУ|АПОУ|ГАОУ|ГБУ)\b( (СО|ВО|МО|РО|АО|ОО|ПО|ТО|УР|ЯО|ХО|РС\s?\(Я\)|КО|КК|РД|ЛО|РА|РБ|РК|РТ|РХ|РМЭ|РМ|УР|ЯО|ИО)\s)?'
			,'^(краевое ГОУНПО|областное ГОУНПО|ГБП ОУ|ОГБОУ|ЧПОУ|АНПОО|АНО ПОО|АНОО ПО|ПО АНО|АНО ПО|АН ПОО|КГБОУ|ГОУ|ПОЧУ|ПО АНО|БОУ ОО НПО|БОУ ОО СПО|ЧОУ ПО|ЧПОУ|ФГБУ|ФГОУ|ФКПОУ|БОУ|БУ|АУ)\b'
			,'\b(СПО|НПО|ПОО|ПОУ|ЧУ ПО|ЧПОУ|ЧОУ|НОУ|ЧУ|БОУ)\s'
			,'\(ССУЗ\)'
		
		];

	}
	foreach ($regexps as $regexp) {
		$name = preg_replace("/$regexp/u", '', $name);
	}
	$name = preg_replace('/\s\s+/u', ' ', $name);
	$name = trim($name);
	return $name;
}



function parent_org($ogrn) {
	$db = \Unecon\DB::connect('abit2014_pk');

	static $parents = [];
	if ( !isset($parents[$ogrn]) ) {
		$sql = "select * from school where ogrn=:ogrn and branch='MAIN'";
		$parents[$ogrn] = $db->fetch_array($sql, ['ogrn'=>$ogrn]);
	}
	return $parents[$ogrn];
}

function find_hyphen_word($word, &$dic, &$abbrs): ?string {
	$parts = explode('-', $word);
	if ( count($parts) == 1 ) return null;
	
	$hyphen_word = null;
	$found = true;
	foreach ($parts as &$part) {
		if (mb_strlen($part)<3) {
			$found = false;
			break;
		}
		if ( in_array($part, $abbrs) ) continue;

		//Если часть слова с прописной - не будем включать в словарь
		if ( isset($dic[$part]) && $dic[$part] == mb_strtolower($dic[$part]) ) {
			$part = $dic[$part];
			continue;
		}

		
		$found = false;
		break;
	}
	if ($found) {
		$hyphen_word = implode('-', $parts);
	}
	return $hyphen_word;
}

function find_in_dict($words, $abbrs) {
	$dic = load_rus_dic();
	$surnames = load_rus_surnames();
	$ngodata_names = load_ngodata_names();
	
	// echo "DIC ". count($dic) ."\n";
	$found_words = [];
	$not_found_words = [];
	foreach ($words as $word=>$data) {
		
		if ($data['socr']) continue;
		$upper_word = mb_strtoupper($word);
		if ( isset($dic[$upper_word]) ) {
			$found_words[$word] = $dic[$upper_word];
			continue;
		} 
		
		if (isset($surnames[$word])) continue;
		if (isset($ngodata_names[$word])) continue;
		
		$hyphen_word = find_hyphen_word($word, $dic, $abbrs);
		if ($hyphen_word) {
			$found_words[$word] = $hyphen_word;
			continue;
		}
		$not_found_words[$word] = $data['cnt'];
	}
	
	foreach ($found_words as $key=>$val) {
	//	echo "$key $val\n";
	}
	foreach ($not_found_words as $k=>$w) {
		// echo "$k $w\n";
	}
	return [$found_words, $not_found_words];
}

function load_rus_dic() {
	return load_dict(__DIR__ . '/russian-words/russian.utf-8');
}



function find_abbr($word, $text) {
	if ( mb_strlen($word)<2 ) return null;
	if ( in_array($word , ['ИМ','СТ']) ) return;
	$result = null;
	$reg = [];
	for ($i=0; $i<mb_strlen($word); $i++) {
		$char = mb_substr($word, $i, 1);
		$reg[] = "\b{$char}\w*";
	}
	$regexp = '/'. implode("\W+",$reg).'/ui';
	//echo "$regexp $text\n";
	if (preg_match($regexp, $text, $matches)) {
		//echo "FOUND\n";
		$result = $matches[0];
		//echo "$word $result\n";
	}
	
	if (!$result) {
		// Возможно с союзом "и" или "с" который не вошел в сокращение
		$regexp = '/'. implode("\W+(И|C|\W+)*",$reg).'/ui';
		if (preg_match($regexp, $text, $matches)) {
			//echo "FOUND\n";
			$result = $matches[0];
			//echo "$word $result\n";
		}
	}
	return $result;
}



/* Дополняет сокрщаения в адресе до полных для склонения */
function full_addr($text) {
	static $hash = [];
	if ( !isset($hash[$text]) ) {
		$replaces = [
			"ст-ца"=>"станица"
			,"г"=>"город"
			,"пгт"=>"поселок"
			,"рп"=>"поселок"
			,"р-н"=>"район"
			,"тер"=>"территория"
		];
		$result = $text;
		foreach ($replaces as $short=>$full) {
			$result = preg_replace("/\b$short\b/ui", "$full", $result);
		}
		$hash[$text] = $result;
		
	}
	//echo $hash[$text] . "\n";
	return $hash[$text];
}



function word_in_address($word, $row) {
	static $socr_words = [];
	if (!$socr_words) {
		$socr_words =  explode("\n", file_get_contents(__DIR__ ."/dict/addr_socr.txt"));
	}
	$regexp = '/^('.implode('|',$socr_words).')$/ui';
	if (preg_match('/^('.implode('|',$socr_words).')$/ui', $word)) {
		
		return false;
	}
	foreach (['area', 'city', 'settlement', 'region','city_district'] as $fld) {
		if (!$row[$fld] ) continue;
		if ( preg_match("/\b{$word}\b/ui", $row[$fld]) ) return true;
		
		// Родительный падеж
		$subject = morpher_inflect(full_addr($row[$fld]), 'rod');
		if ( preg_match("/\b{$word}\b/ui", $subject) ) return true;
		
		// Прилагательные
		$search = preg_replace('/(ОВ)*СК(АЯ|ОГО|ОЕ|ИЙ|ОЙ)$/ui','', $word);
		if ( preg_match("/\b{$search}[А-Я]{0,4}\b/ui", $row[$fld]) ) {
			// echo "FOUND $word\n";
			return true;
		}

		$search = preg_replace('/(АЯ)$/ui','', $word);
		if ( preg_match("/\b{$search}[А-Я]{0,2}\b/ui", $row[$fld]) ) return true;
		/*
		//Свердловска
		$search = preg_replace('/А$/ui','', $word);
		if ( preg_match("/г \b{$search}\b/ui", $address) ) return true;
		*/
	}
	return false;
}

/*
function rus_dic() {
	$words = explode("\n", file_get_contents(__DIR__ . '/russian.dic'));
	$dic = [];
	foreach ($words as $word) {
		$dic[mb_strtoupper($word)] = $word;
	}
	return $dic;	
}
*/

	/*
	Заменяет имени на им.
	Капитализурет буквы. Если инициалы перед ФИО, то без пробела
	*/
	function find_fio(string $subject, $replace_mode=false) {
		static $dict_surnames = [], $dict_ngodata_surnames = [], $dict_ngodata_names = [], $dict_heroes;
		
		if (!$dict_surnames) {
			$dict_surnames = load_dict(__DIR__ . '/russian-words/russian_surnames.utf-8');
			$dict_ngodata_surnames = load_ngodata_names('surnames');
			$dict_ngodata_names = load_ngodata_names(['names','midnames']);
			$dict_heroes = load_heroes();
		}
		
		$surnames = [];
		
		
		$im_word = '\bим\.|\bимени\b|\bим\b';
		if ( !preg_match("/($im_word)/iu", $subject) ) {
			return $replace_mode ? $subject : [];
		}
		$subject = preg_replace("/($im_word)\s*/iu", "им. ", $subject);
		$reg_heroes = "(?P<heroes>(".implode('|', $dict_heroes).'|\s)+)';
		// Может быть двойное А-И.}
		$im_part = '[А-Я](-[А-Я])?\.';
		$reg_im = "(?P<im>\b{$im_part}\s*({$im_part})?)";
		// Вариант оба иницала обязательны. 
		// Иначе можно склеить например Алексей Чернов с г.Ленинград с г и закапсить букву
		$reg_im_must = "(?P<im>\b{$im_part}\s*{$im_part})";
		$reg_fam = "(?P<fam>[А-ЯЁ][А-ЯЁ-]+)";
		$reg_other = "(?P<other>[^\"]*?)";
		
		$els = [
			 ['regexp'=>"/\bим.\s*{$reg_im}\s*{$reg_fam}/ui", 'fam_first'=>false, 'use_dict'=>false]
			,['regexp'=>"/\bим.\s*{$reg_heroes}*{$reg_im}\s*{$reg_fam}/ui", 'fam_first'=>false, 'use_dict'=>false]
			,['regexp'=>"/\bим.{$reg_other}{$reg_im_must}\s*{$reg_fam}/ui", 'fam_first'=>false, 'use_dict'=>true]
			,['regexp'=>"/\bим.\s*{$reg_fam}\s{$reg_im}(\"|$)/ui", 'fam_first'=>true, 'use_dict'=>false]
			,['regexp'=>"/\bим.\s*{$reg_heroes}+{$reg_fam}\s{$reg_im}(\"|$)/ui", 'fam_first'=>true, 'use_dict'=>false]
			,['regexp'=>"/\bим.\s*{$reg_fam}\s{$reg_im}/ui", 'fam_first'=>true, 'use_dict'=>true]
			,['regexp'=>"/\bим.{$reg_other}\s*{$reg_fam}\s{$reg_im_must}/ui", 'fam_first'=>true, 'use_dict'=>true]
		];
		
		$is_fam_first = false;
		
		$callback = function($matches) use(&$is_fam_first) {
			$im = mb_strtoupper(preg_replace('/ /u', '', $matches['im']));
			$fam = mb_upper_case_first($matches['fam']);
			$response = "им.";
			if ( isset($matches['heroes']) ) $response .= ' '.$matches['heroes'];
			if ( isset($matches['other']) ) $response .= ' '.$matches['other'];
			$response .= $is_fam_first ? " {$fam} {$im}" : " {$im}{$fam}";
			return  $response;
		};
		
		foreach($els as $el) {
			$is_fam_first = $el['fam_first'];
			
			if (  preg_match($el['regexp'], $subject, $matches) ) {
				$fam = mb_strtoupper($matches['fam']);
				if (!$el['use_dict'] ||  isset($dict_surnames[$fam]) || isset($dict_ngodata_surnames[$fam]) ) {
					$surnames[] = $fam;
					if ($replace_mode) {
						$subject = preg_replace_callback($el['regexp'], $callback ,$subject);
					}
					break;
				}
			}
		}

		if (!$surnames) {
			// Не нашли сокращений пробуем найти имена
			$regexp="/\bим.\s*($reg_heroes)*(?<fio>[^\"]*)(\"|$)/ui";
			if ( preg_match($regexp, $subject, $matches) ) {
				$names = explode(" ", $matches['fio']);
				$found = true;
				foreach ($names as &$name) {
					$im = mb_strtoupper($name);
					$found = isset($dict_surnames[$im]) || isset($dict_ngodata_surnames[$im]) || isset($dict_ngodata_names[$im]);
					if ($found) {
						$name = mb_upper_case_first($name);
					} else {
						//echo "NO NAME $name\n";
						break;
					}
				}
				if ($found) {
					//echo "FOUND $subject \n";
					$subject = preg_replace("/\b{$matches['fio']}\b/ui", implode(' ', $names), $subject);
					$surnames = array_map( fn($name) => mb_strtoupper($name), $names);

					/*
					$str_heroes = $matches['heroes'] ?? null;
					if ($str_heroes) {
						$subject = preg_replace("/\b{$str_heroes}\b/ui", process_heroes($str_heroes), $subject);
					}
					*/
					//echo "NEW   $subject \n\n";
				} else {
					if (defined('DEBUG') && DEBUG) {
						echo "NOT FOUND fio in $subject\n";
					}
				}
			}
		}
		
		return $replace_mode ? $subject : $surnames;
	}

function load_heroes() {
	return load_dict(__DIR__ . '/dict/heroes.txt');
}