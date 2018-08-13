<?php
if (!defined("CHROOT_PATH"))
	define("CHROOT_PATH", "/var/www");
if (!defined("USER"))
	define("USER", "files");
require_once "ajax_page.class.php";
require_once "dir.class.php";
$page = new ajax_page("files.html", USER);
$page->add_js("https://code.jquery.com/jquery-3.3.1.js");
$page->add_js("files.js");
$page->add_css("normalize.css");
$page->add_css("skeleton.css");
$page->add_css("files.css");
$page->register_ajax_callback("refresh", "refresh");
$page->register_ajax_callback("make_dir", "make_dir");
$page->register_ajax_callback("remove_dirs", "remove_dirs");
$page->register_ajax_callback("rename_dir", "rename_dir");
$page->register_ajax_callback("kill_dirs", "kill_dirs");
$page->register_ajax_callback("delete", "delete");
$page->register_ajax_callback("smart_rename", "smart_rename");
$page->register_ajax_callback("smart_rename_test", "smart_rename_test");
$page->register_ajax_callback("rename_file", "rename_file");
$page->register_ajax_callback("new_file", "new_file");
$page->register_ajax_callback("read_file", "read_file");
$page->register_ajax_callback("write_file", "write_file");
$page->register_ajax_callback("get_dir_tree", "get_dir_tree");

$page->exec();

function load_dir() {
	

	return $dir;
}

function refresh() {
	global $page;

	if (!$page->is_logged_in())
		return ["success" => false, "error" => "Not logged in"];

	if (!isset($_REQUEST["path"]))
		return (object) ["success" => false, "error" => "Path not specified"];

	$dir = new dir($_REQUEST["path"], CHROOT_PATH);
	if ($dir->has_errors())
		return (object) ["success" => false, "error" => join("\n", $dir->get_errors())];

	return (object) [
		"success" => true,
		"path"    => $dir->get_chrooted_path(),
		"subdirs" => $dir->get_subdirs(),
		"files"   => $dir->get_files()
	];
}

function make_dir() {
	global $page;

	if (!$page->is_logged_in())
		return (object) ["success" => false, "error" => "Not logged in"];

	if (!isset($_REQUEST["path"]))
		return (object) ["success" => false, "error" => "Path not specified"];

	if (!isset($_REQUEST["name"]))
		return (object) ["success" => false, "error" => "Directory name not specified"];

	$dir = new dir($_REQUEST["path"], CHROOT_PATH);
	if ($dir->has_errors())
		return (object) ["success" => false, "error" => join("\n", $dir->get_errors())];

	if ($dir->make_dir($_REQUEST["name"])) {
		return (object) [
			"success" => true,
			"path"    => $dir->get_chrooted_path(),
			"subdirs" => $dir->get_subdirs(),
			"files"   => $dir->get_files()
		];
	}
	else {
		return (object) ["success" => false, "error" => join("\n", $dir->get_errors())];
	}
}

function remove_dirs() {
	global $page;

	if (!$page->is_logged_in())
		return (object) ["success" => false, "error" => "Not logged in"];

	if (!isset($_REQUEST["path"]))
		return (object) ["success" => false, "error" => "Path not specified"];

	if (!isset($_REQUEST["names"]))
		return (object) ["success" => false, "error" => "Directory name not specified"];

	$dir = new dir($_REQUEST["path"], CHROOT_PATH);
	if ($dir->has_errors())
		return (object) ["success" => false, "error" => join("\n", $dir->get_errors())];

	if ($dir->remove_dirs($_REQUEST["names"])) {
		return (object) [
			"success" => true,
			"path"    => $dir->get_chrooted_path(),
			"subdirs" => $dir->get_subdirs(),
			"files"   => $dir->get_files()
		];
	}
	else {
		//Return the directory info in case some directories were successfully deleted
		return (object) [
			"success" => false,
			"error"  => join("\n", $dir->get_errors()),
			"path"    => $dir->get_chrooted_path(),
			"subdirs" => $dir->get_subdirs(),
			"files"   => $dir->get_files()
		];
	}
}

function kill_dirs() {
	global $page;

	if (!$page->is_logged_in())
		return (object) ["success" => false, "error" => "Not logged in"];

	if (!isset($_REQUEST["path"]))
		return (object) ["success" => false, "error" => "Path not specified"];

	if (!isset($_REQUEST["names"]))
		return (object) ["success" => false, "error" => "Directory name not specified"];

	$dir = new dir($_REQUEST["path"], CHROOT_PATH);
	if ($dir->has_errors())
		return (object) ["success" => false, "error" => join("\n", $dir->get_errors())];

	if ($dir->kill_dirs($_REQUEST["names"])) {
		return (object) [
			"success" => true,
			"path"    => $dir->get_chrooted_path(),
			"subdirs" => $dir->get_subdirs(),
			"files"   => $dir->get_files()
		];
	}
	else {
		//Return the directory info in case some directories were successfully deleted
		return (object) [
			"success" => false,
			"error"  => join("\n", $dir->get_errors()),
			"path"    => $dir->get_chrooted_path(),
			"subdirs" => $dir->get_subdirs(),
			"files"   => $dir->get_files()
		];
	}
}

function rename_dir() {
	global $page;

	if (!$page->is_logged_in())
		return (object) ["success" => false, "error" => "Not logged in"];

	if (!isset($_REQUEST["path"]))
		return (object) ["success" => false, "error" => "Path not specified"];

	if (!isset($_REQUEST["from"]))
		return (object) ["success" => false, "error" => "Directory name not specified"];

	if (!isset($_REQUEST["to"]))
		return (object) ["success" => false, "error" => "New directory name not specified"];

	$dir = new dir($_REQUEST["path"], CHROOT_PATH);
	if ($dir->has_errors())
		return (object) ["success" => false, "error" => join("\n", $dir->get_errors())];

	if ($dir->rename_dir($_REQUEST["from"], $_REQUEST["to"])) {
		return (object) [
			"success" => true,
			"path"    => $dir->get_chrooted_path(),
			"subdirs" => $dir->get_subdirs(),
			"files"   => $dir->get_files()
		];
	}
	else {
		return (object) ["success" => false, "error" => join("\n", $dir->get_errors())];
	}
}

function smart_rename() {
	global $page;

	if (!$page->is_logged_in())
		return (object) ["success" => false, "error" => "Not logged in"];

	if (!isset($_REQUEST["path"]))
		return (object) ["success" => false, "error" => "Path not specified"];

	if (!isset($_REQUEST["pattern"]))
		return (object) ["success" => false, "error" => "Pattern not specified"];

	if (!isset($_REQUEST["replace"]))
		return (object) ["success" => false, "error" => "Replace string not specified"];

	$dir = new dir($_REQUEST["path"], CHROOT_PATH);
	if ($dir->has_errors())
		return (object) ["success" => false, "error" => join("\n", $dir->get_errors())];

	if ($dir->smart_rename($_REQUEST["pattern"], $_REQUEST["replace"])) {
		return (object) [
			"success" => true,
			"path"    => $dir->get_chrooted_path(),
			"subdirs" => $dir->get_subdirs(),
			"files"   => $dir->get_files()
		];
	}
	else {
		return (object) ["success" => false, "error" => join("\n", $dir->get_errors())];
	}
}

function smart_rename_test() {
	global $page;

	if (!$page->is_logged_in())
		return (object) ["success" => false, "error" => "Not logged in"];

	if (!isset($_REQUEST["path"]))
		return (object) ["success" => false, "error" => "Path not specified"];

	if (!isset($_REQUEST["pattern"]))
		return (object) ["success" => false, "error" => "Pattern not specified"];

	if (!isset($_REQUEST["replace"]))
		return (object) ["success" => false, "error" => "Replace string not specified"];

	$dir = new dir($_REQUEST["path"], CHROOT_PATH);
	if ($dir->has_errors())
		return (object) ["success" => false, "error" => join("\n", $dir->get_errors())];

	return (object) ["success" => true, "renames" => $dir->smart_rename_test($_REQUEST["pattern"], $_REQUEST["replace"])];
}

function delete() {
	global $page;

	if (!$page->is_logged_in())
		return (object) ["success" => false, "error" => "Not logged in"];

	if (!isset($_REQUEST["path"]))
		return (object) ["success" => false, "error" => "Path not specified"];

	if (!isset($_REQUEST["names"]))
		return (object) ["success" => false, "error" => "File name not specified"];

	$dir = new dir($_REQUEST["path"], CHROOT_PATH);
	if ($dir->has_errors())
		return (object) ["success" => false, "error" => join("\n", $dir->get_errors())];

	if ($dir->delete($_REQUEST["names"])) {
		return (object) [
			"success" => true,
			"path"    => $dir->get_chrooted_path(),
			"subdirs" => $dir->get_subdirs(),
			"files"   => $dir->get_files()
		];
	}
	else {
		//Return the directory info in case some directories were successfully deleted
		return (object) [
			"success" => false,
			"error"  => join("\n", $dir->get_errors()),
			"path"    => $dir->get_chrooted_path(),
			"subdirs" => $dir->get_subdirs(),
			"files"   => $dir->get_files()
		];
	}
}

function rename_file() {
	global $page;

	if (!$page->is_logged_in())
		return (object) ["success" => false, "error" => "Not logged in"];

	if (!isset($_REQUEST["path"]))
		return (object) ["success" => false, "error" => "Path not specified"];

	if (!isset($_REQUEST["from"]))
		return (object) ["success" => false, "error" => "File name not specified"];

	if (!isset($_REQUEST["to"]))
		return (object) ["success" => false, "error" => "New file name not specified"];

	$dir = new dir($_REQUEST["path"], CHROOT_PATH);
	if ($dir->has_errors())
		return (object) ["success" => false, "error" => join("\n", $dir->get_errors())];

	if ($dir->rename_file($_REQUEST["from"], $_REQUEST["to"])) {
		return (object) [
			"success" => true,
			"path"    => $dir->get_chrooted_path(),
			"subdirs" => $dir->get_subdirs(),
			"files"   => $dir->get_files()
		];
	}
	else {
		return (object) ["success" => false, "error" => join("\n", $dir->get_errors())];
	}
}

function new_file() {
	global $page;

	if (!$page->is_logged_in())
		return (object) ["success" => false, "error" => "Not logged in"];

	if (!isset($_REQUEST["path"]))
		return (object) ["success" => false, "error" => "Path not specified"];

	if (!isset($_REQUEST["name"]))
		return (object) ["success" => false, "error" => "File name not specified"];

	$dir = new dir($_REQUEST["path"], CHROOT_PATH);
	if ($dir->has_errors())
		return (object) ["success" => false, "error" => join("\n", $dir->get_errors())];

	if ($dir->new_file($_REQUEST["name"])) {
		return (object) [
			"success" => true,
			"path"    => $dir->get_chrooted_path(),
			"subdirs" => $dir->get_subdirs(),
			"files"   => $dir->get_files()
		];
	}
	else {
		return (object) ["success" => false, "error" => join("\n", $dir->get_errors())];
	}
}

function read_file() {
	global $page;

	if (!$page->is_logged_in())
		return (object) ["success" => false, "error" => "Not logged in"];

	if (!isset($_REQUEST["path"]))
		return (object) ["success" => false, "error" => "Path not specified"];

	if (!isset($_REQUEST["name"]))
		return (object) ["success" => false, "error" => "File name not specified"];

	$dir = new dir($_REQUEST["path"], CHROOT_PATH);
	if ($dir->has_errors())
		return (object) ["success" => false, "error" => join("\n", $dir->get_errors())];

	$contents = $dir->read_file($_REQUEST["name"]);
	if ($contents === false) {
		return (object) ["success" => false, "error" => join("\n", $dir->get_errors())];
	}
	else {
		return (object) [
			"success"  => true,
			"contents" => $contents
		];
	}
}

function write_file() {
	global $page;

	if (!$page->is_logged_in())
		return (object) ["success" => false, "error" => "Not logged in"];

	if (!isset($_REQUEST["path"]))
		return (object) ["success" => false, "error" => "Path not specified"];

	if (!isset($_REQUEST["name"]))
		return (object) ["success" => false, "error" => "File name not specified"];

	if (!isset($_REQUEST["contents"]))
		return (object) ["success" => false, "error" => "File contents not specified"];

	$dir = new dir($_REQUEST["path"], CHROOT_PATH);
	if ($dir->has_errors())
		return (object) ["success" => false, "error" => join("\n", $dir->get_errors())];

	if ($contents = $dir->write_file($_REQUEST["name"], $_REQUEST["contents"])) {
		return (object) ["success"  => true];
	}
	else {
		return (object) ["success" => false, "error" => join("\n", $dir->get_errors())];
	}
}

function get_dir_tree() {
	global $page;

	if (!$page->is_logged_in())
		return (object) ["success" => false, "error" => "Not logged in"];

	$dir = new dir("/", CHROOT_PATH);
	if ($dir->has_errors())
		return (object) ["success" => false, "error" => join("\n", $dir->get_errors())];

	if ($dir->load_dir_tree())
		return (object) ["success" => true, "tree" => $dir->get_dir_tree()];
	else
		return (object) ["success" => false, "error" => join("\n", $dir->get_errors())];
}
?>
