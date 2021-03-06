<?php
namespace DOMDocumentPlus;

class DOMDocument extends \DOMDocument {
	public $tidy_errors = array();

	public function loadElement(string $html) {
		$DOM = new DOMDocument();
		libxml_use_internal_errors(true);
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
                        $this->tidy_errors = array_merge($this->tidy_errors, explode("\n", $tidy->errorBuffer));
                return tidy_get_output($tidy);
	}
}
?>