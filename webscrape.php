#!/bin/php
<?php
/**
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *  @Copyright 2010
 *  @Author Markizano Draconus <markizano@markizano.net> http://markizano.net/
 */

define('PS', PATH_SEPARATOR);
define('DS', DIRECTORY_SEPARATOR);
define('DIR_ROOT', __DIR__.DS);
define('DIR_LIBRARY', DIR_ROOT.'library'.DS);
define('DIR_TMP', '/var/cache/php'.DS.'webz'.DS);

define('MYSQL_HOST', 'phpmyadmin');
define('MYSQL_USER', 'apache');
define('MYSQL_PASS', 'apache2mysql');

define('MAX_DEPTH', 2);

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

# The temporary PHP cache of the processing.
$tmp = DIR_TMP.'serialize';
# The pages we will obtain from amazon.com.
$pages = array();

$tidyConfig = array(
    'doctype' => '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">',
    'indent' => true,
    'tab-size' => 4,
    'output-encoding' => 'utf8',
    'newline' => 'LF',
    'wrap' => 120,
    'numeric-entities' => true,
    'output-xhtml' => true,
);

# Create a request object to obtain the category page.
$request    = new Zend_Http_Client('http://www.amazon.com/gp/site-directory/');
$body       = preg_replace('/[\r\n]+/', "\n", $request->request('GET')->getBody());

# Clean up the HTML because it's not compilant by default.
$tidy       = tidy_parse_string($body, $tidyConfig, 'utf8');
$clean      = $tidy->value;

# Create a handler for the HTML.
$xml        = new DomDocument(1.0, 'utf-8');
$xml->loadXML($clean);

# Check to make sure our caching directory exists.
file_exists(DIR_TMP) || mkdir(DIR_TMP, 0755);

# Instanitate my formatter and format the resulting XML 
$format = Kizano_Format::getInstance();
$format->setTld('http://www.amazon.com');
$format->setTidy($tidy);
$format->setXML($xml);
$format->setClient($request);

# Garbage collection
unset($tidy, $request);
if (file_exists($tmp)) {
    $result = unserialize(file_get_contents($tmp));
} else {
    $result = $format->Format($xml);
    file_put_contents($tmp, serialize($result));
}

if (isset($_GET['type'])) {
    switch($_GET['type']) {
        case 'sql':
            header('Content-Type: text/plain; charset=utf-8');
            $formatted = $format->sqlify($result);
            break;
        case 'html':
            $formatted = $format->htmlify($result);
            break;
        default:
            // break;
    }
    print $formatted;
} else {
    if (isset($_SERVER['argv'][1])) {
        switch($_SERVER['argv'][1]) {
        case 'sql':
            $formatted = $format->sqlify($result);
            file_put_contents('query.sql', $formatted);
            print "\n\n\nquery.sql was written :-)\n\n";
            break;
        case 'html':
            $formatted = $format->htmlify($result);
            file_put_contents('query.htm', $formatted);
            print "\n\n\nquery.htm was written :-)\n\n";
        }
    } else {
        var_dump($result);
    }
}

