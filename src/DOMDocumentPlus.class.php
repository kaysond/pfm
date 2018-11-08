<?php
namespace DOMDocumentPlus;
class DOMDocument extends \DOMDocument {
	public function loadElement(string $html) {
		$DOM = new DOMDocument();
		$DOM->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		return $this->importNode($DOM->documentElement, true);
	}

	public function savePrettyHTML(array $params = array()) {
		if ($params == array())
			$params = array("indent" => true, "output-xhtml" => true, "wrap" => 0, "indent-spaces" => 4, "indent-cdata" => true);
                $tidy = new \tidy;
                $tidy->parseString($this->saveHTML(), $params);
                $tidy->cleanRepair();
                if (tidy_error_count($tidy) > 0 || tidy_warning_count($tidy) > 0)
                        trigger_error("Tidy errors/warnings: " . $tidy->errorBuffer, E_USER_WARNING);
                return tidy_get_output($tidy);
	}
}
?>
