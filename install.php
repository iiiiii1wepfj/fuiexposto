<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

# Downloading the dependencies
copy('phgram.phar', 'https://raw.githubusercontent.com/usernein/phgram/master/phgram.phar');
copy('hibp/HIBP.php', 'https://gitlab.com/ExeQue/PHP-HIBP/raw/master/src/HIBP.php');
copy('hibp/HIBPBreach.php', 'https://gitlab.com/ExeQue/PHP-HIBP/raw/master/src/HIBPBreach.php');
copy('hibp/NCConvert.php', 'https://gitlab.com/ExeQue/PHP-HIBP/raw/master/src/NCConvert.php');

include_once 'dbsetup.php';

echo 'Done!';