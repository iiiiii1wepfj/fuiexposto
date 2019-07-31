<?php
# Pass your token through webhook url as GET parameter or write into a filed named '_token'
$token = $_GET['token'] ?? file_get_contents('_token');
$admin = 276145711; # chat_id to receive all logs, error and reports. can be an array

define('BOT_TOKEN', $token);
define('BOT_ADMIN', $admin);

$sudoers = [276145711]; # will be able to use some special commands (/eval, /sql)