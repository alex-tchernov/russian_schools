<?
Class ParserAddr {
	protected $addr = null;
	protected $_city = null;
	protected $ministry_regexp = null;
	
	function __construct(?array $addr) {
		$ministry = load_dict(__DIR__ . '/dict/ministry.txt');
		$this->ministry_regexp = '('.implode('|',$ministry ).')';
		if ($addr) {
			$this->addr = $addr;
		}
	}
	
	public function remove(string $my_name): string {
		// По кругу )
		$this->_city = null;
//		do {
			$prev_name = $my_name;
			$my_name = $this->remove_parts($my_name);
//		} while ($prev_name <> $my_name);
		return $my_name;
	}
	
	public function get_city(): ?string {
		return $this->_city;
	}
	

	protected function remove_parts(string $my_name) {
	//	$my_name = $this->remove_area_and_region($my_name);
		$my_name = $this->remove_region($my_name);
		$my_name = $this->remove_area($my_name);
		
		$my_name = $this->remove_city_with_okrug($my_name);
		$my_name = $this->remove_city($my_name);
		
		if (true || preg_match('/\bфилиал\b/ui', $my_name) ) {
	//		$my_name = $this->remove_area_and_region($my_name);
		}
		
	//	$my_name = $this->remove_inside_area($my_name);
		
		$my_name = $this->remove_selpo($my_name);
	//	echo "$my_name\n";
		$my_name  = preg_replace('/(, ?)+$/u', '', $my_name);
		return $my_name;
	}
	
	function region_socr() {
		$region_full = $this->region_full();
		if ( preg_match('/Саха/ui',$region_full) )  {
			$region_socr = 'РС ?\(?Я\)?';
		} elseif ( preg_match('/Алания/ui',$region_full) ) {
			$region_socr = 'РСО-Алания';
		} elseif ( preg_match('/Кабардино-Балкарская/ui', $region_full) ) {
			$region_socr = 'КБР';
		} else {
			$region_socr = implode('', array_map( fn($el) =>mb_substr($el,0,1), explode(' ', $region_full) ));
		}
		return $region_socr;	
	}
	
	function remove_area_and_region($my_name) {
		$area_full = $this->area_full();
		if ($area_full) {
			$area = $this->area();
			$region_full = $this->region_full();
			$region_full_rod_pd = morpher_inflect($region_full, 'rod');
			$region_full = $this->escape_regexp($region_full);
			$region_full_rod_pd = $this->escape_regexp($region_full_rod_pd);
			

			$area_rod_pds = $this->area_rod_pds();
			//echo "$area_rod_pd\n";
			$regexps = [
				"муниципального района $area $region_full_rod_pd"
				,"(муниципального района )?$area_full $region_full_rod_pd"
			];
			foreach ($area_rod_pds as $area_rod_pd) {
				$regexps[] = "$area_rod_pd (муниципального )?(района|раойна) $region_full_rod_pd";
				$regexps[] = "$area_rod_pd муниципального округа $region_full_rod_pd";
			}
			foreach ($regexps as $regexp) {
				//$regexp = $this->escape_regexp($regexp);
				$regexp = "(муниципального образования )?$regexp";
				//echo "'$regexp' $my_name\n";
				if ( preg_match("/\b{$regexp}\b/ui", $my_name) ) {
					$my_name = preg_replace("/\b$regexp\b/ui", "", $my_name);
					//echo "MAYCH\n";
					//echo "$regexp\t$my_name\n";
				}
			}
		}
		return $my_name;
	}
	
	function remove_inside_area($my_name) {
		$area_full = $this->area_full();
		if ($area_full) {
			$area_rod_pds = $this->area_rod_pds();
			$area = $this->area();
			$regexps = [
				"муниципального образования $area район"
			];
			foreach ($area_rod_pds as $area_rod_pd) {
				$regexps[] = "$area_rod_pd муниципального района";
				$regexps[] = "$area_rod_pd муниципального округа";
			}
			foreach ($regexps as $regexp) {
				//$regexp = $this->escape_regexp($regexp);
				//echo "$regexp $my_name\n";
				if ( preg_match("/\b{$regexp}\b/ui", $my_name) ) {
					$my_name = preg_replace("/\b$regexp\b/ui", "", $my_name);
					//echo "MAYCH\n";
					//echo "$regexp\t$my_name\n";
				}
			}
		}
		return $my_name;
	}
	
	function escape_regexp(string $string): string {
		$string = str_replace(['/','(',')'], ['\/','\(','\)'], $string);
		return $string;
	}

	public function remove_region(string $my_name):string {
		$saved_my_name = $my_name;
		//echo "1 $my_name\n";
		$region_full = $this->region_full();

		if ( preg_match('/Якутия/ui', $region_full) ) {
			$region_full = 'Республика Саха \(Якутия\)';
			// Две закрывающие какая-то опечатка
			$region_full_rod_pd = 'Республики Саха ?\(?Якутия\)?';
		} else if ($region_full == 'Республика Северная Осетия - Алания') {
			$region_full_rod_pd = 'Республики Северная Осетия-Алания';
		} else if (preg_match('/Кемеровская/ui', $region_full) ) {
			$region_full_rod_pd = 'Кемеровской области(-Кузбасса)?';
		} else {
			$region_full_rod_pd = morpher_inflect($region_full, 'rod');
		}
		$region_socr = $this->region_socr();
		$region_full = preg_replace('/-/ui', ' ?-? ?', $region_full);
		$region_full_rod_pd = preg_replace('/-/ui', ' ?-? ?', $region_full_rod_pd);
		$region_full .=	"(,? Российской Федерации)?";
		$region_full_rod_pd .= "(,? Российской Федерации)?";
	//	Республика Саха /Якутия/, Республики Саха ?\(?Якутия\)?\)	
		// Убираем  ТОЧКУ на конце!!
		$ministry = $this->ministry_regexp;

/*
		if ( !preg_match("/$ministry$/ui", $my_name) ) {
			$my_name = preg_replace('/Российской Федерации\.?$/u', '', $my_name);
			$my_name = trim($my_name);
//			$my_name = preg_replace("/$region_full_rod_pd\.?$/ui", '', $my_name);
		}
*/		
		
		$my_name = preg_replace("/$region_full\.?$/ui", '', $my_name);
//		$my_name = preg_replace("/$region_full\.?( Российской Федерации)?$/ui", '', $my_name);
//		echo "$region_full_rod_pd\n\t$my_name\n";
//		$my_name = preg_replace("/^$region_full_rod_pd/ui", '', $my_name);
		// Регион может заканчиваться на скобку - Саха поэтому вместо \и отрицательный look ahead
		$r = "/(?<!\bпо )\b$region_full_rod_pd(?!\w)/ui";
//		echo "$r\n\t$my_name\n";
		$not = "(?<!\b(по|в|и|медицинского развития|пожарной безопасности|противопожарной службы|управление|библиотека) )";
		$my_name = preg_replace("/$not\b$region_full_rod_pd(?!\w)/ui", '', $my_name);
		$my_name = preg_replace("/ $region_socr$/ui", '', $my_name);
		
		$my_name = trim($my_name);
		// МИНИСТЕРСТВА ЗДРАВООХРАНЕНИЯ РСО-АЛАНИЯ - не тот падеж
		if ( preg_match ('/ (в|по|при правительстве|при президенте|при главе)$/ui', $my_name) ) {
			$my_name = $saved_my_name;
		}
//		echo "$my_name\n";
		return $my_name;
	}

	protected function area(): ?string {
		$area = null;
		$area_full = $this->area_full();
		if ($area_full) {
			list($area_type, $area) = $this->area_with_type($area_full);
		}
		return $area;
	}

	protected function area_with_type($area_full) {
		$parts = explode(' ', $area_full);
		$types = ['улус', 'город', 'поселок', 'район', 'поселение'];
		$area_type = '';
		if ( in_array(end($parts), $types) ) {
			$area_type = array_pop($parts);
		} else if (in_array(reset($parts), $types) ) {
			$area_type = array_shift($parts);
		} else {
			echo "NO AREA $area_full\n";
		}
		$area = implode(' ', $parts);
		return [$area_type, $area];
	}
	
	function remove_selpo(string $my_name):string {
		//echo "$my_name\n";
		// Старо-Казеевская СОШ Староказеевского сельского поселения
		if ( preg_match("/\b([А-Я]+)-?([А-Я]+)ская (.*) (?<sp>\\1\\2ского сельского поселения)/ui", $my_name, $matches) ) {
			$sp = $matches['sp'];
			$my_name = preg_replace("/$sp/u", '', $my_name);
			$my_name = preg_replace("/\s+/u", ' ', $my_name);
			//echo "$my_name";
		}
		return $my_name;
	}

	function remove_area(string $my_name):string {
		//if (!isset($this->addr['area'])) return $my_name;
		$region_full = $this->region_full();
		$area_rod_pds = $this->area_rod_pds();
		$area_full = $this->area_full();
		
		if (!$area_full)  return $my_name;
		$area_type = '';
		$area = null;
		$district_types = [];
		list($area_type, $area) = $this->area_with_type($area_full);
		if ($region_full == 'Республика Тыва' && $area_type == 'район') {
			$district_types[] = 'кожуун';
		}
		
		if ( in_array($area_type, ['район','улус']) ) {
			$district_types[] = 'район';
			$district_types[] = 'муниципальный район';
			$district_types[] = 'округ';
			$district_types[] = 'муниципальный округ';
			$district_types[] = 'образование';
		}
		
		if ($area_type == 'улус') {
			
			$district_types[] = '\(улус\) район';
			$district_types[] = 'улус ?\(?район\)?';
			$district_types[] = 'улус';
			// ПЛОХО для Морфера

		}
		
		$district_types_rod_pod = array_map(
			fn($el) => morpher_inflect($el, 'rod'),
			$district_types
		);
		$district_types_rod_pod_regexp = implode('|', $district_types_rod_pod);
		
		$area_full_regexp = $area . ' (' . implode('|', $district_types).')';
		// echo "$area";
		// муниципального района Чишминский район Республики Башкортостан
		// Хлевенского муниципального района Липецкой области
		// 10357 Цивильского муниципального округа Чувашской Республики
		// Селивановского района Владимирской области
		// Катав-Ивановского муниципального района
		// СОШ р.п.Магнитка Кусинского района
		$regexps = [
			 "муниципального образования муниципальн(ый|ого) округ(а)? $area_full_regexp"
			,"муниципального района муниципального образования $area_full_regexp"
			,"муниципального (района|образования|округ(а)?)( ?- ?| )$area_full_regexp"
			,"(МР|МО) $area_full_regexp"
			,"муниципального района $area"
			,"(муниципальный район )?$area_full_regexp"
		];
		foreach ($area_rod_pds as $area_rod_pd) {
		//	echo "$area_rod_pd\n";
		//	$regexps[] = "{$area_rod_pd} (муници-?пального|городского)?\s?($district_types_rod_pod_regexp)";
			// таких вроде пара штук:
			// с. Успенского муниципального образования Успенский
			// поселка мостовского муниципального образования мостовский район
			$not = "(?<!\b(пос(е|ё)лка|села|с\.|п\.) )";
			array_unshift($regexps,"{$not}{$area_rod_pd} (муниципального|городского)?\s?($district_types_rod_pod_regexp)");
		}
		if ($area == 'Островский') {
			array_unshift($regexps, 'муниципального образования город Остров и Островский район');
		} elseif ($area == 'Нерехтский') {
			array_unshift($regexps, 'муниципального района город Нерехта и Нерехтский район');
		} elseif ($area == 'Михайловский') {
			array_unshift($regexps, 'муниципального округа город Михайловка');
			// ФРДО
			array_unshift($regexps, 'городского округа город Михайловка');
		} elseif ($area == 'Нейский') {
			array_unshift($regexps, 'муниципального района город Нея и Нейский район');
		} elseif ($area == 'Анапский') {
			array_unshift($regexps, 'муниципального образования город-курорт Анапа');
		} elseif ($area == 'Демидовский') {
			array_unshift($regexps, 'муниципального образования Демидовский муниципальный округ');
		} elseif ($area == 'Иркутский') {
			array_unshift($regexps, 'Иркутского районного муниципального образования');
		} elseif ($area == 'Олонецкий') {
			array_unshift($regexps, 'Олонецкого национального муниципального района');
		}  elseif ($area == 'Малодербетовский') {
			array_unshift($regexps, 'Малодербетовского районного муниципального образования');
		}  elseif ($area == 'Шатура') {
			array_unshift($regexps, 'муниципального округа Шатура');
		}  elseif ($area == 'Анабарский') {
			array_unshift($regexps, 'муниципального образования Анабарский национальный \(Долгано-Эвенкийский\) Улус \(район\)');
		}  elseif ($area == 'Рязанский') {
			array_unshift($regexps, 'муниципального образования-Рязанский муниципальный район');
		}
		

		$my_name = trim($my_name);
		
		foreach ($regexps as &$reg) {
			$reg = preg_replace('/ё/iu', '(е|ё)', $reg);
			$reg = preg_replace('/-/ui', ' ?-? ?', $reg);
		}
		
		unset($reg);
		
		foreach ($regexps as $reg) {
			// последний символ не словарный (буква/цифра) или конец слова
			$r_end = "((?<=\W)|\b)";
			// backward lookup NOT - исключим
			$not = "(?<!\b(по|в|в МО|в МР|и) )";
			$regexp = "\b(муниципального образования |администрации )?{$not}{$reg}{$r_end}";
//			echo "/{$regexp}$/ui\n\t'$my_name'\n";
//			if ( preg_match("/{$regexp}$/ui", $my_name) ) {
			if ( preg_match("/$regexp/ui", $my_name) ) {
				
				$my_name = preg_replace("/$regexp/ui", "", $my_name);
				$my_name = trim($my_name);
			} else {
//				echo "NO MATH \n";
			}
	/*		
			if ( preg_match("/^$regexp/ui", $my_name) ) {
				$my_name = preg_replace("/^$regexp\b/ui", "", $my_name);
				$my_name = trim($my_name);
			}
	*/
		}
		return $my_name;
	}

	protected function city(): string {
		$city_explode = explode(' ', $this->addr['city']);
		$city_type = array_shift($city_explode);
		$city = implode(' ', $city_explode);
		if ($city == 'Гусиноозерск') {
			$city = 'Гусиноозёрск';
		}
		return $city;
	}

	protected function city_rod_pod(): string {
		$city = $this->city();
		if ($city == 'Джанкой') {
			$city_rod_pod_full = 'города Джанкоя';
		} else {
			$city_rod_pod_full = morpher_inflect('город '.$city, 'rod');
		}
		$city_explode = explode(' ', $city_rod_pod_full);
		$dummy = array_shift($city_explode);
		$city_rod_pod = implode(' ', $city_explode);
		return $city_rod_pod;
	}
	
	protected function city_has_district($city) {
		return in_array($city, ['Саратов', 'Ростов-на-Дону', 'Уфа', 'Волгоград', 'Казань', 'Санкт-Петербург']);
	}

	function city_okrugs(): ?array {
		$city = $this->addr['city'];
		$map = [
			'г Анжеро-Судженск'=>'Анжеро-Судженского городского округа'
			,'г Арсеньев'=>'Арсеньевского городского округа'
			,'г Артем'=>'Артемовского городского округа'
			,'г Большой Камень'=>'городского округа закрытое административно-территориальное образование Большой Камень'
			,'г Дальнереченск'=>'Дальнереченского городского округа'
			,'г Геленджик'=>'Город-Курорт Геленджик'
			,'г Глазов'=>'городской округ город Глазов'
			,'г Бердянск'=>'городской округ Бердянск'
			,'г Знаменск'=>'(Городской округ )?Закрытое Административно-Территориальное образование Знаменск'
			,'г Заречный'=>'Муниципального округа Заречный'
			,'г Заводоуковск'=>'Заводоуковского городского округа'
			,'г Карачаевск'=>'Карачаевского городского округа'
			,'г Костомукша'=>'Костомукшского городского округа'
			,'г Камышлов'=>'Камышловского городского округа'
			,'г Киселевск'=>'Киселевского городского округа'
			,'г Копейск'=>'Копейского городского округа'
//			,'г Кувандык'=>'Кувандыкского городского округа'
			,'г Красноперекопск'=>'городской округ Красноперекопск'
			,'г Люберцы'=>'городской округ Люберцы'
			,'г Ладушкин'=>'Ладушкинский городской округ'
			,'г Лесозаводск'=>'Лесозаводского городского округа'
			,'г Михайловка'=>'муниципального округа город Михайловка'
			,'г Шатура'=>'муниципального округа Шатура'
			,'г Межгорье'=>'(городского округа )?закрытое административно-территориальное образование город Межгорье'
			,'г Находка'=>'Находкинского городского округа'
//			,'г Нижняя Тура'=>'Нижнетуринского городского округа'
			,'г Новая Каховка'=>'Новокаховского городского округа'
			,'г Партизанск'=>'Партизанского городского округа'
			,'г Перевоз'=>'городского округа Перевозский'
			,'г Петрозаводск'=>'Петрозаводского городского округа'
			,'г Петропавловск-Камчатский'=>'Петропавловск-Камчатского городского округа'
//			,'г Полевской'=>'Полевского городского округа'
			,'г Пушкино'=>'городского округа Пушкинский'
			,'г Ивантеевка'=>'городского округа Пушкинский' // :))
			,'г Скопин'=>'муниципального образования ?- ?городской округ город Скопин'
			,'г Сочи'=>'городской округ город-курорт Сочи'
			,'г Старый Оскол'=>'Старооскольского городского округа'
			,'г Светлый'=>'Светловский городской округ'
			,'г Стерлитамак'=>'городского округа город Стерлитамк'
			,'г Симферополь'=>'городской округ Симферополь'
			,'г Североморск'=>'ЗАТО г. ?Североморск'
			,'г Холмск'=>'Холмский городской округ'
//			,'г Касимов'=>'городской округ город Касимов'
//			,'г Сортавала'=>'Сортавальского городского округа' // муниципальные обычно по Area 
			,'г Тайга'=>'Тайгинского городского округа'
			,'г Уссурийск'=>'Уссурийского городского округа'
			,'г Фокино'=>'городского округа Затогород Фокино'
//			,'г Энгельс'=>'Энгельсского городского округа'
//			,'г Рузаевка'=>'Рузаевского городского округа'
//			,'г Гай'=>'Гайского городского округа'
			,'г Чебоксары'=>'города Чебоксары - столицы' //Чувашии
			,'г Усть-Кут'=>'Усть-Кутского муниципального образования'
			,'г Касимов'=>['Касимовского городского округа','городской округ город Касимов']
			,'г Коломна'=>'Коломенского городского округа'
//			,'пгт Малышева'=>'Малышевского городского округа'
//			,'г Кушва'=>'Кушвинского городского округа'
			,'г Ясный'=>['Ясненского муниципального округа','Ясненский городской округ']
			,'г Феодосия'=>'городской округ Феодосия'
			,'г Ялта'=>'городской округ Ялта'
			,'г Щёлково'=>['Щёлковского муниципального района', 'городского округа Щелково']
			,'пгт Янтарный'=>'Янтарный городской округ'
			,'г Мелитополь'=>['городского округа Мелитополь',  'г\. ?о\. ?Мелитополь']
			,'г Фокино'=>['городского округа ЗАТО город Фокино', 'городского округа ЗАТО Фокино']
			,'г Чебаркуль'=>'Чебаркульского городского округа'
			,'поселок Горный'=>'городского округа закрытого административно-территориального образования п. Горный'
			,'г Островной'=>'городского округа закрытое административно-территориальное образование город Островной'
			,'г Новоуральск'=>'Новоуральского городского округа'
			,'г Верхний Уфалей'=>'Верхнеуфалейского городского округа'
			,'г Златоуст'=>'Златоустовского городского округа'
			,'г Миасс'=>'Миасского городского округа'
			,'г Советск'=>'Советского городского округа'
			
			,'пгт Агинское'=>'городского округа Поселок Агинское'
			,'пгт Рефтинский'=>'городского округа Рефтинский'
			,'поселок Жатай'=>'городского округа Жатай'
			,'поселок ЗАТО Сибирский'=>'городского округа закрытого административно-территориального образования СИБИРСКИЙ'
			// area
			,'Соль-Илецкий р-н'=>'Соль-Илецкого городского округа'
			,'Борисоглебский р-н'=>'Борисоглебского городского округа'
			,'Шалинский р-н'=>'Шалинского городского округа'
			,'г Донецк'=>'городского округа Донецк' // Регионы
			,'г Енакиево'=>'городского округа Енакиево'
			,'г Харцызск'=>'городского округа Харцызск'
			,'г Гаджиево'=>'г. ?Гаджиево ЗАТО Александровск'
			,'г Снежногорск'=>'г. ?Снежногорск ЗАТО Александровск'
			,'г Полярный'=>'г. ?Полярный ЗАТО Александровск'
			,'г Шатура'=>'городского округа Шатура'
			,'г Серпухов'=>'городского округа Серпухов'
			,'Смирныховский р-н'=>'городской округ Смирныховский'
		];
		$result = [];
		foreach (['area', 'city', 'settlement'] as $type) {
			$city = $this->addr[$type];
			if ( isset($map[$city]) ) {
				$val = $map[$city];
				if ( is_string($val) && !preg_match('/ /', $val) ) {
				//	$val .= ' (городского|муниципального) (округа|района)';
				}
				$result = array_merge($result, is_array($val) ? $val: [$val]);
			}
		}

		if (!$result) return null;
		return $result;
	}

	function remove_city(string $my_name):string  {
		if ($this->addr['city']) {
			$type = "город |города |г\. ?|г ";
			$city = $this->city();
			$city_rod_pod = $this->city_rod_pod();
			$has_district = $this->city_has_district($city);
			$city = preg_replace('/ё/u', '(е|ё)', $city);
			$city_rod_pod = preg_replace('/ё/u', '(е|ё)', $city_rod_pod);
			
			$regexps = [];
			if ($has_district) {
				$regexps[] = "[А-Я-]+ района ($type)?$city_rod_pod";
			}
			if (!$has_district && $this->addr['kladr'] && preg_match('/^780000/',$this->addr['kladr']) ) {
				$regexps[] = "[А-Я-]+ района Санкт-Петербурга\b";
			}
			// (?<!\() - looke behind исключим открывающую скобку перед городом
			// исключим в городе 
			// административного округа - кажется только ДОСААФ идут названия округов Москвы
			$not = "(?<!( в |административного округа |\([^\)]{0,50}))";
			$regexp = "$not($type)($city|$city_rod_pod)";
			$regexps[] = $regexp;
			foreach ($regexps as $reg) {
				$reg = "\b(муниципального образования |( местной )?администрации )?($reg)\b(?!(-| и\b))";
				if ( preg_match("/$reg/ui", $my_name) ) {
					$my_name = preg_replace("/$reg/ui", '', $my_name);
					$my_name = trim($my_name);
					$this->_city = $city;
				}
			}
		}
		return $my_name;
	}
	
	function remove_city_with_okrug(string $my_name):string {
		$regexps = [];
		$has_district = false;
		$city = null;
		if ($this->addr['city']) {
		
			$city = $this->city();
			$city_rod_pod = $this->city_rod_pod();
			$has_district = $this->city_has_district($city);
			$city = preg_replace('/ё/u', '(е|ё)', $city);
			$city_rod_pod = preg_replace('/ё/u', '(е|ё)', $city_rod_pod);

			// У филиалов часто целиком указано в скобках расположение

			$type = "город |города |г\. ?|г ";
			$regexp = "городского округа (- )?($type)?($city|$city_rod_pod)";
			if ($has_district) {
				$regexps[] = "([А-Я-]+ района )?$regexp";
	//			$regexps[] = "[А-Я-]+ района ($type)?$city_rod_pod";
			} else {
				$regexps[] = $regexp;	
			}
		}
		
		$city_okrugs = $this->city_okrugs();
		if ($city_okrugs) {
			foreach ($city_okrugs as $city_okrug) {
				array_unshift($regexps, "$city_okrug");
			}
		}
		
		foreach ($regexps as $reg) {
			// (?!-) Отрицательный lookahead исключить Кузнецк-12
			$reg = "\b(муниципального образования( ?- ?| ))?$reg\b(?!(-| и\b))";
			//echo "$reg\n\t$my_name\n";
			if ( preg_match("/$reg/ui", $my_name) ) {
				$my_name = preg_replace("/$reg/ui", "", $my_name);
				$my_name = trim($my_name);
				$this->_city = $city;
			}
		}
		return $my_name;
	}


	function area_full() {

		$area = $area_full = $this->addr['area'];
		if ($area) {
			if ( preg_match('/^у /ui', $area) ) {
				$area_full = preg_replace('/^у /ui', '', $area). ' улус';
			}
			foreach (['р-н'=>'район'] as $short=>$full) {
				$area_full = preg_replace("/\b$short\b/ui", $full, $area_full);
			}
			if ($area_full == 'Жиганский улус') {
				$area_full = 'Жиганский национальный эвенкийский район';
			} elseif ($area_full == 'Пряжинский район') {
				$area_full = 'Пряжинский национальный район';
			} elseif ($area_full == 'Пугачевский район') {
				$area_full = 'Пугачёвский район';
			} elseif ($area_full == 'Фаленский район') {
				$area_full = 'Фалёнский район';
			} else if ($area_full == 'Олекминский улус') {
				$area_full = 'Олёкминский улус';
			}
		}
		
		$cities = [
			"г Пушкино" => "Пушкинский"
			,"г Алатырь" => "Алатырский"
			,"г Амурск" => "Амурский"
			,"г Асбест" => "Асбестовский"
			,"г Бикин" => "Бикинский"
			,"г Вольск" => "Вольский"
			,"г Гай" => "Гайский"
			,"г Касимов" => "Касимовский"
			
			,"г Кувандык" => "Кувандыкский"
			,"г Кушва" => "Кушвинский"
			,"г Нижняя Тура" => "Нижнетуринский"
			,"г Полевской" => "Полевский"
			,"г Рузаевка" => "Рузаевский"
			,"г Сортавала" => "Сортавальский"
			,"г Энгельс" => "Энгельсский"
			,"пгт Малышева" => "Малышевский"
			
			,"г Дмитров" => "Дмитровский"
			,"г Воскресенск" => "Воскресенский"
			,"г Серпухов" => "Серпуховский"
			,"г Одинцово" => "Одинцовоский"
			,"г Истра" => "Истринский"
			,"г Сергиев Посад" => "Сергиев-Посадcкий"
			,"г Клин" => "Клинский"
			,"г Коломна" => "Коломненский"
			,"г Наро-Фоминск" => "Наро-Фоминский"
			,"г Ногинск" => "Ногинский"
			,"г Орехово-Зуево" => "Орехово-Зуевский"
			,"г Павловский Посад" => "Павлово-Посадский"
			,"г Шатура" => "Шатурский"
			,"г Донецк" => "Донецкий"
			,"г Луганск" => "Луганский"
			,"г Енакиево" => "Енакиевский"
			,"г Харцызск" => "Харцызский"
			,"г Дебальцево" => "Дебальцевский"
			,"г Алчевск" => "Алчевский"
			,"г Первомайск" => "Первомайский"
			,"г Красный Луч" => ""
			,"г Свердловск" => "Свердловский"
			,"г Антрацит" => "Антрацитовский"
			,"г Краснодон" => "Краснодонский"
			,"г Стаханов" => "Стахановский"
			,"г Кировск" => "Кировскский"
			,"г Брянка" => "Брянский"
			,"г Новая Каховка" => "Ново-Каховский"
			,"г Лисичанск" => "Лисичанскский"
			,"г Шелехов" => "Шелеховский"
			,"г Ржев" => "Ржевский"
			,"г Мелитополь" => "Мелитопольский"
			,"г Белоярский" => "Белоярский"
			,"г Вышний Волочек" => "Вышневолоцкий"
			,"г Зубцов" => "Зубцовский"
			,"г Грозный" => "Грозненский"
			,"г Советская Гавань" => "Советско-Гаванский"
			,"пгт Сонково" => "Сонковский"
			,"рп Лотошино" => "Лотошинский"
			,"г Балашиха" => "Балашихинский"
			,"г Курск" => "Курский"
			,"г Люберцы" => "Люберецкий"
			,"г Перевоз" => "Перевозский"
			,"г Ступино" => "Ступинский"
			,"г Чита" => "Читинский"
			,"г Щёлково" => "Щелковский"
			,"г Заводоуковск" => "Заводоуковский"
			,"г Канаш" => "Канашский"
			,"г Ковылкино" => "Ковылкинский"
			,"г Кимры" => "Кимрский"
			,"г Шатура" => "Шатура"
			,"г Шебекино" => "Шебекинский"
			,"г Кировград" => "Кировградский"
			,"г Сорочинск" => "Сорочинский"
			,"г Аткарск" => "Аткарский"
			,"г Стародуб" => "Стародубский"
		];
		$city = $this->addr['city'];
		if ( isset($cities[$area]) ) {
			$area_full = $cities[$area]. ' район' ;
		} else if (!$area && $city && isset($cities[$city]) ) {
			$area_full = $cities[$city]. ' район' ;
		}
		return $area_full;
	}

	function area_full_rod_pds(): ?array {
		$area_full = $this->area_full();
		$result = null;
		
		if ($area_full == 'Соль-Илецкий район') {
			// ошибка morpher
			$area_full_rodp = 'Соль-Илецкого района';
		} elseif ($area_full == 'Ножай-Юртовский район') {
			$area_full_rodp = 'Ножай-Юртовского района';
		} elseif ($area_full == 'Гаврилов-Ямский район') {
			$area_full_rodp = 'Гаврилов-Ямского района';
		} elseif ($area_full == 'Аяно-Майский район') {
			$area_full_rodp = 'Аяно(-| )Майского района';
		} elseif ($area_full == 'Ачхой-Мартановский район') {
			$area_full_rodp = 'Ачхой-Мартановского района';	
		} elseif ($area_full == 'Урус-Мартановский район') {
			$area_full_rodp = 'Урус-Мартановского района';
		} elseif ($area_full == 'Итум-Калинский район') {
			$area_full_rodp = 'Итум-Калинского района';
		} elseif ($area_full == 'Вагайский район') {
			$area_full_rodp = 'Вагайского района';
		} elseif ($area_full == 'Гайский район') {
			$area_full_rodp = 'Гайского района';
		} elseif ($area_full == 'Чаплынский район') {
			$area_full_rodp = ['Чаплынского района', 'Чаплинского района'];
		} elseif ($area_full == 'Лев-Толстовский район') {
			$area_full_rodp = 'Лев-Толстовского района';
		} elseif ($area_full == 'Дзун-Хемчикский район') {
			$area_full_rodp = 'Дзун-Хемчикского кожууна';
		} elseif ($area_full == 'Пий-Хемский район') {
			$area_full_rodp = 'Пий-Хемского кожууна';
		} elseif ($area_full == 'Барун-Хемчикский район') {
			$area_full_rodp = 'Барун-Хемчикского Кожууна';
		} elseif ($area_full == 'Монгун-Тайгинский район') {
			$area_full_rodp = 'Монгун-Тайгинского Кожууна';
		} elseif ($area_full == 'Бай-Тайгинский район') {
			$area_full_rodp = 'Бай-Тайгинского кожууна';
		} elseif ($area_full == 'Чеди-Хольский район') {
			$area_full_rodp = 'Чеди-Хольского кожууна';
		} elseif ($area_full) {
			$area_full_rodp = morpher_inflect($area_full, 'rod');
		} else {
			$area_full_rodp = null;
		}
		
		if (is_array($area_full_rodp)) {
			$result = $area_full_rodp;
		} else if (is_string($area_full_rodp)) {
			$result = [$area_full_rodp];
		}
		return $result;
	}
	
	function area_rod_pds(): ?array {
		$result = [];
		$area_full_rod_pds = $this->area_full_rod_pds();
		if (!$area_full_rod_pds) return null;
		foreach ($area_full_rod_pds as $area_full_rod_pd) {
			$explode_rod_pd = explode(' ',  $area_full_rod_pd);
			array_pop($explode_rod_pd);
			$result[] = implode(' ', $explode_rod_pd);

		}
		return $result;
	}

	function region_full() {
		$row = $this->addr;
		$region = $row['region'];
		if (!$region) return '';
		
		if ( preg_match('/Чувашская/ui',$region) ) {
			$region = 'Чувашская республика';
		}
		if ( preg_match('/Луганская/ui',$region) ) {
			// Перестановка слов
			$region = 'Луганская Народная республика';
		}		
		if ( preg_match('/Кабардино/ui',$region) ) {
			// Перестановка слов
			$region = 'Кабардино-Балкарская республика';
		}	
		if ( preg_match('/ АО$/ui', $region) ) {
			$region = preg_replace('/ АО$/ui', ' автономный округ', $region);
		}
		$region_full = $region;
		foreach (['обл'=>'область','Респ'=>'Республика'] as $short=>$full) {
			$region_full = preg_replace("/\b$short\b/ui", $full, $region_full);
		}
		return 	$region_full;
	}

	function region_rod_pd() {
		$region_full = region_full();
		$region_full_rodp = morpher_inflect($region_full, 'rod');
		//$region_full_rodp = str_replace(['(',')','/','|'], ['\(','\)','\/','\|'], $region_full_rodp);
		return 	$region_full_rodp;
	}

/*
	function old_remove_area(string $level, string $my_name, array $row_school) {
		$row = $row_school;
		if ( $level != 'school' || !$row['area'] ) {
			return $my_name;
		}
		$MRs = [
			 'администрации муниципального района муниципального образования'
			,'муниципального района'
			,'муниципального образования'
			,'муниципального округа'
			,'МР'
			,'М\.О\.'
			,'МО'
		];
		$found = false;
		
		foreach ($MRs as $MR) {
			if (preg_match("/\b{$MR}\b/u", $my_name)) {
				$found = true;
				break;
			}
		}
		if (!$found) {
			$MR = '';
			//echo $my_name;
			//return $my_name;
		}
		$is_municipality = in_array($MR, ['муниципального образования','МО','муниципального района']);
		$region_full_rodp = $area_rodp = $area = null;
		
		$area_full = $area = $row['area'];
		$area_wo_socr = explode(' ', $area)[0];
		foreach (['р-н'=>'район', 'улус'=>'район'] as $short=>$full) {
			$area_full = preg_replace("/\b$short\b/ui", $full, $area_full);
		}
		
		if ($area_full == 'Соль-Илецкий район') {
			// ошибка morpher
			$area_full_rodp = 'Соль-Илецкого района';
		} else {
			$area_full_rodp = morpher_inflect($area_full, 'rod');
		}
		$area_rodp = explode(' ', $area_full_rodp)[0];
		if ($is_municipality) {
			$area_full = str_replace(' район', '( муниципальный)? район', $area_full);
			$area_rodp .= '( районного)?';
		}
		$region = $row['region'];
		if ($region == 'Чувашская республика - Чувашия') {
			$region = 'Чувашская республика';
			$region_socr = 'ЧР';
		} else if ( $region == 'Респ Саха (Якутия)' || $region == 'Респ Саха /Якутия/') {
			$region_socr = 'РС\(Я\)|РС \(Я\)';
		} else {
			// Респ Башкортостан => РБ
			$region_socr = implode('', array_map(fn($s) =>mb_strtoupper(mb_substr($s,0,1)), explode(' ',$region)));
		}
		$region_full = $region;
		foreach (['обл'=>'область','Респ'=>'Республика'] as $short=>$full) {
			$region_full = preg_replace("/\b$short\b/ui", $full, $region_full);
		}
		$region_full_rodp = morpher_inflect($region_full, 'rod');
		$region_full_rodp = str_replace(['(',')','/','|'], ['\(','\)','\/','\|'], $region_full_rodp);
		$regexps = [
			 "\b\"?{$area_rodp}\s{$MR}\b\"?\s?({$region_socr}|{$region_full_rodp})(\b|$)"
			 
			 ,"\b{$area_rodp}\s{$MR}\"?"
			// После скобки не граница слова РС(Я)!
			// Бывает в кавычках сокращенного не встречал $area ради улус (преобразую в район)
			,"\b{$MR}\b(\s-\s)?\s?\"?($area_wo_socr|$area|$area_full)\"?\s($region_socr|$region_full_rodp)(\b|$)"
			,"\b{$MR}\b(\s-\s)?\s?\"?($area_wo_socr|$area|$area_full)\"?$"
			,"\b{$MR}\b\s$area_full\s(?<preserve>имени|им\.)"
		];
		
		if (!$MR) {
			$regexps = [
				"\b\"?{$area_full_rodp}\"?\s?({$region_socr}|{$region_full_rodp})(\b|$)"
				// Может быть "КАМЫШЕНСКАЯ СОШ ЗАВЬЯЛОВСКОГО РАЙОНА"
				,"\b{$area_full_rodp}\"?$"
			//	,"\b$area_full\s(?<preserve>имени|им\.)"
			];
		}
		foreach ($regexps as $regexp) {
			//echo "$regexp\n$my_name\n";
			if ( preg_match("/$regexp/ui", $my_name) ) {
				$new_name = preg_replace_callback("/$regexp/ui", fn($m) => $m['preserve'] ?? '', $my_name);
				//echo "{$row['id']} $new_name\t|\t$my_name\n";
				$my_name = $new_name;
			}
			if (preg_last_error() !== PREG_NO_ERROR) {
				echo "ERROR:$regexp\n";
			}
		}
		return $my_name;	
	}	
*/	
	
}