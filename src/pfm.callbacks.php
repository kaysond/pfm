<?php
function get_config() {
	try {
		$dir = load_dir([]);
	}
	catch (Exception $e) {
		return (object) ["success" => false, "error" => $e->getMessage()];
	}

	return (object) [
		"success"        => true,
		"baseURLPath"    => BASE_URL_PATH,
		"separator"      => DIRECTORY_SEPARATOR,
		"invalidChars"   => $dir->invalid_chars,
		"maxUploadSize"  => $dir->get_upload_max_filesize(),
		"maxUploadCount" => $dir->get_upload_max_filecount()
	];

}

function refresh() {
	try {
		$dir = load_dir([]);
	}
	catch (Exception $e) {
		return (object) ["success" => false, "error" => $e->getMessage()];
	}

	return (object) [
		"success"       => true,
		"path"          => $dir->get_chrooted_path(),
		"subdirs"       => $dir->get_subdirs(),
		"files"         => $dir->get_files()
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

function rename_pfm() {
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

function duplicate() {
	try {
		$dir = load_dir([
			"from" => "Old name not specified",
			"to"   => "New name not specified"
		]);
	}
	catch (Exception $e) {
		return (object) ["success" => false, "error" => $e->getMessage()];
	}

	if ($dir->duplicate($_REQUEST["from"], $_REQUEST["to"])) {
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
		return (object) [
			"success" => false,
			"path"    => $dir->get_chrooted_path(),
			"subdirs" => $dir->get_subdirs(),
			"files"   => $dir->get_files(),
			"error"   => join("\n", $dir->get_errors())
		];
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

	$renames = $dir->regex_rename_test($_REQUEST["pattern"], $_REQUEST["replace"]);
	if ($renames === false)
		return (object) ["success" => false, "error" => join("\n", $dir->get_errors())];
	else
		return (object) ["success" => true, "renames" => $renames];
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

function get_contents() {
	try {
		$dir = load_dir(["name" => "File name not specified"]);
	}
	catch (Exception $e) {
		return (object) ["success" => false, "error" => $e->getMessage()];
	}

	$contents = $dir->get_contents($_REQUEST["name"]);
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

function put_contents() {
	try {
		$dir = load_dir([
			"name"     => "File name not specified",
			"contents" => "File contents not specified"
		]);
	}
	catch (Exception $e) {
		return (object) ["success" => false, "error" => $e->getMessage()];
	}

	if ($dir->put_contents($_REQUEST["name"], $_REQUEST["contents"])) {
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

function copy_pfm() {
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

function upload() {
	try {
		$dir = load_dir([]);
	}
	catch (Exception $e) {
		return (object) ["success" => false, "error" => $e->getMessage()];
	}

	if (!isset($_FILES["upload"]))
		return (object) ["success" => false, "error" => "No files submitted"];

	if ($dir->upload($_FILES["upload"]) !== false) {
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

function download() {
	try {
		$dir = load_dir([]);
	}
	catch (Exception $e) {
		return (object) ["success" => false, "error" => $e->getMessage()];
	}

	//Some or all may be needed; try to be most compatible
	header("Content-Type: application/octet-stream");
	header("Content-Transfer-Encoding: binary");
	header("Accept-Ranges: bytes");
	header("Cache-control: must-revalidate");
	header("Expires: 0");
	header("Pragma: public");

	//This ajax request expects binary back, so we can't send a regular json response
	if(!isset($_REQUEST["names"]) || count($_REQUEST["names"]) == 0 || $_REQUEST["names"][0] == "") {
		$response = "{\"success\": false, \"error\": \"No files or directories specified for download\"}";
		header("Content-Length: " . strlen($response));
		echo $response;
		return true; //Prevent additional output
	}

	if (count($_REQUEST["names"]) == 1 && $dir->get_file($_REQUEST["names"][0]) !== false) {
		header("Content-Disposition: attachment; filename=\"" . $_REQUEST["names"][0] . '"');
		header("Content-Length: " . $dir->get_file($_REQUEST["names"][0])->size);

		if ($dir->readfile($_REQUEST["names"][0]) === false) {
			echo "{\"success\": false, \"error\": \"Could not read " . $_REQUEST["names"][0] . " - " . addslashes(join("\n", $dir->get_errors())) . "\"}";
		}
		return true;
	}
	else {
		//Some or all may be needed; try to be most compatible
		header("Content-Type: application/octet-stream");
		header("Content-Transfer-Encoding: binary");
		header("Accept-Ranges: bytes");
		header("Cache-control: must-revalidate");
		header("Expires: 0");
		header("Pragma: public");

		$file = tempnam(sys_get_temp_dir(), "pfm");
		if ($dir->zip($_REQUEST["names"], $file) === false) {
			$response = "{\"success\": false, \"error\": \"" . join("\n", $dir->get_errors()) . '"}';
			header("Content-Length: " . strlen($response));
			echo $response;
			return true;
		}
		header("Content-Disposition: attachment; filename=\"files.zip\"");
		header("Content-Length: " . filesize($file));
		@readfile($file);
		unlink($file);
		return true;
	}
}

function search() {
	try {
		$dir = load_dir([
			"query" => "Search query not specified",
			"depth" => "Search depth not specified",
			"regex" => "Query type not specified"
		]);
	}
	catch (Exception $e) {
		return (object) ["success" => false, "error" => $e->getMessage()];
	}

	$results = $dir->search($_REQUEST["query"], intval($_REQUEST["depth"]), $_REQUEST["regex"] == "true");
	if ($results === false)
		return (object) ["success" => false, "error" => join("\n", $dir->get_errors())];
	else
		return (object) ["success" => true, "results" => $results];
}

function size() {
	try {
		$dir = load_dir([
			"name" => "File or directory not specified"
		]);
	}
	catch (Exception $e) {
		return (object) ["success" => false, "error" => $e->getMessage()];
	}
	$size = $dir->size($_REQUEST["name"]);
	if ($size === false)
		return (object) ["success" => false, "error" => join("\n", $dir->get_errors())];
	else
		return (object) ["success" => true, "size" => $size];
}
?>