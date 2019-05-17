<?php
if (!defined('PRETTY_HTML')) define("PRETTY_HTML", false);
if (!defined('MINIFY_HTML')) define("MINIFY_HTML", true);
if (!defined('MINIFY_JS')) define("MINIFY_JS", true);
if (!defined('LOCALIZE_JS')) define("LOCALIZE_JS", true);
if (!defined('MINIFY_CSS')) define("MINIFY_CSS", true);
if (!defined('LOCALIZE_CSS')) define("LOCALIZE_CSS", true);
if (!defined('HTML_ERRORS')) define("HTML_ERRORS", false);
require_once('DOMDocumentPlus.class.php');
require_once('minify/JShrink/Minifier.php');
require_once('minify/JS/JShrink.php');
require_once('minify/JS/ClosureCompiler.php');
require_once('minify/CSS/UriRewriter.php');
require_once('minify/CSSmin.php');
require_once('minify/CSSmin/Utils.php');
require_once('minify/CSSmin/Command.php');
require_once('minify/CSSmin/Colors.php');
require_once('minify/CSSmin/Minifier.php');
require_once('minify/HTML.php');
use DOMDocumentPlus\DOMDocument;
class ajax_page {
	public $DOM, $XPath;
	private $html = array();
	private $js = array();
	private $inline_js = array();
	private $css = array();
	private $callbacks_map = array();
	private $includes = array();
	private $callbacks = '';
	private $header = '';
	private $session_name;
	private $users;

	public function __construct(string $session_name = "", string $users = "") {
		if (!empty($session_name) && !empty($users)) {
			$this->session_name = $session_name;
			$this->users = $users;
			$this->include('secure_login_session.class.php');
			$this->callbacks_map = ['login' => 'login', 'logout' => 'logout', 'is_logged_in' => 'is_logged_in'];
			$this->callbacks = <<<'CB'
function login() { global $ajax_page_session; if (!isset($_REQUEST["username"]) || !isset($_REQUEST["password"])) return (object) ["success" => false, "error" => "Request did not include username and password"]; if ($ajax_page_session->login($_REQUEST["username"], $_REQUEST["password"])) return (object) ["success" => true]; else return (object) ["success" => false, "error" => "Invalid username or password"]; } function logout() { global $ajax_page_session; if ($ajax_page_session) { $ajax_page_session->logout(); return (object) ['success' => true]; } else { return (object) ['success' => false, 'error' => 'Could not find session']; } } function is_logged_in() { global $ajax_page_session; return (object) ['success' => $ajax_page_session && $ajax_page_session->is_valid()]; }
CB;
			$this->callbacks .= PHP_EOL;
		}
	}

	public function set_header(string $header) {
		if (!is_readable($header))
			throw new InvalidArgumentException('Header $header is not readable');
		$this->header = file_get_contents($header);
	}

	public function include(string $file) {
		$this->includes[] = $file;
	}

	public function add_css(string $file) {
		$this->css[] = $file;
	}

	public function add_js(string $file) {
		$this->js[] = $file;
	}

	public function add_html(string $file) {
		$this->html[] = $file;
	}

	public function add_callbacks_from_file(string $file) {
		$contents = php_strip_whitespace($file);
		if ($contents == '')
			throw new InvalidArgumentException("Could not get contents of $file");

		//Remove tags
		$pattern = '/(?:<\?php\s*|\s*\?>)/';
		$contents = preg_replace($pattern, "", $contents);
		$this->callbacks .= $contents . PHP_EOL;

		//Remove string literals then parse for function names
		$pattern = '/"(?:\\\\"|[^"])*?"/';
		$contents = preg_replace($pattern, "", $contents);
		$pattern = "/'(?:\\\\'|[^'])*?'/";
		$contents = preg_replace($pattern, "", $contents);
		$pattern = "/<<<'?(.*)'?(?:\n|\r\n).*?\2\1;/s";
		$contents = preg_replace($pattern, "", $contents);

		$pattern = '/function\s+(\S+)\s*\(./';
		if(!preg_match_all($pattern, $contents, $functions)) //catches false (error) and 0 (no matches)
			throw new InvalidArgumentException("Could not find any functions in $file");

		foreach ($functions[1] as $function) {
			if (array_key_exists($function, $this->callbacks_map)) {
				throw new InvalidArgumentException("Function $function already exists in callbacks map");
			}
		}

		$this->callbacks_map = array_merge($this->callbacks_map, array_combine($functions[1], $functions[1]));
	}

	public function rename_callback(string $from, string $to) {
		if (!array_key_exists($from, $this->callbacks_map) || array_key_exists($to, $this->callbacks_map))
			return false;

		$this->callbacks_map[$to] = $this->callbacks_map[$from];
		unset($this->callbacks_map[$from]);
		return true;
	}

	public function exec() {
		foreach ($this->includes as $include) {
			require_once($include);
		}
		if (!empty($this->session_name) && !empty($this->users)) {
			global $ajax_page_session;
			$ajax_page_session = new secure_login_session\secure_login_session($this->session_name, $this->users);
		}
		eval($this->callbacks);
		foreach ($this->callbacks_map as $request => $callback) {
			if (isset($_REQUEST[$request]) && is_callable($callback)) {
				$response_obj = call_user_func($callback);
				if (is_object($response_obj)) {
					header('Content-Type: application/json');
					echo json_encode($response_obj);
					return;
				}
				else if ($response_obj === true) { //No output
					return;
				}
			}
		}

		echo $this->generateHTML();
	}

	public function compile(string $file) {
		$output = "<?php" . PHP_EOL;
		if ($this->header) {
			$output .= 'namespace {' . PHP_EOL;
			$output .= preg_replace('/(?:<\?php\s*|\s*\?>)/', '', $this->header) . PHP_EOL;
			$output .= '}' . PHP_EOL;
		}

		$pattern = '/(?:<\?php\s*|\s*\?>)/';
		$output .= "//Includes" . PHP_EOL;
		foreach ($this->includes as $include) {
			if (!is_readable($include))
				throw new InvalidArgumentException("Include $include is not readable");

			$contents = preg_replace($pattern, "", php_strip_whitespace($include));
			//Check how namespace is declared and always use namespace {} syntax
			if (!strstr($contents, 'namespace')) {
				$contents = 'namespace { ' . $contents . ' } ';
			}
			else if (preg_match('/namespace [a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*\s*;/', $contents)) {
				$contents = preg_replace('/namespace ([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*;/', 'namespace \1 {', $contents) . '}';
			}
			$output .= $contents . PHP_EOL;
		}
		$output .= 'namespace { ';
		if (!empty($this->session_name) && !empty($this->users)) {
			$output .= 'use secure_login_session\secure_login_session; global $ajax_page_session; $ajax_page_session = new secure_login_session(' . "{$this->session_name}, {$this->users});" . PHP_EOL;
		}
		$output .= "//Callbacks" . PHP_EOL;
		$output .= $this->callbacks . PHP_EOL;

		$output .= "//Callback selection" . PHP_EOL;
		$output .= $this->generate_callbacks_code() . PHP_EOL;

		$output .=  PHP_EOL . '?>' . PHP_EOL;

		$output .= $this->generateHTML();

		$output .= '<?php } ?>'; //close namespace
		file_put_contents($file, $output);
	}

	private function generate_callbacks_code() {
		$output = '$callbacks_map = array(';
		foreach ($this->callbacks_map as $callback => $function) {
			$output .= "'$callback' => '$function',";
		}
		$output = substr($output, 0, -1) . '); ';
		$output .= preg_replace('/\s+/', ' ', <<<'CB'
foreach ($callbacks_map as $request => $callback) {
	if (isset($_REQUEST[$request]) && is_callable($callback)) {
		$response_obj = call_user_func($callback);
		if (is_object($response_obj)) {
			header('Content-Type: application/json');
			echo json_encode($response_obj);
			die();
		}
		else if ($response_obj === true) {
			die();
		}
	}
}
CB
);
	return $output;
	}

	private function generateHTML() {
		$output = '';
		//Load HTML
		$html = '';
		$this->DOM = new DOMDocument();
		foreach($this->html as $html_file) {
				$html .= file_get_contents($html_file);
		}
		$this->DOM->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

		//Add JS
		foreach ($this->js as $js_file) {
			if (MINIFY_JS) {
				$node = $this->DOM->createElement('script');
				$text = $this->DOM->createTextNode(Minify_JS_ClosureCompiler::minify(file_get_contents($js_file), array('maxBytes' => 500000)));
				$node->appendChild($text);

			}
			elseif (LOCALIZE_JS) {
				$node = $this->DOM->createElement('script');
				$text = $this->DOM->createTextNode(file_get_contents($js_file));
				$node->appendChild($text);
			}
			else {
				$node = $this->DOM->createElement('script');
				$node->setAttribute('src', "$js_file");
			}
			$this->DOM->getElementsByTagName("head")->item(0)->appendChild($node);
		}

		//Add CSS
		foreach ($this->css as $css_file) {
			if (MINIFY_CSS) {
				$tag = '<style>' .
					Minify_CSSmin::minify(file_get_contents($css_file)) .
					'</style>';
			}
			elseif (LOCALIZE_CSS) {
				$tag = '<style>' .
				file_get_contents($css_file) .
				'</style>';
			}
			else {
				$tag = "<link rel='stylesheet' type='text/css' href='$css_file'>";
			}

			$element = $this->DOM->loadElement($tag);
			$this->DOM->getElementsByTagName("head")->item(0)->appendChild($element);
		}

		if (HTML_ERRORS && count($this->DOM->tidy_errors) > 0) {
			$output .= htmlspecialchars(implode('<br>', $this->DOM->tidy_errors));
		}

		if (PRETTY_HTML) {
			$output .= $this->DOM->savePrettyHTML();
		}
		elseif (MINIFY_HTML) {
			$output .= MINIFY_HTML::minify($this->DOM->saveHTML());
		}
		else {
			$output .= $this->DOM->saveHTML();
		}

		return $output;
	}
}
?>
