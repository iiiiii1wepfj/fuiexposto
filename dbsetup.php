<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$dbname = 'fuiexposto.db';
$db = new PDO('sqlite:'.$dbname);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->setAttribute(PDO::ATTR_TIMEOUT, 15);

$q = <<<EOF
CREATE TABLE IF NOT EXISTS users (
	key INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	id INTEGER NOT NULL,
	language TEXT NOT NULL DEFAULT 'en',
	date INTEGER NOT NULL DEFAULT (STRFTIME('%s','now')),
	settings TEXT NOT NULL DEFAULT '{"translate":{"use":false,"lang_code":"pt"}}',
	w_for TEXT NULL,
	w_param TEXT NULL,
	w_back TEXT NULL
)
EOF;

$db->query($q);
$q = <<<EOF
CREATE TABLE IF NOT EXISTS domains (
	key INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	id INTEGER NOT NULL,
	last_check INTEGER NOT NULL DEFAULT (STRFTIME('%s','now')),
	domain TEXT NOT NULL
)
EOF;
$db->query($q);