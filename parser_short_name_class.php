<?
require_once __DIR__ . "/parser_addr_class.php";
require_once __DIR__ . "/parser_fio_class.php";
require_once __DIR__ . "/parser_MBOU_class.php";
require_once __DIR__ . "/short_name_fn.php";

Class ParserShortName {
	protected $dict_always_lower = [];
	protected $dict_words = [];
	protected ?string $_name = null; // результат
	protected ?string $_name_fio_full = null; // имени кого
	protected ?string $_name_fio_short = null; // имени сокращенное 
	protected ?string $_name_fio_removed = null; // убрали имени из названия
	protected ?string $_name_with_discipline = null; // c УИОТ
	protected ?string $_name_health = null; // для детей здоровье
	protected ?string $_name_MBOU = null; // для детей здоровье
	protected ?string $_name_orphan = null; // дети сироты
	protected ?string $_name_ministry = null; // министерства
	
	protected ?string $_name_city = null; // город из адреса
	
	protected bool $need_remove_fio = true;
	protected bool $need_remove_addr = true;
	protected bool $need_remove_region = true;
	protected bool $need_remove_area = true;
	protected bool $need_remove_quotes = true;
	protected bool $use_socr_replace = true;
	
	protected string $morpher_inflect = '';
	
	const CASE_MODE_AUTO = 1; // 1 AutoDetect
	const CASE_MODE_CHANGE = 2;
	const CASE_MODE_PRESERVE = 3;
	const SOCR_REPLACES_FILE = __DIR__ .'/dict/my_socr_replaces.txt';
	
	protected $change_case_mode = self::CASE_MODE_AUTO;// 1 AutoDetect; 
	protected $socr_replaces = [];
	protected $typing_errors = [];
	
	protected ParserFIO $fio_parser;
			
	function __construct() {
		$dict_always_lower = load_dict(__DIR__ . '/dict/allways_lower.txt');
		$this->dict_words = $this->load_my_dict();
		
		$this->fio_parser = new ParserFIO();
		
		if (self::SOCR_REPLACES_FILE) {
			$strings = explode( "\n", file_get_contents(self::SOCR_REPLACES_FILE) );
			foreach ($strings as $s) {
				list($key, $val) = explode("\t",$s);
				$key_rod = morpher_inflect($key, 'rod');
				$key =  str_replace('Ё','(Е|Ё)',$key);
				$key_rod =  str_replace('Ё','(Е|Ё)',$key_rod);
				$key =  str_replace('ОБРАЗОВАТЕЛЬ','(ОБЩЕ)?ОБРАЗОВАТЕЛЬ',$key);
				$key_rod =  str_replace('ОБРАЗОВАТЕЛЬ','(ОБЩЕ)?ОБРАЗОВАТЕЛЬ',$key_rod);
				$key_rod =  str_replace('УЧРЕЖДЕНИЯ','УЧРЕЖДЕНИ(Я|Е)',$key_rod);
				$key =  str_replace('-','( ?- ?)',$key);
				$key_rod =  str_replace('-','( ?- ?)',$key_rod);
				
				$this->socr_replaces[ $key_rod ] = $val;
				$this->socr_replaces[ $key ] = $val;
			}
			//var_dump($this->socr_replaces);
		}
		$this->typing_errors = file_to_assoc(__DIR__.'/dict/correct.txt');
	}
	
	
	public function parse(string $full_name, ?array $addr_row = null, ?string $parent_name = null) {
		$this->make_short_name($full_name,$addr_row,$parent_name);
		return $this->get_result();
	}
	
	public function get_result():array {
		return [
			'name'=>$this->_name
			,'fio_full'=>$this->_name_fio_full
			,'fio_short'=>$this->_name_fio_short
			,'fio_removed'=>$this->_name_fio_removed
			,'with_discipline'=>$this->_name_with_discipline
			,'health'=>$this->_name_health
			,'MBOU'=>$this->_name_MBOU
			,'orphan'=>$this->_name_orphan
			,'ministry'=>$this->_name_ministry
			,'city'=>$this->_name_city
		];
	}
	
	// Переделать на флаги в конструеторе
	public function set_need_remove_fio($need) {
		return $this->need_remove_fio = $need;
	}	
	
	public function set_need_remove_addr($need) {
		return $this->need_remove_addr = $need;
	}	
	
	public function set_need_remove_region($need) {
		return $this->need_remove_region = $need;
	}	
	
	public function set_need_remove_area($need) {
		return $this->need_remove_area = $need;
	}		
	
	public function set_need_remove_quotes($need) {
		return $this->need_remove_quotes = $need;
	}	

	public function set_change_case_mode($mode) {
		return $this->change_case_mode = $mode;
	}		
	
	public function set_morpher_inflect(string $inflect) {
		return $this->morpher_inflect = $inflect;
	}		


	protected function load_my_dict() {
		$dict = load_my_words();
		$words = load_dict(__DIR__ . "/dict/dict_words.txt");
		foreach ($words as $word) {
			$upper = mb_strtoupper($word);
			// Слово может быть несколько раз. Возьмем первое (без Capsa)
			if ( !isset($dict[$upper]) ) {
				$dict[$upper] = $word;
			}
		}
		return $dict;
	}
	
	protected function clear_saved() {
		$this->_name = null;
		$this->_name_fio_full = null;
		$this->_name_fio_short = null;
		$this->_name_fio_removed = null;
		$this->_name_with_discipline = null;
		$this->_name_health = null;
		$this->_name_MBOU = null;
		$this->_name_orphan = null;
		$this->_name_ministry = null;
		
		$this->_name_city = null;
	}
	
	protected function replace_words(string $my_name):string {
		$my_name = $this->remove_with_discipline($my_name);
		$my_name = $this->remove_health($my_name);
		$my_name = $this->remove_orphan($my_name);
		$my_name = $this->remove_ministry($my_name);
		
		$replaces = [
			'Для детей дошкольного и младшего школьного возраста'=>''
			,'Структурное подразделение, реализующее основные общеобразовательные программы дошкольного образования, детский сад'=>'детский сад'
		];
		
		foreach ($replaces as $full=>$short) {
			$my_name = preg_replace("/\b$full\b/ui", "$short", $my_name);
		}
		$my_name = trim($my_name);
		//$my_name = preg_replace('/\(\s?\)/u', '', $my_name);
		return $my_name;
	}
	
	protected function remove_with_discipline(string $my_name):string {
		$els = load_dict(__DIR__ . '/dict/with_discipline.txt');
		$regexp = 'с (углубл(е|ё)нным )?изучением? (?<predm>(' . implode('|',$els). '|, |и | )+)';
		// детские сады
		$reg_pre_school = '(общеразвивающего вида )?с приоритетным осуществлением деятельности по (?<predm>.*) развити(я|ю) (детей|воспитанников)';
		if (preg_match("/$regexp/ui", $my_name, $matches)) {
			$this->_name_with_discipline  = $matches['predm'];
			$my_name = preg_replace("/$regexp/ui", '', $my_name);
		} else if (preg_match("/$reg_pre_school/ui", $my_name, $matches) ) {
			$this->_name_with_discipline  = $matches['predm'];
			$my_name = preg_replace("/$reg_pre_school/ui", '', $my_name);
		}	

		return $my_name;
	}

	protected function remove_orphan(string $my_name):string {
		$els = load_dict(__DIR__ . '/dict/orphan.txt');
		$regexp = '(' . implode('|',$els). ')';
		if ( preg_word_match($regexp, $my_name, $matches) ) {
			$this->_name_orphan  = $matches[0];
			$my_name = preg_word_replace($regexp, '', $my_name);
		}
		return $my_name;
	}
	
	protected function remove_health(string $my_name):string {
		$els = load_dict(__DIR__ . '/dict/health.txt');
		$regexp = '(' . implode('|',$els). ')';
		if ( preg_word_match($regexp, $my_name, $matches) ) {
			$this->_name_health  = $matches[0];
			$my_name = preg_word_replace($regexp, '', $my_name);
		}
		return $my_name;
	}
	
	protected function remove_ministry(string $my_name):string {
		//echo "$my_name\n";
		$els = load_dict(__DIR__ . '/dict/ministry.txt');
		$regexp = '(' . implode('|',$els). ')';
		if ( preg_word_match($regexp, $my_name, $matches) ) {
			$this->_name_ministry  = $matches[0];
			// МВД есть "Санкт-Петербургский университет" и т.п.
			if (!preg_match('/Министерства внутренних дел Российской Федерации/ui', $matches[0])) {
				$my_name = preg_word_replace($regexp, '', $my_name);
			}
		}
		return $my_name;
	}
	
	protected function correct_typing_errors(string $my_name):string {
		// shoft hyphen - shy Unicode symbol
		$my_name = preg_replace("/\u{00AD}/", '', $my_name);
		

		// english to russian
		$my_name = str_replace(['A','a','C','c','M','O','o','P','p'],['А','а','С','с','М','О','о','Р','р'],$my_name);
		
		$my_name = preg_replace('/\s+/u', ' ', $my_name);
		// пробел после запятой
		$my_name = preg_replace('/,(\w)/u', ', $1', $my_name);
		
		// Скобка пробел на скобку
		$my_name = preg_replace('/\( /u', '(', $my_name);
		// пробел скобка на скобку
		$my_name = preg_replace('/ \)/u', ')', $my_name);
		
		// скобка без пробела на скобку пробел
		// Исключение в скобках одна бука В(С)ОЩ РС(Я)
		$open ='\('; $close = '\)';
		$my_name = preg_replace("/(?<!{$open}[А-Я]){$close}\b/u", ') ', $my_name);
		// скобка без пробела на скобку пробел
		// Исключение в скобках одна бука В(С)ОЩ РС(Я)
	
		$my_name = preg_replace("/\b{$open}(?![А-Я]{$close})/u", ' (', $my_name);
	
		// пробел между цифрой и буквой
		// Недостатки: ООШ № 1 З буква "З":) 
		if (!preg_match('/\b(1[СС]|3Д|3ДКЛУБ|союз 5У|ООО АШ2О|ООО 1Т)\b/ui',$my_name) ) {
			$my_name = preg_replace('/([0-9])([А-ЯЁ])/ui', '$1 $2', $my_name);
		}

		
		$my_name = trim($my_name);		
		foreach ($this->typing_errors as $key=>$val) {
			//echo "$key\n";
			$my_name = preg_word_replace($key, $val, $my_name);
		}
		return $my_name;
	}
	
	function parseMBOU(string $my_name, ?array $addr_row=null) {
		$parser = new ParserMBOU();
		$result = $parser->parse($my_name, $addr_row);
		if ($result['found']) {
			$my_name = $result['my_name'];
			$this->_name_MBOU = $result['MBOU'];
			$health = 'Для детей, нуждающихся в психолого-педагогической, медицинской и социальной помощи';
			if ( preg_match("/^$health/ui",$my_name) ) {
				$this->_name_health = $health;
				$my_name = preg_replace("/^$health/ui", '', $my_name);
				$this->_name_MBOU .= ' для детей';
			}
		}
		return $my_name;
	}
	
	function make_short_name(string $full_name, ?array $addr_row = null, ?string $parent_name = null) {
		$this->clear_saved();
		// Положить в атрибут?
		$start_full_name = $full_name;
		
		$full_name = preg_replace("/\bNo\b/", '№', $full_name);
		$full_name = preg_replace("/\bNo([0-9]+)\b/", '№ $1', $full_name);
		
		if ($this->change_case_mode == self::CASE_MODE_AUTO) {
			$change_case = ($full_name == mb_strtoupper($full_name));
		} else if ($this->change_case_mode == self::CASE_MODE_PRESERVE)  {
			$change_case = false;
		} else {
			$change_case = true;
		}

		$my_name = $full_name;
		// \r \n и т.п.

		// исправим очепятки
		$my_name = $this->correct_typing_errors($my_name);
		
		$matches = [];

		//echo "$my_name\n";
		if ($this->need_remove_quotes) {
			$my_name = str_replace(["\\\\'", "\\'", '\\\\"','\\"'], ' ', $my_name);
			$my_name = preg_replace("/\'/u", ' ', $my_name);
			$my_name = preg_replace('/["«»“”`\']|>>|<</u', ' ', $my_name);
		}
		$my_name = preg_replace('/\s\s+/u', ' ', $my_name);
		// После того как убрали кавычки запятая может провиснуть
		$my_name = preg_replace('/\s+,/u', ',', $my_name);
		// Уберем точку на конце
		// Редко но встречается (в кавчках)
		$my_name = preg_replace('/\.$/u', '', $my_name);
		
		$my_name = trim($my_name);
		$my_name = $this->parseMBOU($my_name, $addr_row);
		
		$call_back = function($matches) use($addr_row, $start_full_name, $parent_name) {
			$word = $match = $matches[0];
			$changed = false;

			$is_city = $this->is_city_word($word, $addr_row['settlement'] ?? null) ||
				 $this->is_city_word($word, $addr_row['city'] ?? null);
				 
			if ( $is_city ) {
				$changed = true;
				$word = mb_upper_case_first($word);	
			}
			
				
			if ( !$changed && isset($this->dict_words[$word]) ) {
				// Словарное слово
				$changed = true;
				$word = $this->dict_words[$word];
				if ( $word == mb_strtolower($word) && preg_match("/\"$word\"/ui", $start_full_name) ) {
					$word = mb_upper_case_first($word);	
				}				
				//echo "dict $word\n";
			}
			
			//echo "$changed\t$word\t$parent_name\n";
			if (!$changed && !empty($parent_name) && $this->find_abbr($word,$parent_name) ) {
				$changed = true;
				$word = mb_strtoupper($word);
			}
			
			if (!$changed) {
				$word = mb_upper_case_first($word);
			}
			return $word;
		};
		if ($change_case) {
			// два и более символа
			$my_name = preg_replace_callback('/\b[А-ЯЁ-][А-ЯЁ-]+\b/u', $call_back, $my_name);
			// Однобуквенные
			$my_name = preg_replace_callback('/\s[висудпк]\s/ui', 
				fn($matches) => mb_strtolower($matches[0]),
				$my_name
			);
			// аул село город деревня поселок хутор пробел перед, после слово чтобы не попасть в иницалы
			$my_name = preg_replace_callback('/(\(|\s)([асдгпх])\.(\s*)([А-ЯЁ-][А-ЯЁ-]+)/ui', 
				fn($matches) => $matches[1].trim(mb_strtolower($matches[2]).'.'.$matches[3].$matches[4]),
				$my_name
			);
		}
		
		/*
		if ($this->need_remove_fio) {
			$my_name = $this->remove_in_name($my_name);
		}
		*/
		$im_parts = $this->fio_parser->parse($my_name, $change_case);
		
		if ($im_parts) {
			$this->_name_fio_full = $im_parts['im_part'];
			$this->_name_fio_short = $im_parts['short'];
			if ($this->need_remove_fio) {
				$this->_name_fio_removed = 1;
				$my_name = $im_parts['start_part'];
			} else {
				$my_name = $im_parts['start_part'];
				$im_word = $im_parts['im_word'];
				$my_name .= ' '. $im_word ;
//				$my_name .= ' '. $im_parts['im_part'];
				$my_name .= ' '. $im_parts['short'] ?: $im_parts['im_part']; 
			}
			if ($im_parts['end_part']) {
				$my_name .= ' '. $im_parts['end_part'];
			//	echo "$my_name \n {$im_parts['end_part']}\n";
			}
		}
		//echo "$my_name\n";
		$my_name = $this->replace_words($my_name);
		
		if ($this->need_remove_addr && $addr_row) {
			$ParserAddr = new ParserAddr($addr_row);
			$my_name = $ParserAddr->remove($my_name);
			$this->_name_city = $ParserAddr->get_city();
		} else {
			if ($this->need_remove_region && $addr_row) {
				$ParserAddr = new ParserAddr($addr_row);
				$my_name = $ParserAddr->remove_region($my_name);
			}
			if ($this->need_remove_area && $addr_row) {
				$ParserAddr = new ParserAddr($addr_row);
				$my_name = $ParserAddr->remove_area($my_name);
				$my_name = $ParserAddr->remove_city_with_okrug($my_name);
			}
		}

		if ($this->morpher_inflect) {
			//echo "$my_name\n";
			$my_name = morpher_inflect($my_name, $this->morpher_inflect);
			//echo "$my_name\n";
		}
		
		
		$my_name = $this->kladr_socr($my_name);
		$my_name =  preg_replace('/\s?\bим\.([А-Я])/ui', ' им. $1', $my_name);
		$my_name =  preg_replace('/\sимени\s/ui', ' им. ', $my_name);
			// убираем пробел после инциалов
			//$my_name = preg_replace('/ им. (.*)\b([А-Я]\.[А-Я]\.)\s([А-Я][а-яё-]+)$/u', ' им. $1$2$3', $my_name);
		
		// Пробел после номера. И перед ФМЛ№239
		$my_name = preg_replace('/(№|N)\s?([0-9])/u', ' № $2', $my_name);
		
		// Два пробела на пробел 
		$my_name = preg_replace('/\s\s+/u', ' ', $my_name);
		// Пробел запятая 
		//$my_name = preg_replace('/\s+,/u', ',', $my_name);

		// Знак номера перед цифрой 
		$my_name = preg_replace('/\b(СОШ|СШ|ООШ|НШ|Школа|Гимназия|Лицей)\b\s([0-9])/ui', '$1 № $2', $my_name); 

		$my_name = preg_replace('/(СОШ|НОШ|школа)\s?-\s?детский сад/ui', '$1 - детский сад', $my_name); 
		
		
		// Тире в начале строки осталось от предыдущих замен
		$my_name = preg_replace('/^\s*-/ui', '', $my_name); 	
		$my_name = trim($my_name);
		
//		if ($this->use_socr_replace) {
			$my_name = $this->replace_socr($my_name);
//		}
		// Первая заглавная
		if (!preg_match('/^[А-Я]\./ui', $my_name) ) {
			$my_name = mb_strtoupper(mb_substr($my_name,0,1)) .  mb_substr($my_name,1);
		}
		
		$my_name = $this->restore_fio($my_name);
		$this->_name = $my_name;
		return $my_name;
	}
	
	private function restore_fio($my_name) {
		if ( preg_match('/^(Школа|Гимназия|Лицей|ЦО|Детская музыкальная школа|Детская художественная школа|Детская школа искусств|Православная гимназия|СОШ|ООШ|СШ)$/ui', $my_name) ) {
			if ($this->_name_fio_short) {
				$my_name .= ' им. '. $this->_name_fio_short;
				$this->_name_fio_removed = 0;
			}
		}
		return $my_name;
	}
	
	private function is_city_word(string $word, ?string $city):bool {
		if (!$city) return false;
		$socrs = ['ст-ца'=>'станица','г'=>'город','рп'=>'поселок','пгт'=>'поселок','с\/п'=>'поселение','тер'=>'территория'];
		foreach ($socrs as $short=>$full) {
			$city = preg_replace("/\b$short\b/ui", $full, $city);
		}
		$city_rod_pod = morpher_inflect($city, 'rod');
		//echo "$city $city_rod_pod\n";
		return ( preg_match("/\b$word\b/ui", $city) ||
				preg_match("/\b$word\b/ui", $city_rod_pod) ); 	
	}
	
	function replace_socr(string $name):string {
		$end = reg_word_end();
		foreach ($this->socr_replaces as $key=>$val) {
			//echo "$key' '$name' \n";
			$name = preg_replace("/\b$key{$end}/ui", $val, $name);
		}
		//echo "$name\n";
		return $name;
	}
	
	function add_fio($my_name) {
		$words = ['школа','лицей','гимназия','СОШ','ОШ','ЦО','Детская музыкальная школа','Детская школа искусств','Детская музыкальная школа','Спортивная школа'];
		foreach ($words as $word) {
			if ( preg_match("/^$word$/ui", $my_name) ) {
				if ($this->_name_fio_short) {
					$my_name .= ' им. '.$this->_name_fio_short;
					break;
				}
			}
		}
		return $my_name;
	}

	function kladr_socr($my_name) {
		// аул село город деревня поселок хутор пробел перед, после слово чтобы не попасть в инициалы
		// если используем кратое имя, то должны быть только инициалы  порядок им. А.В. Чернов
		// поэтому хватит lookback не далеко
		$not_im = "(?<!\b(им\. ?|имени).{0,5})";
		$my_name = preg_replace_callback("/$not_im\s(?<type>[асдгпх])\.\s*(?<name>[А-ЯЁ-][А-ЯЁ-]+)/ui", 
			fn($matches) => ' '.trim(mb_strtolower($matches['type']).'.'.$matches['name']),
			$my_name
		);
		// Сокращения нас. пунктов с точкой на конце
		$my_name = preg_replace_callback("/$not_im\b(р\.-п|с\.-п|ж\. ?д\. ?станции|г\.\s*п|ст|пос|кп|пгт|пст|свх|свт|хут|ж\.-д|п\.г\.т)\.\s*/ui", 
			fn($matches) => ' '.trim(mb_strtolower($matches[0])),
			$my_name
		);
		// Сокращения нас. пунктов без точки на конце
		$my_name = preg_replace_callback('/\b(р-н|р-на|ст-цы|обл|ст-ца|пр-кт|б-р)|\b/ui', 
			fn($matches) => trim(mb_strtolower($matches[0])),
			$my_name
		);

		// Сокращения нас. пунктов без точки на конце добавим точку
		$my_name = preg_replace_callback('/\b(г|ул)\s/ui', 
			fn($matches) => trim(mb_strtolower($matches[0])).'.',
			$my_name
		);
		
		$replaces = [
			'села'=>'с.'
			,'сельского поселения село'=>'с.'
			,'сельского поселения поселок'=>'п.'
			,'сельского поселения'=>'с.п.'
			,'городского поселения рабочий поселок'=>'р.п.'
	//		,'село'=>'с.'
			,'аула'=>'а.'
			,'города ?- ?курорта'=>'г-к.'
			,'города'=>'г.'
			,'поселка городского типа'=>'пгт '
			,'рабочего поселка'=>'р.п.'
			,'пос(ё|е)лка'=>'п.'
			,'рабочий поселок'=>'р.п.'
	//		,'поселок'=>'п.'
			,'хутора'=>'х.'
			,'станицы'=>'ст-цы '
			,'станица'=>'ст-ца '
			,'деревни'=>'д.'
			,'с\. ?п\.'=>'с.п.'
			,'р\. ?п\.'=>'р.п.'
			,'г\. ?о\.'=>'г.о.'
			,'п ?\.г ?\.т\.'=>'пгт '
			,'пгт\.?'=>'пгт '
			,'закрыто(го|е) административно ?- ?территориально(го|е) образовани(я|е)'=>'ЗАТО '
			,'городского округа'=>'г.о.'
		];
		foreach ($replaces as $full=>$short) {
			$my_name = preg_replace("/\b$full(\s|\b)/ui", "$short", $my_name);
			//$my_name = preg_word_replace($full, "$short", $my_name);
		}
		
		return $my_name;	
	}
	
	// Ищет аббревиатуру слова $word в $text
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
}