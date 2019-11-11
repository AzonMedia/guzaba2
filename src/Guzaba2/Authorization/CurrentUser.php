<?php


namespace Guzaba2\Authorization;

use Guzaba2\Base\Base;

class CurrentUser extends Base implements \Azonmedia\Patterns\Interfaces\WrapperInterface
{

    protected User $User;

    public function __construct(User $User)
    {
        $this->User = $User;
    }

    public function get() : User
    {
        return $this->User;
    }

    public function set(User $User) : void
    {
        $this->User = $User;
    }

    public function substitute(User $User)
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