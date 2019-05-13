<?php
require_once('minify/src/Minify.php');
require_once('minify/src/CSS.php');
require_once('minify/src/JS.php');
require_once('minify/src/Exception.php');
require_once('minify/src/Exceptions/BasicException.php');
require_once('minify/src/Exceptions/FileImportException.php');
require_once('minify/src/Exceptions/IOException.php');
require_once('path-converter/src/ConverterInterface.php');
require_once('path-converter/src/Converter.php');
use MatthiasMullie\Minify;
$min = new Minify\JS(file_get_contents('jquery-3.3.1.js'));
file_put_contents('jqmin.js', $min->minify());

$min2 = new Minify\JS('jquery-3.3.1.js');
$min2->minify('jqmin2.js');
?>