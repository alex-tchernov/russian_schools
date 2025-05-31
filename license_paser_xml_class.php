<?
require_once __DIR__ . '/my_parser_xml_class.php';

// https://stackoverflow.com/questions/911663/parsing-huge-xml-files-in-php
class LicenseParserXML extends myParserXML {
 //   protected $_currentId = "";
	
	// Путь до элемента верхнего уровня который сохраням
	protected $el_path = 'licenses/license';
	protected $xmlDir = __DIR__.'/license_xml';
	
	protected $_license = [];
	protected $supplements = [];
	protected $_supplement = [];
	protected $_program = [];
	protected $_supplement_programs = [];
//	protected $has = [];
	protected $md5_progs = [];
//	protected $level_types =['school', 'school_11', 'school_9', 'spo', 'vpo', 'aspir'];

	protected function id():string {
		return $this->_license['sysGuid'];
	}
	
	protected function saveLic() {
		$xml_flds = [
			 'sysGuid'=>'Uid'
			,'schoolGuid'=>'OrgUid'
			,'statusName'=>'StatusName'
			,'schoolName'=>'FullName'
			,'shortName'=>'ShortName'
			,'Inn'=>'INN'
			,'Ogrn'=>'OGRN'
			,'schoolTypeName'=>'OrgTypeName'
			,'lawAddress'=>'Address'
			,'orgName'=>'ControlOrgan'
			,'regNum'=>'regNum'
			,'dateLicDoc'=>'dateLicDoc'
			,'dateEnd'=>'dateEnd'
		];
		$lic = $this->_license;
		$p = [];
		foreach ($xml_flds as $xml_fld => $fld) {
			if ( !empty($lic[$xml_fld]) ) {
				$p[$fld] = $lic[$xml_fld];
			}
		}
		foreach (['dateLicDoc'] as $date_fld) {
			if ( isset($p[$date_fld]) ) {
				// Отрезаем странное время от дат
				$p[$date_fld] = substr($p[$date_fld],0,10);
			}
		}
		$uid = $p['Uid'];
		// В блядском xml ДУБЛИ!
		$has = $this->db->fetch_value("select count(*) from ro_lic where uid = :uid", ['uid'=>$uid]);
		if (!$has) {
			$flds = array_keys($p);
			$sql = "INSERT INTO ro_lic (".implode(',',$flds).") VALUES (:".implode(',:',$flds).")";
			$this->db->query($sql, $p);
			$lic_id = $this->db->last_id();
			$this->saveSupplements($lic_id);
		}
	}
	
	protected function saveSupplement(int $lic_id, array $suppl, ?array $programms) {
		$xml_flds = [
			 'sysGuid'=>'uid'
			,'licenseFK'=>'licUid'
			,'number'=>'number'
			,'statusName'=>'statusName'
			
			,'schoolGuid'=>'orgUid'
			,'schoolName'=>'fullName'
			,'shortName'=>'shortName'			
			,'lawAddress'=>'address'
			,'orgName'=>'controlOrgan'
			
			,'numLicDoc'=>'numLicDoc'
			,'dateLicDoc'=>'dateLicDoc'
		];
		$p = ['lic_id'=>$lic_id];
		//$suppl = $this->_supplement;
		foreach ($xml_flds as $xml_fld => $fld) {
			if ( !empty($suppl[$xml_fld]) ) {
				$p[$fld] = $suppl[$xml_fld];
			}
		}
		$flds = array_keys($p);
		$this->db->query("INSERT INTO ro_lic_suppl (".implode(',',$flds).")
			VALUES (:".implode(',:',$flds).")"
		, $p);
		$suppl_id = $this->db->last_id();
//		foreach ($this->_supplement_programs as $prog_id) {
		foreach ($programms as $prog_id) {
			$sql = "INSERT INTO ro_lic_suppl_prog (lic_id, lic_suppl_id, lic_prog_id) VALUES(:lic_id, :suppl_id, :prog_id)";
			$this->db->query($sql,
				['lic_id'=>$lic_id, 'suppl_id'=>$suppl_id, 'prog_id'=>$prog_id]);
		}
	}
	
	protected function saveSupplements(int $lic_id) {
		foreach ($this->supplements as $elm) {
			$suppl = $elm['data'];
			$programms = $elm['programms'];
			$this->saveSupplement($lic_id, $elm['data'], $elm['programms']);
		}
	}
	
	protected function pushSupplement() {
		$this->supplements[] = ['data'=>$this->_supplement, 'programms'=>$this->_supplement_programs];
	}

	protected function saveProg() {
		$xml_flds = [
//			 'supplementFk'=>'supplUid'
//			,'sysGuid'=>'progUid'
			'name'=>'name'
			,'code'=>'code'
			,'eduProgramType'=>'type'
			,'eduLevelName'=>'level'
			,'qualificationName'=>'qualification'
		];
		$p = [];
		$prog = $this->_program;
		foreach ($xml_flds as $xml_fld => $fld) {
			$p[$fld] = $prog[$xml_fld];
		}
		$prog_id = $this->prog_id($p);
		$this->_supplement_programs[] = $prog_id;
	}
	
	protected function prog_id(array $prog) {
		$flds = ['name', 'code', 'type', 'level', 'qualification'];
		$vals = [];
		foreach ($flds as $fld) {
			$vals[] = $prog[$fld];
		}
		$md5 = md5(implode('|', $vals));
		if (!isset($this->md5_progs[$md5])) {
			$id = $this->db->fetch_value("select id from ro_lic_prog where md5=:md5", ['md5'=>$md5]);
			if (!$id) {
				$prog['md5'] = $md5;
				$flds[] = 'md5';
				if (mb_strlen($prog['name'])>1000) {
					echo "truncating prog_name to 1000 length: ". mb_strlen($prog['name']) . "\n";
					// есть 5 штук ДПО. Решил проигнорить и оставить VARCHAR
					$prog['name'] = mb_substr($prog['name'],0,997).'...';
				}
				$this->db->query("INSERT INTO ro_lic_prog (".implode(',',$flds).")
					VALUES (:".implode(',:',$flds).")"
				, $prog);
				$id = $this->db->last_id();			
			}
			$this->md5_progs[$md5] = $id;
		}
		return $this->md5_progs[$md5];
	}
	
	protected function startTag($parser, $name, $attribs) {	
		//echo "start $name\n";
		parent::startTag($parser, $name, $attribs);
	}
	
    public function endTag($parser, $name) {
		$full_current = $this->_full_current;
		parent::endTag($parser, $name);

		if ($name == 'license') {
			$this->saveLic();
			$this->_license = [];
			$this->supplements = [];
		}
		
		if ($name == 'licensedProgram') {
			$this->saveProg();
			$this->_program = [];
		}
		
		if ($name == 'supplement') {
			$this->pushSupplement();
			//$this->saveSupplement();
			$this->_supplement = [];
			$this->_supplement_programs = [];
		//	$this->has = [];
		}

		if ( preg_match("#/license/{$name}$#",$full_current) ) {
			$this->_license[$name] = $this->_data;
		}
		
		if ( preg_match("#/supplement/{$name}$#", $full_current) ) {
			$this->_supplement[$name] = $this->_data;
		}
		
		if (preg_match("#/licensedProgram/{$name}$#",$full_current)) {
			$this->_program[$name] = $this->_data;
		}

		$this->_data = '';
    }
}