<?php
require '../config.php';
dol_include_once('/xcall/class/xcall.class.php');

$get=GETPOST('get');
$set=GETPOST('set');

switch ($get) {
	default:
		break;
}

switch ($set) {
	default:
		break;
}



echo 'Test<br />';
echo 'CallAPI <br />';

$xcall = new XCall;

// TODO remove
$xcall->debug = true;

$xcall->login();

$xcall->getCallLineList();

$xcall->startMonitoring();
//$xcall->placeCall(301);

$xcall->logout();
exit;



$xcall->login();
$xcall->getCallLineList();
$xcall->startMonitoring();


exit;
//var_dump($xcall->logout());