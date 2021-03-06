<?php

class Model {
	
	public function newTicket($type, $sender, $message) {
		Db::queryModify('INSERT INTO tickets (type, title, message, `timestamp`)
                         VALUES (?,?,?,NOW())', [$type, $sender, $message]);
		 if (SEND_TICKET_EMAILS) $this->sendEmail(EMAIL, EMAIL, 'Ticket from'.NAME, $message);
	}
	
	public function getLanguage($parameter) {
		if (isset($_COOKIE['language']))
			return $_COOKIE['language'];
		$knownLanguages = ['en', 'cs'];
		if (!empty($parameter)) {
			if (in_array($parameter, $knownLanguages))
				return $parameter;
		} else {
			$browserLanguages = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
			foreach ($browserLanguages as $language) {
				$trimmedLanguage = substr($language, 0, 2);
				if (in_array($trimmedLanguage, $knownLanguages))
					return $trimmedLanguage;
			}
		}
		
		return false;
	}
	
	public function forceChangeLanguage($lang) {
		setcookie('language', $lang, time() + 60 * 60 * 24 * 365); //one year
		$_COOKIE['language'] = $lang;
	}
	
	public function sanitize($data) {
		if ($data == null) {
			return null;
		} elseif (is_string($data)) {
			return htmlspecialchars($data, ENT_QUOTES);
		} elseif (is_array($data)) {
			foreach ($data as $key => $value) {
				$data[$key] = $this->sanitize($value);
			}
			
			return $data;
		} else return $data;
	}
	
	public function sendEmail($from, $to, $subject, $message) {
		$header = "MIME-Version: 1.0".PHP_EOL;
		$header .= 'Content-Type: text/html; charset=UTF-8'.PHP_EOL;
		$header .= 'From: '.$from.PHP_EOL;
		//$header .= 'Content-Transfer-Encoding: base64';
		//$subject = mb_encode_mimeheader($subject, "UTF-8");
		$result = mb_send_mail($to, $subject, $message, $header);
		if (!$result)
			$this->newTicket('error', 'mail sending', 'email was not sent. \$to: '.$to.' ,\$subject: '.$subject.' ,\$message: '.$message.' ,\header: '.$header);
		
		return $result;
	}
	
	public function checkLogin() {
		if (!isset($_SESSION['username'], $_SESSION['login_string']))
			return false;
		
		$DBpassword = Db::queryOne('SELECT `password` FROM `users`
                                    WHERE email = ?', [$_SESSION['username']]);
		if ($DBpassword[0] == null)
			return false;
		
		$passwordCheck = hash('sha512', $DBpassword['password'].$_SERVER['HTTP_USER_AGENT']);
		if ($passwordCheck != $_SESSION['login_string'])
			return false;
		
		//success
		return true;
	}
	
	public function checkIfAdmin($adminId) {
		if (!empty($this->returnAdminPlacesIds($adminId)))
			return true; else return false;
	}
	
	public function checkIfIsAdminOfUser($adminId, $userId) {
		$placesIds = $this->returnAdminPlacesIds($adminId);
		$userPlace = $this->getUserPlaceFromId($userId);
		
		return in_array($userPlace, $placesIds);
	}
	
	protected function returnAdminPlacesIds($adminId) {
		$admin = Db::queryAll('SELECT `place_id` FROM `admins`
                               WHERE `user_id` = ?', [$adminId]);
		$result = [];
		foreach ($admin as $a)
			$result[] = $a['place_id'];
		
		return $result;
	}
	
	public function getUserIdFromEmail($email) {
		$result = Db::queryOne('SELECT id_user FROM users WHERE email = ?', [$email]);
		
		return $result['id_user'];
	}
	
	public function getUserEmailFromId($userID) {
		$result = Db::queryOne('SELECT email FROM users WHERE id_user = ?', [$userID]);
		
		return $result['email'];
	}
	
	public function getUserPlaceFromId($userID) {
		$result = Db::queryOne('SELECT places.id FROM places
                                JOIN tariffs ON tariffs.place_id = places.id
                                JOIN users ON users.user_tariff = tariffs.id_tariff
                                WHERE id_user = ?', [$userID]);
		
		return $result['id'];
	}
	
	public function getTariffName($tariffId, $lang) {
		if ($lang == 'cs')
			return Db::querySingleOne('SELECT `tariffCZE` FROM `tariffs`
            WHERE `id_tariff` = ?', [$tariffId]);
		if ($lang == 'en')
			return Db::querySingleOne('SELECT `tariffENG` FROM `tariffs`
            WHERE `id_tariff` = ?', [$tariffId]);
		
		return false;
	}
	
	public function returnTariffsData($lang) {
		if ($lang == 'cs')
			return Db::queryAll('SELECT `id_tariff`, `tariffCZE`, `priceCZK`, `name`
            FROM `tariffs` JOIN places ON places.id = tariffs.place_id
            ORDER BY `place_id` ASC, `priceCZK` ASC', []);
		if ($lang == 'en')
			return Db::queryAll('SELECT `id_tariff`, `tariffENG`, `priceCZK`, `name`
            FROM `tariffs` JOIN places ON places.id = tariffs.place_id
            ORDER BY `place_id` ASC, `priceCZK` ASC', []);
		
		return false;
	}
	
	public function getLastAttempts($numberOfAtetmpts) {
		$attempts = Db::queryAll('SELECT `id`, `uid_key` FROM `lock_attempts` ORDER BY `timestamp` DESC LIMIT ?', [$numberOfAtetmpts]);
		
		return $attempts;
	}
	
	public function getRandomHash() {
		return hash('sha512', uniqid(mt_rand(1, mt_getrandmax()), false));
	}
}