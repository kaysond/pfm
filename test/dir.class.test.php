<?php
register_shutdown_function( "cleanup" );
define("TEST_PATH", "/var/www/dir_class_test");

mkdir(TEST_PATH);
file_put_contents(TEST_PATH . "/file0", "dir_class_test");
file_put_contents(TEST_PATH . "/file1", "dir_class_test");
file_put_contents(TEST_PATH . "/file2", "dir_class_test");
for ($i = 0; $i < 3; $i++) {
	mkdir(TEST_PATH . "/subdir$i");
	for ($j = 0; $j < 3; $j++) {
		file_put_contents(TEST_PATH . "/subdir$i/file$j", "dir_class_test");
	}
}

echo "Creating new dir()\n";
require_once "dir.class.php";
$dir = new dir("/", "/var/www/dir_class_test");

echo "Testing sorts\n";
$sorts = array("name", "dname", "size", "dsize", "created", "dcreated", "modified", "dmodified");
foreach ($sorts as $sort) {
	$dir->sort_files($sort);
	//Need asserts here
	var_dump($dir->get_files());
}

echo "Getting path\n";
assert($dir->get_chrooted_path() == "/");

echo "Getting subdirectories\n";
assert($dir->get_subdirs() == array("subdir0", "subdir1", "subdir2"));

echo "Getting file names\n";
$dir->sort_files("name");
assert($dir->get_file_names() == array("file0", "file1", "file2"));

echo "Renaming file0 to file3\n";
assert($dir->rename("file0", "file3"));
assert($dir->get_file_names() == array("file1", "file2", "file3"));

echo "Smart renaming fileX to afileX\n";
var_dump($dir->smart_rename_test("/file(\d)/", "afile\\1"));
assert($dir->smart_rename("/file(\d)/", "afile\\1"));
assert($dir->get_file_names() == array("afile1", "afile2", "afile3"));

echo "Copying afile1 to subdir0 - without leading slash\n";
assert($dir->copy("afile1", "subdir0"));
assert(is_file(TEST_PATH . "/subdir0/afile1"));

echo "Copying afile2 to subdir1 - with leading slash\n";
assert($dir->copy("afile2", "/subdir1"));
assert(is_file(TEST_PATH . "/subdir1/afile2"));

echo "Copying subdir0 inside subdir1\n";
assert($dir->copy("subdir0", "/subdir1"));
assert(is_dir(TEST_PATH . "/subdir1/subdir0"));
assert(is_file(TEST_PATH . "/subdir1/subdir0/afile1"));

echo "Deleting afile3\n";
assert($dir->delete("afile3"));
assert(!is_file(TEST_PATH . "/afile3"));

echo "Creating directory subdir3\n";
assert($dir->make_dir("subdir3"));
assert(is_dir(TEST_PATH . "/subdir3"));

echo "Renaming subdir3 to subdir4\n";
assert($dir->rename("subdir3", "subdir4"));
assert(is_dir(TEST_PATH . "/subdir4"));

echo "Removing subdir4\n";
assert($dir->remove_dirs("subdir4"));
assert(!is_dir(TEST_PATH . "/subdir4"));

echo "Writing file0\n";
assert($dir->write_file("afile1", "dir_class_test"));
assert(file_get_contents(TEST_PATH . "/afile1") == "dir_class_test");

echo "Reading file0\n";
assert($dir->read_file("afile1") == "dir_class_test");

echo "Moving subdir0 inside subdir2\n";
assert($dir->move("subdir0", "subdir2"));
assert(is_dir(TEST_PATH . "/subdir2/subdir0"));
assert(!is_dir(TEST_PATH . "/subdir0"));

echo "Killing subdir1\n";
assert($dir->kill_dirs("subdir1"));
assert(!is_dir(TEST_PATH . "/subdir1"));

echo "Refreshing and dumping\n";
assert($dir->refresh());
var_dump($dir);

function cleanup() {
	global $dir;
	print_r($dir);
	if (is_callable(array("dir", "get_errors"))) {
		echo "Errors:\n";
		var_dump($dir->get_errors());
	}
	echo "Cleaning up\n";
	system("rm -rf " . escapeshellarg(TEST_PATH));
}
