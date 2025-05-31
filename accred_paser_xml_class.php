<?
require_once __DIR__ . '/my_parser_xml_class.php';

// https://stackoverflow.com/questions/911663/parsing-huge-xml-files-in-php
class myAccredParserXML extends myParserXML {
 //   protected $_currentId = "";
	
	// Путь до элемента верхнего уровня который сохраням
	protected $el_path = 'OpenData/Certificates/Certificate';
	protected $xmlDir = __DIR__.'/accred_xml';

	protected $_certificate = [];
	protected $_actual_org = [];
	protected $_supplement = [];
	protected $_supplement_actual_org = [];
	protected $_program = [];
	protected $_decision = [];
	protected $has = [];
	protected $level_types =['school', 'school_11', 'school_9', 'spo', 'vpo', 'aspir'];

	protected function id():string {
		return $this->_certificate['Id'];
	}
	
	
	protected function programHasLevel($program) {
		$level = $program['EduLevelName'];
		if ($level == 'Не определен') {
			$level_types = [
				'spo'=> ['среднее профессиональное образование']
				,'vpo'=>['высшее профессиональное образование']
				,'aspir'=>[
					'ВО - ПКВК - аспирантура (адъюнктура)'
					,'послевузовское профессиональное образование (интернатура)'
					,'послевузовское профессиональное образование (ординатура)'
				]
			];
			$level = $program['TypeName'];
			
		} else {
			$level_types = [
				'school' => [
						'Начальное общее образование'
						,'Основное общее образование'
						,'Среднее общее образование'
						,'Среднее (полное) общее образование' //241 запись
				],
			
				'school_11' => [
					'Среднее общее образование'
					,'Среднее (полное) общее образование'
				],
			
				'school_9' => [
					'Основное общее образование'
				],
				
				'spo' => [
					'Среднее профессиональное образование'
				],
				
				'vpo' => [
					'Высшее образование - специалитет'
					,'Высшее образование - бакалавриат'
					,'Высшее образование - магистратура'
					,'Высшее профессиональное образование'
					,'ВО - специалитет' // 687 записей 
					,'ВО - магистратура' // 196 записей
					,'ВО - бакалавриат' // 323 записи )
					,'ВО - специалитет, магистратура' // 44 записи 
					,'Высшее образование - специалитет, магистратура' // 15 записей
				],
			
				'aspir' => [
					'Высшее образование - подготовка кадров высшей квалификации'
					,'Послевузовское профессиональное образование'
				]
			];
		}
		$school_progs = [
			'ignore'=>[
				'Основная общеобразовательная программа дошкольного образования'
			]
			,'school'=>[
				'^Начальное общее образование'
				,'^Начального общего образования'
				,'программ(а|ы)\s+начального общего образования'
			],
			'school_9'=>[
				'^Основное общее образование'
				,'^Основного общего образования'
				,'программ(а|ы)\s+основного общего образования'
				,'^Общее образование$'
				,'^Основное общее$'
				,'^основная общеобразовательная программа$'
				,'^общеобразовательные программы основного общего \(полного\) образования$'
				,'^Типовое основное общее образование$'
				,'^Среднее \(полное\) специальное \(коррекционное\) образование'
			],
			'school_11'=>[
				'^Среднее( \(полное\))? общее образование'
				,'^Среднего( \(полного\))? общего образования'
				,'программ(а|ы)\s+среднего( \(полного\))? общего образования'
			]
			//программа основного общего образования
		];
		$has = [];
		// Много где для программы школы указан уровень СПО иногда ВО (:
		$programm_name = $program['ProgrammName'];
		$ignore = false;
		if ($programm_name) {
			foreach ($school_progs as $type=>$elements) {
				foreach ($elements as $el) {
					if ( preg_match("/$el/ui", $programm_name) ) {
						if ($type == 'ignore') {
							$ignore = true;
							break;
						}
						$has['has_'.$type] = 1;
						$has['has_school'] = 1;
					}
				}
			}
		}
		
		
		if (!$has && !$ignore) {
			foreach ($level_types as $type=>$elements) {
				if ( in_array($level, $elements) ) {
					if ($type == 'vpo') {
						if ( empty($program['ProgrammCode']) ) {
							// СПО или ДПО
							// У нормальных во все годы в аккредитации хотя бы одна программа с кодом
							continue;
						}
						// 4 цифры это СПО не знаю по какому классификатору
						if ( preg_match('/^[0-9]{4}$/',$program['ProgrammCode']) ) {
							$type = 'spo';
						}
					}
					
					$has['has_'.$type] = 1;
				}
			}
		}
		
		return $has;
	}
	
	protected function checkFilled(string $msg, $array, $keys) {
		$bad_keys = [];
		foreach ($keys as $key) {
			if (!$array[$key]) $bad_keys[] = $key;
		}
		if ($bad_keys) {
			echo "$msg " .implode(",", $bad_keys). "\n";
		}
	}
	
	protected function saveOrg($org) {
		//if (!$org['Id']) continue;
		$p = [];
		$xml_flds = [
			 'Id'=>'Uid'
			,'FullName'=>'FullName'
			,'ShortName'=>'ShortName'
			,'OGRN'=>'OGRN'
			,'INN'=>'INN'
			,'KPP'=>'KPP'
			,'HeadEduOrgId'=>'HeadOrgUid'
			,'PostAddress'=>'Address'
			,'Email'=>'email'
			,'WebSite'=>'www'
			,'HeadName'=>'head_fio'
		];
		foreach ($xml_flds as $xml_fld => $fld) {
			if (!empty($org[$xml_fld])) {
				$p[$fld] = $org[$xml_fld];
			}
		}
		if ( !$p ) {
			return;
		}
		
		if (!isset($p['FullName'])) {
			// echo "No FullName " . $this->xmlFileName()."\n";
		}
		/*
		foreach (['school','school_11','school_9','spo','vpo'] as $has) {
			$p['has_'.$has] = $org['has'][$has] ?? 0;
		};
		
		*/
		if ( isset($p['Uid']) ) {
			$exists = $this->db->fetch_value("select id from ro_org where Uid = :uid", ['uid'=>$p['Uid'] ]);
		} else {
			$exists = false;
		}
		
		if (!$exists) {
			$flds = array_keys($p);
			$this->db->query("INSERT INTO ro_org (".implode(',',$flds).")
				VALUES (:".implode(',:',$flds).")"
			 , $p);
		}
	}
	
	protected function saveCert() {
		$xml_flds = [
			 'Id'=>'Uid'
			,'IsFederal'=>'IsFederal'
			,'StatusName'=>'StatusName'
			,'StatusCode'=>'StatusCode'
			,'TypeName'=>'TypeName'
			,'TypeCode'=>'TypeCode'
			,'RegionName'=>'RegionName'
			,'RegionCode'=>'RegionCode'
			,'ControlOrgan'=>'ControlOrgan'
			,'RegNumber'=>'RegNumber'
			,'SerialNumber'=>'SerialNumber'
			,'FormNumber'=>'FormNumber'
			,'IssueDate'=>'IssueDate'
			,'EndDate'=>'EndDate'
			
			,'EduOrgFullName'=>'FullName'
			,'EduOrgShortName'=>'ShortName'
			,'EduOrgINN'=>'INN'
			,'EduOrgKPP'=>'KPP'
			,'EduOrgOGRN'=>'OGRN'
			,'PostAddress'=>'Address'
			
			,'IndividualEntrepreneurLastName'=>'IE_LastName'
			,'IndividualEntrepreneurINN'=>'IE_INN'
		];
		$cert = $this->_certificate;
		$p = [];
		foreach ($xml_flds as $xml_fld => $fld) {
			if ( !empty($cert[$xml_fld]) ) {
				$p[$fld] = $cert[$xml_fld];
			}
		}
		if ($this->_actual_org) {
			$p['OrgUId'] = $this->_actual_org['Id'] ?? null;
		}
		foreach (['IssueDate', 'EndDate'] as $date_fld) {
			if ( isset($p[$date_fld]) ) {
				// Отрезаем странное время от дат
				$p[$date_fld] = substr($p[$date_fld],0,10);
			}
		}
		$flds = array_keys($p);
		$sql = "INSERT INTO ro_cert (".implode(',',$flds).") VALUES (:".implode(',:',$flds).")";
		$this->db->query($sql, $p);
	}
	
	protected function saveSupplement() {
		$xml_flds = [
			 'Id'=>'Uid'
			,'StatusName'=>'StatusName'
			,'Number'=>'Number'
			,'SerialNumber'=>'SerialNumber'
			,'FormNumber'=>'FormNumber'
			,'IssueDate'=>'IssueDate'
			,'IsForBranch'=>'IsForBranch'
			,'Note'=>'Note'
			
			,'EduOrgFullName'=>'FullName'
			,'EduOrgShortName'=>'ShortName'
			,'EduOrgKPP'=>'KPP'
			,'EduOrgAddress'=>'Address'

		];
		$p = [];
		$suppl = $this->_supplement;
		foreach ($xml_flds as $xml_fld => $fld) {
			if ( !empty($suppl[$xml_fld]) ) {
				$p[$fld] = $suppl[$xml_fld];
			}
		}
		$p['CertUid'] = $this->_certificate['Id'];
		if ($this->_supplement_actual_org) {
			$p['OrgUid'] = $this->_supplement_actual_org['Id'] ?? null;
		}
		if ( isset($p['IssueDate']) ) {
			$p['IssueDate'] = substr($p['IssueDate'],0,10);
		}
		$p += $this->has;
		$flds = array_keys($p);
		$this->db->query("INSERT INTO ro_cert_suppl (".implode(',',$flds).")
			VALUES (:".implode(',:',$flds).")"
		, $p);
	}

	protected function saveSupplProg() {
		$p = [];
		$p['CertUid'] = $this->_certificate['Id'];
		$p['SupplUid'] = $this->_supplement['Id'];
		$p['OrgUid'] = $this->_supplement_actual_org['Id'] ?? null;
		
		$xml_flds = ['IsAccredited', 'IsCanceled', 'IsSuspended'];
		$program = $this->_program;
			// Id Программы всюду уникальный - на фиг не нужен
			// KindName, KindCode, TypeCode, EduSubLevelName, EduSubLevelName, EduSubLevelCode  никогда не заполнены
			// EdulevelShortName, EduLevelCode  не добавляют инфрмации
		$skip = [
			'Id'
			, 'KindName'
			, 'KindCode'
			, 'TypeCode'
			, 'EdulevelShortName'
			, 'EduLevelCode'
			, 'EduSubLevelName'
			, 'EduSubLevelCode'
		];
		foreach ($program as $key=>$val) {
			
			if ( in_array($key, $skip) ) continue;
			if ( !empty($val) ) {
				$p[$key] = $val;
			}
		}
		$has = $this->programHasLevel($program);
		foreach ($has as $key=>$val) {
			$this->has[$key] = 1;
		}
		$p += $has;

		$flds = array_keys($p);
		$this->db->query("INSERT INTO ro_cert_suppl_prog (".implode(',',$flds).")
			VALUES (:".implode(',:',$flds).")"
		, $p);		
	}
	
	protected function saveDecision() {
		$p = [];
		$p['CertUid'] = $this->_certificate['Id'];		
		$decision = $this->_decision;
		$change = ['Id'=>'Uid', 'StartedSupplementId'=>'StartedSupplementUid', 'StoppedSupplementId'=>'StoppedSupplementUid'];
		foreach ($decision as $key=>$val) {
			if ( !empty($val) ) {
				if ( isset($change[$key]) ) {
					$key = $change[$key];
				}
				$p[$key] = $val;
			}
		}
		foreach (['DecisionDate','StartedSupplementIssueDate','StoppedSupplementIssueDate','OldIssueDate'] as $fld_d) {
			if ( isset($p[$fld_d]) ) {
				$p[$fld_d] = substr($p[$fld_d], 0, 10);
			}
		}
		$flds = array_keys($p);
		$this->db->query("INSERT INTO ro_cert_decision (".implode(',',$flds).")
			VALUES (:".implode(',:',$flds).")"
		, $p);		
	}	
	
	
    public function endTag($parser, $name) {
		$full_current = $this->_full_current;
		parent::endTag($parser, $name);

		if ($this->el_path == $full_current) {
			$this->saveCert();
			$this->saveOrg($this->_actual_org);
			$this->_certificate = [];
			$this->_actual_org = [];
		}
		
		if ($name == 'EducationalProgram') {
			//$this->fillProgs();
			$this->saveSupplProg();
			$this->_program = [];
		}
		
		if ($name == 'Supplement') {
			//$this->checkSupplemetOrg();
			$this->saveOrg($this->_supplement_actual_org);
			$this->saveSupplement();
			$this->_supplement_actual_org = [];
			$this->_supplement = [];
			$this->has = [];
		}
		
		if ($name == 'Decision') {
			$this->saveDecision();
			$this->_decision = [];
		}
		
		if ( preg_match("#/Supplement/{$name}$#", $full_current) ) {
			$this->_supplement[$name] = $this->_data;
		}
		
		if ( preg_match("#/Certificate/{$name}$#",$full_current) ) {
			$this->_certificate[$name] = $this->_data;
		}

		if (preg_match("#/Certificate/ActualEducationOrganization/{$name}$#",$full_current)) {
			$this->_actual_org[$name] = $this->_data;
		}
	
		if (preg_match("#/Supplement/ActualEducationOrganization/{$name}$#",$full_current)) {
			$this->_supplement_actual_org[$name] = $this->_data;
		}
		
		if (preg_match("#/EducationalProgram/{$name}$#",$full_current)) {
			$this->_program[$name] = $this->_data;
		}

		if (preg_match("#/Decision/{$name}$#",$full_current)) {
			$this->_decision[$name] = $this->_data;
		}
		$this->_data = '';
    }
}