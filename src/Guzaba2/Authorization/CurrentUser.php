<?php

declare(strict_types=1);

namespace Guzaba2\Authorization;

use Guzaba2\Authorization\Interfaces\UserInterface;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Coroutine\Coroutine;

/**
 * Class CurrentUser
 * The current User is stored in the Coroutine Context.
 * @package Guzaba2\Authorization
 */
class CurrentUser extends Base
//\Azonmedia\Patterns\Interfaces\WrapperInterface
{

    //private /* mixed */ $user_id;
    //private string $user_class;
    //private ?UserInterface $User = NULL;
    private UserInterface $User;

    private string $default_user_uuid;

    private string $default_user_class;

    //It may be reworked to accept $index and $class arguments and to create the instance only if needed
    //if only the $index is needed by the application then there is no need to create instance.
    public function __construct(UserInterface $User)
    {
        $this->User = $User;
        $this->default_user_uuid = $User->get_uuid();
        $this->default_user_class = get_class($User);
        //$this->user_id = $user_id;
        //$this->user_class = $user_class;
    }

    /**
     * Returns the default user UUID.
     * This UUID is set in the constructor of CurrentUser
     * @return string
     */
    public function get_default_user_uuid(): string
    {
        return $this->default_user_uuid;
    }

    public function get_default_user_class(): string
    {
        return $this->default_user_class;
    }

    protected function _before_destruct()
    {
        unset($this->User);
        //$this->User = NULL;//this will trigger a typed property error

        //parent::__destruct();
    }

//    /**
//     * No need to instantiate the user just to obtain the ID
//     * @return mixed
//     */
//    public function get_id()
//    {
//        return $this->user_id;
//    }

//    private function initialize_user() : void
//    {
//        if (!$this->User) {
//            $this->User = new $this->user_class($this->user_id);
//        }
//    }


    /**
     * Returns a readonly instance of the current user.
     * @return UserInterface
     */
    public function get(): UserInterface
    {
//        $this->initialize_user();
        return $this->User;
    }

    /**
     * Returns a new (writable) instance of the User.
     * Unlinke get() which returns a readonly instance.
     * @return UserInterface
     */
    public function get_writable_instance(): UserInterface
    {
        $user_class = get_class($this->User);
        return new $user_class($this->User->get_id());
    }

    public function set(UserInterface $User): void
    {
//        if (!is_a($User, $this->user_class, TRUE)) {
//            //throw new InvalidArgumentException();
//        }
//        $this->initialize_user();
        if ($User->user_is_disabled) {
            throw new RunTimeException(sprintf(t::_('The user %1$s can not be set as CurrentUser as it is disabled.'), $User->user_name));
        }
        $this->User = $User;
    }

    public function substitute(UserInterface $User)
    {
        //TODO add ScopeReference too
    }

    public function restore()
    {
    }

//    public function __get(string $property) /* mixed */
//    {
//        return $this->User->{$property};
//    }
//
//    public function __set(string $property, /* mixed */ $value) : void
//    {
//        $this->User->{$property} = $value;
//    }
//
//    public function __isset(string $property) : bool
//    {
//        return isset($this->User->{$property});
//    }
//
//    public function __unset(string $property) : void
//    {
//        unset($this->User->{$property});
//    }
//
//    public function __call(string $method, array $args) /* mixed */
//    {
//        return [$this->User, $method](...$args);
//    }
}
