<?php
require_once '/var/www/shared/unecon/db/db_class.php';
require_once __DIR__ . '/accred_paser_xml_class.php';



$db = \Unecon\DB::connect('frdo');
$db->query("truncate table frdo.ro_org");
$db->query("truncate table frdo.ro_cert");
$db->query("truncate table frdo.ro_cert_suppl");
$db->query("truncate table frdo.ro_cert_suppl_prog");
$db->query("truncate table frdo.ro_cert_decision");

// Так быстрее(?)
$db->query("SET autocommit=0");

$parser = new myAccredParserXML(__DIR__ . '/rosobr_xml/data-20250423-structure-20160713.xml');
$parser->setDb($db);
$parser->setSaveXML(true);
$parser->parse();
$db->query("COMMIT");

