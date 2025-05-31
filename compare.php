<?
require_once '/var/www/shared/unecon/db/db_class.php';
$level = $argv[1] ?? null;
$levels = ['school','spo','vpo','po','dpo'];
if ( !in_array($level, $levels) ) {
	die( "args: compare ".implode('|', $levels) );
}

compare_frdo($level);

function compare_frdo(string $level, ?string $branch=null) {
	$db = \Unecon\DB::connect('frdo');
	function fn_hash($name, $change_schools = false) {
	//	$name = preg_replace('/ им\..*$/ui' ,'', $name);
	//	$name = preg_replace('/(медицинский колледж|медицинское училище)/ui', 'медицинск', $name);
	//	$name = preg_replace('/профессионально-техническое/ui', 'Профессиональное', $name);
	//	$name = preg_replace('/(колледж|техникум)/ui', 'колледж', $name);
		if ($change_schools) {
			$name = preg_replace('/\b(ООШ|ОШ|СОШ|СШ|Школа|общеобразовательная школа)\b/ui', 'школа', $name);
		}
		$name = preg_replace('/Ё/ui', 'Е', $name);
		$name = preg_replace('/[^А-ЯЁ0-9]/ui', '', $name);
		$name = mb_strtoupper($name);
		return $name;
	};
	//$fld = 'has_frdo_' . $level;
	if ($level == 'school') {
		$w = "(has_school_11=1 or has_school_9=1)";
	} else if ($level == 'spo') {
		$w = "has_spo=1";
	} else if ($level == 'vpo') {
		$w = "has_vpo=1";
	}
	if ($branch) {
		$w .= " AND branch = '$branch' ";
	}
	
	$rows = $db->rows("select * from school where $w");
	
	echo "id\tlevel\tOGRN\thas_history\thist_requested\thas_branch\tbranch\tmy_name\tfrdo_name\tstatus\tрегион\n";
	foreach ($rows as $row) {
		$not_found_names = [];
		$id = $row['id'];
		$my_name = $row['new_my_name'];	
		$ogrn = $row['ogrn'];
		$history_requested = file_exists("/home/DOCS/ogrn/fns/egr_".$ogrn.".json");
		$has_history = $db->fetch_value("select count(*) from school where ogrn=:ogrn and status = 'HISTORY'",['ogrn'=>$ogrn]);
		
		$my_number = null;
		if ( preg_match('/[0-9]+/u', $my_name, $matches) ) {
			$my_number = $matches[0];
		}
		
		$my_hash = fn_hash($my_name);
		$my_hash2 = fn_hash($my_name,true);
		$sql = "select * from frdo_school where level=:level and school_id = :id and 
		_my_name<>:my_name 
		order by _my_name";
		$frdo_rows = $db->rows($sql, ['id'=>$id, 'level'=>$level, 'my_name'=>$my_name]);
		foreach ($frdo_rows as $f_r) {
			$frdo_name = $f_r['_my_name'];
			$frdo_city = $f_r['_my_name_city'] ?? '';
			$frdo_hash = fn_hash($frdo_name);
			$frdo_hash2 = fn_hash($frdo_name,true);
			if ($frdo_hash !== $my_hash) {
				$frdo_number = null;
				if ( preg_match('/[0-9]+/u', $frdo_name, $matches) ) {
					$frdo_number = $matches[0];
				}
				$status = '';
				if ( preg_match("/$my_hash/u",$frdo_hash) || preg_match("/$frdo_hash/u",$my_hash) ) {
					$status = 'LIKE';
				} else if ( preg_match("/$my_hash2/u",$frdo_hash2) || preg_match("/$frdo_hash2/u",$my_hash2) ) {
					$status = 'LIKE_CHANGED_TYPE';
				} else if (!$my_number && !$frdo_number) {
					$status = 'NO_NUMBERS';
				} else if ($my_number == $frdo_number) {
					$status = 'NUMBERS_OK';
				} else {
					$status = 'NUMBERS_BAD';
				}
				$not_found_names[$frdo_hash] = ['name'=>$frdo_name,'city'=>$frdo_city, 'nubmer_status'=>$status];
			}
		}
		foreach ($not_found_names as $el) {
			echo "$id\t$level\t{$row['ogrn']}\t$has_history\t$history_requested\t{$row['has_branch']}\t{$row['branch']}\t$my_name\t{$el['name']}\t{$el['nubmer_status']}\t{$row['region']}\n";
		}
	}
}