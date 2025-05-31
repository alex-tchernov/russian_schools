<?
error_reporting(E_ALL);
require_once '/var/www/shared/unecon/db/db_class.php';
require_once __DIR__ .'/parser_short_name_class.php';
require_once __DIR__ .'/short_name_fn.php';

$db = \Unecon\DB::connect('frdo');
$db->query("update school set my_name=null  ");
fill_branch_name($db);
$db->query("update school set my_name=_my_name where my_name is null ");

function fill_branch_name($db) {
	//$sql = "select distinct ogrn from school where branch<>'MAIN' and id in (1326,1598,27222,27178)";
	$sql = "select distinct ogrn from school where branch<>'MAIN'";

	$ogrns = $db->to_array($sql);
	$rows = $db->rows("select * from school where ogrn in (".implode(',',$ogrns).") order by ogrn, branch desc, date_from_name desc ");
	$ogrn = '';
	$found = 0;

	$parser = new ParserShortName();
	$parser->set_morpher_inflect('rod');
	$names_for_branch = $db->to_array("select school_id, short_name from school_short_name_for_branch");

	foreach ($rows as $row) {
	//	echo "{$row['id']}\n";
		if ($ogrn <> $row['ogrn']) {
			$ogrn = $row['ogrn'];
			$regexps = [];
		}
		$school_id = $row['id'];
		if ( in_array($row['branch'], ['MAIN','MAIN_HISTORY']) ) {
			if (isset($names_for_branch[$school_id])) {
				$replace_name =  $names_for_branch[$school_id];
			} else {
				if ($row['_name_MBOU']) {
					$replace_name =  '«' .$row['_name_MBOU'].' '.$row['_my_name']. '»';
				} else {
					$replace_name =  '«' .$row['_my_name']. '»';
				}
			}
			if ($row['branch'] == 'MAIN') {
				$main_row = $row;
				$full_name = $row['full_name'];
				$main_replace_name = $replace_name;
			}
			foreach (name_regexps($row, $parser) as $r) {
				$regexps[] = [$r,$replace_name];
			}
		}
		if ( $row['branch'] == 'BRANCH') {
			$found = false; 
			foreach ($regexps as $el) {
				$regexp = $el[0];
				$replace = $el[1];
				if (!$found) {
					$found = preg_match("/$regexp/ui", $row['_my_name']);
					if (preg_last_error()) {
						echo "ERROR $regexp\n";
					}
				}
				if (!$found && $row['_name_MBOU']) {
					$found = preg_match("/$regexp/ui", $row['_name_MBOU'] .' '.$row['_my_name']);
				}
				if ($found) {
					if ($branch = branch_name($row['_my_name'], $regexp) ) {
						$new_name = "NEW ".$branch . " - филиал $replace";
					} else {
						$new_name = preg_replace("/$regexp/ui", $replace, $row['_my_name']);
					}
					break;
				}
			}
			
			if (!$found) {
				if ( no_main_name($row) ) {
					$found = true;
					if ( preg_match('/ филиал$/ui', $row['_my_name']) ) {
						// Ленинградский филиал
						$new_name .= ' '.$main_replace_name;
					} else {
						
						$new_name = trim(preg_replace('/филиал/ui', '', $row['_my_name']));
						// Уберем слово филиал и добавим перед ним запятую
						$new_name .= ' - филиал '.$main_replace_name;
					}
				//	$new_name .= ' NO филиал '.$main_replace_name;
					
				}
			}
			if ($found) {
				echo ".";
				$db->query("update school set my_name=:new_name where id = :id", ['id'=>$row['id'], 'new_name'=>$new_name]);
			}
		}
	}
}

function branch_name($my_name, $main_reg) {
	$branch_name = null;
	$regexps = [
		"/^Филиал $main_reg( ?- ?| )(?<branch>[А-Я]+ская (ООШ|СОШ|НОШ))$/ui"
		,"/^Филиал $main_reg( ?- ?| )(?<branch>(ООШ|СОШ|НОШ) (д|п|с)\.[А-Я]+)$/ui"
	];
	foreach ($regexps as $reg) {
		//echo "'$reg'\n";
		//echo "'$my_name'\n";
		if ( preg_match($reg, $my_name, $matches) ) {
			$branch_name = $matches['branch'];
			//echo "FOUND $branch_name\n\n";
			break;
		}
	}
	return $branch_name;
}

function no_main_name($row) {
	$name = $row['_my_name'];
	$has = false;
	$settl_type = '(с\.|д\.|п\.)';
	$child_org_names = ['Буратино','Теремок','Ёлочка','Светлячок','Аленушка','Улыбка','Ромашка','Тополёк','Дружные ребята','Красная шапочка','Золотой колосок'];
	$r_child_names = '('.implode('|',$child_org_names).')';
	$schools = "(СОШ|ООШ|НОШ|школа|СШ|основная школа|начальная школа)";
	$has = $has || preg_match('/^[А-Я][А-ЯЁ-]+ский филиал$/ui', $name);
	$has = $has || preg_match("/^(Филиал )?[А-Я][А-ЯЁ-]+(ская|цкая) {$schools}$/ui", $name);
	$has = $has || preg_match("/^(Филиал )?($schools) {$settl_type}[А-Я][А-Я-]+$/ui", $name);
	$has = $has || preg_match("/(Филиал )?^Детский сад ($settl_type)?[А-Я][А-ЯЁ-]+$/ui", $name);
	$has = $has || preg_match("/^(Филиал )?[А-Я][А-ЯЁ-]+ский Детский сад( $r_child_names)?$/ui", $name);
	return $has;
}

function name_regexps(array $row, ParserShortName $parser):array {
	$names = [];

	parser_set_need_remove($parser, $row['full_name']);
	$parser->set_morpher_inflect('');
	$parser->set_need_remove_addr(false);
	$parser->set_need_remove_area(true);
	// оставилии город
	$names[] = $parser->make_short_name($row['full_name'], $row);
	$parser->set_morpher_inflect('rod');
	$names[] = $parser->make_short_name($row['_my_name']);
	
	$names[] = $row['_my_name'];
	// род падеж без города
	$parser->set_need_remove_addr(true);
	$names[] = $parser->make_short_name($row['full_name'], $row);
	
	$regexps = [];
	foreach ($names as $name) {
		$r = mask_reg($name);
		$r = preg_replace('/ё/u', '(е|ё)', $r);
		$r = preg_replace("/\bосновная школа\b/ui", "(ОШ|основная школа)", $r);
		$r = $row['_name_MBOU'].'( ?- ?| )'.$r;	
		if ( preg_match("#\W$#u", $r) ) {
			// Заканичвается не на символ слова (обычно скобка) -- нельзя ставить \b
			$regexps[] = "\b$r";
		} else {
			$regexps[] = "\b$r\b";
		}
	}
	return $regexps;	
}