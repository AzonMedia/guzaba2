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
 * Description of activeEnvironment
 * @category    Guzaba Framework
 * @package        activeEnvironment
 * @subpackage    activeEnvironment
 * @copyright    Copyright (c) Guzaba Ltd - http://guzaba.com
 * @license        http://www.opensource.org/licenses/bsd-license.php BSD License
 * @author        Vesselin Kenashkov <vesko@webstudiobulgaria.com>
 */

namespace Guzaba2\Mvc;

use Guzaba2\Patterns\Interfaces\Wrapper;
use Guzaba2\Patterns\Singleton;

class ActiveEnvironment extends Singleton implements Wrapper
{
    protected $env;

    public function is_set() : bool
    {
        return $this->env instanceof Environment;
    }

    public function get() : ?Environment
    {
        return $this->env;
    }

    public function set(Environment $env)
    {
        $this->env = $env;
    }

    public function __get(string $property)
    {
        return $this->env->$property;
    }

    public function __set(string $property, $value) : void
    {
        $this->env->$property = $value;
    }

    public function __isset(string $property) : bool
    {
        return isset($this->env->$property);
    }

    public function __unset(string $property) : void
    {
        unset($this->env->$property);
    }

    public function __call(string $method, array $args)
    {
        return call_user_func_array([$this->env,$method], $args);
    }

    //public function __invoke() {
    public function &__invoke()
    { //PHP7
        //return $this->env();
        //return call_user_func_array($this->env,func_get_args());
        $ret = call_user_func_array($this->env, func_get_args());//PHP7
        return $ret;
    }

    public function __toString()
    {
        return (string) $this->env;
    }

    public static function get_instance(): \Guzaba2\Patterns\Interfaces\SingletonInterface
    {
        // TODO: Implement get_instance() method.
    }

    public static function get_instances(): array
    {
        // TODO: Implement get_instances() method.
    }

    public function destroy(): void
    {
        // TODO: Implement destroy() method.
    }
}
