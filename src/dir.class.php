<?php
namespace dir;
define("DIRSEP", DIRECTORY_SEPARATOR);
class dir {
	public $invalid_chars = "";
	private $full_path, $chroot_path;
	private $subdirs, $files, $errors = array();

	public function __construct($path, $chroot_path) {
		$this->chroot_path = realpath($chroot_path);
		if ($this->chroot_path === false) {
			$this->error("Invalid chroot path");
			return;
		}

		$this->full_path = $this->sanitize_path($path);
		if ($this->full_path === false || !is_dir($this->full_path)) {
			$this->full_path = $this->chroot_path;
			$this->error("Invalid directory path: \"$path\"");
		}

		if (strtolower(substr(php_uname("s"), 0, 3)) == "win")
			$this->invalid_chars = array('\\','/', '*', '?', '"', '<', '>', '|', ':');
		else
			$this->invalid_chars = array('/', '\0');

		$this->refresh();
	}

	//Returns an absolute (unchrooted) path after checking for valid chroot
	private function sanitize_path($path) {
		if ($path == "")
			$path = DIRSEP;

		if (substr($path, 0, 1) == DIRSEP) {
			$realpath = realpath($this->chroot_path . $path);
			if ($realpath !== false && substr($realpath, 0, strlen($this->chroot_path)) == $this->chroot_path)
				return $realpath;
			else
				return false;
		}
		else if ($this->full_path) { //Could be a filename or subdirectory relative to the current path
			$realpath = realpath($this->full_path . DIRSEP . $path);
			if ($realpath !== false && substr($realpath, 0, strlen($this->chroot_path)) == $this->chroot_path)
				return $realpath;
			else
				return false;
		}
		return false;
	}

	private function is_valid_filename($file) {
		foreach ($this->invalid_chars as $invalid_char) {
			if (strpos($file, $invalid_char) !== false) {
				return false;
			}
		}
		return true;
	}

	private function error($message) {
		$this->errors[] = $message;
	}

	private function is_file($file_name) {
		foreach ($this->files as $file)
			if ($file_name == $file->name)
				return true;

		return false;
	}

	private function is_subdir($subdir) {
		return in_array($subdir, $this->subdirs);
	}

	public function get_file($file_name) {
		foreach ($this->files as $file)
			if ($file_name == $file->name)
				return $file;

		return false;
	}

	public function refresh() {
		if (@$contents = scandir($this->full_path)) {
			$this->files = array();
			$this->subdirs = array();
			foreach ($contents as $dir_file) {
				switch (filetype($this->full_path . DIRSEP . $dir_file)) {
					case "dir":
						if ($dir_file != ".." && $dir_file != ".")
							$this->subdirs[] = $dir_file;
						break;
					case "file":
						$this->files[] = new file($dir_file, $this->full_path);
						break;
					case "link":
						if ($this->sanitize_path($dir_file))
							$this->files[] = new file($dir_file, $this->full_path);
					default:
						break;
				}
			}
			return true;
		}
		else {
			$this->error('Could not get contents of "' . $this->get_chrooted_path() . '"');
			return false;
		}
	}

	public function get_chrooted_path() {
		$path = substr($this->full_path, strlen($this->chroot_path));
		return $path == "" ? DIRSEP : $path;
	}

	public function get_subdirs() {
		return $this->subdirs;
	}

	public function get_files() {
		return $this->files;
	}

	public function get_file_names() {
		return array_map(function($file) { return $file->name; }, $this->files);
	}

	public function sort_files($by) {
		if (count($this->files) > 0) {
			switch ($by) {	
				case "name":
				default:
					usort($this->files, function($a, $b) { return strcmp($a->name, $b->name); });
					break;
				case "dname":
					usort($this->files, function($a, $b) { return strcmp($b->name, $a->name); });
					break;	
				case "size":
					usort($this->files, function($a, $b) { return $a->size - $b->size; });
					break;
				case "dsize":
					usort($this->files, function($a, $b) { return $b->size - $a->size; });
					break;
				case "created":
					usort($this->files, function($a, $b) { return $a->created - $b->created; });
					break;
				case "dcreated":
					usort($this->files, function($a, $b) { return $b->created - $a->created; });
					break;
				case "modified":
					usort($this->files, function($a, $b) { return $a->modified - $b->modified; });
					break;
				case "dmodified":
					usort($this->files, function($a, $b) { return $b->modified - $a->modified; });
					break;
			}
		}
		return $this->files;
	}

	public function rename($from, $to) {
		if (!$this->is_file($from) && !$this->is_subdir($from)) {
			$this->error("Could not find \"$from\"");
			return false;
		}

		if ($this->is_file($to) || $this->is_subdir($to)) {
			$this->error("\"$to\" already exists");
			return false;
		}

		if (!$this->is_valid_filename($to)) {
			$this->error("Invalid target for rename: \"$to\"");
			return false;
		}

		if (@rename($this->full_path . DIRSEP . $from, $this->full_path . DIRSEP . $to)) {
			$this->refresh();
			return true;
		}
		else {
			$this->error("Could not rename \"$from\" to \"$to\"");
			return false;
		}
	}

	public function regex_rename($pattern, $replace) {
		if ($pattern == "") {
			$this->error("Pattern not specified");
			return false;
		}

		//Validate pattern
		if (@preg_match($pattern, "") === false) {
			$this->error("Invalid pattern");
			return false;
		}

		$no_errors = true;
		foreach ($this->files as $file) {
			if (preg_match($pattern, $file->name)) {
				if (!$this->rename($file->name, preg_replace($pattern, $replace, $file->name))) {
					$no_errors = false;
				}
			}
		}
		if (!$no_errors)
			$this->error("Could not rename all files. Partial rename may have occurred");

		$this->refresh();
		return $no_errors;
	}

	public function regex_rename_test($pattern, $replace) {
		if ($pattern == "") {
			$this->error("Pattern not specified");
			return false;
		}

		//Validate pattern
		if (@preg_match($pattern, "") === false) {
			$this->error("Invalid pattern");
			return false;
		}

		$renames = array();
		foreach ($this->files as $file) {
			if (preg_match($pattern, $file->name)) {
				$renames[] = (object) ["from" => $file->name, "to" => preg_replace($pattern, $replace, $file->name)];
			}
		}
		return $renames;
	}

	public function delete($files) {
		if (is_string($files)) 
			$files = array($files);
		if (!is_array($files) || count($files) < 1)
			return false;

		$no_errors = true;
		foreach($files as $file) {
			if (!$this->is_file($file)) {
				$this->error("Could not find \"$file\"");
				$no_errors = false;
			}
			else if (@!unlink($this->full_path . DIRSEP . $file)) {
				$this->error("Could not delete \"$file\"");
				$no_errors = false;
			}
		}
		$this->refresh();
		return $no_errors;
	}

	public function put_contents($file, $contents) {
		if (!$this->is_file($file)) {
			$this->error("Could not find \"$file\"");
			return false;
		}
		$filepath = $this->full_path . DIRSEP . $file;
		if (!is_writable($filepath)) {
			$this->error("\"$file\" is not writable");
			return false;
		}
		if (@file_put_contents($filepath, $contents)) {
			//Update filesize
			clearstatcache(true, $filepath);
			$this->get_file($file)->size = filesize($filepath);
			return true;
		}
		else {
			$this->error("Could not write to \"$file\"");
			return false;
		}
	}

	public function get_contents($file) {
		if (!$this->is_file($file)) {
			$this->error("Could not find \"$file\"");
			return false;
		}
		$filepath = $this->full_path . DIRSEP . $file;
		if (!is_readable($filepath)) {
			$this->error("\"$file\" is not readable");
			return false;
		}
		@$contents = file_get_contents($filepath);
		if ($contents === false) {
			$this->error("Could not open \"$file\"");
			return false;
		}
		else {
			return $contents;
		}
	}

	public function readfile($file) {
		if (!$this->is_file($file)) {
			$this->error("Could not find \"$file\"");
			return false;
		}
		$filepath = $this->full_path . DIRSEP . $file;
		if (!is_readable($filepath)) {
			$this->error("\"$file\" is not readable");
			return false;
		}
		if (@readfile($filepath) === false) {
			$this->error("Could not open \"$file\"");
			return false;
		}
		return true;
	}

	public function new_file($file) {
		if ($this->is_file($file)) {
			$this->error("\"$file\" already exists");
			return false;
		}
		if (!$this->is_valid_filename($file)) {
			$this->error("Invalid file name: \"$file\"");
			return false;
		}
		if (@!touch($this->full_path . DIRSEP . $file)) {
			$this->error("Could not create \"$file\"");
			return false;
		}
		$this->refresh();
		return true;
	}

	public function zip($dirs_files, $output) {
		if (!is_array($dirs_files))
			$dirs_files = array($dirs_files);

		if (!is_writable($output)) {
			$this->error("Could not create zip file");
			return false;
		}
		$zip = new \ZipArchive();
		if ($zip->open($output, ZipArchive::OVERWRITE) === false) {
			$this->error("Could not create zip file");
			return false;
		}

		if ($this->_addToZip($dirs_files, $zip, "") === false)
			$this->error("Could not add all files to zip archive");

		if ($zip->count() < 1) {
			$zip->close();
			@unlink($output);
			$this->error("Resulting zip file was empty");
			return false;
		}
		
		$zip->close();
		return true;
	}

	public function _addToZip($dirs_files, $zip, $path) {
		$no_errors = true;
		if (count($dirs_files) == 0) {
			$dirs_files = array_merge($this->get_subdirs(), $this->get_file_names());
		}
		foreach ($dirs_files as $dir_file) {
			$fullpath = $this->full_path . DIRSEP . $dir_file;
			if ($this->is_file($dir_file) && is_readable($fullpath)) {
				if ($path == "")
					$zipfilepath = $dir_file;
				else
					$zipfilepath = $path . "/" . $dir_file; //Always use forward slash for portability

				$zip->addFile($fullpath, $zipfilepath);
			}
			else if ($this->is_subdir($dir_file)) {
				$dir = new dir($this->get_chrooted_path() . DIRSEP . $dir_file, $this->chroot_path);
				if ($dir->has_errors()) {
					$this->error("Could not open path $path" . DIRSEP . $dir_file . " to add to zip file");
					$no_errors = false;
				}
				else {
					if ($path == "")
						$zipfilepath = $dir_file;
					else
						$zipfilepath = $path . "/" . $dir_file; //Always use forward slash for portability
					if ($dir->_addToZip(array(), $zip, $zipfilepath) === false) {
						$this->error(join("\n", $dir->get_errors()));
						$no_errors = false;
					}
				}
			}
			else {
				$this->error("Could not add $path" . DIRSEP . $dir_file . " to zip file");
				$no_errors = false;
			}
		}
		return $no_errors;
	}

	public function get_upload_max_filecount() {
		return ini_get("max_file_uploads");
	}

	public function get_upload_max_filesize() {
		$ini_size = ini_get("upload_max_filesize");
		if (stristr($ini_size, "k"))
			$upload_bytes = intVal($ini_size) * 1024;
		else if (stristr($ini_size, "m"))
			$upload_bytes = intVal($ini_size) * 1024 * 1024;
		else if (stristr($ini_size, "g"))
			$upload_bytes = intVal($ini_size) * 1024 * 1024 * 1024;

		$ini_size = ini_get("post_max_size");
		if (stristr($ini_size, "k"))
			$post_bytes = intVal($ini_size) * 1024;
		else if (stristr($ini_size, "m"))
			$post_bytes = intVal($ini_size) * 1024 * 1024;
		else if (stristr($ini_size, "g"))
			$post_bytes = intVal($ini_size) * 1024 * 1024 * 1024;

		return min($upload_bytes, $post_bytes);
	}

	public function upload($file) {
		//$file should be from $_FILES
		if (is_array($file["name"])) {
			$no_errors = true;
			for ($i = 0; $i < count($file["name"]); $i++) {
				if ($file["error"][$i] == UPLOAD_ERR_OK && is_uploaded_file($file["tmp_name"][$i])) {
					//sanitize file name
					if ($this->is_file($file["name"][$i])) {
						$this->error("File " . $file["name"][$i] . " already exists");
						$no_errors = false;
					}
					else if (!$this->is_valid_filename($file["name"][$i])) {						
						$this->error("Filename " . $file["name"][$i] . " is invalid");
						$no_errors = false;
					}
					else if (@move_uploaded_file($file["tmp_name"][$i], $this->full_path . DIRSEP . $file["name"][$i]) === false) {
						$this->error("Could not move uploaded file " . $file["name"][$i]);
						$no_errors = false;
					}
				}
				else {
					$this->error("Upload of file " . $file["name"][$i] . " failed");
					$no_errors = false;
				}
			}
			$this->refresh();
			return $no_errors;
		}
		else {
			if ($file["error"] == UPLOAD_ERR_OK) {
				//sanitize file name
				if ($this->is_file($file["name"])) {
					$this->error("File " . $file["name"] . " already exists");
					return false;
				}
				else if (!$this->is_valid_filename($file["name"])) {						
					$this->error("Filename " . $file["name"] . " is invalid");
					return false;
				}
				else if (@move_uploaded_file($file["tmp_name"], $this->full_path . DIRSEP . $file["name"] === false) ) {
					$this->error("Could not move uploaded file " . $file["name"]);
					return false;
				}
				else {
					$this->refresh();
					return true;
				}
			}
			else {
				$this->error("Upload of file " . $file["name"] . " failed");
				return false;
			}
		}
	}

	public function make_dir($subdir) {
		if (!$this->is_valid_filename($subdir)) {
			$this->error("Invalid directory name: \"$subdir\"");
			return false;
		}
		if (@mkdir($this->full_path . DIRSEP . $subdir, 0775, true)) {
			$this->refresh();
			return true;
		}
		else {
			$this->error("Could not create directory \"$subdir\"");
			return false;
		}
	}

	public function remove_dirs($subdirs) {
		if (is_string($subdirs))
			$subdirs = array($subdirs);
		if (!is_array($subdirs) || count($subdirs) < 1)
			return false;

		$errFlag = false;
		foreach ($subdirs as $subdir) {
			if (!in_array($subdir, $this->subdirs)) {
				$this->error("Could not find \"$subdir\"");
				$errFlag = true;
				continue;
			}
			if (@rmdir($this->full_path . DIRSEP . $subdir)) {
				continue;
			}
			else {
				$this->error("Could not remove \"$subdir\" (directories must be empty for removal)");
				$errFlag = true;
			}
		}
		$this->refresh();
		return !$errFlag;
	}

	public function copy($dirs_files, $to) {
		if (is_string($dirs_files))
			$dirs_files = array($dirs_files);
		if (!is_array($dirs_files) || count($dirs_files) < 1)
			return false;

		$to_path = $this->sanitize_path($to);
		if ($to_path === false) {
			$this->error("Invalid destination path: \"$to\"");
			return false;
		}

		$no_errors = true;
		foreach ($dirs_files as $dir_file) {
			if ($this->is_file($dir_file)) {
				if(@!copy($this->full_path . DIRSEP . $dir_file, $to_path . DIRSEP . $dir_file)) {
					$this->error("Could not move \"$dir_file\" to \"$to\"");
					$no_errors = true;
				}
			}
			else if ($this->is_subdir($dir_file)) {
				$from_dir = new dir($this->get_chrooted_path() . DIRSEP . $dir_file, $this->chroot_path);
				if ($from_dir->has_errors()) {
					$this->error("Invalid path. Partial copy may have occurred");
					$no_errors = false;
					continue;
				}
				$to_dir = new dir($to, $this->chroot_path);
				if ($to_dir->has_errors()) {
					$this->error("Invalid path. Partial copy may have occurred");
					$no_errors = false;
					continue;
				}
				if (!$to_dir->make_dir($dir_file)) {
					$this->error("Could not copy \"" . $this->get_chrooted_path() . DIRSEP . "$dir_file\" to " . $to_dir->get_chrooted_path());
					$no_errors = false;
					continue;
				}
				$from_dir->copy($from_dir->get_file_names(), $to . DIRSEP . $dir_file);
				$from_dir->copy($from_dir->get_subdirs(), $to . DIRSEP . $dir_file);
			}
			else {
				$this->error("Could not find \"$dir_file\"");
				$no_errors = false;
				continue;
			}
		}
		$this->refresh();
		return $no_errors;
	}

	public function duplicate($from, $to) {
		if (!$this->is_file($from)) {
			$this->error("Could not find \"$from\"");
			return false;
		}

		if ($this->is_file($to)) {
			$this->error("\"$to\" already exists");
			return false;
		}

		if (!$this->is_valid_filename($to)) {
			$this->error("Invalid target for duplication: \"$to\"");
			return false;
		}

		if (copy($this->full_path . DIRSEP . $from, $this->full_path . DIRSEP . $to)) {
			$this->refresh();
			return true;
		}
		else {
			$this->error("Could not duplicate \"$from\"");
			return false;
		}
	}

	public function move($dirs_files, $to_dir) {
		if (is_string($dirs_files))
			$dirs_files = array($dirs_files);
		if (!is_array($dirs_files) || count($dirs_files) < 1)
			return false;

		$to = $this->sanitize_path($to_dir);
		if ($to === false) {
			$this->error("Invalid destination path");
			return false;
		}

		$no_errors = true;
		foreach ($dirs_files as $dir_file) {
			if (!$this->is_subdir($dir_file) && !$this->is_file($dir_file)) {
				$this->error("Could not find \"$dir_file\"");
				$no_errors = false;
				continue;
			}
			else if (is_file($to . DIRSEP . $dir_file) || is_dir($to . DIRSEP . $dir_file)) {
				$this->error("Could not move \"$dir_file\" to \"$to_dir\" (already exists)");
				$no_errors = false;
				continue;
			}
			else if (@!rename($this->full_path . DIRSEP . $dir_file, $to . DIRSEP . $dir_file)) {
				$this->error("Could not move \"$dir_file\" to \"$to_dir\"");
				$no_errors = true;
			}
		}
		$this->refresh();
		return $no_errors;
	}

	public function kill_dirs($subdirs) {
		if (is_string($subdirs))
			$subdirs = array($subdirs);
		if (!is_array($subdirs) || count($subdirs) < 1) {
			$this->error("Subdirectories not properly specified");
			return false;
		}

		$errFlag = false;
		foreach ($subdirs as $subdir) {
			if(!$this->kill($subdir))
				$errFlag = true;
		}

		return !$errFlag;
	}

	private function kill($subdir) {
		if (!in_array($subdir, $this->subdirs)) {
			$this->error("Subdirectory \"$subdir\" does not exist");
			return false;
		}

		$dir = new dir($this->get_chrooted_path() . DIRSEP . $subdir, $this->chroot_path);
		if ($dir->has_errors()) {
			$this->error("Invalid path. Partial kill may have occurred");
			return false;
		}

		foreach ($dir->get_file_names() as $file_name) {
			if (!$dir->delete($file_name)) {
				$this->error("Could not delete \"" . $dir->get_chrooted_path() . DIRSEP . $file_name . "\". Partial kill may have occurred");
				return false;
			}
		}

		foreach ($dir->subdirs as $subdir_name) {
			if (!$dir->kill($subdir_name)) {
				foreach ($dir->get_errors() as $error)
					$this->error($error);

				$this->error("Could not kill \"" . $dir->get_chrooted_path() . DIRSEP . $subdir . "\". Partial kill may have occurred");
				return false;
			}
		}

		if (!$this->remove_dirs($subdir)) {
			$this->error("Could not remove directory \"$subdir\". Partial kill may have occurred");
			return false;
		}
		return true;
	}

	public function search($query, $depth, $regex) {
		$results = array();
		$path = $this->get_chrooted_path();
		if ($path != "/")
			$path .= DIRSEP;
		$file_names = $this->get_file_names();
		if ($regex) {
			//Validate pattern
			if (@preg_match($query, "") === false) {
				$this->error("Invalid pattern");
				return false;
			}
			foreach ($file_names as $name) {
				if (preg_match($query, $name))
					$results[] = $path . $name;
			}
			foreach ($this->subdirs as $name) {
				if (preg_match($query, $name))
					$results[] = $path . $name;
				if ($depth > 0) {
					$dir = new dir($path . $name, $this->chroot_path);
					if ($dir->has_errors()) {
						$this->error("Invalid path while searching");
						return false;
					}
					$results = array_merge($results, $dir->search($query, $depth - 1, $regex));
				}
			}
		}
		else {
			foreach ($file_names as $name) {
				if (stristr($name, $query))
					$results[] = $path . $name;
			}
			foreach ($this->subdirs as $name) {
				if (stristr($name, $query))
					$results[] = $path . $name;
				if ($depth > 0) {
					$dir = new dir($path . $name, $this->chroot_path);
					if ($dir->has_errors()) {
						$this->error("Invalid path while searching");
						return false;
					}
					$results = array_merge($results, $dir->search($query, $depth - 1, $regex));
				}
			}
		}
		return $results;
	}

	public function size($dir_file) {
		if ($this->is_file($dir_file)) {
			return $this->get_file($dir_file)->size;
		}
		else if ($this->is_subdir($dir_file)) {
			$dir = new dir($this->get_chrooted_path() . DIRSEP . $dir_file, $this->chroot_path);
			if ($dir->has_errors()) {
				$this->error("Could not get size of $dir_file");
				return false;
			}
			$size = 0;
			foreach ($dir->get_files() as $file) {
				$size += $file->size;
			}
			foreach ($dir->get_subdirs() as $subdir) {
				$dir_size = $dir->size($subdir);
				if ($dir_size === false) {
					$this->error("Could not get size of " . $this->get_chrooted_path() . DIRSEP . $dir_file . DIRSEP . $subdir);
					return false;
				}
				else {
					$size += $dir_size;
				}
			}
			return $size;
		}
		else {
			$this->error("Could not find $dir_file");
			return false;
		}
	}

	public function get_errors() {
		return $this->errors;
	}

	public function has_errors() {
		return count($this->errors) > 0;
	}

}

class file {
	public $name, $size, $owner, $group, $permissions, $created, $modified;

	public function __construct($basename, $path) {
		$this->name = $basename;
		$fullpath = $path . DIRSEP . $basename;
		$this->size = filesize($fullpath);
		//These need windows equivalents
		$this->owner = posix_getpwuid(fileowner($fullpath))["name"];
		$this->group = posix_getgrgid(filegroup($fullpath))["name"];
		$this->permissions = substr(sprintf('%o', fileperms($fullpath)), -4);
		$this->modified = filemtime($fullpath);
		//filectime() only gives creation time on windows; gives "change" time on linux
		$this->created = filectime($fullpath);
	}
}

?>
