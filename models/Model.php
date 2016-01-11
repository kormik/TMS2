<?php

class Model {

    public function newTicket($type, $sender, $message) {
        Db::queryModify('INSERT INTO tickets (type, title, message, `timestamp`)
                         VALUES (?,?,?,NOW())', [$type, $sender, $message]);
    }

    //TODO better language-from-browser parser
    public function getLanguage($parameter) {
        if (isset($_COOKIE['language'])) return $_COOKIE['language'];
        if (empty($parameter)) return substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
        $knownLanguages = ['en', 'cs'];
        if (in_array($parameter, $knownLanguages)) return $parameter;
        else return false;
    }

    public function forceChangeLanguage($language) {
        setcookie('language', $language, time()+60*60*24*365);
        $_COOKIE['language'] = $language;
    }

    public function returnTariffs($lang) {
        if ($lang == 'cs') return Db::queryAll('SELECT `id_tariff`, `tariffCZE`, `priceCZK`, `name`
            FROM `tariffs` JOIN places ON places.id = tariffs.place_id', []);
        if ($lang == 'en') return Db::queryAll('SELECT `id_tariff`, `tariffENG`, `priceCZK`, `name`
            FROM `tariffs` JOIN places ON places.id = tariffs.place_id', []);
        return false;
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

    //TODO solve localhost unreadable message mail (endoded BASE64)
    public function sendEmail($from, $to, $subject, $message) {
        $header = "MIME-Version: 1.0".PHP_EOL;
        $header .= 'Content-Type: text/html; charset=UTF-8'.PHP_EOL;
        $header .= 'From: '.$from.PHP_EOL;
        //$header .= 'Content-Transfer-Encoding: base64';
        //$subject = mb_encode_mimeheader($subject, "UTF-8");
        $result = mb_send_mail($to, $subject, $message, $header);
        if (!$result) $this->newTicket('error', 'mail send', 'email was not sent. \$to: '.$to.' ,\$subject: '.$subject.' ,\$message: '.$message.' ,\header: '.$header);
        return $result;
    }

    public function checkLogin() {
        if (!isset($_SESSION['username'], $_SESSION['login_string'])) return false;

        $DBpassword = Db::queryOne('SELECT `password` FROM `users`
                                    WHERE email = ?', [$_SESSION['username']]);
        if ($DBpassword[0] == null) return false;

        $passwordCheck = hash('sha512', $DBpassword['password'].$_SERVER['HTTP_USER_AGENT']);
        if ($passwordCheck != $_SESSION['login_string']) return false;
        //success
        return true;
    }

    public function returnAdminPlacesIds() {
        //check username with prevent session login spoofing
        if (!$this->checkLogin()) return false;

        $userId = $this->getUserIdFromEmail($_SESSION['username']);
        $admin = Db::queryAll('SELECT `place_id` FROM `admins`
                               WHERE `user_id` = ?', [$userId]);
        if (empty($admin[0])) return false;

        $result = [];
        foreach ($admin as $a) $result[] = $a["place_id"];
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

    public function getRandomHash() {
        return hash('sha512', uniqid(mt_rand(1, mt_getrandmax()), false));
    }
}