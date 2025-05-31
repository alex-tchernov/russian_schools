<?
error_reporting(E_ALL);
require_once '/var/www/shared/unecon/db/db_class.php';
require_once __DIR__ .'/parser_short_name_class.php';

//['school','spo','vpo','other']
//go('ro_org', "fullName like '%филиал%'");
go('school', "full_name like '%филиал%'");
function go(string $table, $where) {
	$db = \Unecon\DB::connect('frdo');
	$db->query("SET autocommit=0");
	$db->query("UPDATE  $table  set branch_town=NULL, branch_name=NULL");
	$rows = $db->rows("select * from $table where $where ");
	foreach ($rows as $row) {
		$id = $row['id'];
		$ogrn = $row['ogrn'] ?? $row['my_ogrn'] ;
		$parent_name = $db->fetch_value("select new_my_name from school where ogrn=:ogrn and branch='MAIN'", ['ogrn'=>$ogrn]);
		
		$result = parse_branch($row,$parent_name);
		if ($result) {
			$p = ['id'=>$id];
			foreach (['branch_name','branch_town','branch_parent'] as $fld) {
				$p[$fld] = $result[$fld] ?? '';
			}
			$db->query("UPDATE $table SET branch_town=:branch_town, branch_name=:branch_name,branch_parent=:branch_parent
				where id=:id", $p);
		}
	}
	$db->query("COMMIT");
}

function parse_branch($row, ?string $parent_name = null) {
	$result = [];
	$my_name = $row['new_my_name'];
	//echo "$my_name\n";
	$dash = "(-|–|—)";
	//
	//СШ - детский сад
	$region = "(\sРеспублики [А-ЯЁ-]+|\s[А-ЯЁ-]+\s(области|края|Республики|автономного округа))";
	$kladr_short = 'п\.|с\.|д\.|г\.|пос\.|х\.|р\.п\.';
	$in = "\b($kladr_short|деревне|хуторе|пгт|пос(ё|е)лке|селе|городе|станице)";
	$kladr_type = "\b($kladr_short|пос\.|деревни|хутора|пгт|поселка|села|города|станицы)";
	$addr_word = "$kladr_type ?[А-ЯЁ-]+( [А-ЯЁ]+)?";
	//$region = "(\sРеспублики [А-ЯЁ-]+)";
	//$r = "/^([А-ЯЁ]+кий) филиал/ui";
	//echo "'$r' $my_name\n";
	//Начальная школа – детский сад
	$school_type = "СОШ|ООШ|СШ|НОШ|ОШ|школа|СОШ ?$dash ?Интернат|СШ ?$dash ?Интернат";
	$school_parent_type = "\b($school_type|средней школы|лицей|гимназия)\b";
	$school_child_type = "\b(($school_type|начальная школа|колледж|техникум)( $dash детский сад)?|детский сад|детский сад [А-Я]+)\b";
	$MBOU = '[А-Я]{1,3}(ОУ|ОБУ)';
	
	//echo "'$my_name'\n";
	$parents = [
		"$school_parent_type( № [0-9]+)"
		,"$school_parent_type( № [0-9]+)? $addr_word"
		,"[А-ЯЁ-]+(ская|ский|ской|цкая|нная|рная|чная) $school_parent_type( № [0-9]+)?"
	];
	if ($parent_name) {
		$parents[] = $parent_name;
		//$parents = [$parent_name];
	}
	
	$r_parent = '(?<parent>('.implode('|', $parents).'))';
	$r_branch = "(?<branch>($school_child_type $addr_word|[А-ЯЁ-]+(ская|ский|цкая|нная|рная|чная) $school_child_type)( № [0-9]+)?)";
	// обл Липецкая, р-н Данковский, с Теплое
	$r_addr = "(обл|край|Респ) [А-ЯЁ-]+, ?р-н [А-ЯЁ-]+, ?(с|п|д|х) (?<addr_town>[А-ЯЁ-]+( [А-ЯЁ]+)?)";
//	$r_addr = "(обл|край|Респ) [А-ЯЁ-]+, ?р-н [А-ЯЁ-]+, ?(с|п|д|х)";
	
//	$r_parent = "(?<parent>[А-ЯЁ-]+(чная|ская|ской) $school_parent_type( № [0-9]+)?)";
	//echo "'$r_branch'\n$my_name\n";
	
	if ( preg_match("/^(<branch>([А-ЯЁ-]+(кий|кой|ный)) филиал( № [0-9]+))?/ui", $my_name, $matches) ) {
		$result['branch_name'] = $matches['branch'];
		//echo "'$branch_name' '$my_name'\n";
	} else if ( preg_match("/^(([А-ЯЁ-]+кая) $school_child_type) ([ ,–-]+)?филиал/ui", $my_name, $matches) ) {
		$result['branch_name']  = $matches[1];
		//echo "'$branch_name' '$my_name'\n";
	} else if ( preg_match("/^$r_branch([ ,-]+)филиал $MBOU ($dash )?$r_parent$/ui", $my_name, $matches) ) {
//	} else if ( preg_match("/^$r_branch([ ,-]+)филиал $MBOU (- )?$r_parent$/ui", $my_name, $matches) ) {
		// НОШ д.Верхнеидрисово филиал МОБУ СОШ с.Кульчурово
		// 60498
		//echo "1 MATCH {$matches[0]}\n {$matches['branch']}";
		$result['branch_parent'] =  $matches['parent'];
		$result['branch_name']  = $matches['branch'];		
	} else if ( preg_match("/^Филиал $MBOU ($dash )?$r_parent,? ($dash )?$r_branch$/ui", $my_name, $matches) ) {
		//echo "2 MATCH {$matches[0]}\n";
		$result['branch_parent'] =  $matches['parent'];
		$result['branch_name']  = $matches['branch'];
	} else if ( preg_match("/^Филиал $r_branch $MBOU (- )?$r_parent$/ui", $my_name, $matches) ) {
		// Филиал Мишкинская НОШ МКОУ Мишкинская СОШ
		//echo "MATCH {$matches[0]}\n";
		$result['branch_parent'] =  $matches['parent'];
		$result['branch_name']  = $matches['branch'];
	} 	elseif ( preg_match("/ в\s{$in}\s?(?<town>[А-ЯЁ-]+(\s[А-ЯЁ-]+)?){$region}?$/ui", $my_name, $matches) ) {
		//echo $matches[0];
		$result['branch_town_type']  = $matches[1];
		$result['branch_town']  = $matches['town'];
		//echo "'$town' '$my_name'\n";
	} else if (preg_match("/^Филиал $MBOU $r_parent \($r_addr\)$/ui", $my_name, $matches) ) {
		//echo "MATCH {$matches[0]}\n";
		$result['branch_town']  = $matches['addr_town'];
	} else if (  $parent_name && preg_match("/^Филиал $MBOU ($dash )?$parent_name$/ui", $my_name) ) {
		// Только родитель
		$result['branch_parent'] =  $parent_name;
		//echo "$id\t'$my_name'\n";
	} else if ( preg_match("/^Филиал $r_branch$/ui", $my_name, $matches) ) {
		// Только сам филиал
		$result['branch_name']  = $matches['branch'];
		//echo "MATCH {$matches[0]}\n";;
	}
	//var_dump($result);
	return $result;
}