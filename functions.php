<?php
# Just PDO, but with querySingle (check SQLite3::querySingle), which returns the first column of the first fetched row
class MyPDO extends PDO {
	public function querySingle($query) {
		$result = $this->query($query);
		return $result? $result->fetchColumn() : $result;
	}
}
# Choose the appropriate language for the user
function setLanguage($user_id) {
	global $db, $lang, $bot;
	$language = $db->querySingle("SELECT language FROM users WHERE id={$user_id}") ?: $bot->Language() ?? 'en';
	$language = strtolower(str_replace(['-', '_'], '', $language));
	if (isset($lang->data->$language)) {
		$lang->language = $language;
	} else {
		$lang->language = 'en';
	}
	return $lang;
}
# user_exists, add_user and update_user are 3 shortcuts to check if the user exists, insert a new user into the database and update some data about the user
function user_exists($uid) {
	global $db;
	$stmt = $db->prepare('SELECT 1 AS yes FROM users WHERE id=?');
	$stmt->execute([$uid]);
	$res = $stmt->fetch();
	return isset($res['yes']);
}
function add_user($uid, $params = []) {
	global $db;
	$language = $params['language'] ?? 'en';
	$date = time();
	extract($params);
	$language = $language ?? 'en';
	$stmt = $db->prepare('INSERT INTO users (id, date, language) VALUES (?, ?, ?)');
	$stmt->execute([$uid, $date, $language]);
	return $stmt;
}
function update_user($uid, $params) {
	global $db;
	$values = [];
	foreach ($params as $key => $val) {
		$val = $db->quote($val);
		$values[] = "{$key}={$val}";
	}
	$str = join(', ', $values);
	
	$q = 'UPDATE users SET %s WHERE id=%s';
	$q = sprintf($q, $str, $uid);
	return $db->query($q);
}
# Check if the given email is valid
function is_valid_email($string){
	return (bool)filter_var($string, FILTER_VALIDATE_EMAIL);
}
# Check if the given domain is valid
function is_valid_domain($domain_name) {
	return (preg_match("/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $domain_name) //valid chars check
		&& preg_match("/^.{1,253}$/", $domain_name) //overall length check
		&& preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})+$/", $domain_name)); //length of each label
}
# Format the date to DD/MM/YYYY format
function format_date($date){
	return date('d/m/Y', strtotime($date));
}
/*
Function to translate the results of the search for email
Note its steps:
 1. Check if the string is cached on the wanted language
  1.1. If yes, return the cached translation
 2. Try to translate the string
  2.2. If failed, return the given string (not translated)
 3. Cache the translation
 4. Return the translation
*/
function translate($txt, $lang = 'pt'){
	$txt = urlencode($txt);
	$cache = json_decode(file_get_contents('strings/cache.json'), true);
	if (isset($cache[$lang][$txt])) return $cache[$lang][$txt];
	
	$json = @file_get_contents("https://translate.googleapis.com/translate_a/single?client=gtx&sl=en&tl={$lang}&dt=t&q={$txt}");
	
	$response = $http_response_header[0];
	if($response != 'HTTP/1.0 200 OK'){
		return urldecode($txt);
	}
	$dec = json_decode($json, true);
	$cache[$lang][$txt] = $dec[0][0][0];
	file_put_contents('strings/cache.json', json_encode($cache));
	return $cache[$lang][$txt];
}
# Fetch the saved domains of the user $user_id
function get_saved_domains($user_id) {
	global $db;
	$stmt = $db->query('SELECT key, domain, last_check FROM domains WHERE id=?');
	$stmt->execute([$user_id]);
	$domains = $stmt->fetchAll();
	$domains = array_column($domains, null, 'domain');
	return $domains;
}