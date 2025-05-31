<?
require_once '/var/www/shared/unecon/db/db_class.php';
$db = \Unecon\DB::connect('frdo');

$rows = $db->rows("select * from ro_lic_prog");
$db->query("SET autocommit=0");
foreach ($rows as $row) {
	$id = $row['id'];
	$level = $row['level'];
	$type = $row['type'];
	$has_school = $has_school_11 = $has_school_9 = $has_spo = $has_vpo = $has_aspir = 0;
	if ( in_array($level, ['Среднее общее образование', 'Среднее (полное) общее образование']) )  {
		if ( empty($type) || in_array($type , ['среднее общее образование', 'среднее (полное) общее образование']) ) {
			$has_school_11 = 1;
			$has_school = 1;
		}
	} else if ( in_array($level, ['Основное общее образование']) ) {
		if ( empty($type) || in_array($type , ['основное общее образование']) ) {
			$has_school_9 = 1;
			$has_school = 1;
		}
	} else if ( in_array($level, ['Среднее профессиональное образование']) ) {
		if ( empty($type) || in_array($type , ['Среднее профессиональное образование','среднее профессиональное образование - программы подготовки специалистов среднего звена']) ) {
			$has_spo = 1;
		}
	} else if ( in_array($level, ['Высшее образование - специалитет','Высшее образование - бакалавриат','Высшее образование - магистратура','Высшее профессиональное образование']) ) {
		if ( empty($type) || in_array($type , ['высшее образование - программы специалитета','высшее образование - программы бакалавриата','высшее образование - программы магистратуры','']) ) {
			$has_vpo = 1;
		} else if ( in_array($type, ['Высшее образование - ПКВК - адъюнктура']) ) {
			$has_aspir = 1;
		}
	} else if ( in_array($level,['Высшее образование - подготовка кадров высшей квалификации']) ) {
		$has_aspir = 1;
	}
	$db->query("update ro_lic_prog set 
		has_school=:has_school, has_school_11=:has_school_11, has_school_9=:has_school_9, has_spo=:has_spo, has_vpo=:has_vpo, has_aspir=:has_aspir
		where id=:id", 
	['id'=>$id, 'has_school'=>$has_school, 'has_school_11'=>$has_school_11, 'has_school_9'=>$has_school_9, 'has_spo'=>$has_spo, 'has_vpo'=>$has_vpo, 'has_aspir'=>$has_aspir]
	);
}
$db->query("COMMIT");