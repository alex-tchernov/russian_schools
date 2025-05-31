<?

Class ParserFIO {
	protected $dict_surnames = [];
	protected $dict_ngodata_surnames = [];
	protected $dict_my_last_names = [];
	
	protected $dict_ngodata_first_names = [];
	protected $dict_my_first_names = [];
	
	protected $dict_ngodata_second_names = [];
	protected $dict_my_second_names = [];
	protected $dict_heroes = [];
	
	protected $array_known_fio = [];
//	protected string $reg_heroes;
	
	protected $echo = false;

	
	function __construct() {
		$this->dict_surnames = load_dict(__DIR__ . '/russian-words/russian_surnames.utf-8');
		$this->dict_ngodata_surnames = load_ngodata_names('surnames');
		$this->dict_ngodata_first_names = load_ngodata_names('names');
		$this->dict_ngodata_second_names = load_ngodata_names('midnames');
		$heroes = load_dict(__DIR__ .'/dict/fio/heroes.txt');
		foreach ($heroes as $h) {
			$this->dict_heroes[] = ['masked'=>mask_reg($h), 'text'=>$h];
		}

		$this->dict_my_first_names = $this->load_names(__DIR__ .'/dict/fio/my_first_names.txt');
		$this->dict_my_second_names = $this->load_names(__DIR__ .'/dict/fio/my_second_names.txt');
		$this->dict_my_last_names = $this->load_names(__DIR__ .'/dict/fio/my_last_names.txt');
		
		$this->array_known_fio = $this->load_known(__DIR__ .'/dict/fio/my_known_fio.txt');
		/*
		foreach ($fios as $key=>$val) {
			$this->array_known_fio[mask_reg($key)] = $val;
		}
		*/
		
	//	$this->reg_heroes = "(?P<heroes>(".implode('|', $this->dict_heroes).'|\s|,\s|)+)';
	}
	

	
	function parse(string $name,bool $change_case = true) {
		
		$result = $this->parse_in_name($name);

		if ($result) {
			//echo "$name\n";
			['hero'=>$hero,'name_part'=>$im_part] = $this->parse_heroes($result['im_part']);
			
			$fio_parsed = $this->parse_known_fio($im_part,$change_case);
			if (!$fio_parsed) {			
				$fio_parsed = $this->parse_fio_with_initials($im_part,$change_case);
			//	var_dump($fio_parsed);
			}
			if (!$fio_parsed) {
				$fio_parsed = $this->parse_full_fio($im_part,$change_case);
			}
			if ($fio_parsed) {
				$full = trim($fio_parsed['full']);
				if ($hero) {
					$full = $hero . $full;
				}
				$result['im_part'] = $full;
				$result['short_fio_found'] = true;
				$result['short'] = $fio_parsed['short'];
				$result['short_end'] = $fio_parsed['end'] ?? null;
			} else {
				$result['short_fio_found'] = false;
				$result['short'] = mb_substr($im_part,0,255);
			}
			
			//$result['short'] = null;
		}
		
		return $result;
	}
	
	function parse_heroes($name) {
		$hero = '';
		do {
			$found = false;
			foreach ($this->dict_heroes as $elm) {
				$masked = $elm['masked'];
				$txt = $elm['text'];
				$regexp = "/^$masked(?<space>\s|,\s)?/ui";
				if ( preg_match($regexp, $name, $matches) ) {
					$found = true;
					$hero .= $txt;
					if ( isset($matches['space']) ) {
						$hero .= $matches['space'];
					}
					$name = preg_replace($regexp, '', $name);
				}
			}
		} while ($found);
		return ['hero'=>$hero, 'name_part'=>$name];
	}
	
	// Разобрать имени 
	// Пробует найти имени и остановиться на продолжении не имеющим отношения
	function parse_in_name(string $my_name): ?array {
		$name = $my_name;
		$end = '';
		// ищем в цикле может быть им Чернова А.В. пос. им. Морозова
		while ( $has = preg_match('/^(.*)\b(во имя |им\. ?|имени )(.*)$/ui', $name, $matches) ) {
			$main_part = $matches[1];
			$im_word = $matches[2];
			$im_part = $matches[3];
			$continue = false;
			if ( preg_match('/[А-ЯЁ\.-]+$/ui', trim($main_part), $last_match) ) {
				$last_word = $last_match[0];
				// Имя встретилось как название территории
				// р-н Имени Лазо
				// поселок им Морозова
				// Можно заменить на in_array	
				$continue = preg_match('/^(города|села|поселка|полка|дивизиона|р\.п\.|с\.|остров|пос\.|п\.|пр-кт|ул|р-н)$/ui', $last_word);
				
				if ( !$continue && preg_match('/^района$/ui', $last_word) ) {
					//echo "$im_part\n";
					// Может быть школа Лужского района им. Чернова А.В.
					// здесь Чернова А.В. к школе
					// поэтому оставим только два известных мне исключения
					if ( preg_match('/^(Полины Осипенко|Лазо)/ui',trim($im_part)) ) {
						$continue = true;
					}
				}
			}
			if ($continue) {
				$name = $main_part;
				$end = $im_word.$im_part.$end;
				continue;
			}
			break;
		}
		if (!$has) {
			return null;
		}
		$main_part = trim($main_part);
		$im_word = trim($im_word);
		$im_part = $im_part.$end;
		// Нужно исключить когда населенный пункт после имени
		$short = '(в )?(с\.|х\.|п\.|а\.|д\.|г\.|с\. ?\п.|ст\.|р\. ?п\.|г\. ?п\.|г\. ?п|пос\.)';
		$words = [
			 'село'
			,'хутор'
			,'рабочий поселок'
			,'посёлок'
			,'поселок'
			,'п\.г\.т\.'
			,'ж\.-д\.'
			//,'ж\.-д\.ст\.'
			,'Ж-Д\.ст\.'
			,'с Зуевка'
			,'Г\.О\. ?Мелитополь'
			,'с\. 2-е Иткулово'
			,'аул'
			,'деревня'
			,'пгт'
			,'станица'
			,'районе'
			,'район'
			,'национального муниципального'
			,'муниципального'
			,'Муницмпального образования'
			,'Муниципальногорайона'
			,'муниципальный'
			,'районного'
			,'района'
			,'села'
			,'хутора'
			,'Хут\.'
			,'гор\.'
			,'Талар'	// название школы
			,'администрации'
			,'городского'
			,'государственного'
			,'сельского поселения'
			,'посёлка'
			,'поселка'
			,'рабочего поселка'
			,'городского округа'
			,'аула'
			,'ЗАТО'
			,'деревни'
			,'города'
			,'город'
			,'станицы'
			,'при'
			,'с углубл(е|ё)нным'
			,'с дополнительным'	
			,'министерства'	
			,'департамента'
			,'\(№[0-9]+\)'
			,'\('
			,'-'
			,'-'
			,';'
			,'федерального государственного'
			,'регионального отделения'
			,'общероссийской'
			,'Российской академии'
			,'каз(е|ё)нного'
			,'образовательного'
			,'средняя'
			,'детский сад'
			,'улус'
			,'улуса'
			,'кожууна'
			,'основная'
			,'начальная'
			,'образовательное'
			,'частного'
			,'федеральной службы'
			,'\b[А-Я]+ отделения российской академии'
			,'на территории'
			,'филиал'
			,'Санкт-Петербургский'
			,'ст-цы'
			,'края'
			,'Хутора'
			,'Санкт-Петербург'
			,'Санкт-Петербурга'
			,'азовского немецкого национального'
			,'центрального административного района'
			,'научного центра'
			,'институт'
			,'федерального'
			,'федеральный'
			,'области'
			,'на базе'
			,'МБОУ'
			,'МОБУ'
			,'МОБУ'
			,'МАОУ'
			,'МОУ'
			,'ДЗМ'
			,'ОМР СО'
			,'Посю Глушицкий' //пос.
			,'национального исследовательского центра'
			,'информационно-коммуникационный центр'
			,'для обучающихся'
			,'для детей'
			,'с естественно'
			,'ОГБПОУ'
			,'РАН'
			,'МР'
			,'МО'
			,'№'
			,'школа'
			,'учебный корпус'
			,'ОШ'
			,'НШ'
			,'республики'
			,'общеобразовательная школа'
			,'епархии'
			,'центр образования'
			,'дом знаний'
			,'войск национальной гвардии'
			,'Открытие'
			,'Развитие'
			,'с кадетскими классами'
			,'МЧС России'
			,'павлово - посадского'
			,'Жилого комплекса'
			,'М-Р Черемушки'
			,'Новосибирского облпотребсоюза'
			,'камско - устьинского муниципального'
			,'северо-восточный федеральный'
			,'реализующая адаптированные'
			,'открытого акционерного'
			,'закрытого административно-территориального образования'
		];
		$long = '('.implode('|',$words).')';
		$found = false;
		// 
		//echo "$long\n";
		//$regexp = "/\b[А-ЯЁ.][А-ЯЁ.)-]+(; ?|, |\s)($short\s?[А-Я][А-ЯЁ-]|в (городе|селе|селении|пос(ё|е)лке|сельского|республике|усадьбе|пгт|Москве|Санкт-Петербурге|ЗАТО|с \.Старое|с\. Б\.Кирдяшево|Тамбовка)\b|$long(\b|\s)).*$/ui";
		$regexp = "/\b[А-ЯЁ.][А-ЯЁ.)-]+(; ?|, |\s)($short\s?[А-Я][А-ЯЁ-]|в (городе|селе|селении|пос(ё|е)лке|сельского|республике|усадьбе|пгт|Москве|Санкт-Петербурге|ЗАТО|с \.Старое|с\. Б\.Кирдяшево|Тамбовка)\b|$long(\b|\s)).*$/ui";
		
		$search = $im_part;
		$is_pochet = preg_match('/(гражданина|жителя|земляка)/ui', $im_part);
		$end_part = null;
		while ( preg_match($regexp, $search, $matches, PREG_OFFSET_CAPTURE) ) {
			//var_dump($matches);
			$match = $matches[0][0];
			//echo "match $match\n";
			$match_pos = $matches[0][1];
			$parts = explode(' ', $match);
			$first = array_shift($parts);
			if ( preg_match('/^(гражданина|жителя|земляка)$/ui', $first) ) {
				//$main_part .= ' '.$first;
				$search = implode(' ', $parts);
				continue;
			}
			if ( preg_match('/государственного и партийного деятеля/ui', $match) ) {
				$search = implode(' ', $parts);
				continue;
			}
			// Инициал наложился
			//echo "$match\n";
			if ( preg_match('/^[А-Я]\. ?А\. ?города/ui', $match) ) {
				$search = implode(' ', $parts);
				continue;
			}
			$test_im_part = $this->remove_heroes(str_replace(implode(' ', $parts), '', $im_part));
			//echo "$test_im_part\n";
			if ( preg_match('/^Святителя Луки Министерства/ui', $im_part) ) {
				// do nothing
			} else if ( mb_strlen($test_im_part)<6 || $this->is_fio_part($test_im_part) ) {
				//echo "is_part\n";
				$search = implode(' ', $parts);
				continue;
			} else if ( isset($parts[1]) && preg_match("/героя/ui",$parts[1]) ) {
				$search = implode(' ', $parts);
				continue;
			} else if ( preg_match("/Палантая/ui",implode(' ', $parts)) ) {
				// Ключников Палантай
				$search = implode(' ', $parts);
				continue;
			}
			$fio_has_words = preg_match('/ |\./ui', $test_im_part);
			//echo "'{$parts[0]}'\n";
			// Проверим на название населенного пункта или учрждения
			if ($fio_has_words && in_array(mb_strtolower($parts[0]), ['муниципального','муниципальный','муниципальногорайона','государственного','республики','районного','улус','улуса','края','кожууна','ст-цы','района','районе','научного','городского','с\.п\.','сельского','епархии','национального','основная','начальная','средняя','детский','области']) ) {
				//echo "'$test_im_part' IM_PART; $im_part first: '$first' part_0: {$parts[0]}\n";
				if ($is_pochet) {
					$tmp_part = substr($search,0,$match_pos);
					//echo "'$tmp_part' $match_pos\n";
					if (preg_match('/поч(е|ё)тного гражданина\s?(муниципального образования)?$/ui', $tmp_part)) {
						// Это почетный гражданин Московского района 
						array_shift($parts);
						$search = implode(' ', $parts);
						continue;
					}
				} // Название района перед
				//echo "FIRST '$first'\n";
				// ский - улус
				// ская - школа
				// ской - области
				// ском - районе
				$test_im_part = trim(str_replace($first, '', $test_im_part));
				if (preg_match('/(ного|ского|цкого|ской|цкой|ский|ском|ская|цкая|ная)$/ui', $first) && 
					mb_strlen($test_im_part)>5 &&
					!$this->is_fio_part($test_im_part)
				) {
					array_unshift($parts, $first);
					//$add_part = implode(' ', $parts);
				}
			}
			$end_part = implode(' ', $parts);
			//$main_part .= ' '.$add_part;
			$im_part = preg_replace('/'.mask_reg($end_part).'$/u', '', $im_part);

			//$im_part = trim($im_part, ',');
			break;
		}
		$im_part = trim($im_part);
		$im_part = trim($im_part, ',');
		// Заканичвается на ; или ')'
		if ( preg_match('/(;|\sв)$/ui', $im_part) ||
			(  preg_match('/\)$/u', $im_part) && !preg_match('/\(/u', $im_part) )
		) {
			$fin = mb_substr($im_part, -1);
			if (preg_match('/^в$/ui', $fin) ) {
				$fin .= ' ';
			}
			$im_part = mb_substr($im_part,0, -1);
			$end_part = $fin.($end_part ?? '');
			$im_part = trim($im_part);
		}
		//$this->in_name = $im_part;
		return ['start_part'=>$main_part, 'im_word'=>$im_word,'im_part'=>$im_part, 'end_part'=>$end_part];
	}

	private function remove_heroes($name) {
//		echo "$name\n";
		
		['name_part'=>$name] = $this->parse_heroes($name);
		$r = mask_reg($name);
//		echo "'$name'\n";		
		foreach ($this->dict_heroes as $elm) {
			// осталась часть титула героя
			if ( preg_match ("/^$r/ui", $elm['text'] ) ) {
				return '';
			}
		}
		$name = trim($name);

		$words = [
	/*		 'первого президента Чеченской'
			,'первого президента республики Саха'
			,'первого президента'
			,'первого министра просвещения Тувинской народной'
			,'первого председателя совета министров Луганской народной'
			,'первого губернатора Волгоградской'
			,'поч(е|ё)тного гражданина Удмуртской Республики, начальника'
			,'поч(е|ё)тного гражданина [А-Я]+ской' // области
			,'поч(е|ё)тного гражданина Тихорецкого' // Района
			,'защитника Луганской народной'
			,'почетного гражданина муниципального образования Кизнерский'
			,'народного учителя Республики Саха'
			,'народного учителя'
			,'народного поэта' */
			'6-Ой Орловско'
			,'10-летия'
			,'50-летия Медведевского'
			,'75-летия Новосибирской'
			,'200-летия'
			,'ГУВД Краснодарского' // майора милиции ГУВД Краснодарского Края
			,'святителя'
	/*		,'начальника Управления пожарной охраны УВД Самарской'
			,'начальника'
			,'заслуженных художников Российской Федерации и Чувашской'

			,'героя'
			,'генерал'
			,'воина'
			,'прокурора [А-Я]+ской' // области
			,'военнослужащего' 
			 */
		];
		foreach ($words as $word) {
			//$dash = dash_regexp();
			//$word = preg_replace('/-/ui', "$dash", $word);
			if ( preg_match('/\W$/ui', $word) ) {
				$regexp = "/\b$word/ui";
			} else {
				$regexp = "/\b$word\b/ui";
			}
			$name = preg_replace($regexp, " ", $name);
		}
		
		$name = preg_replace('/\s\s+/ui', " ",$name);
		$name = trim($name);
		//echo "'$name'\n";
		return $name;
	}
	
	protected function is_fio_part($name) {
		$is = false;
		$name = trim($name);
		$fio_parts = [
			 'Анатолия Анатольевича'
			,'Гавриила Никитовича'
			,'Николая Васильевича'
			,'Кузнецова Н\.' // А.
			,'Горячева А\.' // А.
			,'Архипова И\.' // C.
			,'Дьяченко Ф.\.' // C.
			,'Горячева А\.' //  А.
			,'Дьяченко Ф\.' //  C.
			,'Николая Васильевича' 
			,'Давыдова Владимира' 
			,'Евгения'  // САВИЦКОГО МУНИЦИПАЛЬНОГО
			,'Евдокии'  // БЕРШАНСКОЙ МУНИЦИПАЛЬНОГО 
			,'Ивана Федосеевича'  // Лубянецкого муниципального
			,'Белика Семёна Ефимовича, первого Председателя Протоцкого'  // дальше скобки
			,'Димитрия'  // Донского
			,'святого благоверного князя Димитрия'  // Донского
			,'Иннокентия'  //  (Вениаминова)
			,'святого князя Александра'  // Невского
			,'Александра'  // Невского
			,'митрополита Платона'  //  (Левшина)
			,'народного артиста СССР И\.'
			,'акад\. М\.'
			,'выпускников Куйбышевского военно-пехотного училища' // Оно № 1
		];
		//echo "'$name'\n";
		foreach ($fio_parts as $fio_part) {
			if ( preg_match("/^$fio_part$/ui", $name) ) {
				$is =  true;
				break;
			}
		}
		return 	$is;
	}
	
	protected function parse_known_fio(string $fio, $change_case=true) {
		$fio = trim($fio);
		foreach ($this->array_known_fio as $el) {
			$r = mask_reg($el['full']);
			if (preg_match("/^$r$/ui", $fio)) {
				return [ 'full'=>$el['full'], 'short'=>$el['short'] ];
			}
		}
		return null;
	}

	function parse_full_fio(string $fio, $change_case=true) {
		$fio = trim($fio);
		$is_fam_first = $is_fam_last = false;
		$found = false;
		$fio_short = null;
		//$reg_heroes = $this->reg_heroes;
	
		$r = '[А-Я][А-ЯЁ-]+';
		//^героя РФ Алексея Викторовича Чернова$
//		if ( preg_match("/^({$reg_heroes} )?(?<fam>$r)$/ui", $fio, $matches) ) {
		if ( preg_match("/^(?<fam>$r)$/ui", $fio, $matches) ) {
			$is_fam_first = $found = $this->is_surname($matches['fam']);
			if (!$found) {
				if ($this->echo) echo "unknown single fam: {$matches['fam']}\n";
			}
//		} else if ( preg_match("/^({$reg_heroes} )?(?<im>$r) (?<fam>$r)$/ui", $fio, $matches) ) {
		} else if ( preg_match("/^(?<im>$r) (?<fam>$r)$/ui", $fio, $matches) ) {
			$is_fam = $this->is_surname($matches['fam']);
			$is_first = $this->is_first_name($matches['im']);
			$is_fam_last = $found = $is_fam && $is_first;
			if (!$found) {
				if (!$is_fam && !$is_first) {
					if ($this->echo) echo "unknown fam and im in fam_im: {$matches['im']} {$matches['fam']} \n";
				} else if (!$is_fam) {
					if ($this->echo) echo "unknown fam in fam_im: {$matches['im']} {$matches['fam']}\n";
				} else {
					if ($this->echo) echo "unknown im in fam_im: {$matches['im']} {$matches['fam']}\n";
				}
			}
//		} else if ( preg_match("/^{$reg_heroes}?(?<n1>$r) (?<n2>$r) (?<n3>$r)$/ui", $fio, $matches) ) {
		} else if ( preg_match("/^(?<n1>$r) (?<n2>$r) (?<n3>$r)$/ui", $fio, $matches) ) {
			$is_fam_first = $this->is_surname($matches['n1']) && $this->is_first_name($matches['n2']) && $this->is_second_name($matches['n3']);
			$is_fam_last = $this->is_surname($matches['n3']) && $this->is_first_name($matches['n1']) && $this->is_second_name($matches['n2']);
			if ($is_fam_first && $is_fam_last) {
				if ($this->echo) echo "Undectable fio: {$matches['n1']} {$matches['n2']} {$matches['n3']}\n";
			} elseif ($is_fam_first) {
				$found = true;
				$matches['fam'] = $matches['n1'];
				$matches['im'] = $matches['n2'];
				$matches['otc'] = $matches['n3'];
			} elseif ($is_fam_last) {
				$found = true;
				$matches['fam'] = $matches['n3'];
				$matches['im'] = $matches['n1'];
				$matches['otc'] = $matches['n2'];
			} else {
				if ($this->is_first_name($matches['n2'])) {
					if (!$this->is_surname($matches['n1'])) {
						if ($this->echo) echo "unknown fam1: {$matches['n1']} in {$matches['n1']} {$matches['n2']} {$matches['n3']}\n";
					}
					if (!$this->is_second_name($matches['n3'])) {
						if ($this->echo) echo "unknown otc: {$matches['n3']} in {$matches['n1']} {$matches['n2']} {$matches['n3']} \n";
					}
				} else if ($this->is_first_name($matches['n1'])) {
					if (!$this->is_surname($matches['n3'])) {
						if ($this->echo) echo "unknown fam3: {$matches['n3']} in in {$matches['n1']} {$matches['n2']} {$matches['n3']} \n";
					}
					if (!$this->is_second_name($matches['n2'])) {
						if ($this->echo) echo "unknown otc3: {$matches['n2']} in in {$matches['n1']} {$matches['n2']} {$matches['n3']}\n";
					}
				} else {
					if ($this->echo) echo "unknown im in: {$matches['n1']} {$matches['n2']} {$matches['n3']}\n";
				}
			}
		}
		if (!$found) {
			return null;
		}
		if ($found && $change_case) {
			foreach (['im','otc','fam'] as $name_part) {
				if ( isset($matches[$name_part]) ) {
					$part = mb_upper_case_first($matches[$name_part]);
					$matches[$name_part] = $part;
					$fio = preg_replace("/\b$part\b/ui", $part, $fio);
				}
			}
		}
		if (! isset($matches['im']) ) {
			$short = $matches['fam'];
		} else if (! isset($matches['otc']) ) {
			//$heroes = $matches['heroes'] ?? null;
			$short = $this->initial($matches['im']).' '.$matches['fam'];
		} else {
			$short = $this->initial($matches['im']).$this->initial($matches['otc']).' '.$matches['fam'];
		}

		return ['full'=>$fio ,  'short'=>$short, 'heroes'=>$matches['heroes'] ?? null];
	}
	
	protected function parse_fio_with_initials(string $fio, $change_case=true) {
		$fio = trim($fio);
		$found = false;
		//$reg_heroes = $this->reg_heroes;
		$result = null;
		if (!$found) {
			// ^А.В. Чернова( .*)?$
//			$found = preg_match("/^{$reg_heroes}?(?<im>[А-ЯЁ])\. ?(?<otc>[А-ЯЁ])\. ?(?<fam>[А-ЯЁ][А-ЯЁ-]+)(?<end> .*)?$/ui", $fio, $matches);
			$found = preg_match("/^(?<im>[А-ЯЁ])\. ?(?<otc>[А-ЯЁ])\. ?(?<fam>[А-ЯЁ][А-ЯЁ-]+)$/ui", $fio, $matches);
		}
		if (!$found) {
			// ^Чернова А.В.$
//			$found = preg_match("/^{$reg_heroes}?(?<fam>[А-ЯЁ][А-ЯЁ-]+) (?<im>[А-ЯЁ])\. ?(?<otc>[А-ЯЁ])\.?$/ui", $fio, $matches);
			$found = preg_match("/^(?<fam>[А-ЯЁ][А-ЯЁ-]+) (?<im>[А-ЯЁ])\. ?(?<otc>[А-ЯЁ])\.?$/ui", $fio, $matches);
		}		
		if (!$found) {
			// %А. Чернова$
//			$found = preg_match("/^{$reg_heroes}?(?<im>[А-ЯЁ])\. ?(?<fam>[А-ЯЁ][А-ЯЁ-]+)$/ui", $fio, $matches);
			$found = preg_match("/^(?<im>[А-ЯЁ])\. ?(?<fam>[А-ЯЁ][А-ЯЁ-]+)$/ui", $fio, $matches);
		}		
		if ($found) {
			if ($change_case) {
				foreach (['im','otc','fam'] as $name_part) {
					if ( isset($matches[$name_part]) ) {
						$part = mb_upper_case_first($matches[$name_part]);
						$matches[$name_part] = $part;
						$fio = preg_replace("/\b$part\b/ui", $part, $fio);
					}
				}
			}
			$full = '';

			$full .= $matches['im'].'.';
			if ( isset($matches['otc']) ) {
				$full .= $matches['otc'].'.';
			}
			$full .= ' '.$matches['fam'];
			$short = $full;
			if ( isset($matches['heroes']) && mb_strlen($matches['heroes']) ) {
				$full = trim($matches['heroes']). ' ' .$full;
			}
			if ( isset($matches['end']) ) {
				$full .= $matches['end'];
			}
			$result = ['full'=>$full , 'short'=>$short, 'end'=>$matches['end'] ?? null, 'heroes'=>$matches['heroes'] ?? null];
		}
		return $result;
	}
	
	protected function initial($name) {
		$parts = explode('-', $name);
		$lettes = [];
		foreach ($parts as $part) {
			$lettes[] = mb_strtoupper(mb_substr($part,0,1));
		}
		return implode('-', $lettes).'.';
	}
	
	private function load_names(string $file) {
		$d = load_dict($file);
		$dict = [];
		foreach ($d as $val) {
			$name = mb_strtoupper(morpher_inflect($val, 'rod'));
			if ( preg_match('/ERROR/ui', $name) ) {
				echo "morph_error $val\n";
			}
			$dict[$name] = $name;
		}
		return $dict;
	}

	private function load_known(string $fname) {
		$lines = explode("\n", file_get_contents($fname));
		$array = [];
		foreach ($lines as $line) {
			$words = explode("\t", $line);
			$full = $words[0];
			$short = $words[1] ?? $full;
			$array[] = ['full'=>$full, 'short'=>$short];
		}
		return $array;
	}
	
	private function is_surname($fam) {
		$fam = mb_strtoupper($fam);
		$fam = str_replace('Ё','Е',$fam);
		return  isset($this->dict_surnames[$fam]) || isset($this->dict_ngodata_surnames[$fam]) || isset($this->dict_my_last_names[$fam]);
	}
	
	private function is_first_name($im) {
		$im = mb_strtoupper($im);
		$is =  isset($this->dict_ngodata_first_names[$im]);// || isset($this->dict_my_first_names[$im]);
		//echo "$im $is\n";
		return $is;
	}	
	
	private function is_second_name($otc) {
		$otc = mb_strtoupper($otc);
		
		return  isset($this->dict_ngodata_second_names[$otc]) || isset($this->dict_my_second_names[$otc]);;
	}
}