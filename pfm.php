<?php
namespace {
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
define('CHROOT_PATH',GLOBAL_CHROOT_PATH);

}
//Includes
namespace secure_login_session { define("PBKDF2_HASH_ALGORITHM", "sha256"); define("PBKDF2_ITERATIONS", 1000); define("PBKDF2_SALT_BYTE_SIZE", 24); define("PBKDF2_HASH_BYTE_SIZE", 24); define("HASH_SECTIONS", 4); define("HASH_ALGORITHM_INDEX", 0); define("HASH_ITERATION_INDEX", 1); define("HASH_SALT_INDEX", 2); define("HASH_PBKDF2_INDEX", 3); define("FAILED_ATTEMPTS", 5); define("DISABLE_IP_CHECK", false); class secure_login_session { function __construct($session_name, $users) { if (session_status() == PHP_SESSION_ACTIVE) { throw new Exception("Session already active"); } $this->users = json_decode($users, true); $session_hash = 'sha256'; if (in_array($session_hash, hash_algos())) ini_set('session.hash_function', $session_hash); ini_set('session.hash_bits_per_character', 6); ini_set('session.use_only_cookies', 1); $cookieParams = session_get_cookie_params(); $secure = true; $httponly = true; session_set_cookie_params($cookieParams["lifetime"], $cookieParams["path"], $cookieParams["domain"], $secure, $httponly); session_name($session_name); $this->session_start(); $this->session_regenerate_id(); } private function session_start() { session_start(); if (isset($_SESSION['EXPIRED'])) { if ($_SESSION['EXPIRED'] < time()-120) { $this->logout(); throw new Exception("Session expired"); } if (isset($_SESSION['NEW_ID'])) { session_commit(); session_id($_SESSION['NEW_ID']); session_start(); } } } private function session_regenerate_id() { $id = session_create_id(); $_SESSION['NEW_ID'] = $id; $_SESSION['EXPIRED'] = time(); $session = $_SESSION; session_commit(); session_id($id); session_start(); $_SESSION = $session; unset($_SESSION['EXPIRED']); unset($_SESSION['NEW_ID']); } public function login($user, $password) { if ($this->users !== NULL && array_key_exists($user, $this->users)) { if ($this->validate_password($password, $this->users[$user]["login_hash"])) { $_SESSION["user"] = $user; $session_hash = $this->users[$user]["login_hash"] . $_SERVER["HTTP_USER_AGENT"]; if (!DISABLE_IP_CHECK) $session_hash .= $_SERVER["REMOTE_ADDR"]; $_SESSION["hash"] = $this->create_hash($session_hash); $this->users[$user]["brute_force"] = 0; return true; } else { $this->users[$user]["brute_force"]++; if ($this->users[$user]["brute_force"] >= FAILED_ATTEMPTS) { $this->users[$user . "||LOCKED"] = $this->users[$user]; unset($this->users[$user]); } return false; } } else return false; } public function logout() { $_SESSION = array(); if (ini_get("session.use_cookies") || ini_get("session.use_only_cookies")) { $params = session_get_cookie_params(); setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"] ); } session_destroy(); } public function is_valid() { if (!isset($_SESSION["user"]) || !isset($_SESSION["hash"]) ) return false; if ($this->users !== NULL && array_key_exists($_SESSION["user"], $this->users)) { $session_hash = $this->users[$_SESSION["user"]]["login_hash"] . $_SERVER["HTTP_USER_AGENT"]; if (!DISABLE_IP_CHECK) $session_hash .= $_SERVER["REMOTE_ADDR"]; if ($this->validate_password($session_hash, $_SESSION["hash"] ) ) return true; else return false; } else return false; } public function add_user($user, $password) { if ($this->users !== NULL && !array_key_exists($user, $this->users)) { $this->users[$user] = array("login_hash" => $this->create_hash($password), "brute_force" => 0); return true; } else return false; } public function dump_user_file($file) { if (file_put_contents($file, json_encode($this->users))) return true; else return false; } private function create_hash($password) { $salt = base64_encode(random_bytes(PBKDF2_SALT_BYTE_SIZE)); return PBKDF2_HASH_ALGORITHM . ":" . PBKDF2_ITERATIONS . ":" . $salt . ":" . base64_encode($this->pbkdf2( PBKDF2_HASH_ALGORITHM, $password, $salt, PBKDF2_ITERATIONS, PBKDF2_HASH_BYTE_SIZE, true )); } private function validate_password($password, $correct_hash) { $params = explode(":", $correct_hash); if(count($params) < HASH_SECTIONS) return false; $pbkdf2 = base64_decode($params[HASH_PBKDF2_INDEX]); return $this->slow_equals( $pbkdf2, $this->pbkdf2( $params[HASH_ALGORITHM_INDEX], $password, $params[HASH_SALT_INDEX], (int)$params[HASH_ITERATION_INDEX], strlen($pbkdf2), true ) ); } private function slow_equals($a, $b) { $diff = strlen($a) ^ strlen($b); for($i = 0; $i < strlen($a) && $i < strlen($b); $i++) { $diff |= ord($a[$i]) ^ ord($b[$i]); } return $diff === 0; } private function pbkdf2($algorithm, $password, $salt, $count, $key_length, $raw_output = false) { $algorithm = strtolower($algorithm); if(!in_array($algorithm, hash_algos(), true)) trigger_error('PBKDF2 ERROR: Invalid hash algorithm.', E_USER_ERROR); if($count <= 0 || $key_length <= 0) trigger_error('PBKDF2 ERROR: Invalid parameters.', E_USER_ERROR); if (function_exists("hash_pbkdf2")) { if (!$raw_output) { $key_length = $key_length * 2; } return hash_pbkdf2($algorithm, $password, $salt, $count, $key_length, $raw_output); } $hash_length = strlen(hash($algorithm, "", true)); $block_count = ceil($key_length / $hash_length); $output = ""; for($i = 1; $i <= $block_count; $i++) { $last = $salt . pack("N", $i); $last = $xorsum = hash_hmac($algorithm, $last, $password, true); for ($j = 1; $j < $count; $j++) { $xorsum ^= ($last = hash_hmac($algorithm, $last, $password, true)); } $output .= $xorsum; } if($raw_output) return substr($output, 0, $key_length); else return bin2hex(substr($output, 0, $key_length)); } }}
namespace { use dir\dir; function load_dir(array $input_checks) { if (!is_logged_in()->success) throw new Exception("Not logged in"); if (!isset($_REQUEST["path"])) throw new Exception("Path not specified"); foreach ($input_checks as $input => $error) { if (!isset($_REQUEST[$input])) throw new Exception($error); } $dir = new dir($_REQUEST["path"], CHROOT_PATH); if ($dir->has_errors()) throw new Exception(join("\n", $dir->get_errors())); return $dir; } } 
namespace dir { define("DIRSEP", DIRECTORY_SEPARATOR); class dir { public $invalid_chars = ""; private $full_path, $chroot_path; private $subdirs, $files, $errors = array(); public function __construct($path, $chroot_path) { $this->chroot_path = realpath($chroot_path); if ($this->chroot_path === false) { $this->error("Invalid chroot path"); return; } $this->full_path = $this->sanitize_path($path); if ($this->full_path === false || !is_dir($this->full_path)) { $this->full_path = $this->chroot_path; $this->error("Invalid directory path: \"$path\""); } if (strtolower(substr(php_uname("s"), 0, 3)) == "win") $this->invalid_chars = array('\\','/', '*', '?', '"', '<', '>', '|', ':'); else $this->invalid_chars = array('/', '\0'); $this->refresh(); } private function sanitize_path($path) { if ($path == "") $path = DIRSEP; if (substr($path, 0, 1) == DIRSEP) { $realpath = realpath($this->chroot_path . $path); if ($realpath !== false && substr($realpath, 0, strlen($this->chroot_path)) == $this->chroot_path) return $realpath; else return false; } else if ($this->full_path) { $realpath = realpath($this->full_path . DIRSEP . $path); if ($realpath !== false && substr($realpath, 0, strlen($this->chroot_path)) == $this->chroot_path) return $realpath; else return false; } return false; } private function is_valid_filename($file) { foreach ($this->invalid_chars as $invalid_char) { if (strpos($file, $invalid_char) !== false) { return false; } } return true; } private function error($message) { $this->errors[] = $message; } private function is_file($file_name) { foreach ($this->files as $file) if ($file_name == $file->name) return true; return false; } private function is_subdir($subdir) { return in_array($subdir, $this->subdirs); } public function get_file($file_name) { foreach ($this->files as $file) if ($file_name == $file->name) return $file; return false; } public function refresh() { if (@$contents = scandir($this->full_path)) { $this->files = array(); $this->subdirs = array(); foreach ($contents as $dir_file) { switch (filetype($this->full_path . DIRSEP . $dir_file)) { case "dir": if ($dir_file != ".." && $dir_file != ".") $this->subdirs[] = $dir_file; break; case "file": $this->files[] = new file($dir_file, $this->full_path); break; case "link": if ($this->sanitize_path($dir_file)) $this->files[] = new file($dir_file, $this->full_path); default: break; } } return true; } else { $this->error('Could not get contents of "' . $this->get_chrooted_path() . '"'); return false; } } public function get_chrooted_path() { $path = substr($this->full_path, strlen($this->chroot_path)); return $path == "" ? DIRSEP : $path; } public function get_subdirs() { return $this->subdirs; } public function get_files() { return $this->files; } public function get_file_names() { return array_map(function($file) { return $file->name; }, $this->files); } public function sort_files($by) { if (count($this->files) > 0) { switch ($by) { case "name": default: usort($this->files, function($a, $b) { return strcmp($a->name, $b->name); }); break; case "dname": usort($this->files, function($a, $b) { return strcmp($b->name, $a->name); }); break; case "size": usort($this->files, function($a, $b) { return $a->size - $b->size; }); break; case "dsize": usort($this->files, function($a, $b) { return $b->size - $a->size; }); break; case "created": usort($this->files, function($a, $b) { return $a->created - $b->created; }); break; case "dcreated": usort($this->files, function($a, $b) { return $b->created - $a->created; }); break; case "modified": usort($this->files, function($a, $b) { return $a->modified - $b->modified; }); break; case "dmodified": usort($this->files, function($a, $b) { return $b->modified - $a->modified; }); break; } } return $this->files; } public function rename($from, $to) { if (!$this->is_file($from) && !$this->is_subdir($from)) { $this->error("Could not find \"$from\""); return false; } if ($this->is_file($to) || $this->is_subdir($to)) { $this->error("\"$to\" already exists"); return false; } if (!$this->is_valid_filename($to)) { $this->error("Invalid target for rename: \"$to\""); return false; } if (@rename($this->full_path . DIRSEP . $from, $this->full_path . DIRSEP . $to)) { $this->refresh(); return true; } else { $this->error("Could not rename \"$from\" to \"$to\""); return false; } } public function regex_rename($pattern, $replace) { if ($pattern == "") { $this->error("Pattern not specified"); return false; } if (@preg_match($pattern, "") === false) { $this->error("Invalid pattern"); return false; } $no_errors = true; foreach ($this->files as $file) { if (preg_match($pattern, $file->name)) { if (!$this->rename($file->name, preg_replace($pattern, $replace, $file->name))) { $no_errors = false; } } } if (!$no_errors) $this->error("Could not rename all files. Partial rename may have occurred"); $this->refresh(); return $no_errors; } public function regex_rename_test($pattern, $replace) { if ($pattern == "") { $this->error("Pattern not specified"); return false; } if (@preg_match($pattern, "") === false) { $this->error("Invalid pattern"); return false; } $renames = array(); foreach ($this->files as $file) { if (preg_match($pattern, $file->name)) { $renames[] = (object) ["from" => $file->name, "to" => preg_replace($pattern, $replace, $file->name)]; } } return $renames; } public function delete($files) { if (is_string($files)) $files = array($files); if (!is_array($files) || count($files) < 1) return false; $no_errors = true; foreach($files as $file) { if (!$this->is_file($file)) { $this->error("Could not find \"$file\""); $no_errors = false; } else if (@!unlink($this->full_path . DIRSEP . $file)) { $this->error("Could not delete \"$file\""); $no_errors = false; } } $this->refresh(); return $no_errors; } public function put_contents($file, $contents) { if (!$this->is_file($file)) { $this->error("Could not find \"$file\""); return false; } $filepath = $this->full_path . DIRSEP . $file; if (!is_writable($filepath)) { $this->error("\"$file\" is not writable"); return false; } if (@file_put_contents($filepath, $contents)) { clearstatcache(true, $filepath); $this->get_file($file)->size = filesize($filepath); return true; } else { $this->error("Could not write to \"$file\""); return false; } } public function get_contents($file) { if (!$this->is_file($file)) { $this->error("Could not find \"$file\""); return false; } $filepath = $this->full_path . DIRSEP . $file; if (!is_readable($filepath)) { $this->error("\"$file\" is not readable"); return false; } @$contents = file_get_contents($filepath); if ($contents === false) { $this->error("Could not open \"$file\""); return false; } else { return $contents; } } public function readfile($file) { if (!$this->is_file($file)) { $this->error("Could not find \"$file\""); return false; } $filepath = $this->full_path . DIRSEP . $file; if (!is_readable($filepath)) { $this->error("\"$file\" is not readable"); return false; } if (@readfile($filepath) === false) { $this->error("Could not open \"$file\""); return false; } return true; } public function new_file($file) { if ($this->is_file($file)) { $this->error("\"$file\" already exists"); return false; } if (!$this->is_valid_filename($file)) { $this->error("Invalid file name: \"$file\""); return false; } if (@!touch($this->full_path . DIRSEP . $file)) { $this->error("Could not create \"$file\""); return false; } $this->refresh(); return true; } public function zip($dirs_files, $output) { if (!is_array($dirs_files)) $dirs_files = array($dirs_files); if (!is_writable($output)) { $this->error("Could not create zip file"); return false; } $zip = new ZipArchive(); if ($zip->open($output, ZipArchive::OVERWRITE) === false) { $this->error("Could not create zip file"); return false; } if ($this->_addToZip($dirs_files, $zip, "") === false) $this->error("Could not add all files to zip archive"); if ($zip->count() < 1) { $zip->close(); @unlink($output); $this->error("Resulting zip file was empty"); return false; } $zip->close(); return true; } public function _addToZip($dirs_files, $zip, $path) { $no_errors = true; if (count($dirs_files) == 0) { $dirs_files = array_merge($this->get_subdirs(), $this->get_file_names()); } foreach ($dirs_files as $dir_file) { $fullpath = $this->full_path . DIRSEP . $dir_file; if ($this->is_file($dir_file) && is_readable($fullpath)) { if ($path == "") $zipfilepath = $dir_file; else $zipfilepath = $path . "/" . $dir_file; $zip->addFile($fullpath, $zipfilepath); } else if ($this->is_subdir($dir_file)) { $dir = new dir($this->get_chrooted_path() . DIRSEP . $dir_file, $this->chroot_path); if ($dir->has_errors()) { $this->error("Could not open path $path" . DIRSEP . $dir_file . " to add to zip file"); $no_errors = false; } else { if ($path == "") $zipfilepath = $dir_file; else $zipfilepath = $path . "/" . $dir_file; if ($dir->_addToZip(array(), $zip, $zipfilepath) === false) { $this->error(join("\n", $dir->get_errors())); $no_errors = false; } } } else { $this->error("Could not add $path" . DIRSEP . $dir_file . " to zip file"); $no_errors = false; } } return $no_errors; } public function get_upload_max_filecount() { return ini_get("max_file_uploads"); } public function get_upload_max_filesize() { $ini_size = ini_get("upload_max_filesize"); if (stristr($ini_size, "k")) $upload_bytes = intVal($ini_size) * 1024; else if (stristr($ini_size, "m")) $upload_bytes = intVal($ini_size) * 1024 * 1024; else if (stristr($ini_size, "g")) $upload_bytes = intVal($ini_size) * 1024 * 1024 * 1024; $ini_size = ini_get("post_max_size"); if (stristr($ini_size, "k")) $post_bytes = intVal($ini_size) * 1024; else if (stristr($ini_size, "m")) $post_bytes = intVal($ini_size) * 1024 * 1024; else if (stristr($ini_size, "g")) $post_bytes = intVal($ini_size) * 1024 * 1024 * 1024; return min($upload_bytes, $post_bytes); } public function upload($file) { if (is_array($file["name"])) { $no_errors = true; for ($i = 0; $i < count($file["name"]); $i++) { if ($file["error"][$i] == UPLOAD_ERR_OK && is_uploaded_file($file["tmp_name"][$i])) { if ($this->is_file($file["name"][$i])) { $this->error("File " . $file["name"][$i] . " already exists"); $no_errors = false; } else if (!$this->is_valid_filename($file["name"][$i])) { $this->error("Filename " . $file["name"][$i] . " is invalid"); $no_errors = false; } else if (@move_uploaded_file($file["tmp_name"][$i], $this->full_path . DIRSEP . $file["name"][$i]) === false) { $this->error("Could not move uploaded file " . $file["name"][$i]); $no_errors = false; } } else { $this->error("Upload of file " . $file["name"][$i] . " failed"); $no_errors = false; } } $this->refresh(); return $no_errors; } else { if ($file["error"] == UPLOAD_ERR_OK) { if ($this->is_file($file["name"])) { $this->error("File " . $file["name"] . " already exists"); return false; } else if (!$this->is_valid_filename($file["name"])) { $this->error("Filename " . $file["name"] . " is invalid"); return false; } else if (@move_uploaded_file($file["tmp_name"], $this->full_path . DIRSEP . $file["name"] === false) ) { $this->error("Could not move uploaded file " . $file["name"]); return false; } else { $this->refresh(); return true; } } else { $this->error("Upload of file " . $file["name"] . " failed"); return false; } } } public function make_dir($subdir) { if (!$this->is_valid_filename($subdir)) { $this->error("Invalid directory name: \"$subdir\""); return false; } if (@mkdir($this->full_path . DIRSEP . $subdir, 0775, true)) { $this->refresh(); return true; } else { $this->error("Could not create directory \"$subdir\""); return false; } } public function remove_dirs($subdirs) { if (is_string($subdirs)) $subdirs = array($subdirs); if (!is_array($subdirs) || count($subdirs) < 1) return false; $errFlag = false; foreach ($subdirs as $subdir) { if (!in_array($subdir, $this->subdirs)) { $this->error("Could not find \"$subdir\""); $errFlag = true; continue; } if (@rmdir($this->full_path . DIRSEP . $subdir)) { continue; } else { $this->error("Could not remove \"$subdir\" (directories must be empty for removal)"); $errFlag = true; } } $this->refresh(); return !$errFlag; } public function copy($dirs_files, $to) { if (is_string($dirs_files)) $dirs_files = array($dirs_files); if (!is_array($dirs_files) || count($dirs_files) < 1) return false; $to_path = $this->sanitize_path($to); if ($to_path === false) { $this->error("Invalid destination path: \"$to\""); return false; } $no_errors = true; foreach ($dirs_files as $dir_file) { if ($this->is_file($dir_file)) { if(@!copy($this->full_path . DIRSEP . $dir_file, $to_path . DIRSEP . $dir_file)) { $this->error("Could not move \"$dir_file\" to \"$to\""); $no_errors = true; } } else if ($this->is_subdir($dir_file)) { $from_dir = new dir($this->get_chrooted_path() . DIRSEP . $dir_file, $this->chroot_path); if ($from_dir->has_errors()) { $this->error("Invalid path. Partial copy may have occurred"); $no_errors = false; continue; } $to_dir = new dir($to, $this->chroot_path); if ($to_dir->has_errors()) { $this->error("Invalid path. Partial copy may have occurred"); $no_errors = false; continue; } if (!$to_dir->make_dir($dir_file)) { $this->error("Could not copy \"" . $this->get_chrooted_path() . DIRSEP . "$dir_file\" to " . $to_dir->get_chrooted_path()); $no_errors = false; continue; } $from_dir->copy($from_dir->get_file_names(), $to . DIRSEP . $dir_file); $from_dir->copy($from_dir->get_subdirs(), $to . DIRSEP . $dir_file); } else { $this->error("Could not find \"$dir_file\""); $no_errors = false; continue; } } $this->refresh(); return $no_errors; } public function duplicate($from, $to) { if (!$this->is_file($from)) { $this->error("Could not find \"$from\""); return false; } if ($this->is_file($to)) { $this->error("\"$to\" already exists"); return false; } if (!$this->is_valid_filename($to)) { $this->error("Invalid target for duplication: \"$to\""); return false; } if (copy($this->full_path . DIRSEP . $from, $this->full_path . DIRSEP . $to)) { $this->refresh(); return true; } else { $this->error("Could not duplicate \"$from\""); return false; } } public function move($dirs_files, $to_dir) { if (is_string($dirs_files)) $dirs_files = array($dirs_files); if (!is_array($dirs_files) || count($dirs_files) < 1) return false; $to = $this->sanitize_path($to_dir); if ($to === false) { $this->error("Invalid destination path"); return false; } $no_errors = true; foreach ($dirs_files as $dir_file) { if (!$this->is_subdir($dir_file) && !$this->is_file($dir_file)) { $this->error("Could not find \"$dir_file\""); $no_errors = false; continue; } else if (is_file($to . DIRSEP . $dir_file) || is_dir($to . DIRSEP . $dir_file)) { $this->error("Could not move \"$dir_file\" to \"$to_dir\" (already exists)"); $no_errors = false; continue; } else if (@!rename($this->full_path . DIRSEP . $dir_file, $to . DIRSEP . $dir_file)) { $this->error("Could not move \"$dir_file\" to \"$to_dir\""); $no_errors = true; } } $this->refresh(); return $no_errors; } public function kill_dirs($subdirs) { if (is_string($subdirs)) $subdirs = array($subdirs); if (!is_array($subdirs) || count($subdirs) < 1) { $this->error("Subdirectories not properly specified"); return false; } $errFlag = false; foreach ($subdirs as $subdir) { if(!$this->kill($subdir)) $errFlag = true; } return !$errFlag; } private function kill($subdir) { if (!in_array($subdir, $this->subdirs)) { $this->error("Subdirectory \"$subdir\" does not exist"); return false; } $dir = new dir($this->get_chrooted_path() . DIRSEP . $subdir, $this->chroot_path); if ($dir->has_errors()) { $this->error("Invalid path. Partial kill may have occurred"); return false; } foreach ($dir->get_file_names() as $file_name) { if (!$dir->delete($file_name)) { $this->error("Could not delete \"" . $dir->get_chrooted_path() . DIRSEP . $file_name . "\". Partial kill may have occurred"); return false; } } foreach ($dir->subdirs as $subdir_name) { if (!$dir->kill($subdir_name)) { foreach ($dir->get_errors() as $error) $this->error($error); $this->error("Could not kill \"" . $dir->get_chrooted_path() . DIRSEP . $subdir . "\". Partial kill may have occurred"); return false; } } if (!$this->remove_dirs($subdir)) { $this->error("Could not remove directory \"$subdir\". Partial kill may have occurred"); return false; } return true; } public function search($query, $depth, $regex) { $results = array(); $path = $this->get_chrooted_path(); if ($path != "/") $path .= DIRSEP; $file_names = $this->get_file_names(); if ($regex) { if (@preg_match($query, "") === false) { $this->error("Invalid pattern"); return false; } foreach ($file_names as $name) { if (preg_match($query, $name)) $results[] = $path . $name; } foreach ($this->subdirs as $name) { if (preg_match($query, $name)) $results[] = $path . $name; if ($depth > 0) { $dir = new dir($path . $name, $this->chroot_path); if ($dir->has_errors()) { $this->error("Invalid path while searching"); return false; } $results = array_merge($results, $dir->search($query, $depth - 1, $regex)); } } } else { foreach ($file_names as $name) { if (stristr($name, $query)) $results[] = $path . $name; } foreach ($this->subdirs as $name) { if (stristr($name, $query)) $results[] = $path . $name; if ($depth > 0) { $dir = new dir($path . $name, $this->chroot_path); if ($dir->has_errors()) { $this->error("Invalid path while searching"); return false; } $results = array_merge($results, $dir->search($query, $depth - 1, $regex)); } } } return $results; } public function size($dir_file) { if ($this->is_file($dir_file)) { return $this->get_file($dir_file)->size; } else if ($this->is_subdir($dir_file)) { $dir = new dir($this->get_chrooted_path() . DIRSEP . $dir_file, $this->chroot_path); if ($dir->has_errors()) { $this->error("Could not get size of $dir_file"); return false; } $size = 0; foreach ($dir->get_files() as $file) { $size += $file->size; } foreach ($dir->get_subdirs() as $subdir) { $dir_size = $dir->size($subdir); if ($dir_size === false) { $this->error("Could not get size of " . $this->get_chrooted_path() . DIRSEP . $dir_file . DIRSEP . $subdir); return false; } else { $size += $dir_size; } } return $size; } else { $this->error("Could not find $dir_file"); return false; } } public function get_errors() { return $this->errors; } public function has_errors() { return count($this->errors) > 0; } } class file { public $name, $size, $owner, $group, $permissions, $created, $modified; public function __construct($basename, $path) { $this->name = $basename; $fullpath = $path . DIRSEP . $basename; $this->size = filesize($fullpath); $this->owner = posix_getpwuid(fileowner($fullpath))["name"]; $this->group = posix_getgrgid(filegroup($fullpath))["name"]; $this->permissions = substr(sprintf('%o', fileperms($fullpath)), -4); $this->modified = filemtime($fullpath); $this->created = filectime($fullpath); } }}
namespace { use secure_login_session\secure_login_session; global $ajax_page_session; $ajax_page_session = new secure_login_session(SESSION_NAME, AUTH_METHOD);
//Callbacks
function login() { global $ajax_page_session; if (!isset($_REQUEST["username"]) || !isset($_REQUEST["password"])) return (object) ["success" => false, "error" => "Request did not include username and password"]; if ($ajax_page_session->login($_REQUEST["username"], $_REQUEST["password"])) return (object) ["success" => true]; else return (object) ["success" => false, "error" => "Invalid username or password"]; } function logout() { global $ajax_page_session; if ($ajax_page_session) { $ajax_page_session->logout(); return (object) ['success' => true]; } else { return (object) ['success' => false, 'error' => 'Could not find session']; } } function is_logged_in() { global $ajax_page_session; return (object) ['success' => $ajax_page_session && $ajax_page_session->is_valid()]; }
function get_config() { try { $dir = load_dir([]); } catch (Exception $e) { return (object) ["success" => false, "error" => $e->getMessage()]; } return (object) [ "success" => true, "baseURLPath" => BASE_URL_PATH, "separator" => DIRECTORY_SEPARATOR, "invalidChars" => $dir->invalid_chars, "maxUploadSize" => $dir->get_upload_max_filesize(), "maxUploadCount" => $dir->get_upload_max_filecount() ]; } function refresh() { try { $dir = load_dir([]); } catch (Exception $e) { return (object) ["success" => false, "error" => $e->getMessage()]; } return (object) [ "success" => true, "path" => $dir->get_chrooted_path(), "subdirs" => $dir->get_subdirs(), "files" => $dir->get_files() ]; } function get_subdirs() { try { $dir = load_dir([]); } catch (Exception $e) { return (object) ["success" => false, "error" => $e->getMessage()]; } return (object) [ "success" => true, "path" => $dir->get_chrooted_path(), "subdirs" => $dir->get_subdirs() ]; } function make_dir() { try { $dir = load_dir(["name" => "Directory name not specified"]); } catch (Exception $e) { return (object) ["success" => false, "error" => $e->getMessage()]; } if ($dir->make_dir($_REQUEST["name"])) { return (object) [ "success" => true, "path" => $dir->get_chrooted_path(), "subdirs" => $dir->get_subdirs(), "files" => $dir->get_files() ]; } else { return (object) ["success" => false, "error" => join("\n", $dir->get_errors())]; } } function remove_dirs() { try { $dir = load_dir(["names" => "Directory names not specified"]); } catch (Exception $e) { return (object) ["success" => false, "error" => $e->getMessage()]; } if ($dir->remove_dirs($_REQUEST["names"])) { return (object) [ "success" => true, "path" => $dir->get_chrooted_path(), "subdirs" => $dir->get_subdirs(), "files" => $dir->get_files() ]; } else { return (object) [ "success" => false, "error" => join("\n", $dir->get_errors()), "path" => $dir->get_chrooted_path(), "subdirs" => $dir->get_subdirs(), "files" => $dir->get_files() ]; } } function kill_dirs() { try { $dir = load_dir(["names" => "Directory names not specified"]); } catch (Exception $e) { return (object) ["success" => false, "error" => $e->getMessage()]; } if ($dir->kill_dirs($_REQUEST["names"])) { return (object) [ "success" => true, "path" => $dir->get_chrooted_path(), "subdirs" => $dir->get_subdirs(), "files" => $dir->get_files() ]; } else { return (object) [ "success" => false, "error" => join("\n", $dir->get_errors()), "path" => $dir->get_chrooted_path(), "subdirs" => $dir->get_subdirs(), "files" => $dir->get_files() ]; } } function rename_pfm() { try { $dir = load_dir([ "from" => "Old name not specified", "to" => "New name not specified" ]); } catch (Exception $e) { return (object) ["success" => false, "error" => $e->getMessage()]; } if ($dir->rename($_REQUEST["from"], $_REQUEST["to"])) { return (object) [ "success" => true, "path" => $dir->get_chrooted_path(), "subdirs" => $dir->get_subdirs(), "files" => $dir->get_files() ]; } else { return (object) ["success" => false, "error" => join("\n", $dir->get_errors())]; } } function duplicate() { try { $dir = load_dir([ "from" => "Old name not specified", "to" => "New name not specified" ]); } catch (Exception $e) { return (object) ["success" => false, "error" => $e->getMessage()]; } if ($dir->duplicate($_REQUEST["from"], $_REQUEST["to"])) { return (object) [ "success" => true, "path" => $dir->get_chrooted_path(), "subdirs" => $dir->get_subdirs(), "files" => $dir->get_files() ]; } else { return (object) ["success" => false, "error" => join("\n", $dir->get_errors())]; } } function regex_rename() { try { $dir = load_dir([ "pattern" => "Directory name not specified", "replace" => "New directory name not specified" ]); } catch (Exception $e) { return (object) ["success" => false, "error" => $e->getMessage()]; } if ($dir->regex_rename($_REQUEST["pattern"], $_REQUEST["replace"])) { return (object) [ "success" => true, "path" => $dir->get_chrooted_path(), "subdirs" => $dir->get_subdirs(), "files" => $dir->get_files() ]; } else { return (object) [ "success" => false, "path" => $dir->get_chrooted_path(), "subdirs" => $dir->get_subdirs(), "files" => $dir->get_files(), "error" => join("\n", $dir->get_errors()) ]; } } function regex_rename_test() { try { $dir = load_dir([ "pattern" => "Directory name not specified", "replace" => "New directory name not specified" ]); } catch (Exception $e) { return (object) ["success" => false, "error" => $e->getMessage()]; } $renames = $dir->regex_rename_test($_REQUEST["pattern"], $_REQUEST["replace"]); if ($renames === false) return (object) ["success" => false, "error" => join("\n", $dir->get_errors())]; else return (object) ["success" => true, "renames" => $renames]; } function delete() { try { $dir = load_dir(["names" => "File names not specified"]); } catch (Exception $e) { return (object) ["success" => false, "error" => $e->getMessage()]; } if ($dir->delete($_REQUEST["names"])) { return (object) [ "success" => true, "path" => $dir->get_chrooted_path(), "subdirs" => $dir->get_subdirs(), "files" => $dir->get_files() ]; } else { return (object) [ "success" => false, "error" => join("\n", $dir->get_errors()), "path" => $dir->get_chrooted_path(), "subdirs" => $dir->get_subdirs(), "files" => $dir->get_files() ]; } } function new_file() { try { $dir = load_dir(["name" => "File name not specified"]); } catch (Exception $e) { return (object) ["success" => false, "error" => $e->getMessage()]; } if ($dir->new_file($_REQUEST["name"])) { return (object) [ "success" => true, "path" => $dir->get_chrooted_path(), "subdirs" => $dir->get_subdirs(), "files" => $dir->get_files() ]; } else { return (object) ["success" => false, "error" => join("\n", $dir->get_errors())]; } } function get_contents() { try { $dir = load_dir(["name" => "File name not specified"]); } catch (Exception $e) { return (object) ["success" => false, "error" => $e->getMessage()]; } $contents = $dir->get_contents($_REQUEST["name"]); if ($contents === false) { return (object) ["success" => false, "error" => join("\n", $dir->get_errors())]; } else { return (object) [ "success" => true, "contents" => $contents ]; } } function put_contents() { try { $dir = load_dir([ "name" => "File name not specified", "contents" => "File contents not specified" ]); } catch (Exception $e) { return (object) ["success" => false, "error" => $e->getMessage()]; } if ($dir->put_contents($_REQUEST["name"], $_REQUEST["contents"])) { return (object) [ "success" => true, "path" => $dir->get_chrooted_path(), "subdirs" => $dir->get_subdirs(), "files" => $dir->get_files() ]; } else { return (object) ["success" => false, "error" => join("\n", $dir->get_errors())]; } } function move() { try { $dir = load_dir([ "names" => "Names not specified", "to" => "New name not specified" ]); } catch (Exception $e) { return (object) ["success" => false, "error" => $e->getMessage()]; } if ($dir->move($_REQUEST["names"], $_REQUEST["to"])) { return (object) [ "success" => true, "path" => $dir->get_chrooted_path(), "subdirs" => $dir->get_subdirs(), "files" => $dir->get_files() ]; } else { return (object) ["success" => false, "error" => join("\n", $dir->get_errors())]; } } function copy_pfm() { try { $dir = load_dir([ "names" => "Names not specified", "to" => "New name not specified" ]); } catch (Exception $e) { return (object) ["success" => false, "error" => $e->getMessage()]; } if ($dir->copy($_REQUEST["names"], $_REQUEST["to"])) { return (object) [ "success" => true, "path" => $dir->get_chrooted_path(), "subdirs" => $dir->get_subdirs(), "files" => $dir->get_files() ]; } else { return (object) ["success" => false, "error" => join("\n", $dir->get_errors())]; } } function upload() { try { $dir = load_dir([]); } catch (Exception $e) { return (object) ["success" => false, "error" => $e->getMessage()]; } if (!isset($_FILES["upload"])) return (object) ["success" => false, "error" => "No files submitted"]; if ($dir->upload($_FILES["upload"]) !== false) { return (object) [ "success" => true, "path" => $dir->get_chrooted_path(), "subdirs" => $dir->get_subdirs(), "files" => $dir->get_files() ]; } else { return (object) ["success" => false, "error" => join("\n", $dir->get_errors())]; } } function download() { try { $dir = load_dir([]); } catch (Exception $e) { return (object) ["success" => false, "error" => $e->getMessage()]; } header("Content-Type: application/octet-stream"); header("Content-Transfer-Encoding: binary"); header("Accept-Ranges: bytes"); header("Cache-control: must-revalidate"); header("Expires: 0"); header("Pragma: public"); if(!isset($_REQUEST["names"]) || count($_REQUEST["names"]) == 0 || $_REQUEST["names"][0] == "") { $response = "{\"success\": false, \"error\": \"No files or directories specified for download\"}"; header("Content-Length: " . strlen($response)); echo $response; return true; } if (count($_REQUEST["names"]) == 1 && $dir->get_file($_REQUEST["names"][0]) !== false) { header("Content-Disposition: attachment; filename=\"" . $_REQUEST["names"][0] . '"'); header("Content-Length: " . $dir->get_file($_REQUEST["names"][0])->size); if ($dir->readfile($_REQUEST["names"][0]) === false) { echo "{\"success\": false, \"error\": \"Could not read " . $_REQUEST["names"][0] . " - " . addslashes(join("\n", $dir->get_errors())) . "\"}"; } return true; } else { header("Content-Type: application/octet-stream"); header("Content-Transfer-Encoding: binary"); header("Accept-Ranges: bytes"); header("Cache-control: must-revalidate"); header("Expires: 0"); header("Pragma: public"); $file = tempnam(sys_get_temp_dir(), "pfm"); if ($dir->zip($_REQUEST["names"], $file) === false) { $response = "{\"success\": false, \"error\": \"" . join("\n", $dir->get_errors()) . '"}'; header("Content-Length: " . strlen($response)); echo $response; return true; } header("Content-Disposition: attachment; filename=\"files.zip\""); header("Content-Length: " . filesize($file)); @readfile($file); unlink($file); return true; } } function search() { try { $dir = load_dir([ "query" => "Search query not specified", "depth" => "Search depth not specified", "regex" => "Query type not specified" ]); } catch (Exception $e) { return (object) ["success" => false, "error" => $e->getMessage()]; } $results = $dir->search($_REQUEST["query"], intval($_REQUEST["depth"]), $_REQUEST["regex"] == "true"); if ($results === false) return (object) ["success" => false, "error" => join("\n", $dir->get_errors())]; else return (object) ["success" => true, "results" => $results]; } function size() { try { $dir = load_dir([ "name" => "File or directory not specified" ]); } catch (Exception $e) { return (object) ["success" => false, "error" => $e->getMessage()]; } $size = $dir->size($_REQUEST["name"]); if ($size === false) return (object) ["success" => false, "error" => join("\n", $dir->get_errors())]; else return (object) ["success" => true, "size" => $size]; }

//Callback selection
$callbacks_map = array('login' => 'login','logout' => 'logout','is_logged_in' => 'is_logged_in','get_config' => 'get_config','refresh' => 'refresh','get_subdirs' => 'get_subdirs','make_dir' => 'make_dir','remove_dirs' => 'remove_dirs','kill_dirs' => 'kill_dirs','duplicate' => 'duplicate','regex_rename' => 'regex_rename','regex_rename_test' => 'regex_rename_test','delete' => 'delete','new_file' => 'new_file','get_contents' => 'get_contents','put_contents' => 'put_contents','move' => 'move','upload' => 'upload','download' => 'download','search' => 'search','size' => 'size','copy' => 'copy_pfm','rename' => 'rename_pfm'); foreach ($callbacks_map as $request => $callback) { if (isset($_REQUEST[$request]) && is_callable($callback)) { $response_obj = call_user_func($callback); if (is_object($response_obj)) { header('Content-Type: application/json'); echo json_encode($response_obj); die(); } else if ($response_obj === true) { die(); } } }

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>File Manager</title>
<script src="jquery-3.4.1.js"></script><script src="pfm.js"></script><link rel="stylesheet" type="text/css" href="normalize.css"><link rel="stylesheet" type="text/css" href="skeleton.css"><link rel="stylesheet" type="text/css" href="pfm.css"><link rel="stylesheet" type="text/css" href="Roboto.css"></head>

<body>

<div id="header">
	<div class="menu">
		<ul class="ribbon">
			<li class="subdirs">Subdirectories</li>
			<li class="files">Files</li>
			<li class="search">Search</li>
		</ul>
		<form id="path-form">		
			<input type="text" id="path" size="15">
		</form>
	</div>
	<div class="title">File Manager</div>
	<div class="logout"><a id="logout" href="#">Logout</a></div>
</div>

<div id="subdirs-menu">
	<form id="subdirs-form">
		<input type="hidden" id="subdirs-action">
		<input type="submit" value="Up">
		<input type="submit" value="New">
		<input type="submit" value="Rename">
		<input type="submit" value="Copy">
		<input type="submit" value="Move">
		<input type="submit" value="Delete">
		<input type="submit" value="Kill">
		<input type="submit" value="Download">
		<input type="submit" value="Select All">
	</form>
</div>

<div id="files-menu">
	<form id="files-form">
		<input type="hidden" id="files-action">
		<input type="submit" value="New">
		<input type="submit" value="Rename">
		<input type="submit" value="Regex Rename">
		<input type="submit" value="Move">
		<input type="submit" value="Copy">
		<input type="submit" value="Duplicate">
		<input type="submit" value="Delete">
		<input type="submit" value="Edit">
		<input type="submit" value="Upload">
		<input type="submit" value="Download">
	</form>
</div>

<div class="ribbon regex-rename">
	<form id="regex-rename-form">
		<label for="pattern">Pattern </label><input type="text" id="pattern">
		<label for="replace">Replace </label><input type="text" id="replace">
		<input type="submit" class="rename" value="Regex Rename">
		<input type="submit" class="confirm" value="Confirm">
		<input id="regex-rename-clear" class="confirm" type="button" value="Clear">
	</form>
</div>

<div id="search-menu" class="ribbon search">
	<label for="filter">Filter </label><input type="text" id="filter">
	<form id="search-form">
		<label for="search-query">Advanced Search </label><input type="text" id="search-query">
		<label for="search-depth">Depth </label><input type="text" id="search-depth" class="setting" size="1" value="1">
		<label for="search-regex">Regex </label><input type="checkbox" id="search-regex" class="setting">
		<input type="submit" value="Search">
	</form>
</div>

<div class="ribbon upload">
	<form id="upload-form">
		<input type="hidden" id="max-file-size" name="MAX_FILE_SIZE">
		<input type="file" id="upload" name="upload[]" multiple>
		<input type="submit" value="Upload">
	</form>
</div>

<div id="login" class="container">
	<div class="row">
		<div class="twelve columns">
			<h1>File Manager</h1>
			<div id="login-errors" class="errors"></div>
			<form id="login-form">
					<input type="password" id="password">
					<input type="submit" value="Login">
			</form>
		</div>
	</div>
</div>

<div id="error" class="toast errors"></div>
<div id="toast" class="toast"></div>

<div id="manager">
	<div class="scroll subdirs">
		<div>
			<table class="subdirs">
				<thead><tr><th>Subdirectories</th></tr></thead>
				<tbody id="subdirs"></tbody>
			</table>
		</div>
	</div>
	<div class="scroll files">
		<div>
			<table class="files">
				<thead class="files">
					<tr>
						<th width="1"><input type="checkbox" id="check-all"></th>
						<th id="file-name" class="files col-name">Name</th>
						<th id="file-size" class="files col-size">Size</th>
						<th id="file-owner" class="files col-owner">Owner</th>
						<th id="file-group" class="files col-group">Group</th>
						<th id="file-perms" class="files col-perms">Permissions</th>
						<th id="file-created" class="files col-created">Created</th>
						<th id="file-modified" class="files col-modified">Modified</th>
					</tr>
				</thead>
				<tbody id="files"></tbody>
			</table>
		</div>
	</div>
</div>

<div id="progress-wrapper">
	<div id="ul-progress">
		<label>Uploading... </label><div class="progress"><span id="ul-progress-text" class="progress-text">&nbsp;</span><div id="ul-progress-bar" class="progress-bar">&nbsp;</div></div>
	</div>
	<div id="dl-progress">
		<label>Downloading... </label><div class="progress"><span id="dl-progress-text" class="progress-text">&nbsp;</span><div id="dl-progress-bar" class="progress-bar">&nbsp;</div></div>
	</div>
</div>

<div class="modal dir-select">
	<div class="modal-list">
		<table summary="Directory selection">
			<thead><tr><th></th></tr></thead>
			<tbody id="dir-select"></tbody>
		</table>
	</div>
	<div class="modal-buttons">
		<input type="button" value="Select" id="dir-select-select"> <input type="submit" value="Cancel" id="dir-select-cancel">
	</div>
</div>

<div class="modal search-results">
	<div class="modal-list">
		<table summary="Search results">
			<thead><tr><th>Search Results</th><th></th></tr></thead>
			<tbody id="search-results"></tbody>
		</table>
	</div>
	<div id="search-results-buttons" class="modal-buttons">
		<input type="button" value="Close" id="search-results-close">
	</div>
</div>

<div id="edit-box">
	<form id="edit-form">
		<textarea id="edit" rows="20"></textarea>
		<br>
		<input type="hidden" id="edit-action">
		<input type="submit" value="Save"> <input type="submit" value="Save &amp; Close"> <input type="submit" value="Close">
	</form>
</div>

<div id="filecolumns-menu" class="contextmenu">
	<label><input type="checkbox" class="column-sel setting" id="chkbx-name" checked> Name</label>
	<label><input type="checkbox" class="column-sel setting" id="chkbx-size" checked> Size</label>
	<label><input type="checkbox" class="column-sel setting" id="chkbx-owner" checked> Owner</label>
	<label><input type="checkbox" class="column-sel setting" id="chkbx-group" checked> Group</label>
	<label><input type="checkbox" class="column-sel setting" id="chkbx-perms" checked> Permissions</label>
	<label><input type="checkbox" class="column-sel setting" id="chkbx-created" checked> Created</label>
	<label><input type="checkbox" class="column-sel setting" id="chkbx-modified" checked> Modified</label>
</div>

</body>
</html>
<?php } ?>