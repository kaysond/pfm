<?php
//Configuration Options

//Global chroot path - no user will have access outside this chroot
define('GLOBAL_CHROOT_PATH', '/var/www_test/pfm/files');

//This path is prepended to the links in pfm
define('BASE_URL_PATH', 'files');

//PHP Session Name
define('SESSION_NAME', 'pfm');

//Authentication Options
//Precedence: ACL_FILE, ACL, PASSWORD
//See README.md for details

define('ACL_FILE','/var/www_config/userfile.json');
define('ACL','');
define('PASSWORD','');

/*************************************
*
* DO NOT EDIT ANYTHING BELOW THIS LINE
*
**************************************/
if (defined('ACL_FILE') && !empty(ACL_FILE)) {
	define('AUTH_METHOD', file_get_contents(ACL_FILE));
}
else if (defined('ACL') && !empty(ACL)) {
	define('AUTH_METHOD', ACL);
}
else if (defined('PASSWORD') && !empty(PASSWORD)) {
	define('AUTH_METHOD', PASSWORD);
}
else {
	throw new InvalidArgumentException('No authentication method specified!');
}

//User setup
define('CHROOT_PATH', GLOBAL_CHROOT_PATH);
?>
