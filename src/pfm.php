<?php
// define('MINIFY_CSS', true);
// define('MINIFY_JS', true);
// define('MINIFY_HTML', true);

define('MINIFY_JS', false);
define('MINIFY_HTML', false);
define('MINIFY_CSS', false);
define('LOCALIZE_JS', false);
define('LOCALIZE_HTML', false);
define('LOCALIZE_CSS', false);

require_once 'ajax_page.class.php';
$page = new ajax_page('SESSION_NAME', 'AUTH_METHOD');
$page->set_header('pfm.header.php');
$page->include('pfm.inc.php');
$page->include('dir.class.php');
$page->add_html('pfm.html');
$page->add_js('jquery-3.4.1.js');
$page->add_js('pfm.js');
$page->add_css('normalize.css');
$page->add_css('skeleton.css');
$page->add_css('pfm.css');
$page->add_css('Roboto.css');
$page->add_callbacks_from_file('pfm.callbacks.php');
$page->rename_callback('copy_pfm', 'copy');
$page->rename_callback('rename_pfm', 'rename');

$page->compile('..' . DIRECTORY_SEPARATOR . 'pfm.php');
?>
