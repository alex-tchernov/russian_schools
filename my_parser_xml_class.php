<?

// https://stackoverflow.com/questions/911663/parsing-huge-xml-files-in-php

class myParserXML {
	protected $el_path = null;
	protected $xmlDir = __DIR__.'/xml';
    protected $_stack = [];
    protected $_file = "";
    protected $_parser = null;
	protected $db;
	protected $needSaveXML = false;
	protected $count_parsed = 0;
    protected $_current = "";
    protected $_full_current = "";
    protected $_data = "";
    protected $_xml = "";
    protected $_inside_data = false;
	
    public function __construct($file)
    {
        $this->_file = $file;

        $this->_parser = xml_parser_create("UTF-8");
		//
        xml_set_object($this->_parser, $this);
		xml_parser_set_option($this->_parser, XML_OPTION_CASE_FOLDING, false);
        xml_set_element_handler($this->_parser, "startTag", "endTag");
		xml_set_character_data_handler($this->_parser, "contents");
    }
	
	public function setDb($db) {
		$this->db = $db;
	}
	
	public function setSaveXML(bool $save) {
		$this->needSaveXML = $save;
	}	
	
    public function parse() {
        $fh = fopen($this->_file, "r");
        if (!$fh) {
            die("Epic fail!\n");
        }

        while (!feof($fh)) {
			//if ($this->count_parsed > 10000) break;
            $data = fread($fh, 4096);
            xml_parse($this->_parser, $data, feof($fh));
        }
    }

	protected function contents($parser, $data)	{
		
		if ($this->_inside_data)
			$this->_data .= $data; // need to concatenate data
		else
			$this->_data = $data;

		$this->_inside_data = true;
	}
	
    protected function startTag($parser, $name, $attribs) {
        array_push($this->_stack, $name);
        $this->_current = $name;
		$this->_full_current = implode('/', $this->_stack);
		$this->_inside_data = false;
		$this->_data = '';
		if ($this->_full_current == $this->el_path) {
			$this->_xml = "<$name>";
		} elseif ( preg_match('#'.$this->el_path.'.*#', $this->_full_current) ) {
			$this->_xml .= "<$name>";
		}
    }
	
	
    public function endTag($parser, $name) {
		$full_current = $this->_full_current;
		if ( preg_match('#'.$this->el_path.'.*#', $this->_full_current) ) {
			$this->_xml .=  htmlspecialchars($this->_data, ENT_XML1);
			$this->_xml .= "</$name>";
		}
		
		if ($this->el_path == $full_current) {
			//$this->printCert();
			if ($this->needSaveXML) {
				$this->saveXml();
			}
			$i = $this->count_parsed++;
			if ($i % 1000 == 0) {
				echo "$i\n";
			}
		}
		
        $this->_current = array_pop($this->_stack);
		$this->_full_current = implode('/', $this->_stack);
		$this->_inside_data = false;
		//$this->_data = '';
	}
	
	
	protected function id():string {
		
	}
	
	protected function xmlFileName() {
		$id = $this->id();
		$parts = explode('-',$id);
		$part = $parts[0];
		if (mb_strlen($part)>10) {
			$part = mb_substr($part,0,6);
		}
		$dir = $this->xmlDir ."/$part";
		if (!is_dir($dir)) mkdir($dir);		
		$fname = "$dir/$id.xml";
		return $fname;
	}
	
	function saveXml() {
		$xml = '<?xml version="1.0" encoding="UTF-8"?>'.$this->_xml;
		file_put_contents($this->xmlFileName(), $this->_formatXml($xml));
	}

	protected function _formatXml($xml) {
		$dom = new DOMDocument();

		// Initial block (must before load xml string)
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput = true;
		// End initial block
		
		$dom->loadXML($xml);
		$out = $dom->saveXML();
		return $out;
	}
}