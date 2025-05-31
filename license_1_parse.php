<?php
require_once '/var/www/shared/unecon/db/db_class.php';
require_once __DIR__ . '/license_paser_xml_class.php';



$db = \Unecon\DB::connect('frdo');
$db->query("truncate table frdo.ro_lic");
$db->query("truncate table frdo.ro_lic_suppl");
$db->query("truncate table frdo.ro_lic_suppl_prog");
// Можно оставлять, полагаю
$db->query("truncate table frdo.ro_lic_prog");

// Так быстрее!
$db->query("SET autocommit=0");

$parser = new LicenseParserXML(__DIR__ . '/rosobr_xml/data-20250424-structure-20150421.xml');
$parser->setDb($db);
$parser->setSaveXML(true);
$parser->parse();
$db->query("COMMIT");

