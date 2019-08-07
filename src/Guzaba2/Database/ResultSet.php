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
 * @category    Guzaba Framework
 * @package        Object-Relational Mappings
 * @subpackage    Object-Relational Mappings
 * @copyright    Copyright (c) Guzaba Ltd - http://guzaba.com
 * @license        http://www.opensource.org/licenses/bsd-license.php BSD License
 * @author        Vesselin Kenashkov <vesko@webstudiobulgaria.com>
 */


namespace org\guzaba\framework\database\classes;

use org\guzaba\framework;
use org\guzaba\framework\constants as c;
use org\guzaba\framework\kernel\classes\kernel as k;
use org\guzaba\framework\filesystem\classes\paths as p;
use org\guzaba\framework\translator\classes\translator as t;

/**
 * An object that wraps around the array returned from a query.
 */
class resultSet extends resultSet_config implements \Iterator, \ArrayAccess, \Countable
{

    /**
     * Version information
     * @var array
     */
    protected static $_version_data = array(
        'revision'=> '$Rev:: 282                                              $:',
        'author'=>   '$Author:: vesko                                         $:',
        'date'=>     '$Date:: 2010-01-10 16:29:39 +0200 (Sun, 10 Jan 2010)    $:',
    );
    
    protected $data = array();//two dimensional array. The individual records are single-dimensional arrays
    //public $length;//overloading may be used to make this read only
    protected $data_length;
    
    public function __construct(array $data) {
        foreach ($data as $record) {
            $this->data[] = new record($record);
        }
        //$this->data = $data;
        $this->data_length = count($data);
    }
    
    public function __get($property) {
        if ($property=='length') {
            $ret = $this->data_length;
        } else {
            $ret = parent::__get($property);
        }
        return $ret;
    }
    //no need to overload the rest - length is a readonly property

    //@implements \ArrayAccess
    public function offsetExists($offset) {
        return isset($this->data[$offset]);
    }

    //@implements \ArrayAccess
    public function offsetGet($offset) {
        return $this->data[$offset];
    }

    //@implements \ArrayAccess
    public function offsetSet($offset,$value) {
        //throw new framework\base\exceptions\runTimeException(sprintf(t::_('No records can be set from a resultSet.')));
        $this->data[$offset] = $value;
    }

    //@implements \ArrayAccess
    public function offsetUnset($offset) {
        //throw new framework\base\exceptions\runTimeException(sprintf(t::_('No records can be unset from a resultSet.')));
        unset($this->data[$offset]);
    }
    
    /**
     * Returns a set of guzaba objects for each record (using objectSet)
     * But converting the result set to objects will loose any fields that are not match any of the properties of the object
     */
    public function asObjects() {
        return new objectSet($this);
    }
    
    //@implements \Iterator
    public function rewind() {
        reset($this->data);
    }

    //@implements \Iterator
    public function current() {
        $var = current($this->data);
        return $var;
    }

    //@implements \Iterator
    public function key() {
        $var = key($this->data);
        return $var;
    }

    //@implements \Iterator
    public function next() {
        $var = next($this->data);
        return $var;
    }

    //@implements \Iterator
    public function valid() {
        $var = $this->current() !== false;
        return $var;
    }
    
    //@implements \Countable
    public function count() {
        return $this->data_length;
    }
}