<?php
define("CHROOT_PATH", "/var/www_test/pfm/files");
define("USER", "test");
define("BASE_URL_PATH", "files");
require_once "ajax_page.class.php";
require_once "dir.class.php";
$page = new ajax_page("pfm.html", USER);
$page->add_js("https://code.jquery.com/jquery-3.3.1.js");
$page->add_js("pfm.js");
$page->add_inline_js("dir.baseURLPath = '" . BASE_URL_PATH . "'"); //fix this hack
$page->add_css("normalize.css");
$page->add_css("skeleton.css");
$page->add_css("pfm.css");
$page->register_ajax_callback("refresh", "refresh");
$page->register_ajax_callback("get_subdirs", "get_subdirs");
$page->register_ajax_callback("make_dir", "make_dir");
$page->register_ajax_callback("remove_dirs", "remove_dirs");
$page->register_ajax_callback("rename", "rename1");
$page->register_ajax_callback("kill_dirs", "kill_dirs");
$page->register_ajax_callback("delete", "delete");
$page->register_ajax_callback("regex_rename", "regex_rename");
$page->register_ajax_callback("regex_rename_test", "regex_rename_test");
$page->register_ajax_callback("new_file", "new_file");
$page->register_ajax_callback("read_file", "read_file");
$page->register_ajax_callback("write_file", "write_file");
$page->register_ajax_callback("move", "move");
$page->register_ajax_callback("copy", "copy1");

$page->exec();

function load_dir(array $input_checks) {
	global $page;

	if (!$page->is_logged_in()->success)
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

function refresh() {
	try {
		$dir = load_dir([]);
	}
	catch (Exception $e) {
		return (object) ["success" => false, "error" => $e->getMessage()];
	}

	return (object) [
		"success" => true,
		"path"    => $dir->get_chrooted_path(),
		"subdirs" => $dir->get_subdirs(),
		"files"   => $dir->get_files()
	];
}

function get_subdirs() {
	try {
		$dir = load_dir([]);
	}
	catch (Exception $e) {
		return (object) ["success" => false, "error" => $e->getMessage()];
	}

	return (object) [
		"success" => true,
		"path"    => $dir->get_chrooted_path(),
		"subdirs" => $dir->get_subdirs()
	];
}

function make_dir() {
	try {
		$dir = load_dir(["name" => "Directory name not specified"]);
	}
	catch (Exception $e) {
		return (object) ["success" => false, "error" => $e->getMessage()];
	}

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
	try {
		$dir = load_dir(["names" => "Directory names not specified"]);
	}
	catch (Exception $e) {
		return (object) ["success" => false, "error" => $e->getMessage()];
	}

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
	try {
		$dir = load_dir(["names" => "Directory names not specified"]);
	}
	catch (Exception $e) {
		return (object) ["success" => false, "error" => $e->getMessage()];
	}

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

function rename1() {
	try {
		$dir = load_dir([
			"from" => "Old name not specified",
			"to"   => "New name not specified"
		]);
	}
	catch (Exception $e) {
		return (object) ["success" => false, "error" => $e->getMessage()];
	}

	if ($dir->rename($_REQUEST["from"], $_REQUEST["to"])) {
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

function regex_rename() {
	try {
		$dir = load_dir([
			"pattern" => "Directory name not specified",
			"replace" => "New directory name not specified"
		]);
	}
	catch (Exception $e) {
		return (object) ["success" => false, "error" => $e->getMessage()];
	}

	if ($dir->regex_rename($_REQUEST["pattern"], $_REQUEST["replace"])) {
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

function regex_rename_test() {
	try {
		$dir = load_dir([
			"pattern" => "Directory name not specified",
			"replace" => "New directory name not specified"
		]);
	}
	catch (Exception $e) {
		return (object) ["success" => false, "error" => $e->getMessage()];
	}

	return (object) ["success" => true, "renames" => $dir->regex_rename_test($_REQUEST["pattern"], $_REQUEST["replace"])];
}

function delete() {
	try {
		$dir = load_dir(["names" => "File names not specified"]);
	}
	catch (Exception $e) {
		return (object) ["success" => false, "error" => $e->getMessage()];
	}

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

function new_file() {
	try {
		$dir = load_dir(["name" => "File name not specified"]);
	}
	catch (Exception $e) {
		return (object) ["success" => false, "error" => $e->getMessage()];
	}

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
	try {
		$dir = load_dir(["name" => "File name not specified"]);
	}
	catch (Exception $e) {
		return (object) ["success" => false, "error" => $e->getMessage()];
	}

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
	try {
		$dir = load_dir([
			"name"     => "File name not specified",
			"contents" => "File contents not specified"
		]);
	}
	catch (Exception $e) {
		return (object) ["success" => false, "error" => $e->getMessage()];
	}

	if ($dir->write_file($_REQUEST["name"], $_REQUEST["contents"])) {
		return (object) ["success" => true, "filesize" => $dir->get_file($_REQUEST["name"])->size];
	}
	else {
		return (object) ["success" => false, "error" => join("\n", $dir->get_errors())];
	}
}

function move() {
	try {
		$dir = load_dir([
			"names" => "Names not specified",
			"to"    => "New name not specified"
		]);
	}
	catch (Exception $e) {
		return (object) ["success" => false, "error" => $e->getMessage()];
	}

	if ($dir->move($_REQUEST["names"], $_REQUEST["to"])) {
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

function copy1() {
	try {
		$dir = load_dir([
			"names" => "Names not specified",
			"to"    => "New name not specified"
		]);
	}
	catch (Exception $e) {
		return (object) ["success" => false, "error" => $e->getMessage()];
	}

	if ($dir->copy($_REQUEST["names"], $_REQUEST["to"])) {
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
?>
