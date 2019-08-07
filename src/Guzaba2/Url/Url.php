<?php
/*
 * Guzaba Framework
 * http://framework.guzaba.org
 *
 * This source file is subject to the BSD license that is bundled with this
 * package in the file LICENSE.txt and available also at:
 * http://www.opensource.org/licenses/bsd-license.php
 */

/**
 * Description of url
 * @category    Guzaba Framework
 * @package        url
 * @subpackage    url
 * @copyright    Copyright (c) Guzaba Ltd - http://guzaba.com
 * @license        http://www.opensource.org/licenses/bsd-license.php BSD License
 * @author        Vesselin Kenashkov <vesko@webstudiobulgaria.com>
 * @todo refactor
 */

namespace Guzaba2\Url;

use Guzaba2\Base\Base;
use org\guzaba\framework;
use org\guzaba\framework\constants as c;
use Guzaba2\Kernel\Kernel as k;
use org\guzaba\framework\filesystem\classes\paths as p;
use Guzaba2\Translator\Translator as t;

/**
 * If both imclude_language and include_country are enabled the language will be first and then country.
 * The reason for this is that we may want to enable URL rewriting in a different language like domain.bg/bg/България/начало
 * To allow the country name to be in a different language
 *
 *
 * Allows the framework to be executed through proxy
 * For this a reverse proxy is setup
 * As per this task the followig code is added in 
 * On the live server where the proxy will reside server (there is Include directive in the usr/local/apache/conf/httpd.conf in the virtual host section to incldue this file (in fact all files on the directory))
 * This file enables the reverse proxy for SSL and also adds some additional headers that are needed by Athena to function properly when accessed through the proxy.
 *
 SSLProxyEngine on

<Location "/login/">
    #SSLProxyEngine on
    ProxyPass "https://thesite.com/"

    RewriteEngine On

    #RewriteRule .* - [E=PROXY_USER:%{LA-U:REQUEST_URI}] # note mod_rewrite's lookahead option - LA-U # /72/proxy:http://192.168.0.33:8089/PROJECTS/site
    RewriteRule .* - [E=FORWARDED_REQUEST_URI:%{REQUEST_URI}] # /72/site
    RequestHeader set X-forwarded-request-uri %{FORWARDED_REQUEST_URI}e

    RewriteRule .* - [E=FORWARDED_SERVER_PORT:%{SERVER_PORT}]
    RequestHeader set X-forwarded-server-port %{FORWARDED_SERVER_PORT}e

    RewriteRule .* - [E=FORWARDED_SERVER_NAME:%{SERVER_NAME}]
    RequestHeader set X-forwarded-server-name %{FORWARDED_SERVER_NAME}e

    # this is not available
    #RewriteRule .* - [E=FORWARDED_SCRIPT_NAME:%{SCRIPT_NAME}]
    #RequestHeader set X-forwarded-script-name %{FORWARDED_SCRIPT_NAME}e



    #RewriteRule .* - [E=FORWARDED_SERVER_PROTOCOL:%{SERVER_PROTOCOL}]
    #RequestHeader set X-forwarded-server-protocol %{FORWARDED_SERVER_PROTOCOL}e

    #RewriteRule .* - [E=FORWARDED_REQUEST_SCHEME:%{REQUEST_SCHEME}]
    #RequestHeader set X-forwarded-request-scheme %{FORWARDED_REQUEST_SCHEME}e

    RewriteCond %{HTTPS} =on
    RewriteRule .* - [E=FORWARDED_HTTPS:on]
    RequestHeader set X-forwarded-https %{FORWARDED_HTTPS}e



    # e at the end is needed because this is an environment variable

    </Location>

 */
class Url extends Base implements \Iterator, \ArrayAccess
{

    protected const DOMAIN = 'uscoachwaysonline.com';
    protected const DIR_PATH = '/PROJECTS/uscoachways_2/uscoachwaysonline-com/';
    protected const PORT = 8072;

    //this is used to split the names of the variables
    const REWRITING_SEPARATOR = '/';

    //the arrays will be always passed as arguments
    const ARGS_START = '?';

    const ARGS_SEPARATOR = '&';

    //flags
    const ABSOLUTE = 1;
    const DISABLE_REWRITING = 2;
    const DISABLE_CHECKS = 4;
    const VARS_ONLY = 8;
    const NO_PROCESS = 16;
    const FROM_RECORD = 32;
    const FROM_OBJECT = 64;
    const HTTPS = 128;
    const HTTP = 256;
    const WORKER = 512;//to go to the worker entry point

    //other constants
    const LONG_IP = 1;

    const AS_STRING = 1;

    /**
     * Represents the anchor identifier
     */
    const ANCHOR = '#';


    //protected $dir_path;

    protected $add_array = array();
    protected $flags = 0;

    protected $class_id;
    protected $record_array = array();
    protected $url_string;
    protected $object;

    protected static $env;
    protected static $url_rewriting;
    protected static $general_aliases_arr = array();

    public function __construct($arg=array(), $flags=0) {

        if (!self::$env) {
            self::$env = framework\mvc\classes\activeEnvironment::get_instance();
        }

        if (!self::$url_rewriting) {
            self::$url_rewriting = url_rewriting::get_instance();
        }

        
        $this->flags = $flags;
        if (is_string($arg)) {
            if ($this->flags&self::NO_PROCESS) {
                $this->url_string = $arg;
            } else {
                $this->add_array = self::set_default_params(self::parse($arg));
            }
        } elseif (is_array($arg)) {
            if ($this->flags&self::FROM_RECORD) {
                if (!isset($arg['class_id'])) {
                    throw new framework\base\exceptions\runTimeException(sprintf(t::_('When building URL from a database record class ID must be added to the array (index "class_id").')));
                }
                $this->class_id = $arg['class_id'];
                unset($arg['class_id']);
                $this->record_array = $arg;
            } else {
                if (count($arg)) {
                    $this->add_array = self::set_default_params($arg);
                } else {
                    //in the initialization process (session,vars) an instance of the object may be needed and it must not reference constants
                    $this->add_array = array();
                }
            }
        } elseif (is_object($arg)) {

            if ($arg instanceof url) {
                $this->add_array = self::set_default_params($arg->get_array());
            //} elseif ($arg instanceof cms\navigation\interfaces\navigation_capable) {
            } elseif ($arg::_implements('org\guzaba\cms\navigation\interfaces\navigation_capable')) {

                $view_operation = $arg->get_view_operation();
                /*
                $this->add_array[c\APP] = $view_operation['application'];
                $this->add_array[c\P] = $view_operation['package'];
                $this->add_array[c\C] = $view_operation['controller'];
                $this->add_array[c\A] = $view_operation['action'];

                if ($arg instanceof framework\orm\classes\activeRecord) {
                    $this->add_array[c\ID] = $arg->get_index();
                } //else - maybe a tableGateway - in this case nothing more is assigned
                */
                $this->flags |= self::FROM_OBJECT;
                $this->object = $arg;
            } else {
                throw new framework\base\exceptions\runTimeException(sprintf(t::_('An unsupported object of class %s was provided as argument to the constructor of the url.'),get_class($arg)));
            }
        } else {
            throw new framework\base\exceptions\runTimeException(sprintf(t::_('An unsupported type %s was provided as argument to the constructor of the url.'),gettype($arg)));
        }
    }

    public function get_array() {
        return $this->add_array;
    }

    public function get_string() {
        if ($this->flags&self::NO_PROCESS) {
            $ret = $this->url_string;
        } elseif ($this->flags&self::FROM_RECORD) {
            $ret = $this->form_from_record();
        } elseif ($this->flags&self::FROM_OBJECT) {
            $ret = $this->form_from_object($this->object);
        } else {
            $ret = self::u($this->add_array,$this->flags);
        }
        return $ret;
    }

    protected function form_from_object(\org\guzaba\cms\navigation\interfaces\navigation_capable $object) {
        $this->class_id = k::get_class_id($object::_class);
        //the rest of the fields (array for example) are not relevant here...
        $view_operation = $object->get_view_operation();
        $this->add_array[c\APP] = $view_operation['application'];
        $this->add_array[c\P] = $view_operation['package'];
        $this->add_array[c\C] = $view_operation['controller'];
        $this->add_array[c\A] = $view_operation['action'];
        $this->add_array[c\ID] = $object->get_index();

        $lang = self::$env->{c\L};
        $country = self::$env->{c\COUNTRY};

        if (!isset(self::$general_aliases_arr[$lang])) {
            self::$general_aliases_arr[$lang] = self::$url_rewriting->get_general_aliases($lang);
        }
        $general_aliases = self::$general_aliases_arr[$lang];

        $url_string = '';

        if (self::$url_rewriting->include_language) {
            $url_string .= $lang.self::REWRITING_SEPARATOR;
        }

        if (self::$url_rewriting->include_country) {
            $url_string .= $country.self::REWRITING_SEPARATOR;
        }


        if (!($this->flags&self::DISABLE_CHECKS)) {

            if (!($this->flags&self::DISABLE_REWRITING)) {


                $explicit_url_rewriting = $object->get_url_rewrite($lang);
                if ($explicit_url_rewriting) {
                    $url_string .= $explicit_url_rewriting;
                } else {

                    //look is there a general alias for this class
                    foreach ($general_aliases as $general_alias_record) {

                        if ($general_alias_record['class_id']==$this->class_id) {
                            $general_alias = $general_alias_record['alias'];
                            break;
                        }
                    }
                    if (isset($general_alias)) {
                        $url_string .= $general_alias.self::REWRITING_SEPARATOR.$object->get_index().self::REWRITING_SEPARATOR.$object->generate_url_rewrite($lang);

                    } else {

                        $url_string .=
                            ($this->{c\APP}!=self::$env->get_var_default_value(c\APP)?c\APP.self::REWRITING_SEPARATOR.$this->{c\APP}.self::REWRITING_SEPARATOR:'').
                            ($this->{c\P}!=self::$env->get_var_default_value(c\P)?c\P.self::REWRITING_SEPARATOR.$this->{c\P}.self::REWRITING_SEPARATOR:'').
                            ($this->{c\C}!=self::$env->get_var_default_value(c\C)?c\C.self::REWRITING_SEPARATOR.$this->{c\C}.self::REWRITING_SEPARATOR:'').
                            ($this->{c\A}!=self::$env->get_var_default_value(c\A)?c\A.self::REWRITING_SEPARATOR.$this->{c\A}.self::REWRITING_SEPARATOR:'').
                            c\ID.self::REWRITING_SEPARATOR.$this->{c\ID}.self::REWRITING_SEPARATOR;
                        $url_string .= 'rw'.self::REWRITING_SEPARATOR.$object->generate_url_rewrite($lang);
                    }
                }
            } else {

                $url_string .=
                    ($this->{c\APP}!=self::$env->get_var_default_value(c\APP)?c\APP.self::REWRITING_SEPARATOR.$this->{c\APP}.self::REWRITING_SEPARATOR:'').
                    ($this->{c\P}!=self::$env->get_var_default_value(c\P)?c\P.self::REWRITING_SEPARATOR.$this->{c\P}.self::REWRITING_SEPARATOR:'').
                    ($this->{c\C}!=self::$env->get_var_default_value(c\C)?c\C.self::REWRITING_SEPARATOR.$this->{c\C}.self::REWRITING_SEPARATOR:'').
                    ($this->{c\A}!=self::$env->get_var_default_value(c\A)?c\A.self::REWRITING_SEPARATOR.$this->{c\A}.self::REWRITING_SEPARATOR:'').
                    c\ID.self::REWRITING_SEPARATOR.$this->{c\ID};
            }

        } else {

            $url_string .=
                ($this->{c\APP}!=self::$env->get_var_default_value(c\APP)?c\APP.self::REWRITING_SEPARATOR.$this->{c\APP}.self::REWRITING_SEPARATOR:'').
                ($this->{c\P}!=self::$env->get_var_default_value(c\P)?c\P.self::REWRITING_SEPARATOR.$this->{c\P}.self::REWRITING_SEPARATOR:'').
                ($this->{c\C}!=self::$env->get_var_default_value(c\C)?c\C.self::REWRITING_SEPARATOR.$this->{c\C}.self::REWRITING_SEPARATOR:'').
                ($this->{c\A}!=self::$env->get_var_default_value(c\A)?c\A.self::REWRITING_SEPARATOR.$this->{c\A}.self::REWRITING_SEPARATOR:'').
                c\ID.self::REWRITING_SEPARATOR.$this->{c\ID};
        }
        //k::logtofile('dev9',$url_string);
        if (!($this->flags&self::VARS_ONLY)) {

            if ($this->flags&self::WORKER) {
                $url_string = 'worker_server.php'.self::REWRITING_SEPARATOR.$url_string;
            }

            $url_string = self::get_dir_path().$url_string;


            
            if ($this->flags&self::ABSOLUTE) {
                if ($this->flags&self::HTTPS) {
                    $url_string = self::get_base_url_excluding_path('https://').$url_string;
                    //$url_string = self::get_base_url_excluding_path('http://').$url_string;
                } elseif ($this->flags&self::HTTP) {
                    $url_string = self::get_base_url_excluding_path('http://').$url_string;
                } else {
                    $url_string = self::get_base_url_excluding_path().$url_string;
                }
            }
        }
        
        //k::logtofile('dev9',$url_string);
        //k::logtofile('dev3',$url_string);
        return $url_string;
    }

    /**
     * This does not check permissions and does not do any requests to the database.
     */
    protected function form_from_record() {
        //a new empty object is temporarily created with the provided data...
        //this way the object's own generate_url_rewrite method can be used
        $class_name = k::get_class_by_id($this->class_id);

        //$object = new $class_name(0);//no exceptions thrown here as this is a new object
        if ($class_name::_inherits(framework\orm\classes\activeRecordSingle::_class)) {
            $object =& $class_name::get_instance(0, $OBJECT);
        } elseif ($class_name::_inherits(framework\orm\classes\activeRecordVersioned::_class)) {
            $object =& $class_name::get_instance(0, 0, $OBJECT);
        } else {
            $object = new $class_name(0);
        }

        //set general fields
        foreach ($object->get_field_names() as $property) {
            if (isset($this->record_array[$property])) {
                $object->$property = $this->record_array[$property];
            }
        }

        if (!self::$env) {
            self::$env = framework\mvc\classes\activeEnvironment::get_instance();
        }
        $lang = self::$env->{c\L};
        //then language specific fields
        foreach ($object->get_languages_field_names() as $property) {
            if (isset($this->record_array[$property])) {
                $object->$lang->$property = $this->record_array[$property];
            }
        }
        $url_string = $this->form_from_object($object);

        return $url_string;
    }

    public function __toString() {
    
        //because the __toString method should not throw exceptions all the exceptions should be caught here and logged
        $string = '';
        try {
            $string = $this->get_string();
            if (!is_string($string)) {
                //throw new framework\base\exceptions\runTimeException(sprintf(t::_('The get_string() method of url did not return a string when called from __toString(). The return type is %s.'),gettype($string)));
                //__toString should not throw an error;
                $string = '#';
                k::logtofile('runTimeErrors',sprintf(t::_('The get_string() method of url did not return a string when called from __toString(). The return type is %s.'),gettype($string)));
            }
        } catch (\Throwable $exception) {
            k::logtofile('url_toString_exceptions',$exception->getFile().' '.$exception->getLine().' '.$exception->getMessage().PHP_EOL.PHP_EOL.$exception->getTraceAsString());
        }
        return $string;
    }

    public function &__invoke() {
        $args = func_get_args();
        return call_user_func_args(array(__CLASS__,'u'),$args);//a static call
    }

    //overloading
    public function __set(string $property,$value) : void
    {
        if (array_key_exists($property,$this->add_array)) {
            $this->add_array[$property] = $value;
        } else {
            parent::__set($property,$value);
        }
    }

    public function __get(string $property)
    {
        if (array_key_exists($property,$this->add_array)) {
            $ret = $this->add_array[$property];
        } else {
            //throw new GeneralException(sprintf('Trying to get unexisting criterion "%s" on an instance of "%s" (ORM).',$property,get_class($this)));
            $ret = parent::__get($property);
        }
        return $ret;
    }

    public function __isset(string $property) : bool
    {
        return isset($this->add_array[$property])?true:parent::__isset($property);
    }

    public function __unset(string $property) : void
    {
        if (array_key_exists($property,$this->add_array)) {
            unset($this->add_array[$property]);
        } else {
            parent::__unset($property);
        }
    }


    //implementation of \Iterator
    public final function rewind() {
        reset($this->add_array);
    }

    public final function current() {
        $var = current($this->add_array);
        return $var;
    }

    public final function key() {
        $var = key($this->add_array);
        return $var;
    }

    public final function next() {
        $var = next($this->add_array);
        return $var;
    }

    public final function valid() {
        $var = $this->current() !== false;
        return $var;
    }

    //implementation of \ArrayAccess
    //the implementation of ArrayAccess uses the overloading
    public function offsetExists($offset) {
        return isset($this->{$offset});//use the overloading
    }

    public function offsetGet($offset) {
        return $this->{$offset};
    }

    public function offsetSet($offset,$value) {
        $this->{$offset} = $value;
    }

    public function offsetUnset($offset) {
        unset($this->{$offset});
    }


    //static method below

    public static function get_request_uri($flags=0) {
        $ret = '';
        if ($flags&self::ABSOLUTE) {
            if ($flags&self::HTTPS) {
                $url_string = self::get_base_url_excluding_path('https://');
                //$url_string = self::get_base_url_excluding_path('http://');
            } elseif ($flags&self::HTTP) {
                $url_string = self::get_base_url_excluding_path('http://');
            } else {
                $url_string = self::get_base_url_excluding_path();
            }
        }
        //$ret .= isset($_SERVER['REQUEST_URI'])?$_SERVER['REQUEST_URI']:'';
        if (isset($_SERVER['HTTP_X_FORWARDED_REQUEST_URI'])) {
            $ret .= $_SERVER['HTTP_X_FORWARDED_REQUEST_URI']; 
        } elseif (isset($_SERVER['REQUEST_URI'])) {
            $ret .= $_SERVER['REQUEST_URI'];
        } else {
            //do not add anything
        }
        return $ret;
    }

    /**
     * Returns whether this call is thorugh a proxy.
     * @return bool
     *
     * @author vesko@azonmedia.com
     * @since 0.7.1
     * @created 15.05.2018
     */
    public static function is_through_proxy() : bool
    {

        return isset($_SERVER['HTTP_X_FORWARDED_FOR']);
    }

    protected static function get_script_name() : string
    {

        if (isset($_SERVER['SCRIPT_NAME'])) {
            //$ret = dirname($_SERVER['SCRIPT_NAME'])==p::DIR?p::DIR:dirname($_SERVER['SCRIPT_NAME']).self::REWRITING_SEPARATOR;
            if (dirname($_SERVER['SCRIPT_NAME'])==p::DIR) {
                $ret = p::DIR;
            } else {
                $ret = dirname(p::get_relative_path($_SERVER['SCRIPT_NAME']));
                if ($ret==p::CURDIR) {
                    $ret = '';
                } 
                $ret = $ret.self::REWRITING_SEPARATOR;
            }
        } else {
            //$ret = '';//dont return empty string - this will create issues - instead return /
            $ret = p::DIR;
        }
        //if (strpos($ret,'./')===0) { //we look at teh beginning
        if (strpos($ret,'./')===0) { //we look at teh beginning
            $ret = substr($ret,2);
        }
        if ($ret[-1] == '/') {
            $ret = substr($ret,0,-1);//remove trailing /
        }
        return $ret;
    }

    /**
     * Returns the relative path fro mthe DocumentRoot to the index.php entry point where the application is deployed
     * @return string The directory part of the URL
     */
    public static function get_dir_path() {
        
        // /home/release5/aktivnipotrebiteli_subdomain/cli.php
        // /index.php
        //must be able to get the requested dir path even if there is a 

        $script_name = self::get_script_name();
        
        if (framework\RUNLEVEL == 3 || framework\RUNLEVEL == 7) { //cli or test
            $ret = self::DIR_PATH;
        } elseif (self::is_through_proxy()) {

            //there is no HTTP_X_FORWARDED_SCRIPT_NAME as in fact there is no script accessed at the proxy
            //because of this the get_request_uri() must be used in conjunction with SCRIPT_NAME to obtain the path
            $request_uri = self::get_request_uri();
            //$script_name is contained inside $request_uri
            //so - the part in $request_uri that is before the $script_name is part of the redirect of the proxy
            //the part of $request_uri that are arguments and must be dropped
            //what needs to be returned is from the beginning of $request_uri until the position where $script_name ends inside the $request_uri
            if (!$script_name || $script_name == '/') { //this can happen on live where there is no subpath
                $part_before = '/login/';//this is a harcoded value of the path on proxy server that will be used to proxy the site
                $ret = $part_before;
            } elseif ( strpos($request_uri, $script_name) !== FALSE) { //in DEV env there is usually a path
                list ($part_before, $part_after) = explode($script_name, $request_uri);
                $ret = $part_before.$script_name;

            } else {
                //this is terribly wrong... but we still need to return something
                k::logtofile_simple('WRONG_FORWARDING', $request_uri.' '.$script_name.PHP_EOL.print_r($_SERVER, TRUE));//NOVERIFY
                $ret = $request_uri;
            }
        } else {
            $ret = $script_name;
        }
        

        //if ($ret[0]==p::CURDIR) {
        if (strlen($ret) && $ret[0]==p::CURDIR) {
            $ret = substr($ret,1);
        }
        if (strlen($ret) && $ret[-1] != '/') {
            $ret .= p::DIR;//append /
        }
        if (strlen($ret) && $ret[0] != '/') {
            $ret = '/'.$ret;
        }
        if (!strlen($ret)) {
            $ret = '/';
        }
        return $ret;
    }

    public static function get_subdomain() {
        if (isset($_SERVER['SERVER_NAME'])) {
            $domain = $_SERVER['SERVER_NAME'];
            $domain_arr = explode('.',$domain);
            if (isset($domain_arr[2])) {
                //$subdomain = strtolower(str_replace('-',' ',$domain_arr[0]));
                $subdomain = $domain_arr[0];
            } else {
                $subdomain = '';
            }
            $ret = $subdomain;
        } else {
            $ret = '';
        }
        return $subdomain;
    }

    public static function form_subdomain($subdomain) {
        return strtolower(str_replace(' ','-',$subdomain));
    }

    public static function get_domain() {
        //the domain can not be retreived by $_SERVER['SERVER_NAME'] because it is not know the TLD (some contain . like co.uk)
        //$url = url::get_instance();
        
        if (self::DOMAIN) {
            
            $ret = self::DOMAIN;
        } else {
            if (isset($_SERVER['SERVER_NAME'])) {
                $ret = $_SERVER['SERVER_NAME'];
            } else {
                $ret = get_current_user();
            }
        }
        return $ret;
    }
    
    /**
     * Returns the current domain as found in the server variables (including the forwarded one)
     *
     */
    public static function get_current_domain() {
        if (isset($_SERVER['HTTP_X_FORWARDED_SERVER_NAME'])) {
            $ret = $_SERVER['HTTP_X_FORWARDED_SERVER_NAME'];
        } elseif (isset($_SERVER['SERVER_NAME'])) {
            $ret = $_SERVER['SERVER_NAME'];
        } else {
            //$ret = get_current_user();
            $ret = self::DOMAIN;
        }

        if (!$ret) {
            $ret = 'localhost';
        }

        return $ret;
    }


    public static function get_main_domain() {
        $ret = self::DOMAIN;
        if (!$ret) {
            $ret = isset($_SERVER['SERVER_NAME'])?$_SERVER['SERVER_NAME']:'';
        }
        return $ret;
    }

    public static function get_base_url($protocol='') {
        //return self::u(array(),true,true);
        $ret = self::get_base_url_excluding_path($protocol).self::get_dir_path();

        return $ret;
    }

    public static function get_base_url_excluding_path($protocol='') {
        //$domain = isset($_SERVER['SERVER_NAME'])?$_SERVER['SERVER_NAME']:'';

        $domain = self::get_current_domain();
        
        $url_string = '';
        if (!$protocol) { //if no protocol supplied stay on the same as it was
            //$protocol_arr = explode('/',$_SERVER['SERVER_PROTOCOL']);
            //$protocol = strtolower($protocol_arr[0]).'://';
            if (isset($_SERVER['HTTP_X_FORWARDED_HTTPS']) && strtolower($_SERVER['HTTP_X_FORWARDED_HTTPS']) == 'on') {
                $protocol = 'https://';
            } elseif (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on') {
                $protocol = 'https://';
                //$protocol = 'http://';
            } else {
                $protocol = 'http://';
            }
        }
        
        $url_string .= $protocol;
        
        $url_string .= $domain;
        
        /*
        if ($protocol) {
            if ($protocol=='https://') {
                $port = '443';
                //$port = '80';
            } else {
                $port = '80';
            }
        //} else {
        //} elseif (isset($_SERVER['SERVER_PORT'])) {
        }
        */

        $port = self::get_port();
        if (!$port && $protocol) {
            if ($protocol=='https://') {
                $port = '443';
                //$port = '80';
            } elseif (self::PORT) {
                $port = self::PORT;
            } else {
                $port = '80';
            }
        }
        
        if ($port && $port!='80' && $port!='443') {
            $url_string .= ':'.$port;
        }

        return $url_string;
    }

    /**
     * Returns a constant - self::HTTP or self::HTTPS or 0 if unknown
     * @return int
     */
    public static function get_protocol($as_string=false) {
        if (!empty($_SERVER['HTTPS'])) {
            $ret = self::HTTPS;
            //$ret = self::HTTP;
        } else {
            $ret = self::HTTP;
        }
        if ($as_string) {
            if ($ret==self::HTTPS) {
                $ret = 'https';
                //$ret = 'http';
            } else {
                $ret = 'http';
            }
        }
        return $ret;
    }

    public static function get_port() {

        //$ret = isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : null;
        if (isset($_SERVER['HTTP_X_FORWARDED_SERVER_PORT'])) {
            $ret = $_SERVER['HTTP_X_FORWARDED_SERVER_PORT'];
        } elseif (isset($_SERVER['SERVER_PORT'])) {
            $ret = $_SERVER['SERVER_PORT'];
        } else {
            $ret = NULL;
        }
        
        return $ret;
    }




    public static function u(array $add_array,$flags=0) {
        if (!is_array($add_array)) {
            throw new framework\base\exceptions\runTimeException(sprintf(t::_('The first argument is not an array. The URL can not be composed.')));
        }
        if (!is_int($flags)) {
            throw new framework\base\exceptions\runTimeException(sprintf(t::_('The second argument is not int. It can contain flags.')));
        }

        $url_string = '';
        if (!self::$env) {
            self::$env = framework\mvc\classes\activeEnvironment::get_instance();
        }
        if (!self::$url_rewriting) {
            self::$url_rewriting = url_rewriting::get_instance();
        }

        $lang = self::$env->{c\L};
        $country = self::$env->{c\COUNTRY};

        $add_array[c\APP] = isset($add_array[c\APP])?$add_array[c\APP]:self::$env->get_var_default_value(c\APP);
        $add_array[c\P] = isset($add_array[c\P])?$add_array[c\P]:self::$env->get_var_default_value(c\P);
        $add_array[c\C] = isset($add_array[c\C])?$add_array[c\C]:self::$env->get_var_default_value(c\C);
        $add_array[c\A] = isset($add_array[c\A])?$add_array[c\A]:self::$env->get_var_default_value(c\A);
        $add_array[c\ID] = isset($add_array[c\ID])?$add_array[c\ID]:self::$env->get_var_default_value(c\ID);

        //if (isset($add_array['op_id'])&&empty($add_array[c\ID])) {
        //    $add_array[c\ID] = $add_array['op_id'];
        //}

        //if there is lang provided in the URL then the rewriting must be switched to this lang (if there is rewriting)
        $target_lang = '';
        if (isset($add_array[c\L])) {
            $target_lang = $add_array[c\L];
        } elseif (isset($add_array[c\IL])) {
            $target_lang = $add_array[c\IL];
        } else {
            $target_lang = $lang;
        }

        //if there is country provided in the argument then the country should be switched
        $target_country = '';
        if (isset($add_array[c\COUNTRY])) {
            $target_country = $add_array[c\COUNTRY];
        } else {
            $target_country = $country;//the target country remains the current one
        }
        if ($target_country && strlen($target_country)==2) {
            //we need to replace the country code with the full country name
            $target_country = \org\guzaba\cms\countries\models\countries::get_instance()->get_country_name_by_code($target_country, $target_lang);
        }
        $target_country = \org\guzaba\cms\navigation\models\links::form_url_rewrite($target_country);
        

        //must check the home link - if the provided link is the same like the home link the it should lead to / and ignoreall params
        //but the links are part of the cms, not the framework...
        //$sessionSubject = framework\session\classes\sessionSubject::get_instance();
        //$current_user =& \org\guzaba\cms\users\models\user::get_readonly_instance($sessionSubject->get_index(), $CURRENT_USER);//SPEED
        //$home_link = $current_user->get_home_link();
        //if ($home_link->get_array() == $add_array) {
        //    return self::REWRITING_SEPARATOR;
        //}


        if (!isset(self::$general_aliases_arr[$target_lang])) {
            self::$general_aliases_arr[$target_lang] = self::$url_rewriting->get_general_aliases($target_lang);
        }
        $general_aliases = self::$general_aliases_arr[$target_lang];


        //if it is not in the rewriting map it is not rewritable... so no need to check does it implement framework\url\interfaces\rewritable
        //if ((!($flags&self::DISABLE_CHECKS))&&isset($add_array[c\ID])) {
        //if more than the usual 5 arguments are set then no URL rewriting should be applied (pos could be set)
        //$max_params = isset($add_array[c\L])?6:5;

        /*
        if (isset($add_array[c\L])) {
            if (isset($add_array['op_id'])) {
                $max_params = 7;
            } else {
                $max_params = 6;
            }
        } else {
            if (isset($add_array['op_id'])) {
                $max_params = 6;
            } else {
                $max_params = 5;
            }
        }
        */
        $base_max_params = 5;
        $max_params = $base_max_params;
        if (isset($add_array[c\L])) {
            $max_params++;
        }
        if (isset($add_array[c\IL])) {
            $max_params++;
        }
        if (isset($add_array[c\COUNTRY])) {
            $max_params++;
        }
        if (isset($add_array['op_id'])) {
            $max_params++;
        }

        //the anchor handling must occur here as there are cases where self::build_args() is not used (when there is a direct alias found)
        $anchor = '';
        if (isset($add_array[self::ANCHOR])) {
            $anchor = $add_array[self::ANCHOR];
            unset($add_array[self::ANCHOR]);
        }
        //check for arrays
        $arrays = [];
        foreach ($add_array as $varname=>$varvalue) {
            if (is_array($varvalue)) {
                $arrays[$varname] = $varvalue;
                unset ($add_array[$varname]);
            }
        }

        //if ((!($flags&self::DISABLE_CHECKS))&&!empty($add_array[c\ID])&&count($add_array)<=$max_params) {
        if ((!($flags&self::DISABLE_CHECKS))&&count($add_array)<=$max_params) {



            //$explicit_url_rewriting_data = self::$url_rewriting->search_for_alias($add_array[c\APP],$add_array[c\P],$add_array[c\C],$add_array[c\A],$add_array[c\ID],$target_lang);
            $id = !empty($add_array['op_id'])?$add_array['op_id']:$add_array[c\ID];




            $explicit_url_rewriting_data = self::$url_rewriting->search_for_alias($add_array[c\APP],$add_array[c\P],$add_array[c\C],$add_array[c\A],$id,$target_lang);


            if (count($explicit_url_rewriting_data)) {
                $class = k::get_class_by_id($explicit_url_rewriting_data[0]['class_id']);

                if (!$id) {
                    $id = $explicit_url_rewriting_data[0]['object_id'];
                }

                //k::logtofile('class',$class);
                //if ($class=='org\\guzaba\\framework\\operations\\classes\\operation') {
                if ($class==framework\operations\classes\operation::_class) {
                    //$class = 'org\\guzaba\\cms\\operations\\models\\operation';
                    $class = \org\guzaba\cms\operations\models\operation::_class;
                }
                if ($class==\org\guzaba\cms\operations\models\operation::_class) {
                    //$add_array[c\ID] = $explicit_url_rewriting_data[0]['object_id'];//it may be not set so it has to be set here explicitely
                    $add_array['op_id'] = $explicit_url_rewriting_data[0]['object_id'];//it may be not set so it has to be set here explicitely
                }
                $class_found = true;

                //$explicit_url_rewriting = $explicit_url_rewriting_data[0]['alias'];//this is not used but can be used in order to save the object creation for the operations
                
            } else {


                foreach ($general_aliases as $general_alias) {

                    if ($general_alias['application']==$add_array[c\APP]&&$general_alias['package']==$add_array[c\P]&&$general_alias['controller']==$add_array[c\C]&&$general_alias['action']==$add_array[c\A]) {
                        $class = k::get_class_by_id($general_alias['class_id']);
                        //if ($class=='org\\guzaba\\framework\\operations\\classes\\operation') {
                        if ($class==framework\operations\classes\operation::_class) {
                            //$class = 'org\\guzaba\\cms\\operations\\models\\operation';
                            $class = \org\guzaba\cms\operations\models\operation::_class;
                        }
                        $class_found = true;
                    }
                }
            }

            

            //if (isset($class_found)) {
            if (isset($class_found)&&!empty($id)) {
                //

                try {
                    
                    //the object should be created in order to check does it exist or does it have permissions
                    //it is not created just to object the URL rewriting data



                    //$object = new $class($add_array[c\ID]);
                    //$object = new $class($id);
                    if ($class::_inherits(framework\orm\classes\activeRecordSingle::_class)) {
                        $object =& $class::get_instance($id, $OBJECT);
                    } elseif ($class::_inherits(framework\orm\classes\activeRecordVersioned::_class)) {
                        $object =& $class::get_instance($id, $class::VERSION_LAST, $OBJECT);
                    } else {
                        $object = new $class($id);
                    }


                    if (!($flags&self::DISABLE_REWRITING)) {

                        $rewrite_automatic = $object->generate_url_rewrite($target_lang);


                        if ($explicit_rewrite = $object->get_url_rewrite($target_lang)) { //first look for explicitly set rewriting
                            
                            if (isset($add_array[c\L])||self::$url_rewriting->include_language) {
                                //$url_string .= $add_array[c\L].self::REWRITING_SEPARATOR;
                                $url_string .= $target_lang.self::REWRITING_SEPARATOR;
                            } elseif (isset($add_array[c\IL])) {
                                //$url_string .= $add_array[c\IL].self::REWRITING_SEPARATOR;
                                $url_string .= $target_lang.self::REWRITING_SEPARATOR;
                            }

                            if (isset($add_array[c\COUNTRY]) || self::$url_rewriting->include_country) {
                                $url_string .= $target_country.self::REWRITING_SEPARATOR;
                            }

                            $url_string .= $explicit_rewrite;
                            
                        } else { //then for some generic rewriting for this type of object
                            
                            foreach ($general_aliases as $general_alias) {
                            
                                if ($general_alias['class_id']==k::get_class_id($class)) {

                                    if (isset($add_array[c\L])||self::$url_rewriting->include_language) {
                                        //$url_string .= $add_array[c\L].self::REWRITING_SEPARATOR;
                                        $url_string .= $target_lang.self::REWRITING_SEPARATOR;
                                    } elseif (isset($add_array[c\IL])) {
                                        //$url_string .= $add_array[c\IL].self::REWRITING_SEPARATOR;
                                        $url_string .= $target_lang.self::REWRITING_SEPARATOR;
                                    }

                                    if (isset($add_array[c\COUNTRY])||self::$url_rewriting->include_country) {
                                        $url_string .= $target_country.self::REWRITING_SEPARATOR;   
                                    }

                                    $url_string .= $general_alias['alias'].self::REWRITING_SEPARATOR;
                                    $url_string .= $add_array[c\ID].self::REWRITING_SEPARATOR;
                                    $url_string .= $rewrite_automatic;
                                    $url_string .= self::$url_rewriting->append_to_automatic_rewriting;

                                    $general_alias_found = true;
                                    break;
                                }
                            }

                            if (isset($general_alias_found)) {

                            } else {

                                $add_array = self::clear_default_params($add_array);
                                if (isset($rewrite_automatic)) {
                                    $add_array['rw'] = $rewrite_automatic;
                                }

                                //this type of object has no generic alias

                                if (self::$url_rewriting->include_language) {
                                    $add_array[c\L] = $target_lang;
                                }

                                if (self::$url_rewriting->include_country) {
                                    $add_array[c\COUNTRY] = $target_country;
                                }

                                //array_walk($add_array, function(&$value,&$key) { 
                                //    if (!$value || !strlen((string) $value)) {
                                //        $value = '$$';
                                //    } 
                                //} );
                                //$url_string .= str_replace('=',self::REWRITING_SEPARATOR,http_build_query($add_array,'',self::REWRITING_SEPARATOR));
                                //$url_string .= str_replace('=',self::REWRITING_SEPARATOR,urldecode(http_build_query($add_array,'',self::REWRITING_SEPARATOR)));
                                //$url_string .= self::REWRITING_SEPARATOR;
                                //$url_string .= self::$url_rewriting->append_to_automatic_rewriting;
                                $url_string .= self::build_args($add_array);
                            }

                        }
                    } else {

                        $add_array = self::clear_default_params($add_array);

                        if (self::$url_rewriting->include_language) {
                            $add_array[c\L] = $target_lang;
                        }

                        if (self::$url_rewriting->include_country) {
                            $add_array[c\COUNTRY] = $target_country;
                        }

                        //array_walk($add_array, function(&$value,&$key) { 
                        //    if (!$value || !strlen((string) $value)) {
                        //        $value = '$$';
                        //    }
                        //} );
                        //$url_string .= str_replace('=',self::REWRITING_SEPARATOR,http_build_query($add_array,'',self::REWRITING_SEPARATOR));
                        //$url_string .= str_replace('=',self::REWRITING_SEPARATOR,urldecode(http_build_query($add_array,'',self::REWRITING_SEPARATOR)));
                        //$url_string .= self::REWRITING_SEPARATOR;//it is very important for the full urls to have a closing / (otherwise there will eb a problem in certain situations... if there is no ending / then it will be treated as file and ingnored in the composed path (in an include for example) and will provide wrong variables - like in the tinymce plugins where js_vars is included). This is not anymore an issue as js_vars is not used anymore (not included)... even an operation does not exist
                        //$url_string .= self::$url_rewriting->append_to_automatic_rewriting;
                        $url_string .= self::build_args($add_array);
                    }
                } catch (framework\orm\exceptions\permissionDeniedException $exception) {
                    $url_string .= '#';
                } catch (framework\orm\exceptions\missingRecordException $exception) {
                    $url_string .= '#';
                }
            } else {

                $add_array = self::clear_default_params($add_array);

                if (self::$url_rewriting->include_language) {
                    $add_array[c\L] = $target_lang;
                }

                if (self::$url_rewriting->include_country) {
                    $add_array[c\COUNTRY] = $target_country;
                }

                //array_walk($add_array, function(&$value,&$key) { 
                //    if (!$value || !strlen((string) $value)) {
                //        $value='$$';
                //    }
                //} );
                //$url_string .= str_replace('=',self::REWRITING_SEPARATOR,http_build_query($add_array,'',self::REWRITING_SEPARATOR));
                //$url_string .= str_replace('=',self::REWRITING_SEPARATOR,urldecode(http_build_query($add_array,'',self::REWRITING_SEPARATOR)));
                //$url_string .= self::REWRITING_SEPARATOR;
                //$url_string .= self::$url_rewriting->append_to_automatic_rewriting;
                $url_string .= self::build_args($add_array);
            }
        } else {

            $add_array = self::clear_default_params($add_array);

            if (self::$url_rewriting->include_language) {
                $add_array[c\L] = $target_lang;
            }

            if (self::$url_rewriting->include_country) {
                $add_array[c\COUNTRY] = $target_country;
            }

            //array_walk($add_array,function(&$value,&$key){ if (!strlen($value)) {$value='$$';} });
            //$url_string .= str_replace('=',self::REWRITING_SEPARATOR,http_build_query($add_array,'',self::REWRITING_SEPARATOR));
            //$url_string .= str_replace('=',self::REWRITING_SEPARATOR,urldecode(http_build_query($add_array,'',self::REWRITING_SEPARATOR)));
            //$url_string .= self::REWRITING_SEPARATOR;
            //$url_string .= self::$url_rewriting->append_to_automatic_rewriting;
            $url_string .= self::build_args($add_array);
        }


        if (!($flags&self::VARS_ONLY)) {
            if ($flags&self::WORKER) {
                $url_string = 'worker_server.php'.self::REWRITING_SEPARATOR.$url_string;
            }
            $url_string = self::get_dir_path().$url_string;

            if ($flags&self::ABSOLUTE) {




                if ($flags&self::HTTPS) {
                    $url_string = self::get_base_url_excluding_path('https://').$url_string;
                    //$url_string = self::get_base_url_excluding_path('http://').$url_string;
                } elseif ($flags&self::HTTP) {
                    $url_string = self::get_base_url_excluding_path('http://').$url_string;
                } else {
                    $url_string = self::get_base_url_excluding_path().$url_string;
                }


                
            }
        }

        if ($arrays) {
            $url_string .= self::ARGS_START.http_build_query($arrays);
        }

        if ($anchor) {
            $url_string .= self::ANCHOR.$anchor;
        }

        return $url_string;
    }

    public static function set_default_params(array $add_array) {
        if (!self::$env) {
            self::$env = framework\mvc\classes\activeEnvironment::get_instance();
        }

        $add_array[c\APP] = isset($add_array[c\APP])?$add_array[c\APP]:self::$env->get_var_default_value(c\APP);
        $add_array[c\P] = isset($add_array[c\P])?$add_array[c\P]:self::$env->get_var_default_value(c\P);
        $add_array[c\C] = isset($add_array[c\C])?$add_array[c\C]:self::$env->get_var_default_value(c\C);
        $add_array[c\A] = isset($add_array[c\A])?$add_array[c\A]:self::$env->get_var_default_value(c\A);
        $add_array[c\ID] = isset($add_array[c\ID])?$add_array[c\ID]:self::$env->get_var_default_value(c\ID);

        return $add_array;
    }

    public static function clear_default_params(array $add_array) {

        if (!self::$env) {
            self::$env = framework\mvc\classes\activeEnvironment::get_instance();
        }

        static $static_cache = [];
        if (!count($static_cache)) {
            $static_cache[c\APP] = self::$env->get_var_default_value(c\APP);
            $static_cache[c\P] = self::$env->get_var_default_value(c\P);
            $static_cache[c\C] = self::$env->get_var_default_value(c\C);
            $static_cache[c\A] = self::$env->get_var_default_value(c\A);
            $static_cache[c\ID] = self::$env->get_var_default_value(c\ID);
        }

        /*
        if ($add_array[c\APP]==self::$env->get_var_default_value(c\APP)) {
            unset($add_array[c\APP]);
        }

        if ($add_array[c\P]==self::$env->get_var_default_value(c\P)) {
            unset($add_array[c\P]);
        }

        if ($add_array[c\C]==self::$env->get_var_default_value(c\C)) {
            unset($add_array[c\C]);
        }

        if ($add_array[c\A]==self::$env->get_var_default_value(c\A)) {
            unset($add_array[c\A]);
        }

        if ($add_array[c\ID]==self::$env->get_var_default_value(c\ID)) {
            unset($add_array[c\ID]);
        }
        */

        if ($add_array[c\APP]==$static_cache[c\APP]) {
            unset($add_array[c\APP]);
        }

        if ($add_array[c\P]==$static_cache[c\P]) {
            unset($add_array[c\P]);
        }

        if ($add_array[c\C]==$static_cache[c\C]) {
            unset($add_array[c\C]);
        }

        if ($add_array[c\A]==$static_cache[c\A]) {
            unset($add_array[c\A]);
        }

        if ($add_array[c\ID]==$static_cache[c\ID]) {
            unset($add_array[c\ID]);
        }

        return $add_array;
    }

    /**
     * Parses the provided URL string into array of pairs varname=>varvalue. If the string has an achnor it will the assigned to the pass-by-reference variable anchor
     * @param string $url_string
     * @param string $anchor
     * @return array
     */
    public static function parse($url_string,&$anchor=null) {
        $path = self::get_dir_path();


        $absolute = self::get_base_url_excluding_path();

        $index = p::$INDEXFILE.p::FILE.'html';
        if ($path!=self::REWRITING_SEPARATOR) {
            $url_string = str_replace(array($path),'',$url_string);
        }
        //$url_string = str_replace(array($path,$absolute,self::REWRITING_SEPARATOR.$index),array('','',''),$url_string);
        $url_string = str_replace(array($absolute,self::REWRITING_SEPARATOR.$index),array('',''),$url_string);
        if ($url_string&&$url_string{0}==self::REWRITING_SEPARATOR) {
            $url_string = substr($url_string,1);
        }
        //if there is an anchor it must be cut and provided in the pass-by-reference $anchor
        $anchor_pos = strrpos($url_string,'#');
        if ($anchor_pos!==false) {
            $anchor = substr($url_string,$anchor_pos);
            $url_string = substr($url_string,0,$anchor_pos);
        }
        $url_elements = explode(self::REWRITING_SEPARATOR,$url_string);
        $url_arr = array();
        for ($aa=0;$aa<count($url_elements);$aa=$aa+2) {
            if (isset($url_elements[$aa+1])) {
                $url_arr[$url_elements[$aa]] = $url_elements[$aa+1];
            }
        }
        return $url_arr;
    }

    /**
     * @param int $long 
     * @return string|int
     */
    public static function get_ip_address($long=false) {
        if (framework\session\classes\sessionSubject::is_instantiated()) { //we must avoid triggering the creation of sessionSubject here if it hasnt been instantiated so far
            $ip = (string) framework\session\classes\sessionSubject::get_instance()->remote_addr;//this is a property of subject, not sessionSubject
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = (string) $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = (string) $_SERVER['REMOTE_ADDR'];
        } else {
            $ip = '';
        }
        if ($long&&$ip) {
            $ip = ip2long($ip);
        }
        return $ip;
    }

    /**
     * @param int $long.
     * @return string|int
     */
     public static function get_ip ($long = false) {
         $real_client_ip = ''; // sanity
         $keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');

        foreach ($keys as $key) {
            if (array_key_exists($key, $_SERVER)) {
                $exp = explode(',', $_SERVER[$key]);
                foreach ($exp as $ip) {
                    $real_client_ip = trim($ip);
                    if (filter_var($real_client_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== FALSE)
                        return ($long) ? ip2long($real_client_ip) : $real_client_ip;
                }
            }
        }

        // if we are here return $_SERVER['REMOTE_ADDR']
        $real_client_ip = $_SERVER['REMOTE_ADDR'];

        return ($long) ? ip2long($real_client_ip) : $real_client_ip;
    }

    /**
     * Get the User Agent (the browser). If not defined returns empty string.
     * @return string
     */
    public static function get_user_agent() {
        return isset($_SERVER['HTTP_USER_AGENT'])?$_SERVER['HTTP_USER_AGENT']:'';
    }

    /**
     * Builds URL string based on the provided variables in $add_array
     * Used by @see self::u()
     * @param array $add_array Associative array with $varname=>$varvalue
     * @return string
     *
     * @author vesko@azonmedia.com
     * @since 0.7.1
     * @created 19.02.2018
     */
    private static function build_args(array $add_array) : string
    {
        $url_string = '';
        array_walk($add_array, function(&$value,&$key) { 
           if (!$value || !strlen((string) $value)) {
               $value = '$$';
           }
        } );
        //this will be handled in self::u()
        //look for an anchor (it may not be necessarily the last element - it doesnt matter where it is)
        //$anchor = '';
        //if (isset($add_array[self::ANCHOR])) {
        //    $anchor = $add_array[self::ANCHOR];
        //    unset($add_array[self::ANCHOR]);
        //}

        $url_string .= str_replace('=',self::REWRITING_SEPARATOR,urldecode(http_build_query($add_array,'',self::REWRITING_SEPARATOR)));
        $url_string .= self::REWRITING_SEPARATOR;//it is very important for the full urls to have a closing / (otherwise there will be a problem in certain situations... 

        //if ($anchor) {
        //    $url_string .= self::ANCHOR.$anchor;
        //}
        return $url_string;
    }
}

