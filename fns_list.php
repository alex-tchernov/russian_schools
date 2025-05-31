<?
require_once '/var/www/shared/unecon/db/db_class.php';
require_once __DIR__ .'/parser_short_name_class.php';

define('PATH','/home/DOCS/ogrn/fns');
$files = scandir(PATH);
$db = \Unecon\DB::connect('frdo');

foreach ($files as $file) {
	if ( preg_match('/^egr_/' ,$file) ) {
		$file = PATH . '/'.$file;
		//echo "$file\n";
		$str = file_get_contents($file);
		$data = json_decode($str,true);		
		if (!$data) {
			echo "bad file $file!\n";
			//unlink($file);
			continue ;
		}
		$cnt = count($data['items']);
		if ($cnt <> 1) {
			//echo "$file count $cnt\n";
			continue;
		}
		filials($db, $data['items'][0]);
//		exit();
	}
}

function filials($db,  $item) {
	$ogrn = $item['ЮЛ']['ОГРН'];
	$has = $db->fetch_value("select count(*) from school where ogrn=:ogrn and status='HISTORY'",['ogrn'=>$ogrn]);
	if ($has) return;
	//	echo "$ogrn\n";
	$history = [];
	$orgs = $item['ЮЛ']['Филиалы'] ?? [];
	foreach ($orgs as $org) {
		$kpp = $org['КПП'] ?? null;
		$name =  $org['Наименование'] ?? '';
		$type = $org['Тип'];
		if ($kpp) {
			$has = $db->fetch_value("select count(*) from school where ogrn=:ogrn and kpp=:kpp",['ogrn'=>$ogrn,'kpp'=>$kpp]);
			//echo "$ogrn $kpp\n";
			if (!$has) {
				echo "NEW $ogrn $kpp $name\n";
			}
		} else {
			
			echo "NO KPP $ogrn $type $name\n";
		}
	}

}
