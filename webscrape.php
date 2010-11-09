<?php

define('PS', PATH_SEPARATOR);
define('DS', DIRECTORY_SEPARATOR);
define('DIR_ROOT', __DIR__.DS);
define('DIR_LIBRARY', DIR_ROOT.'library'.DS);
define('MYSQL_HOST', 'phpmyadmin');
define('MYSQL_USER', 'apache');
define('MYSQL_PASS', 'apache2mysql');

ini_set('include_path',
    join(PATH_SEPARATOR, array(
        get_include_path(),
        DIR_ROOT,
        DIR_LIBRARY,
    ))
);

# Take advantage of the Zend Autoloader
require 'Zend/Loader/Autoloader.php';
$autoloader = Zend_Loader_Autoloader::getInstance();
$autoloader->setFallbackAutoloader(true);

# The pages we will obtain from amazon.com.
$pages = array();

$tidyConfig = array(
#    'doctype' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">',
    'doctype' => '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">',
    'indent' => true,
    'tab-size' => 4,
    'output-encoding' => 'utf8',
    'newline' => 'LF',
    'wrap' => 120,
    'numeric-entities' => true,
#    'input-xml' => true,
    'output-xhtml' => true,
#    'markup' => false,
);

# Create a request object to obtain the category page.
$request = new Zend_Http_Client('http://www.amazon.com/gp/site-directory/');
$body = preg_replace('/[\r\n]+/', "\n", $request->request('GET')->getBody());

# Clean up the HTML because it's not compilant by default.
$tidy = tidy_parse_string($body, $tidyConfig, 'utf8');
$clean = $tidy->value;

# Create a handler for the HTML.
$xml = new DomDocument(1.0, 'utf-8');
$xml->loadXML($clean);

# Check to make sure our caching directory exists.
file_exists('/tmp/webz') || mkdir('/tmp/webz', 0755);

# Instanitate my formatter and format the resulting XML 
$format = Kizano_Format::getInstance();
$format->setTld('http://www.amazon.com');
$format->setTidy($tidy);
$format->setXML($xml);
$format->setClient($request);
if(file_exists('/tmp/webz/serialize')){
    $result = unserialize(file_get_contents('/tmp/webz/serialize'));
}else{
    error_reporting(0);
    $result = $format->Format($xml);
    error_reporting(E_ALL | E_STRICT);
    file_put_contents('/tmp/webz/serialize', serialize($result));
}

#var_dump($result);die;
header('Content-Type: text/plain; charset=utf-8');
$formatted = $format->sqlify($result);
print $formatted;

