<?php
declare(strict_types=1);
/*
 * Guzaba Framework
 * http://framework.guzaba.org
 *
 * This source file is subject to the BSD license that is bundled with this
 * package in the file LICENSE.txt and available also at:
 * http://www.opensource.org/licenses/bsd-license.php
 */

/**
 * Description of environment
 * @category    Guzaba Framework
 * @package        Model-View-Controller
 * @subpackage    Controller
 * @copyright    Copyright (c) Guzaba Ltd - http://guzaba.com
 * @license        http://www.opensource.org/licenses/bsd-license.php BSD License
 * @author        Vesselin Kenashkov <vesko@webstudiobulgaria.com>
 */

namespace Guzaba2\Mvc;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\RunTimeException;
use org\guzaba\framework;
use org\guzaba\framework\constants as c;
use Guzaba2\Translator\Translator as t;

/**
 * Environment variables - part of the controller's execution environment
 */
class Environment extends Base implements \ArrayAccess, \Iterator, \Countable
{

    /**
     * Version information
     * @var array
     */
    protected static $_version_data = [
        'revision' => '$Rev:: 41                                               $:',
        'author' => '$Author:: vesko                                         $:',
        'date' => '$Date:: 2009-09-03 20:29:53 +0300 (Thu, 03 Sep 2009)    $:',
    ];

    protected $init_vars;

    //who is calling this
    const LOCAL_API_CALL = 0;

    protected $api_call_type = self::LOCAL_API_CALL;

    /**
     *
     * @var array
     */
    protected $vars;

    public function __construct()
    {
        parent::__construct();
        $this->init_vars = framework\init\classes\vars::get_instance();
        $this->vars = $this->init_vars->get_vars();
        $this->api_call_type = $this->init_vars->get_api_call_type();
    }

    public function get_vars(): array
    {
        return $this->vars;
    }

    public function get_api_call_type()
    {
        return $this->api_call_type;
    }

    public function get_var_default_value($var_name)
    {
        return $this->init_vars->get_var_default_value($var_name);
    }

    /**
     * @param $var_name
     * @return mixed
     * @throws RunTimeException
     */
    public function get_var_init_value($var_name)
    {
        if (isset($this->init_vars->$var_name)) {
            return $this->init_vars->$var_name;
        } else {
            throw new RunTimeException(sprintf(t::_('A variable named "%s" is not set to be initialized in the configuration file of framework\init\classes\vars.'), $var_name));
        }
    }

    public function __call(string $method, array $args)
    {
        if (method_exists($this->init_vars, $method)) {
            $ret = call_user_func_array([$this->init_vars, $method], $args);
        } else {
            $ret = parent::__call($method, $args);
        }
        return $ret;
    }

    /**
     * @return array
     * @todo move this to a helper
     */
    public function _current_url()
    {
        $url_arr = [
            c\APP => $this->{c\APP},
            c\P => $this->{c\P},
            c\C => $this->{c\C},
            c\A => $this->{c\A},
            c\ID => $this->{c\ID},
        ];
        return $url_arr;
    }

    public function __get(string $var_name)
    {
        if (isset($this->vars[$var_name])) {
            $ret = $this->vars[$var_name];
        } else {
            $ret = parent::__get($var_name);
        }
        return $ret;
    }

    public function __set(string $var_name, $var_value): void
    {
        $this->vars[$var_name] = $var_value;
        /*
        if (isset($this->vars[$var_name])) {
            $ret = $this->vars[$var_name] = $var_value;
        } else {
            $ret = parent::__set($var_name,$var_value);
        }
        return $ret;
         */
    }

    public function __isset(string $var_name): bool
    {
        if (isset($this->vars[$var_name])) {
            $ret = true;
        } else {
            $ret = parent::__isset($var_name);
        }
        return $ret;
    }

    public function __unset(string $var_name): void
    {
        throw new RunTimeException(sprintf(t::_('Properties (vars) can not be unset from the environment class.')));
    }

    /*
    //not compatible with parent in PHP7
    public function __invoke($var_name) {
        return $this->$var_name;
    }
    */

    public function __toString()
    {
        $str = '';
        foreach ($this->vars as $name => $value) {
            if (is_array($name)) {
                $name = print_r($name, TRUE);//NOVERIFY
            }
            if (is_array($value)) {
                $value = print_r($value, TRUE);//NOVERIFY
            }
            $str .= $name . framework\url\classes\url::REWRITING_SEPARATOR . $value . framework\url\classes\url::REWRITING_SEPARATOR;
        }
        return $str;
    }

    /**
     * @implements \Iterator
     */
    public function current()
    {
        return current($this->vars);
    }

    public function key()
    {
        return key($this->vars);
    }

    public function next()
    {
        next($this->vars);
    }

    public function rewind()
    {
        reset($this->vars);
    }

    public function valid()
    {
        return isset($this->vars[$this->key()]);
    }

    public function offsetExists($offset)
    {
        return isset($this->vars[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->vars[$offset];
    }

    public function offsetSet($offset, $value)
    {
        $this->vars[$offset] = $value;
    }

    /**
     * @param mixed $offset
     * @throws RunTimeException
     */
    public function offsetUnset($offset)
    {
        throw new RunTimeException(sprintf(t::_('Properties (vars) can not be unset from the environment class.')));
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->vars);
    }
}
