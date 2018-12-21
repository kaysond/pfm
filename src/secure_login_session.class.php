<?php
//These constants may be changed without breaking existing hashes.
define("PBKDF2_HASH_ALGORITHM", "sha256");
define("PBKDF2_ITERATIONS", 1000);
define("PBKDF2_SALT_BYTE_SIZE", 24);
define("PBKDF2_HASH_BYTE_SIZE", 24);

define("HASH_SECTIONS", 4);
define("HASH_ALGORITHM_INDEX", 0);
define("HASH_ITERATION_INDEX", 1);
define("HASH_SALT_INDEX", 2);
define("HASH_PBKDF2_INDEX", 3);

define("FAILED_ATTEMPTS", 5);
define("DISABLE_IP_CHECK", false);

class secure_login_session {

	function __construct($session_name, $userfile) {
		if (session_status() == PHP_SESSION_ACTIVE) {
			throw new Exception("Session already active");
		}

		$this->userfile = $userfile;

		//Hash algorithm to use for the session ID
		$session_hash = 'sha256';

		if (in_array($session_hash, hash_algos()))
			ini_set('session.hash_function', $session_hash);

		//Possible values are '4' (0-9, a-f), '5' (0-9, a-v), and '6' (0-9, a-z, A-Z, "-", ",")
		ini_set('session.hash_bits_per_character', 6);

		//Force the session to only use cookies, not URL variables
		ini_set('session.use_only_cookies', 1);

		//Start from existing settings
		$cookieParams = session_get_cookie_params();

		//Only send the session cookie over https
		$secure = true;

		//Do not allow javascript access to the session cookie
		$httponly = true;

		//Do not allow the cookie to be sent cross-domain (PHP 7.3+)
		//$samesite = "Strict";

		session_set_cookie_params($cookieParams["lifetime"], $cookieParams["path"], $cookieParams["domain"], $secure, $httponly);//, $samesite);
		session_name($session_name);

		$this->session_start();

		//Create a new ID every request to prevent hijacking
		$this->session_regenerate_id();
	}

	private function session_start() {
		session_start();
		if (isset($_SESSION['EXPIRED'])) {
			//2min+ old destroyed session. could be a hijack attempt or unstable network
			if ($_SESSION['EXPIRED'] < time()-120) {
				$this->logout();
				throw new Exception("Session expired");
			}
			//Move to the new session ID
			if (isset($_SESSION['NEW_ID'])) {
				session_commit();
				session_id($_SESSION['NEW_ID']);
				session_start();
			}
		}
	}

	private function session_regenerate_id() {
		//Store the new session ID in case a valid request comes in with the old one
		$id = session_create_id();
		$_SESSION['NEW_ID'] = $id;    
		$_SESSION['EXPIRED'] = time();
		$session = $_SESSION;
		session_commit();

		session_id($id);
		session_start();
		$_SESSION = $session;
		unset($_SESSION['EXPIRED']);
		unset($_SESSION['NEW_ID']);
	}

	public function login($user, $password) {
		$users = json_decode(file_get_contents($this->userfile), true);
		if ($users !== NULL && array_key_exists($user, $users)) {
			if ($this->validate_password($password, $users[$user]["login_hash"])) {
				$_SESSION["user"] = $user;
				$session_hash = $users[$user]["login_hash"] . $_SERVER["HTTP_USER_AGENT"];
				if (!DISABLE_IP_CHECK)
					$session_hash .= $_SERVER["REMOTE_ADDR"];

				$_SESSION["hash"] = $this->create_hash($session_hash);
				$users[$user]["brute_force"] = 0;
				if (file_put_contents($this->userfile, json_encode($users)) )
					return true;
				else
					return false;
			}
			else {
				$users[$user]["brute_force"]++;
				if ($users[$user]["brute_force"] >= FAILED_ATTEMPTS) {
					$users[$user . "||LOCKED"] = $users[$user];
					unset($users[$user]);
				}
				file_put_contents($this->userfile, json_encode($users));
				return false;
			}
		}
		else
			return false;
	}

	public function logout() {
		//Unset all of the session variables.
		$_SESSION = array();

		if (ini_get("session.use_cookies") || ini_get("session.use_only_cookies")) {
			$params = session_get_cookie_params();
			setcookie(session_name(), '', time() - 42000,
				$params["path"], $params["domain"],
				$params["secure"], $params["httponly"]
			);
		}

		session_destroy();
	}

	public function is_valid() {
		if (!isset($_SESSION["user"]) || !isset($_SESSION["hash"]) )
			return false;
		$users = json_decode(file_get_contents($this->userfile), true);
		if ($users !== NULL && array_key_exists($_SESSION["user"], $users)) {
			$session_hash = $users[$_SESSION["user"]]["login_hash"] . $_SERVER["HTTP_USER_AGENT"];
			if (!DISABLE_IP_CHECK)
				$session_hash .= $_SERVER["REMOTE_ADDR"];
			if ($this->validate_password($session_hash, $_SESSION["hash"] ) ) 
				return true;
			else
				return false;
		}
		else
			return false;
	}

	public function add_user($user, $password) {
		$users = json_decode(file_get_contents($this->userfile), true) ;
		if ($users !== NULL && !array_key_exists($user, $users)) {
			$users[$user] = array("login_hash" => $this->create_hash($password), "brute_force" => 0);
			if (file_put_contents($this->userfile, json_encode($users)) )
				return true;
			else
				return false;
		}
		else
			return false;
	}

	private function create_hash($password)
	{
		//format: algorithm:iterations:salt:hash
		$salt = base64_encode(random_bytes(PBKDF2_SALT_BYTE_SIZE));
		return PBKDF2_HASH_ALGORITHM . ":" . PBKDF2_ITERATIONS . ":" .  $salt . ":" . 
			base64_encode($this->pbkdf2(
				PBKDF2_HASH_ALGORITHM,
				$password,
				$salt,
				PBKDF2_ITERATIONS,
				PBKDF2_HASH_BYTE_SIZE,
				true
			));
	}

	private function validate_password($password, $correct_hash)
	{
		$params = explode(":", $correct_hash);
		if(count($params) < HASH_SECTIONS)
		   return false; 
		$pbkdf2 = base64_decode($params[HASH_PBKDF2_INDEX]);
		return $this->slow_equals(
			$pbkdf2,
			$this->pbkdf2(
				$params[HASH_ALGORITHM_INDEX],
				$password,
				$params[HASH_SALT_INDEX],
				(int)$params[HASH_ITERATION_INDEX],
				strlen($pbkdf2),
				true
			)
		);
	}

	//Compares two strings $a and $b in length-constant time.
	private function slow_equals($a, $b)
	{
		$diff = strlen($a) ^ strlen($b);
		for($i = 0; $i < strlen($a) && $i < strlen($b); $i++)
		{
			$diff |= ord($a[$i]) ^ ord($b[$i]);
		}
		return $diff === 0; 
	}

	/*
	 * PBKDF2 key derivation function as defined by RSA's PKCS #5: https://www.ietf.org/rfc/rfc2898.txt
	 * $algorithm - The hash algorithm to use. Recommended: SHA256
	 * $password - The password.
	 * $salt - A salt that is unique to the password.
	 * $count - Iteration count. Higher is better, but slower. Recommended: At least 1000.
	 * $key_length - The length of the derived key in bytes.
	 * $raw_output - If true, the key is returned in raw binary format. Hex encoded otherwise.
	 * Returns: A $key_length-byte key derived from the password and salt.
	 *
	 * Test vectors can be found here: https://www.ietf.org/rfc/rfc6070.txt
	 *
	 * This implementation of PBKDF2 was originally created by https://defuse.ca
	 * With improvements by http://www.variations-of-shadow.com
	 */
	private function pbkdf2($algorithm, $password, $salt, $count, $key_length, $raw_output = false)
	{
		$algorithm = strtolower($algorithm);
		if(!in_array($algorithm, hash_algos(), true))
			trigger_error('PBKDF2 ERROR: Invalid hash algorithm.', E_USER_ERROR);
		if($count <= 0 || $key_length <= 0)
			trigger_error('PBKDF2 ERROR: Invalid parameters.', E_USER_ERROR);

		if (function_exists("hash_pbkdf2")) {
			//The output length is in NIBBLES (4-bits) if $raw_output is false!
			if (!$raw_output) {
				$key_length = $key_length * 2;
			}
			return hash_pbkdf2($algorithm, $password, $salt, $count, $key_length, $raw_output);
		}

		$hash_length = strlen(hash($algorithm, "", true));
		$block_count = ceil($key_length / $hash_length);

		$output = "";
		for($i = 1; $i <= $block_count; $i++) {
			//$i encoded as 4 bytes, big endian.
			$last = $salt . pack("N", $i);
			//first iteration
			$last = $xorsum = hash_hmac($algorithm, $last, $password, true);
			//perform the other $count - 1 iterations
			for ($j = 1; $j < $count; $j++) {
				$xorsum ^= ($last = hash_hmac($algorithm, $last, $password, true));
			}
			$output .= $xorsum;
		}

		if($raw_output)
			return substr($output, 0, $key_length);
		else
			return bin2hex(substr($output, 0, $key_length));
	}


}

?>
