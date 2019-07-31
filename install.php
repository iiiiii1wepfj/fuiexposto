<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

# Downloading the dependencies
copy('https://raw.githubusercontent.com/usernein/phgram/master/phgram.phar', 'phgram.phar');
copy('https://gitlab.com/ExeQue/PHP-HIBP/raw/master/src/HIBP.php', 'hibp/HIBP.php');
copy('https://gitlab.com/ExeQue/PHP-HIBP/raw/master/src/HIBPBreach.php', 'hibp/HIBPBreach.php');
copy('https://gitlab.com/ExeQue/PHP-HIBP/raw/master/src/NCConvert.php', 'hibp/NCConvert.php');

include_once 'dbsetup.php';

echo 'Done!';