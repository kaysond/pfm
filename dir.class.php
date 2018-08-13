<?php
class dir
{
	private $path, $chroot_path, $invalid_chars = "";
	private $dir_tree, $subdirs, $files, $errors  = array();

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
			$path = DIRECTORY_SEPARATOR;

		if (substr($path, 0, 1) == DIRECTORY_SEPARATOR) {
			$realpath = realpath($this->chroot_path . $path);
			if ($realpath !== false && substr($realpath, 0, strlen($this->chroot_path)) == $this->chroot_path)
				return $realpath;
			else
				return false;
		}
		else { //Could be a filename or subdirectory relative to the current path
			$realpath = realpath($this->full_path . DIRECTORY_SEPARATOR . $path);
			if ($realpath !== false && substr($realpath, 0, strlen($this->chroot_path)) == $this->chroot_path)
				return $realpath;
			else
				return false;
		}
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

	private function find_file($file_name) {
		foreach ($this->files as $file)
			if ($file_name == $file->name)
				return true;

		return false;
	}

	public function refresh() {
		if (@$contents = scandir($this->full_path)) {
			$this->files = array();
			$this->subdirs = array();
			foreach ($contents as $file) {
				switch (filetype($this->full_path . DIRECTORY_SEPARATOR . $file)) {
					case "dir":
						if ($file != ".." && $file != ".")
							$this->subdirs[] = $file;
						break;
					case "file":
						$this->files[] = new file($file, $this->full_path);
						break;
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
		return $path == "" ? DIRECTORY_SEPARATOR : $path;
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

	public function rename_file($file, $to) {
		if (!$this->find_file($file)) {
			$this->error("Could not find \"$file\"");
			return false;
		}

		if (!$this->is_valid_filename($to)) {
			$this->error("Invalid target for file rename: \"$to\"");
			return false;
		}

		if (@rename($this->full_path . DIRECTORY_SEPARATOR . $file, $this->full_path . DIRECTORY_SEPARATOR . $to)) {
			$this->refresh();
			return true;
		}
		else {
			$this->error("Could not rename \"$file\" to \"$to\"");
			return false;
		}
	}

	public function smart_rename($pattern, $replace) {
		if ($pattern == "") {
			$this->error("Pattern not specified");
			return false;
		}

		$flagError = false;
		foreach ($this->files as $file) {
			if (preg_match($pattern, $file->name)) {
				if (!$this->rename_file($file->name, preg_replace($pattern, $replace, $file->name))) {
					$flagError = true;
				}
			}
		}
		if ($flagError)
			$this->error("Could not rename all files. Partial rename may have occurred");

		$this->refresh();
		return !$flagError;
	}

	public function smart_rename_test($pattern, $replace) {
		if ($pattern == "") {
			$this->error("Pattern not specified");
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

	public function copy_files($files, $to_dir) {
		if (is_string($files))
			$files = array($files);
		if (!is_array($files) || count($files) < 1)
			return false;

		$to = $this->sanitize_path($to_dir);
		if ($to === false) {
			$this->error("Invalid destination path");
			return false;
		}

		$flagError = false;
		foreach ($files as $file) {
			if (!$this->find_file($file)) {
				$this->error("Could not find \"$file\"");
				$flagError = true;
				continue;
			}
			if (@!copy($this->full_path . DIRECTORY_SEPARATOR . $file, $to. DIRECTORY_SEPARATOR . $file)) {
				$this->error("Could not copy \"$file\" to $to");
				$flagError = false;
			}
		}
		$this->refresh();
		return !$flagError;
	}

	public function move_files($files, $to_dir) {
		if (is_string($files))
			$files = array($files);
		if (!is_array($files) || count($files) < 1)
			return false;

		$to = $this->sanitize_path($to_dir);
		if ($to === false) {
			$this->error("Invalid destination path");
			return false;
		}

		$flagError = false;
		foreach ($files as $file) {
			if (!$this->find_file($file)) {
				$this->error("Could not find \"$file\"");
				$flagError = true;
				continue;
			}
			if (@!rename($this->full_path . DIRECTORY_SEPARATOR . $file, $to. DIRECTORY_SEPARATOR . $file)) {
				$this->error("Could not move \"$file\" to \"$to\"");
				$flagError = false;
			}
		}
		$this->refresh();
		return !$flagError;
	}

	public function delete($files) {
		if (is_string($files)) 
			$file = array($files);
		if (!is_array($files) || count($files) < 1)
			return false;

		$errFlag = false;
		foreach($files as $file) {
			if (!$this->find_file($file)) {
				$this->error("Could not find \"$file\"");
				$errFlag = true;
			}
			else if (@!unlink($this->full_path . DIRECTORY_SEPARATOR . $file)) {
				$this->error("Could not delete \"$file\"");
				$errFlag = true;
			}
		}
		$this->refresh();
		return !$errFlag;
	}

	public function write_file($file, $contents) {
		if (!$this->find_file($file)) {
			$this->error("Could not find \"$file\"");
			return false;
		}
		$filepath = $this->full_path . DIRECTORY_SEPARATOR . $file;
		if (!is_writable($filepath)) {
			$this->error("\"$file\" is not writable");
			return false;
		}
		if (@file_put_contents($this->full_path . DIRECTORY_SEPARATOR . $file, $contents)) {
			return true;
		}
		else {
			$this->error("Could not write to \"$file\"");
			return false;
		}
	}

	public function read_file($file) {
		if (!$this->find_file($file)) {
			$this->error("Could not find \"$file\"");
			return false;
		}
		$filepath = $this->full_path . DIRECTORY_SEPARATOR . $file;
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

	public function new_file($file) {
		if ($this->find_file($file)) {
			$this->error("\"$file\" already exists");
			return false;
		}
		if (!$this->is_valid_filename($file)) {
			$this->error("Invalid file name: \"$file\"");
			return false;
		}
		if (@!touch($this->full_path . DIRECTORY_SEPARATOR . $file)) {
			$this->error("Could not create \"$file\"");
			return false;
		}
		$this->refresh();
		return true;
	}

/****This needs a security audit***
	public function upload($file) {
		//Assume $file is from $_FILES
		if ($file["error"] == UPLOAD_ERR_OK) {
			if (move_uploaded_file($file["tmp_name"], $this->full_path . DIRECTORY_SEPARATOR . $file["name"]) ) {
				$this->refresh();
				return true;
			}
			else {
				$this->error("Could not move uploaded file");
				return false;
			}
		}
		else {
			$this->error("Upload failed");
			return false;
		}
	}
*/
	public function make_dir($subdir) {
		if (!$this->is_valid_filename($subdir)) {
			$this->error("Invalid directory name: \"$subdir\"");
			return false;
		}
		if (@mkdir($this->full_path . DIRECTORY_SEPARATOR . $subdir, 0775, true)) {
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
			if (@rmdir($this->full_path . DIRECTORY_SEPARATOR . $subdir)) {
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

	public function rename_dir($from, $to) {
		if (!in_array($from, $this->subdirs)) {
			$this->error("Could not find \"$from\"");
			return false;
		}
		
		if (!$this->is_valid_filename($to)) {
			$this->error("Invalid target for directory rename: \"$to\"");
			return false;
		}
		
		if (@rename($this->full_path . DIRECTORY_SEPARATOR . $from, $this->full_path . DIRECTORY_SEPARATOR . $to)) {
			$this->refresh();
			return true;
		}
		else {
			$this->error("Could not rename \"$from\" to \"$to\"");
			return false;
		}
	}

	public function copy_dirs($subdirs, $to_dir) {
		//needs to be run recursively via this class
		return false;
	}

	public function move_dirs($subdirs, $to_dir) {
		if (is_string($subdirs))
			$subdirs = array($subdirs);
		if (!is_array($subdirs) || count($subdirs) < 1)
			return false;

		$to = $this->sanitize_path($to_dir);
		if ($to === false) {
			$this->error("Invalid destination path");
			return false;
		}

		$flagError = false;
		foreach ($subdirs as $subdir) {
			if (!in_array($subdir, $this->subdirs, true)) {
				$this->error("Could not find \"$subdir\"");
				$flagError = true;
				continue;
			}
			if (@!rename($this->full_path . DIRECTORY_SEPARATOR . $subdir, $to . DIRECTORY_SEPARATOR . $subdir)) {
				$this->error("Could not move \"$subdir\" to \"$to\"");
				$flagError = false;
			}
		}
		$this->refresh();
		return !$flagError;
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

		$dir = new dir($this->get_chrooted_path() . DIRECTORY_SEPARATOR . $subdir, $this->chroot_path);
		if ($dir->has_errors()) {
			$this->error("Invalid path. Partial kill may have occurred");
			return false;
		}

		foreach ($dir->get_file_names() as $file_name) {
			if (!$dir->delete($file_name)) {
				$this->error("Could not delete \"" . $dir->get_chrooted_path() . DIRECTORY_SEPARATOR . $file_name . "\". Partial kill may have occurred");
				return false;
			}
		}

		foreach ($dir->subdirs as $subdir_name) {
			if (!$dir->kill($subdir_name)) {
				foreach ($dir->get_errors() as $error)
					$this->error($error);

				$this->error("Could not kill \"" . $dir->get_chrooted_path() . DIRECTORY_SEPARATOR . $subdir . "\". Partial kill may have occurred");
				return false;
			}
		}

		if (!$this->remove_dirs($subdir)) {
			$this->error("Could not remove directory \"$subdir\". Partial kill may have occurred");
			return false;
		}
		return true;
	}

	public function load_dir_tree() {
		$dirout = new StdClass();
		foreach ($this->get_subdirs() as $subdir_name) {
			$subdir = new dir($this->get_chrooted_path() . DIRECTORY_SEPARATOR . $subdir_name, $this->chroot_path);
			if ($subdir->has_errors()) {
				$this->error("Could not get index of " . $subdir->get_chrooted_path());
				return false;
			}

			if (!$subdir->load_dir_tree()) {
				$this->error("Could not get tree of " . $subdir->get_chrooted_path());
				return false;
			}
			$subdirout = $subdir->get_dir_tree();
			$dirout->{$subdir_name} = $subdirout;
		}

		$this->dir_tree = $dirout;

		return true;
	}

	public function get_dir_tree() {
		return $this->dir_tree;
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
		$fullpath = $path . DIRECTORY_SEPARATOR . $basename;
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
