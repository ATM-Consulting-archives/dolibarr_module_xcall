<?php

class XCall
{
	public $debug = false;
	
	private $xcall_url = 'https://restletrouter.centrex9.fingerprint.fr';
	private $xcall_url_ws = 'wss://restletrouter.centrex9.fingerprint.fr';
//	private $xcall_url_ws = 'wss://myistra.centrex9.fingerprint.fr'; // Vu sur l'interface web de test
//	private $xcall_url_ws = 'ws://restletrouter.centrex9.fingerprint.fr';
	
	private $cookie = null;
	private $xapplication = null;
	
	/**
	 * Tableau contenant toutes les lignes joignables
	 * @var array 
	 */
	private $TLineContactable = array();
	
	public function __construct()
	{
		global $conf;
		
		if (!empty($conf->global->XCALL_URL)) $this->xcall_url = $conf->global->XCALL_URL;
		if (!empty($conf->global->XCALL_URL_WS)) $this->xcall_url_ws = $conf->global->XCALL_URL_WS;
		
		
		// TODO FIXME besoin d'une persistance ?
		if (!empty($_SESSION['dolibarr_xcall_myRCC_SESSIONID'])) $this->cookie = $_SESSION['dolibarr_xcall_myRCC_SESSIONID'];
		if (!empty($_SESSION['dolibarr_xcall_X_Application'])) $this->xapplication = $_SESSION['dolibarr_xcall_X_Application'];
		
		$this->curl = curl_init();
	}
	
	private function setCookie($id)
	{
		$_SESSION['dolibarr_xcall_myRCC_SESSIONID'] = $id;
		$this->cookie = $id;
	}
	
	private function setXApplication($id)
	{
		$_SESSION['dolibarr_xcall_X_Application'] = $id;
		$this->xapplication = $id;
	}
	
	/**
	 * Methode d'appel à l'API
	 * 
	 * @param string	$method	POST, PUT, GET etc
	 * @param string	$url
	 * @param array		$data	array("param" => "value") ==> index.php?param=value
	 * @return call result
	 */
	private function callAPI($method, $url, $data = false, $header = array(), $useAuth = false)
	{
		global $user;
		
		if ($this->debug) echo '<h5>NEW CALL -> ['.$method.'] '.$url.'</h5>';
		if ($this->debug) var_dump($data);
		
		curl_reset($this->curl);
		curl_setopt($this->curl, CURLOPT_HEADER, true);
		
		switch ($method)
		{
			case 'POST':
				curl_setopt($this->curl, CURLOPT_POST, 1);
				if ($data) curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
				
				break;
			case 'PUT':
				curl_setopt($this->curl, CURLOPT_PUT, 1);
				
				break;
			default:
				if ($data) $url = sprintf("%s?%s", $url, http_build_query($data));
		}

		if ($this->debug) var_dump(__METHOD__.' $url = '.$url);

		curl_setopt($this->curl, CURLOPT_URL, $url);
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1);
		
		if (!empty($header))
		{
			if ($this->debug) var_dump($header);
			curl_setopt($this->curl, CURLOPT_HTTPHEADER, $header);
		}
		
		// TODO FIXME utile ???
		curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
		
		if ($useAuth)
		{
			if (empty($user->array_options)) $user->fetch_optionals();
			if (!empty($user->array_options['options_xcall_login']) && !empty($user->array_options['options_xcall_pwd']) && empty($this->cookie))
			{
				curl_setopt($this->curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
				curl_setopt($this->curl, CURLOPT_USERPWD, $user->array_options['options_xcall_login'].':'.$user->array_options['options_xcall_pwd']);
			}
		}

		$result = curl_exec($this->curl);
		
		$info = curl_getinfo($this->curl);
		$header_size = $info['header_size'];
		// Récupération du header
		$header_info = substr($result, 0, $header_size);
		// Récupération de la réponse sans le header
		$response = substr($result, $header_size);
		

		if (empty($this->cookie))
		{
			// TODO simplifier la récupération du cookie
			preg_match('/^Set-Cookie:\s*myRCC_SESSIONID=(.[^;\r\n]*)/mi', $header_info, $matches);
			if (!empty($matches[1])) $this->setCookie($matches[1]);
		}

		if (empty($this->xapplication))
		{
			preg_match('/^X-Application:\s*(.[^\r\n]*)/mi', $header_info, $matches);
			if (!empty($matches[1])) $this->setXApplication($matches[1]);
		}
		
		if ($this->debug) var_dump($result);
		
		return json_decode($response);
	}
	
	public function login()
	{
		$header = array(
			'Accept: application/json, text/plain, */*'
			,'Content-Type: application/json'
			,'X-Application: myRCC'
		);
		
		return $this->callAPI('POST', $this->xcall_url.'/restletrouter/v1/service/Login', false, $header, true);
	}
	
	public function logout()
	{
		// TODO FIXME 405 Method Not Allowed
		$header = array(
			'Accept: application/json, text/plain, */*'
			,'Content-Type: application/json'
			,'X-Application:'.$this->xapplication
			,'Cookie: myRCC_SESSIONID='.$this->cookie
		);
		
		$response = $this->callAPI('POST', $this->xcall_url.'/restletrouter/v1/service/Logout', false, $header, false);
		
		curl_close($this->curl);
		
		return $response;
	}
	
	/**
	 * Méthode qui retourne les postes joignable, si offset ou length est renseigné ceci permet de récupérer les [length] prochains postes à partir de [offset]
	 * 
	 * @param int $offset
	 * @param int $length
	 * @return array
	 */
	public function getCallLineList($offset = 0, $length = 0)
	{
		$header = array(
			'Accept: application/json, text/plain, */*'
			,'Content-Type: application/json'
			,'X-Application:'.$this->xapplication
			,'Cookie: myRCC_SESSIONID='.$this->cookie
		);
		
		$data = array();
		if ($offset > 0 || $length > 0) $data = array('offset' => $offset, 'length' => $length);
		
		$response = $this->callAPI('GET', $this->xcall_url.'/restletrouter/v1/rcc/Extension', $data, $header, false);
		if (!empty($response->items))
		{
			// TODO FIXME peut être à modifier pour changer la clé
			foreach ($response->items as $item)
			{
				$this->TLineContactable[$item->addressNumber] = $item;
			}
		}
		
		return $response;
	}
	
	
	
	public function startMonitoring()
	{
		$header = array(
			'Connection: Upgrade'
			,'Cookie: myRCC_SESSIONID='.$this->cookie
		);
		
		$r = $this->callAPI('GET', $this->xcall_url_ws.'/restletrouter/ws-service/myRCC', false, $header, false);
		var_dump($r);
//		$r = $this->callAPI('GET', $this->xcall_url_ws.'/restletrouter/ws-service/myRCC', false, $header, false);

// TODO remove		
return 'FIN';
		
		$header = array(
			'Accept: application/json, text/plain, */*'
			,'Content-Type: application/json'
			,'X-Application:'.$this->xapplication
			,'Cookie: myRCC_SESSIONID='.$this->cookie
		);
		//{"name":"myRCCListener","restUri":"v1/service/EventListener/bean/myRCCListener"}
		$r2 = $this->callAPI('POST', $this->xcall_url.'/restletrouter/v1/service/EventListener/bean', array('name' => 'CallLines'), $header, false);
//		var_dump($r2);
		
		$header = array(
			'Accept: application/json, text/plain, */*'
			,'X-Application:'.$this->xapplication
			,'Cookie: myRCC_SESSIONID='.$this->cookie
		);
		$r3 = $this->callAPI('GET', $this->xcall_url.'/restletrouter/v1/rcc/CallLine', array('listenerName' => 'CallLines'), $header, false);
//		var_dump($r3);
		
		return 'FIN';
	}
	
	/**
	 * Passer un appel d'un poste vers un autre
	 * 
	 * @param int $destination
	 * @return array
	 */
	public function placeCall($destination)
	{
		$header = array(
			'Accept: application/json, text/plain, */*'
			,'Content-Type: application/json'
			,'X-Application:'.$this->xapplication
			,'Cookie: myRCC_SESSIONID='.$this->cookie
		);
		var_dump($this->TLineContactable);exit;
		$response = $this->callAPI('POST', $this->xcall_url.'/restletrouter/'.$this->TLineContactable[300]->restUri.'/placeCall', array('destination' => $destination), $header);
		
//		var_dump($response); // Si erreur => exceptionId; cause; message
		return $response;
	}
}