<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

# Loading requirements...
require 'phgram.phar';
require 'hibp/load.php'; # requires all ExeQue\HIBP scripts
require 'config.php'; # main configs (token, admins, etc)
require 'langs.php'; # class to manage strings
require 'functions.php'; # extra functions

# Creating variables...
# NOTE: To use this script you need to write your bot token into _token (or pass it through a GET parameter)
$bot = new Bot(BOT_TOKEN, BOT_ADMIN); # phgram class
$handler = new BotErrorHandler(BOT_TOKEN, BOT_ADMIN); # phgram error handler
$lang = new Langs('strings/langs.json', 'ptbr'); # class to manage the strings
$hibp = new ExeQue\HIBP(); # HaveIBeenPwned lib

# Setting database
$dbname = 'fuiexposto.db';
$db = new MyPDO('sqlite:'.$dbname);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->setAttribute(PDO::ATTR_TIMEOUT, 15);

# statement we'll use to update the date of the last check
$update_domain = $db->prepare('UPDATE domains SET last_check=? WHERE key=?');
# select all domains, all users that registered them and when they registered
$rows = $db->query("SELECT domain,
	COUNT(domain) AS count,
	GROUP_CONCAT(DISTINCT domains.id || '-' || domains.last_check || '-' || domains.key) AS users
	FROM domains
	GROUP BY domain")->fetchAll();

foreach ($rows as $row) {
	# $row['users'] is in the format: "id-last_check-key,id-last_check-key,id-last_check-key", which all users are separated by commas
	$users = explode(',', $row['users']);
	
	$breaches = $hibp->getBreachesForDomain($row['domain']);
	$breaches = ExeQue\HIBP_Breach::parseFromResponseData($breaches) ?: [];
	
	foreach ($breaches as $breach) {
		$data = join(', ', $breach->data_classes);
		$data = htmlspecialchars_decode($data);
		$desc = strip_tags(htmlspecialchars_decode($breach->description));
		
		foreach ($users as $user) {
			list($user_id, $last_checked_date, $domain_key) = explode('-', $user);
			if (strtotime($breach->added_date) >= $last_checked_date) {
				$settings = $db->querySingle("SELECT settings FROM users WHERE id={$user_id}"); # user personal settings
				$settings = json_decode($settings, true);
				$translate = $settings['translate']['use']; # does the user want to translate?
				$lang_code = $settings['translate']['lang_code'];
				
				if ($translate) {
					$desc = translate($desc, $lang_code); # translate() is at functions.php
					$data = translate($data, $lang_code);
				}
				
				$arg = [
					'name' => $breach->name,
					'domain' => $breach->domain,
					'date' => format_date($breach->breach_date),
					'description' => $desc,
					'data' => $data,
					'count_pwned_accounts' => number_format($breach->pwn_count)
				];
				
				$result = $lang->result_text($arg);
				$msg = $lang->alert_text(['domain' => $row['domain'], 'description' => $result]);
				
				$bot->send($msg, ['chat_id' => $user_id]);
			}
			# the date should be updated on each check to avoid repeated alerts
			$update_domain->execute([time(), $domain_key]);
		}
	}
}