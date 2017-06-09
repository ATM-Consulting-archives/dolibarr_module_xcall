<?php

require '../config.php';
dol_include_once('/xcall/class/xcall.class.php');

/*
$xcall = new XCall;
$xcall->debug = true;

$xcall->clearSession();
$xcall->login();
$xcall->startMonitoring();
$xcall->startListener();

exit;
*/

$action = GETPOST('action', 'aZ', 2);
if (!empty($action))
{
	$response = new stdClass;
	$response->TError = array();
	$response->TResult = array();
	
	$xcall = new XCall;
//	$xcall->debug = true;
	
	switch ($action) {
		case 'clearSession':
			$xcall->clearSession();

			break;
		case 'login':
			$response->TResult[] = $xcall->login();

			break;
		case 'getCallLineList':
			$response->TResult[] = $xcall->login();
			$response->TResult[] = $xcall->getCallLineList();

			break;
		case 'startMonitoring':
			$response->TResult[] = $xcall->login();
			$response->TResult[] = $xcall->startMonitoring();
			$response->TResult[] = $xcall->startListener();

			break;
		case 'placeCall':
			$xcall->clearSession();
			$response->TResult[] = $xcall->login();
			$response->TResult[] = $xcall->startMonitoring();
			$response->TResult[] = $xcall->startListener();
			
			$destination = GETPOST('destination');

			echo $destination;exit;
			// preg_match très large pour les numéros avec +33 ou avec des espaces...
			if (!preg_match('/^\+?[0-9 ]{3,16}/', $destination)) $response->TError[] = 'Erreur : le numéro ne correspond pas à un format attendu';
			else $response->TResult[] = $xcall->placeCall($destination);

			break;
		case 'stopMonitoring':
			$response->TResult[] = $xcall->stopMonitoring();

			break;
		case 'logout':
			$response->TResult[] = $xcall->logout();

			break;

		default:
			break;
	}
	
	if (!empty($xcall->errors)) $response->TError = $xcall->errors;
	
	__out($response);
	exit;
}