<?php
//Requires CHROOT_PATH to be defined in the header
use dir\dir;
function load_dir(array $input_checks) {
	if (!is_logged_in()->success)
		throw new Exception("Not logged in");

	if (!isset($_REQUEST["path"]))
		throw new Exception("Path not specified");

	foreach ($input_checks as $input => $error) {
		if (!isset($_REQUEST[$input]))
			throw new Exception($error);
	}

	$dir = new dir($_REQUEST["path"], CHROOT_PATH);
	if ($dir->has_errors())
		throw new Exception(join("\n", $dir->get_errors()));

	return $dir;
}
?>