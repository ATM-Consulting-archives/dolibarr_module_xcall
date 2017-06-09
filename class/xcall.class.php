<?php

class XCall
{
	public $debug = false;
	
//	private $xcall_url = 'https://restletrouter.centrex9.fingerprint.fr';
	private $xcall_url = 'https://myistra.centrex9.fingerprint.fr';
//	private $xcall_url_ws = 'wss://restletrouter.centrex9.fingerprint.fr';
	private $xcall_url_ws = 'wss://myistra.centrex9.fingerprint.fr'; // Vu sur l'interface web de test
	
	private $cookie = null;
	private $xapplication = null;
	
	public $errors = array();
	
	public $last_http_code = null;
	/**
	 * Tableau contenant toutes les lignes joignables
	 * @var array 
	 */
	public $TLineContactable = array();
	
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
	
	public function clearSession()
	{
		unset($_SESSION['dolibarr_xcall_myRCC_SESSIONID'], $_SESSION['dolibarr_xcall_X_Application']);
		$this->cookie = $this->xapplication = null;
	}
	
	/**
	 * Methode d'appel à l'API
	 * 
	 * @param string	$method	POST, PUT, GET etc
	 * @param string	$url
	 * @param array		$data	array("param" => "value") ==> index.php?param=value
	 * @return call result
	 */
	private function callAPI($method, $url, $data = false, $header = array(), $useAuth = false, $ph=false)
	{
		global $user,$conf;
		
		if ($this->debug) echo '<h5>NEW CALL -> ['.$method.'] '.$url.'</h5>';
		if ($this->debug) { echo '<b>$data =></b>'; var_dump($data); }
		
		curl_reset($this->curl);
		curl_setopt($this->curl, CURLOPT_HEADER, true);
		
		switch ($method)
		{
			case 'POST':
				curl_setopt($this->curl, CURLOPT_POST, 1);
				if ($data) curl_setopt($this->curl, CURLOPT_POSTFIELDS, $data);
				
				break;
			case 'PUT':
				curl_setopt($this->curl, CURLOPT_PUT, 1);
				
				break;
			default:
				if ($data) $url = sprintf("%s?%s", $url, http_build_query($data));
		}

		if ($this->debug) { echo '<b>'.__METHOD__.' URL used =</b> '.$url; }

		curl_setopt($this->curl, CURLOPT_URL, $url);
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1);
		
		if (!empty($header))
		{
			if ($this->debug) { echo '<br /><br /><b>HEADER =></b>'; var_dump($header); }
			curl_setopt($this->curl, CURLOPT_HTTPHEADER, $header);
		}
		
		// TODO FIXME utile ???
//		curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
		
		if ($useAuth)
		{
			if (empty($user->array_options)) $user->fetch_optionals();
			
			$login = !empty($user->array_options['options_xcall_login']) ? $user->array_options['options_xcall_login'] : $conf->global->XCALL_DEFAULT_LOGIN;
			$pwd = !empty($user->array_options['options_xcall_pwd']) ? $user->array_options['options_xcall_pwd'] : $conf->global->XCALL_DEFAULT_PWD;
			curl_setopt($this->curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
			curl_setopt($this->curl, CURLOPT_USERPWD, $login.':'.$pwd);
		}

		$result = curl_exec($this->curl);
		
		$info = curl_getinfo($this->curl);
		$this->last_http_code = $info['http_code'];
		
		$header_size = $info['header_size'];
		// Récupération du header
		$header_info = substr($result, 0, $header_size);
		// Récupération de la réponse sans le header
		$response = substr($result, $header_size);
		
		if (empty($this->cookie))
		{
			// TODO simplifier la récupération du cookie
			preg_match('/^Set-Cookie:\s*(.[^;\r\n]*)/mi', $header_info, $matches);
			if (!empty($matches[1])) $this->setCookie($matches[1]);
		}

		if (empty($this->xapplication))
		{
			preg_match('/^X-Application:\s*(.[^\r\n]*)/mi', $header_info, $matches);
			if (!empty($matches[1])) $this->setXApplication($matches[1]);
		}
		
		if ($this->debug) { echo '<b>Resultat brut =></b>'; var_dump($result); }
		if ($ph) {
			echo '-----------------------';
			var_dump($header_info, $response);
			echo '-----------------------';
		}
		return json_decode($response);
	}
	
	/**
	 * Méthode pour récupérer le couple cookie/X-Application de session pour les futur appels (sert de jeton de connexion) 
	 * 
	 * @return boolean
	 */
	public function login()
	{
		global $user;
		
		if (empty($this->cookie) || empty($this->xapplication))
		{
			$header = array(
				'Accept: application/json, text/plain, */*'
				,'Content-Type: application/json'
				,'X-Application: myRCC'
			);
			
			$this->callAPI('POST', $this->xcall_url.'/restletrouter/v1/service/Login', false, $header, true);
			
			if ($this->last_http_code != 200)
			{
				$this->error = 'xcall_error_code_'.$this->last_http_code;
				$this->errors[] = $this->error;
				return false;
			}
		}
		
		return true;
	}
	
	public function logout()
	{
		$header = array(
			'Accept: application/json, text/plain, */*'
			,'Content-Type: application/json'
			,'X-Application:'.$this->xapplication
			,'Cookie: '.$this->cookie
		);
		
		// La méthode est bien GET et non POST comme l'indique la doc
		$this->callAPI('GET', $this->xcall_url.'/restletrouter/v1/service/Logout', false, $header, false);
		
		curl_close($this->curl);
		if ($this->last_http_code != 204)
		{
			$this->error = 'xcall_error_code_'.$this->last_http_code;
			$this->errors[] = $this->error;
			return false;
		}
		
		return true;
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
			,'Cookie: '.$this->cookie
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
//		var_dump($this->TLineContactable);
		return $response;
	}
	
	
	/**
	 * 1. Upgrade de la connexion pour modifier le mode
	 * 2. Lance le mode écoute
	 * 3. Check si des actions sont en cours
	 */
	public function startMonitoring()
	{
		$header = array(
			'Upgrade: WebSocket'
			,'Connection: Upgrade'
			,'Cookie: '.$this->cookie
		);
		
		$r = $this->callAPI('GET', $this->xcall_url_ws.'/restletrouter/ws-service/myRCC', false, $header, false);
//		$r = $this->callAPI('GET', 'https://myistra.centrex9.fingerprint.fr/restletrouter/ws-service/myRCC', false, $header, false, true);
//		$r = $this->callAPI('GET', 'wss://myistra.centrex9.fingerprint.fr/restletrouter/ws-service/myRCC', false, $header, false, true);
//		$r = $this->callAPI('GET', 'wss://restletrouter.centrex9.fingerprint.fr/restletrouter/ws-service/myRCC', false, $header, false, true);	
//		$r = $this->callAPI('GET', 'wss://istra1/restletrouter/ws-service/myRCC', false, $header, false, true);	
		
		if ($this->last_http_code != 101)
		{
			var_dump('WS DOWN last_http_code = '.$this->last_http_code);
			return false;
		}
		
		return true;
	}
	
	public function startListener() {
		
		$header = array(
			'Accept: */*'
			,'Content-Type: application/json'
			,'X-Application:'.$this->xapplication
			,'Cookie: '.$this->cookie
		);
		
		/* Ecoute */
		$r = $this->callAPI('POST', $this->xcall_url.'/restletrouter/v1/service/EventListener/bean', array('name' => 'myRCCListener'), $header, false);
		// resultat: {"name":"myRCCListener","restUri":"v1/service/EventListener/bean/myRCCListener"}

		if ($this->last_http_code != 200)
		{
			var_dump('BEAN myRCCListener DOWN last_http_code = '.$this->last_http_code, $r);
//			return false;
		}
	
		
		
		$header = array(
			'Accept: application/json, text/plain, */*'
			,'X-Application:'.$this->xapplication
			,'Cookie: '.$this->cookie
		);
		
		/* Actions en cours ? */
		$r2 = $this->callAPI('GET', $this->xcall_url.'/restletrouter/v1/rcc/CallLine', array('listenerName' => 'myRCCListener'), $header, false);
		
		if ($this->last_http_code != 200)
		{
			var_dump('CallLine DOWN last_http_code = '.$this->last_http_code, $r2);
			return false;
		}
		// do stuff
		
		return true;
	}
	
	public function stopMonitoring()
	{
		$header = array(
			'Accept: application/json, text/plain, */*'
			,'Content-Type: application/json'
			,'X-Application:'.$this->xapplication
			,'Cookie: '.$this->cookie
		);
		
		$r = $this->callAPI('DELETE', $this->xcall_url.'/restletrouter/v1/service/EventListener/bean/CallLines', array('listenerName' => 'myRCCListener'), $header, false);
		
		if ($this->last_http_code != 200)
		{
			var_dump('CallLines myRCCListener DOWN last_http_code = '.$this->last_http_code, $r);
			return false;
		}
		
		return true;
	}
	
	/**
	 * Passer un appel d'un poste vers un autre
	 * 
	 * @param string $destination	numéro court d'un autre poste téléphonique ou un format standard (0623000000 || +33623000000 || 06 23 00 00 00)
	 * @return array
	 */
	public function placeCall($destination)
	{
		global $user;
		
		if (!empty($this->TLineContactable[$user->array_options['options_xcall_address_number']]))
		{
			$url = $this->xcall_url.'/restletrouter/';
			$url .= $this->TLineContactable[$user->array_options['options_xcall_address_number']]->restUri.'/placeCall';
			
			$header = array(
				'Accept: application/json, text/plain, */*'
				,'Content-Type: application/json'
				,'X-Application:'.$this->xapplication
				,'Cookie: '.$this->cookie
			);
			
			//var_dump($this->TLineContactable);exit;
			$response = $this->callAPI('POST', $url, array('destination' => $destination), $header);
			if (!empty($response->exceptionId))
			{
				$this->error = $response->exceptionId.'; '.$response->message;
				$this->errors[] = $this->error;
				return false;
			}
//		var_dump($response); // Si erreur => exceptionId; cause; message
			return $response;
		}

		$this->error = 'xcall_placecall_error_address_number_not_defined';
		$this->errors[] = $this->error;
		return false;
	}
}