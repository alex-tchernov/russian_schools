<?

if (preg_match("/ШКОЛА((?<=\W)|\b)/ui", "ШКОЛА-САД")) {
	echo "MATCH!\n";
}

echo morpher_inflect('Частное учреждение образовательная организация', 'rod');
