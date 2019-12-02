<?php
declare(strict_types=1);

namespace Guzaba2\Authorization;

use Guzaba2\Authorization\Interfaces\UserInterface;
use Guzaba2\Base\Base;
use Guzaba2\Coroutine\Coroutine;

/**
 * Class CurrentUser
 * The current User is stored in the Coroutine Context.
 * @package Guzaba2\Authorization
 */
class CurrentUser extends Base implements \Azonmedia\Patterns\Interfaces\WrapperInterface, \Azonmedia\Di\Interfaces\CoroutineDependencyInterface
{

    private UserInterface $User;

    public function __construct(UserInterface $User)
    {
        $this->User = $User;
    }

    public function __destruct()
    {
//        print 'CURRENT USER DESTR'.PHP_EOL;
//        debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        unset($this->User);
        //$this->User = NULL;//this will trigger a typed property error
        parent::__destruct();
    }

    public function destroy() : void
    {
        unset($this->User);
    }

    public function get() : UserInterface
    {
        return $this->User;
        //$Context = Coroutine::getContext();
        //return $Context->{UserInterface::class};
    }

    public function set(UserInterface $User) : void
    {
        $this->User = $User;
        //$Context = Coroutine::getContext();
        //$Context->{UserInterface::class} = $User;
    }

    public function substitute(UserInterface $User)
    {
        //TODO add ScopeReference too
    }

    public function restore()
    {

    }

    public function __get(string $property) /* mixed */
    {
        return $this->User->{$property};
    }

    public function __set(string $property, /* mixed */ $value) : void
    {
        $this->User->{$property} = $value;
    }

    public function __isset(string $property) : bool
    {
        return isset($this->User->{$property});
    }

    public function __unset(string $property) : void
    {
        unset($this->User->{$property});
    }

    public function __call(string $method, array $args) /* mixed */
    {
        return [$this->User, $method](...$args);
    }
}