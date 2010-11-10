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


/**
 *  Formats various pages to strip unwanted HTML and return an associated array of the categories.
 */
class Kizano_Format
{

    protected static $_instance;

    /**
     *  The tidy xml cleanup object.
     *  @type Tidy
     */
    protected $_tidy;

    /**
     *  The DomDocument xml manipulation object
     *  @type DomDocument
     */
    protected $_xml;

    /**
     *  The HTTP Request client.
     *  @type Zend_Http_Client
     */
    protected $_client;

    /**
     *  The top-level domain to work.
     *  @type string
     */
    protected $_tld;

    /**
     *  MySQL link resource.
     *  @type resource
     */
    protected $_link;

    /**
     *  Helps construct a new instance of this class by opening a DB connection so we can access
     *      mysql_real_escape_string without issues.
     *  @return void
     */
    public function __construct()
    {
        foreach (array('MYSQL_HOST', 'MYSQL_USER', 'MYSQL_PASS') as $mysql) {
            if (!defined($mysql)) {
                throw new Kizano_Exception(sprintf(
                    '%s::%s(): `%s\' not defined. Must define MySQL configuration before intancing this class.',
                    __CLASS__,
                    __FUNCTION__,
                    $mysql
                ));
            }
        }
        # Open a MySQL connection so we can access mysql_real_escape_string() without issues.
        $this->_link = mysql_connect(MYSQL_HOST, MYSQL_USER, MYSQL_PASS);
        if (!$this->_link || $e = mysql_error($this->_link)) {
            mysql_close($this->_link);
            throw new Kizano_Exception(sprintf(
                '%s::%s(): Error opening the mysql connection! Please check your credentials. MySQL Error: %s',
                __CLASS__,
                __FUNCTION__,
                isset($e)? $e: null
            ));
        }
    }

    /**
     *  Kills the connection to the DB before this object is destroyed.
     *  @return void
     */
    public function __destruct()
    {
        mysql_close($this->_link);
    }

    /**
     *  Implements the singleton design pattern.
     *  @return Kizano_Format
     */
    public static function getInstance()
    {
        if (empty(self::$_instance)) {
            self::$_instance = new self;
        }
        return self::$_instance;
    }

    /**
     *  Reassigns this intance of a tidy class.
     *  @param tidy     Tidy        The tidy object to assign.
     *  @return void
     */
    public function setTidy(Tidy $tidy)
    {
        $this->_tidy = $tidy;
    }

    /**
     *  Gets this tidy class instance.
     *  @return Tidy
     */
    public function getTidy()
    {
        return $this->_tidy;
    }

    /**
     *  Reassigns this intance of a xml class.
     *  @param xml     xml        The xml object to assign.
     *  @return void
     */
    public function setxml(DomDocument $xml)
    {
        $this->_xml = $xml;
    }

    /**
     *  Gets this xml class instance.
     *  @return xml
     */
    public function getxml()
    {
        return $this->_xml;
    }

    /**
     *  Reassigns this intance of a Zend_Http_Client class.
     *  @param client     Zend_Http_Client        The HTTP client object to assign.
     *  @return void
     */
    public function setClient(Zend_Http_Client $client)
    {
        $this->_client = $client;
    }

    /**
     *  Gets this Zend_Http_Client class instance.
     *  @return Zend_Http_Client
     */
    public function getClient()
    {
        return $this->_client;
    }

    public function getTld()
    {
        return $this->_tld;
    }

    public function setTld($tld)
    {
        if (!is_string($tld)) {
            throw new Kizano_Exception(sprintf(
                '%s::%s() Expected string($tld); Received(%s)',
                __CLASS__,
                __FUNCTION__,
                get_type($tld)
            ));
        }
        $this->_tld = $tld;
    }

    /**
     *  Renders an HTML printout of an array.
     *  @param data     Array       The data to render.
     *  @param depth    Integer     The number of levels deep this rendering is.
     *  @return string
     */
    public function htmlify(array $data, $depth = 1)
    {
        $space = str_repeat(chr(32), 4 * $depth);
        $result = "$space<ul>\n";
        foreach ($data as $key => $datum) {
            if (is_array($datum)) {
                $result .= "$space    <li>\n$space        $key\n";
                $result .= $this->htmlify($datum, $depth + 2);
            } else {
                $result .= "$space    <li>\n";
                $result .= "$space        $datum\n";
            }
            $result .= "$space    </li>\n";
        }
        $result .= "$space</ul>\n";
        return $result;
    }

    /**
     *  Renders a suitable query for inserting into MySQL.
     *  @param data         Array       The data to render.
     *  @param parent_id    Integer     The ID of the parent category.
     *  @param parent_key   String      A name to use as the parent key.
     *  @return array
     */
    public function sqlify(array $data, $parent_id = 1, $parent_key = null)
    {
        $result = null;
        static $category_id;
        if (!$category_id) $category_id = 1;
        if ($category_id === 1 && !$parent_key) {
            $result .= <<<EO_QUERY
CREATE TABLE IF NOT EXISTS `category`(
  `category_id` INT(9) UNSIGNED AUTO_INCREMENT,
  `parent_id` INT(9) UNSIGNED DEFAULT 0 NOT NULL,
  `category_name` VARCHAR(32) DEFAULT '' NOT NULL,
  `desc` VARCHAR(64) DEFAULT '',
  `count` TINYINT(5) UNSIGNED DEFAULT 0 NOT NULL,
  `slug` VARCHAR(128) DEFAULT '' NOT NULL,
  PRIMARY KEY (`category_id`),
  UNIQUE (`slug`)
) ENGINE=INNODB DEFAULT CHARACTER SET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=0;\n\n
EO_QUERY;
        }
        foreach ($data as $key => $datum) {
            # If the data key has children,
            if (is_array($datum)) {
                # Then add this data key to the sql mapping query.
                $name = mysql_real_escape_string($key, $this->_link);
                $slug = mysql_real_escape_string($this->_sluggify("$parent_key|$key"), $this->_link);
                $result .= "INSERT INTO `category` SET ".
                    "`category_id` = '$category_id', ".
                    "`parent_id` = '$parent_id', ".
                    "`category_name` = '$name', ".
                    "`slug` = '$slug'".
                ";\n";
                unset($name);
                $category_id++;
                $result .= $this->sqlify($datum, $category_id -1, $slug);
                unset($slug);
            } else {
                # Else if the data is a leaf, then add it to the data map query.
                $name = mysql_real_escape_string($datum, $this->_link);
                $slug = mysql_real_escape_string($this->_sluggify("$parent_key|$datum"), $this->_link);
                $result .= "INSERT INTO `category` SET ".
                    "`category_id` = '$category_id', ".
                    "`parent_id` = '$parent_id', ".
                    "`category_name` = '$name', ".
                    "`slug` = '$slug'".
                ";\n";
                unset($name, $slug);
                $category_id++;
            }
        }
        return $result;
    }

    /**
     *  Creates a suitable string to use as a slug.
     *  @param slug     The string to sluggify.
     *  @return string
     */
    protected function _sluggify($slug)
    {
        # First convert all spaces to underscores,
        # Next convert all non-alphanumeric characters into dashes,
        # Finally, return the result.
        return preg_replace('/[^|0-9A-Za-z_\-]+/i', '-', str_replace(chr(32), '_', $slug));
    }

    /**
     *  Formats a given page.
     *  @param content  String      The content of the page to format.
     *  @return array
     */
    public function Format(DomDocument $xml)
    {
        $result = array();
        $divs = $xml->getElementsByTagName('div');
        foreach ($divs as $div) { # Global div content.
            # Believe me, I tried the getElementById() <- it didn't work :(
            if ($div->getAttribute('id') == 'siteDirectory') { # <div id='siteDirectory'>
                # Get the table's columns.
                $tr = $div->getElementsByTagName('table')->item(0)->getElementsByTagName('tr')->item(0);
                foreach ($tr->getElementsByTagName('td') as $td) {                            # Iterate thru the rows
                    # Retrieve a single column of categories
                    foreach ($td->getElementsByTagName('div') as $i => $grouping) {                 # popover-grouping
                        foreach ($grouping->getElementsByTagName('div') as $_i => $category) {
                            if ($grouping->getAttribute('class') == 'popover-grouping') {
                                $category = $grouping->getElementsByTagName('div')->item(0)->nodeValue;
                                $categoryName = Kizano_String::strip_whitespace($category);
                            }
                            $anchors = $grouping->getElementsByTagName('a');
                            foreach ($anchors as $a) {
                                $subcategory = Kizano_String::strip_whitespace($a->nodeValue);
                                $result[$categoryName][$subcategory] = $a->getAttribute('href');
                            }
                            unset($subcategory, $category, $categoryName);
                        }
                    }
                }
            }
        }
        unset($divs, $tr);
        # Pass the resulting array on to the next part of the scraping process.
        $result = $this->_nextLevel($result, 0);
        return $result;
    }

    /**
     *  Next step in the webscraping process. Extracts the 2nd level links and gets their categories.
     *  @param headings     Array   The categories from $this->Format();
     *  @param level        Integer The depth we have sunk into this beast.
     *  @return array
     *  @Example:
     *      array
     *        'Books' => 
     *          array
     *            'Books' => string '/books-used-books-textbooks/b/ref=sd_allcat_bo/192-7717356-4636554?ie=UTF8&node=283155' (length=86)
     *            'Kindle eBooks' => string '/Kindle-eBooks/b/ref=sd_allcat_kbo/192-7717356-4636554?ie=UTF8&node=1286228011' (length=78)
     *            'Textbooks' => string '/New-Used-Textbooks-Books/b/ref=sd_allcat_tb/192-7717356-4636554?ie=UTF8&node=465600' (length=84)
     *            'Audiobooks' => string '/Audiobooks-Books/b/ref=sd_allcat_ab/192-7717356-4636554?ie=UTF8&node=368395011' (length=79)
     *            'Magazines' => string '/magazines/b/ref=sd_allcat_magazines/192-7717356-4636554?ie=UTF8&node=599858' (length=76)
     */
    protected function _nextLevel(array $headings, $level = 0)
    {
        $result = array();
#        if ($level >= MAX_DEPTH) return false;
        if (is_array($headings) && count($headings)) {
            foreach ($headings as $hKey => $heading) {
                $result[$hKey] = array();
                if (is_array($heading) && count($heading)) {
                    foreach ($heading as $cKey => $href) {
                        $result[$hKey][$cKey] = array();
                        $uri = $this->_url($this->getTld().$href);
                        if (!$uri){
                            continue;
                        }
                        print "$uri\n";
                        $page = $this->_web_get_contents($uri);
                        $this->_tidy->parseString($page);
	                    unset($page, $uri);
                        @$this->_xml->loadXML($this->_tidy->value);
                        $divs = $this->_xml->getElementsByTagName('div');
                        $list = $this->_getList($divs);
                        unset($divs);
                        if(!is_array($list) || empty($list)){
                            if(isset($result[$hKey][$cKey])) unset($result[$hKey][$cKey]);
                            $result[$hKey][] = $cKey;
                            unset($list, $href);
                            continue;
                        }
/*
                        set_error_handler(function(){
                            $args = func_get_args();
                            var_dump($args);die;
                        }, E_WARNING);
*/
                        if ($level >= MAX_DEPTH) {
                            die('over the limit!');
                            if (is_array($list)) {
                                $listKeys = array_keys($list);
                                $listVals = array_values($list);
                                $current = Current($listVals);
                                if (is_string($current)) {
                                    if ($current{0} != '/') {
                                        $result[$hKey][$cKey] = $listKeys;
                                    }
                                } else {
                                    $listValues = array_map('array_keys', $listVals);
                                }
                            }
                            if (isset($listValues)) {
                                $result[$hKey][$cKey] = array_combine($listKeys, $listValues);
                            } else {
                                $current = Current($listKeys);
                                if (is_string($current) && $current{0} != '/') {
                                    $result[$hKey][$cKey] = $listKeys;
                                }
                            }
#                            var_dump($result);die;
                            unset($list);
                            break 2;
                        } else {
                            $result[$hKey][$cKey] = $this->_nextLevel($list, $level+1);
                        }
#                        print_r(array(
#                            "\033[31mnextLevel\033[00m" => $nextLevel,
#                            "\033[31mlist\033[00m" => $list,
#                            "\033[31mresult\033[00m" => $result,
#                            "\033[31mlevel\033[00m" => $level
#                        ));
                        unset($list);
                    }
                }
            }
        }
        return array_unique($result);
    }

    /**
     *  Gets a list of the categories and such from the list of <div>s
     *  @param divs     DomNodeList     The list of <div>s to search
     *  @return array
     */
    protected function _getList(DomNodeList $divs)
    {
        $result = array();
        # Believe me, I tried the getElementById() <- it didn't work :(
        foreach ($divs as $div) {
            if ($div->getAttribute('id') == 'leftcol') {
                $left_nav = $div->getElementsByTagName('div')->item(0);
                # Not all pages have the same left navigation.
                if ($left_nav->getAttribute('class') != 'left_nav') continue;

                $h3s = $left_nav->getElementsByTagName('h3');
                $uls = $left_nav->getElementsByTagName('ul');
                /**
                 *  Iterate over each of the unordered lists instead of the headings, because
                 *  not all pages have titled categories.
                 */
                foreach ($uls as $i => $ul) {
                    # Assign the category title if applicable.
                    $h3 = $h3s->item($i);
                    if(!empty($h3)) $catName = trim($h3->nodeValue);
                    $lis = $ul->getElementsByTagName('li');
                    # For each of the items in the list.
                    foreach ($lis as $li) {
                        # Assign the leaf's value.
                        $a = $li->getElementsByTagName('a')->item(0);
                        if(empty($a) || !($a instanceof DomNode)) {
                            unset($a, $li);
                            continue;
                        }
                        $leaf = trim($li->getElementsByTagName('a')->item(0)->nodeValue);
                        $href = $li->getElementsByTagName('a')->item(0)->getAttribute('href');
                        if (empty($catName)) {
                            if (in_array($leaf, $result)) continue;
                            $result[$leaf] = $href;
                        } else {
                            if (isset($result[$catName]) && in_array($leaf, $result[$catName])) {
                                unset($leaf, $href, $a);
                                continue;
                            }
                            $result[$catName][$leaf] = $href;
                        }
                        unset($leaf, $href, $a);
                    }
                    unset($catname, $lis);
                }
                unset($h3s, $uls, $left_nav);
            }
        }
        return $result;
    }

    /**
     *  Formats a URL so it's unique, but consistent, maintains its original value and is valid.
     *  @param url      string      The URL to format.
     *  @return mixed               The formatted URL on success, FALSE on error.
     */
    protected function _url($url)
    {
        $url = trim($url);
        if(empty($url)) return false;
        # The Url has some unique token near the end of it and in the query here,
        # we need to strip it for caching purposes.
        $parsed = parse_url($url);
        # Amazon sometimes returns this weird host that isn't parsable, so we need to skip it.
        if ($parsed['host'] == 'www.amazon.comhttps') return false;
        if ($parsed['scheme'] == 'https') return false;
        $path = explode('/', $parsed['path']);
        if (isset($parsed['query'])) {
            $query = explode('&', $parsed['query']);
            foreach ($query as $k => $q) {
                if(preg_match('/^(node|ie|pf_rd_i).*/i', $q)) continue;
                unset($query[$k]);
            }
            $parsed['query'] = '?' . join('&', $query);
        }
        # Pop the unique token from the end of the product path.
        array_pop($path);
        $parsed['path'] = join('/', $path);
        $parsed['scheme'] .= '://';
        $result = join(null, $parsed);
        return $result;
    }

    /**
     *  Gets the contents of a web page.
     *  @param url      The web page to obtain
     *  @return mixed   string on success containing the web page. False on error.
     */
    protected function _web_get_contents($uri)
    {
        if (empty($this->_client)) return false;
        $filename = hash('sha256', $uri);
        if (!file_exists(DIR_TMP."$filename")) {
print "\033[36mNew URL!\033[00m\n"; # DEBUG
            try{
                $this->_client->setUri($uri);
            }catch(Exception $e){
                var_dump($e, $uri);
                die;
            }
            $result = $this->_client->request('GET')->getBody();
            file_put_contents(DIR_TMP."$filename", $result);
            return $result;
        } else {
            return file_get_contents(DIR_TMP."$filename");
        }
    }
}

