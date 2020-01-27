<?php
declare(strict_types=1);

namespace Guzaba2\Authorization;

use Guzaba2\Authorization\Interfaces\UserInterface;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
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

    //It may be reworked to accept $index and $class arguments and to create the instance only if needed
    //if only the $index is needed by the application then there is no need to create instance.
    public function __construct(UserInterface $User)
    //public function __construct( /* mixed */ $user_id, string $user_class)
    {
        $this->User = $User;
        $this->default_user_uuid = $User->get_uuid();
        //$this->user_id = $user_id;
        //$this->user_class = $user_class;
    }

    /**
     * Returns the default user UUID.
     * This UUID is set in the constructor of CurrentUser
     * @return string
     */
    public function get_default_user_uuid() : string
    {
        return $this->default_user_uuid;
    }

    public function __destruct()
    {
        unset($this->User);
        //$this->User = NULL;//this will trigger a typed property error

        parent::__destruct();
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


    public function get() : UserInterface
    {
//        $this->initialize_user();
        return $this->User;
    }

    public function set(UserInterface $User) : void
    {
//        if (!is_a($User, $this->user_class, TRUE)) {
//            //throw new InvalidArgumentException();
//        }
//        $this->initialize_user();
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