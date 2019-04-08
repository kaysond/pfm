<?php
require_once 'ajax_page.class.php';
$page = new ajax_page(true);
$page->set_header('pfm.header.php');
$page->include('pfm.inc.php');
$page->include('dir.class.php', 'dir\dir');
$page->add_html('pfm.html');
$page->add_js('https://code.jquery.com/jquery-3.3.1.min.js');
$page->add_js('pfm.js');
$page->add_css('normalize.css');
$page->add_css('skeleton.css');
$page->add_css('pfm.css');
$page->add_css('https://fonts.googleapis.com/css?family=Roboto:400,500,700,900');
$page->add_callbacks_from_file('pfm.callbacks.php');
$page->rename_callback('copy_pfm', 'copy');
$page->rename_callback('rename_pfm', 'rename');

$page->compile('..' . DIRECTORY_SEPARATOR . 'pfm.php');
?>