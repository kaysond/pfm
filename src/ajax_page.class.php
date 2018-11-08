<?php
define("PRETTY_HTML", true);
use DOMDocumentPlus\DOMDocument as DOMDocument;
require_once "DOMDocumentPlus.class.php";
require_once "secure_login_session.class.php";
class ajax_page {
	public $DOM, $XPath;
	private $html, $js, $inline_js, $css, $ajax_callbacks = array();
	private $session;

	public function __construct($html_file, string $session = "") {
		$this->html[] = $html_file;
		if (!empty($session)) {
			$this->session = new secure_login_session($session);
			$this->ajax_callbacks = array(
				"login" => array($this, "login"),
				"logout" => array($this, "logout"),
				"is_logged_in" => array($this, "is_logged_in")
			);
		}
	}

	public function add_css($css_file) {
		$this->css[] = $css_file;
	}

	public function add_js($js_file) {
		$this->js[] = $js_file;
	}

	public function add_inline_js($js) {
		$this->inline_js[] = $js;
	}

	public function add_html($html_file) {
		$this->html[] = $html_file;
	}

	public function register_ajax_callback(string $request, callable $callback) {
		$this->ajax_callbacks[$request] = $callback;
	}

	public function login() {
		if (!isset($_REQUEST["username"]) || !isset($_REQUEST["password"]))
			return (object) ["success" => false, "error" => "Request did not include username and password"];
		if ($this->session && $this->session->login($_REQUEST["username"], $_REQUEST["password"]))
			return (object) ["success" => true];
		else
			return (object) ["success" => false, "error" => "Invalid username or password"];
	}

	public function logout() {
		if ($this->session) {
			$this->session->logout();
			return (object) ["success" => true];
		}
		else {
			return (object) ["success" => false, "error" => "Could not find session"];
		}
	}

	public function is_logged_in() {
		return (object) ["success" => $this->session && $this->session->is_valid()]; 
	}

	public function exec() {
		foreach ($this->ajax_callbacks as $request => $callback) {
			if (isset($_REQUEST[$request]) && is_callable($callback)) {
				$response_obj = call_user_func($callback);
				if ($response_obj !== false) {
					header('Content-Type: application/json');
					echo json_encode($response_obj);
					return;
				}
			}
		}

		$this->generateHTML();
		if (PRETTY_HTML)
			echo $this->DOM->savePrettyHTML();
		else
			echo $this->DOM->saveHTML();
	}

	private function generateHTML() {
		$this->DOM = new DOMDocument();
        $html = "";
        foreach($this->html as $html_file) {
                $html .= file_get_contents($html_file);
        }
        $this->DOM->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $this->XPath = new DOMXPath($this->DOM);

        //Add JS
        foreach ($this->js as $js_file) {
                $element = $this->DOM->loadElement("<script type='text/javascript' src='$js_file'>");
                $this->DOM->getElementsByTagName("head")->item(0)->appendChild($element);
        }

        foreach ($this->inline_js as $inline_js) {
                $element = $this->DOM->loadElement("<script type='text/javascript'>$inline_js</script>");
                $this->DOM->getElementsByTagName("head")->item(0)->appendChild($element);
        }

        //Add CSS
        foreach ($this->css as $css_file) {
                $element = $this->DOM->loadElement("<link rel='stylesheet' type='text/css' href='$css_file'>");
                $this->DOM->getElementsByTagName("head")->item(0)->appendChild($element);
        }
	}
}
?>
