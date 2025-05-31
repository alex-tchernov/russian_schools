<?
require_once '/var/www/shared/unecon/db/db_class.php';

set_is_bad_ogrn();
set_my_ogrn();
set_school_id();

set_has();
set_has_actual_cert();

set_school_has();

function set_has_actual_cert() {
	$db = \Unecon\DB::connect('frdo');
	$db->query("update ro_org set has_actual_cert = 0");
	$db->query("update school set has_actual_cert = 0");
	
	$db->query("update ro_org o inner join ro_cert_suppl suppl 
	on o.uid = suppl.OrgUid 
	inner join ro_cert cert on suppl.CertUid = cert.uid
	set o.has_actual_cert = 1 
	where ".cert_actual_where());
	
	$db->query("update school s inner join ro_org o on s.id=o.school_id
	set s.has_actual_cert=1 where o.has_actual_cert=1");

/*
	// first_issue
	$db->query("update school set first_cert_issue = null, last_cert_issue = null");
	
	$db->query("create temporary table tmp_ussue as 
	select school_id, min(cert.IssueDate) first_issue, max(cert.IssueDate) last_issue
	from ro_org o inner join ro_cert_suppl s ON o.Uid = s.OrgUid
	inner join ro_cert cert on s.CertUid = cert.Uid
	where school_id is not null
	group by school_id");
	
	$db->query("alter table tmp_ussue add primary key (school_id)");
	$db->query("update school s inner join tmp_ussue tmp on s.id = tmp.school_id
		set s.first_cert_issue = tmp.first_issue, s.last_cert_issue = tmp.last_issue");
*/		
}

function cert_actual_where() {
	$y = date('Y');		
	$where = "cert.StatusName in  ('Действующее','Возобновлено') and 
	cert.StatusName in  ('Действующее','Переоформлено','Выдан дубликат','Переоформлено в части приложения','Приостановлено частично')
	and (cert.EndDate is NULL or cert.EndDate>'$y-01-01')";
	
	return $where;
}

function set_has() {
	$db = \Unecon\DB::connect('frdo');

	foreach (['school', 'school_11', 'school_9', 'spo', 'vpo', 'aspir'] as $level) {
		$fld = 'has_'.$level;
		$db->query("update ro_org o set o.{$fld} = 0");
		$db->query("update ro_org o inner join ro_cert_suppl suppl on o.Uid = suppl.OrgUid and suppl.{$fld} = 1 
		set o.{$fld} = 1");
	}
	
	foreach (['school_11', 'school_9', 'spo', 'vpo'] as $level) {
		$s_fld = 'has_'.$level;
		$o_fld = 'has_actual_'.$level;
		$db->query("update ro_org o set o.{$o_fld} = 0");
		$db->query("update ro_org o 
			inner join ro_cert_suppl suppl on o.uid = suppl.OrgUid 
			inner join ro_cert cert on suppl.CertUid = cert.uid
			set o.$o_fld = 1 
			WHERE suppl.{$s_fld} = 1 AND ".cert_actual_where());
	}
}

function set_is_bad_ogrn($table = 'ro_org') {
	$db = \Unecon\DB::connect('frdo');
	// Первая цифра 3 - ИП
	// 13 цифра остаток от деления на 11 первых 12 и 0 если остаток 10
	$db->query("update $table 
	set is_bad_ogrn = 1 
	where ogrn is not null and ogrn not rlike '^[0-9]+$' ");

	$db->query("update $table 
	set is_bad_ogrn = 1 
	where ogrn is not null and substr(ogrn,1,1) not  in ('1','3','5') ");
	
	$db->query("update $table 
	set is_bad_ogrn = 1 
	where ogrn is not null and ogrn rlike '^[0-9]+$' and substr(ogrn,1,1)<>3 and mod(substr(ogrn,1,12),11)<>substr(ogrn,13,1) and not (mod(substr(ogrn,1,12),11)=10 and substr(ogrn,13,1) = '0')");
	
	$db->query("update $table o
	inner join bad_ogrn on o.Uid = bad_ogrn.OrgUid
	set o.is_bad_ogrn = 1");
}

function set_my_ogrn() {
	$db = \Unecon\DB::connect('frdo');
	$db->query("update ro_org set my_ogrn = null");
	
	$db->query("update ro_org o inner join bad_ogrn b on  o.uid = b.OrgUid set my_ogrn = b.good_ogrn");

	$db->query("update ro_org set my_ogrn = OGRN where is_bad_ogrn=0");
	
	$db->query("update ro_org o 
		inner join ro_org h on  o.HeadOrgUid = h.Uid	
		set o.my_ogrn = h.OGRN
		where o.my_ogrn is null and h.is_bad_ogrn=0
	");

	$db->query("create temporary table tmp_ogrn as 
		select distinct o.id, o.Uid, o2.OGRN 
		from ro_org o 
		inner join ro_cert_suppl s on  o.Uid = s.OrgUid	
		inner join ro_cert cert on s.CertUid = cert.Uid
		inner join ro_org o2 ON cert.OrgUid = o2.Uid
		where o.my_ogrn is null AND o2.Uid is not null
		GROUP BY o.id
		HAVING MIN(o2.OGRN) = MAX(o2.OGRN)
	");
	$db->query("ALTER  table tmp_ogrn add primary key (id) ");

	$db->query("ALTER  table tmp_ogrn add column  is_bad_ogrn tinyint default 0");
	set_is_bad_ogrn("tmp_ogrn");
	
	
	$db->query("UPDATE ro_org o 
		inner join tmp_ogrn ON o.id = tmp_ogrn.id
		set o.my_ogrn = tmp_ogrn.OGRN
		where o.my_ogrn is null and tmp_ogrn.is_bad_ogrn = 0
	");

	$db->query("update ro_org o 
		inner join school s on  o.INN = s.INN	
		set o.my_ogrn = s.ogrn
		where o.my_ogrn is null 
	");

	$db->query("update ro_org o 
		inner join ro_cert_suppl s on  o.Uid = s.OrgUid	
		inner join ro_cert c on  s.CertUid = s.Uid	
		set o.my_ogrn = c.OGRN
		where o.my_ogrn is null and c.OGRN is not null
	");

	// Не найденные c frdo по имени и school по kladr 
	$db->query("create temporary table t_ogrn as 
		select distinct o.id,frdo.ogrn
		from ro_org o
		inner join frdo_school frdo on
		o.school_id is null and o.my_ogrn is null and REGEXP_REPLACE(o.FullName, '[«»\" ]','') = REGEXP_REPLACE(frdo.full_name, '[«»\" ]','')
		inner join school s on frdo.ogrn = s.ogrn and o.kladr = s.kladr
		group by o.id
		having min(s.ogrn) = max(s.ogrn)");

	$db->query("update ro_org o inner join t_ogrn t on o.id = t.id
				set my_ogrn = t.ogrn 
				where o.my_ogrn is null");

}

function update_empty_school_id(string $sql) {
	$db = \Unecon\DB::connect('frdo');
	$db->query("DROP TEMPORARY TABLE IF EXISTS t");
	$db->query("CREATE TEMPORARY table t as $sql GROUP BY o.id HAVING COUNT(s.id)=1");
	$db->query("ALTER table t ADD CONSTRAINT PK PRIMARY KEY(id)");
	$db->query("UPDATE ro_org o INNER JOIN t ON o.id = t.id SET o.school_id=t.school_id WHERE o.school_id IS NULL");
}

function set_school_id() {
	$db = \Unecon\DB::connect('frdo');
	// В mysql regexp без 
	$regexp = '[^А-ЯЁа-яё0-9A-Za-z]';
	$regexp_school = '(школа|ООШ|СОЩ|)';
	$sql_distance = "round(st_distance_sphere(POINT(o.geo_lon,o.geo_lat), POINT(s.geo_lon,s.geo_lat))/1000,1)";


	$db->query("update ro_org set school_id = null, distance_to_main = null, distance_to_school = null ");
	
	$db->query("update ro_org o inner join school s on o.my_ogrn=s.ogrn and s.branch='MAIN' set o.distance_to_main = $sql_distance");


	$db->query("update ro_org o inner join hand_org_school s
		on o.Uid = s.OrgUid 
		SET o.school_id = s.school_id
		WHERE o.school_id IS NULL 
	");


	// Полное Наименование
	update_empty_school_id("SELECT o.id, s.id school_id 
		FROM ro_org o 
			INNER JOIN school s on o.my_ogrn = s.ogrn 
				AND REGEXP_REPLACE(o.FullName, '$regexp','') = REGEXP_REPLACE(s.full_name, '$regexp','')
		WHERE o.school_id IS NULL AND length(o.FullName)>10
	");
	
	// Наименование my_name 
	update_empty_school_id("SELECT o.id, s.id school_id 
		FROM ro_org o 
			INNER JOIN school s on o.my_ogrn = s.ogrn 
				AND REGEXP_REPLACE(o.new_my_name, '$regexp','') = REGEXP_REPLACE(s.new_my_name, '$regexp','')
		WHERE o.school_id IS NULL AND length(o.new_my_name)>6
	");


	// По КПП Филиалов
	$db->query("update ro_org o inner join school s 
		on o.my_ogrn = s.ogrn and o.kpp = s.kpp  
		SET o.school_id = s.id
		WHERE o.school_id IS NULL AND o.kpp NOT like '%1001' AND $sql_distance < 5
	");
	
	// Расстояние меньше чем до головного и меньше 1км (и такой единственный)
	update_empty_school_id("SELECT o.id, s.id school_id 
		FROM ro_org o 
			INNER JOIN school s on o.my_ogrn = s.ogrn 
		WHERE o.school_id IS NULL AND length(o.new_my_name)>6 AND $sql_distance < o.distance_to_main/2
			AND $sql_distance < 1
	");

	// Расстояние меньше чем до головного и меньше 5 км 
	update_empty_school_id("SELECT o.id, s.id school_id 
		FROM ro_org o 
			INNER JOIN school s on o.my_ogrn = s.ogrn 
		WHERE o.school_id IS NULL AND length(o.new_my_name)>6 AND $sql_distance < o.distance_to_main/2
			AND $sql_distance < 5
	");
	
	// По branch_name 
	update_empty_school_id("SELECT o.id, s.id school_id 
		FROM ro_org o 
			INNER JOIN school s on o.my_ogrn = s.ogrn 
				AND o.branch_name = s.branch_name
		WHERE o.school_id IS NULL and length(o.branch_name)>8
	");	

	// По branch_name 
	update_empty_school_id("SELECT o.id, s.id school_id 
		FROM ro_org o 
			INNER JOIN school s on o.my_ogrn = s.ogrn AND 
				s.new_my_name rlike o.branch_name 
		WHERE o.school_id IS NULL and length(o.branch_name)>8
	");	
	
	// По branch_town 
	update_empty_school_id("SELECT o.id, s.id school_id 
		FROM ro_org o 
			INNER JOIN school s on o.my_ogrn = s.ogrn AND (o.branch_town = s.branch_town
			OR s.settlement like concat('%',o.branch_town))
		WHERE o.school_id IS NULL 
	");
		
	// На головной
	$db->query("update ro_org o inner join school s 
		on o.my_ogrn = s.ogrn and s.branch='MAIN'
		SET o.school_id = s.id
		WHERE o.school_id IS NULL AND (o.kpp IS NULL OR o.kpp like '%1001') AND 
		(o.distance_to_main IS NULL OR o.distance_to_main < 5000)
		and o.fullName not like '%филиал%' and (o.shortName not like '%филиал%' or o.shortName is null)
		and o.fullName not like '%подразделение%' 	
	");
	
	// distance
	$db->query("update ro_org o inner join school s on o.school_id=s.id set distance_to_school = $sql_distance where o.school_id is not null ");
	
}

function set_school_has() {
	$db = \Unecon\DB::connect('frdo');
	/*
	foreach (['school','school_11','school_9','spo','vpo'] as $level) {
		$fld_school = 'has_cert_'.$level;
		$fld_org = 'has_'.$level;
		$db->query("update school set $fld_school = 0");
		$db->query("update school s inner join ro_org o ON s.id = o.school_id
			set s.$fld_school = 1 where o.$fld_org=1");
	}
	*/
	
	/*
	foreach (['school','spo','vpo','dpo','po'] as $level) {
		$fld_school = 'has_frdo_'.$level;
		$db->query("update school set $fld_school = 0");
		$db->query("update school s set s.$fld_school = 1 
		where id in (select school_id from frdo_school where school_id is not null and level = '$level')");
	}
	*/
	
	foreach (['school_11','school_9','vpo','spo'] as $level) {
		$y = date('Y');		
		$db->query("update school set has_$level = 0");
		// Eсть в ФРДО и есть аккердитация
		$frdo_level = in_array($level, ['school_11','school_9']) ? 'school' : $level ;
		$sql = "update school s 
			inner join frdo_school f on s.id = f.school_id
			inner join ro_cert_suppl suppl on s.id = suppl.school_id
			set s.has_$level = 1 
			where f.level='$frdo_level' and suppl.has_$level = 1";

		$db->query($sql);
		
	/*	// Переименовали в этом году есть запись в ФРДО на старое имя и аккредитация на новое имя
		// Обновляем основную запись branch = 'MAIN'
		$db->query("drop temporary  table if exists t_frdo_main_history");
		$sql = "create temporary table t_frdo_main_history as 
			select distinct s.ogrn, date_add(s.date_to_name, INTERVAL 1 DAY) d from school s 
			inner join frdo_school f on s.id = f.school_id 
			where f.level='$frdo_level' and s.branch='MAIN_HISTORY' ";
		$db->query($sql);
		$db->query("alter table t_frdo_main_history add index pk (ogrn) ");
		
		$date_from_name = ($y-1).'-01-01';
		$db->query("update school s 
			inner join ro_org o on s.id = o.school_id 
			inner join t_frdo_main_history t on s.ogrn = t.ogrn and s.date_from_name = t.d
			set s.has_$level =1 
			where o.has_$level = 1 and s.branch = 'MAIN' and s.date_from_name>='$date_from_name'");
		
		// Переименовали есть записи на старое имя в ФРДО 
		// Обновляем запись branch = 'MAIN_HISTORY' по ФРДО
		$db->query("drop temporary  table if exists t_main_ogrn");
		
		$sql = "create temporary table t_main_ogrn as 
			select distinct s.ogrn from school s
			inner join ro_org o on s.id = o.school_id 
			where o.has_$level = 1 and (s.branch='MAIN_HISTORY' or s.branch='MAIN') ";
		$db->query($sql);
		$db->query("alter table t_main_ogrn add index pk (ogrn) ");
		
		$date_from_name = ($y-1).'-01-01';
		$db->query("update school s 
			inner join frdo_school f on s.id = f.school_id 
			inner join t_main_ogrn t on s.ogrn = t.ogrn 
			set s.has_$level =1 
			where f.level = '$frdo_level' and (s.branch<>'BRANCH')");
			
		
		// Никогда не было в ФРДО посчитаем что если выдали недавно - есть шанс
		// Нет в ФРДО но впервые аккердитация получена за 3 последних года
		$y_start = $y - 3;
		$db->query("update school s SET s.has_$level = 1 WHERE 
			s.status='ACTIVE' AND id in (select o.school_id from ro_org o 
					inner join ro_cert_suppl s on o.Uid = s.OrgUid
					inner join ro_cert cert on s.CertUid = cert.Uid
					where o.school_id is not null and o.has_$level = 1 and o.has_actual_cert = 1
					group by o.school_id
					having min(cert.IssueDate) >= '$y_start-01-01')");
*/			
		// Школа подвед кого-то, кто не вносит в ФРДО 
		// для ФСИН сделаем исключение по названиям
		// Я не захотел чтобы учреждения ФСИН были в списке школ, СПО
		$sql = "update school s 
			inner join okogu on s.okogu = okogu.code
			set s.has_$level = 1 
			where s.id in 
			(select school_id from ro_cert_suppl suppl where suppl.school_id is not null and suppl.has_$level=1) and okogu.not_in_frdo=1";
		if ($level <> 'vpo')
			$sql .= " and 
			(s.okogu<>'1318010' or s.full_name like '%институт%' or s.full_name like '%академия%' or s.full_name like '%университет%')";
		$db->query($sql);
		
		// Действующая аккредитация
		$sql = "update school s 
			inner join ro_cert_suppl suppl on s.id = suppl.school_id 
			inner join ro_cert cert on suppl.CertUid = cert.Uid
			set s.has_$level = 1 
			where suppl.has_$level = 1 AND s.status='ACTIVE' AND suppl.StatusName='Действующее'
			AND cert.StatusName = 'Действующее' AND (cert.EndDate is null or cert.EndDate>'2025-01-01')";
			
		if ($level <> 'vpo')
			$sql .= " and 
			(s.okogu is null or s.okogu<>'1318010' or s.full_name like '%институт%' or s.full_name like '%академия%' or s.full_name like '%университет%')";
		$db->query($sql);
		
		
	}
			
	$sql = "UPDATE `school` s  
			inner join ro_org o on s.ogrn = o.my_ogrn
			set full_name = o.FullName
			where 
			  replace(replace(o.fullName,'«','\"'),'»','\"') = s.full_name";	
}