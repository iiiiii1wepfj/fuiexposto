<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

# Loading requirements...
require 'phgram.phar';
require 'hibp/load.php'; # requires all ExeQue\HIBP scripts
require 'bot.php'; # function handle()
require 'config.php'; # main configs (token, admins, etc)
require 'langs.php'; # class to manage strings
require 'functions.php'; # extra functions

# Creating variables...
$bot = new Bot(BOT_TOKEN, BOT_ADMIN); # phgram class
$bot->report_show_view = true;
$handler = new BotErrorHandler(BOT_TOKEN, BOT_ADMIN); # phgram error handler
$lang = new Langs('strings/langs.json'); # class to manage the strings
$hibp = new ExeQue\HIBP();

# Setting the database
$dbname = 'fuiexposto.db';
if (!file_exists($dbname)) include 'dbsetup.php'; # if the database doesn't exist, it is created
$db = new MyPDO('sqlite:'.$dbname); # MyPDO is at functions.php
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->setAttribute(PDO::ATTR_TIMEOUT, 15);

$args = compact('handler', 'sudoers', 'hibp'); # extra args to pass to handle()

# Running...
try {
	handle($bot, $db, $lang, $args);
} catch (Throwable $t) {
	$bot->log((string)$t);
}

# Adding new users to the database
if (($user_id = $bot->UserID()) && !user_exists($user_id)) {
	add_user($user_id, ['date' => ($bot->Date() ?? time()), 'language' => $bot->Language()]);
}