<?php

namespace Guzaba2\Orm\Traits;

use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Translator\Translator as t;

trait ActiveRecordAuthorization
{
    public function __call(string $method, array $args) /* mixed */
    {
        if (!strpos($method,ActiveRecordInterface::AUTHZ_METHOD_PREFIX)===0) {
            //throw
        }
        $action = substr($method, strlen(ActiveRecordInterface::AUTHZ_METHOD_PREFIX));
        if (!method_exists($this, $action)) {
            //throw
        }

        //$Request = Coroutine::getContext()->Request;
        //object user_id from $Request
        //self::CurrentUser
        if (!self::uses_service('AuthorizationProvider')) {
            throw new RunTimeException(sprintf(t::_('The ActiveRecord is not using the service AuthorizationProvider. A method %s requiring authorization was invoked.'), $method));
        }
        $this->check_permission($action);

        return [$this, $action](...$args);
    }

    /**
     * Will throw if the current role is not allowed to perform the provided $action on this object
     * @param string $action
     */
    public function check_permission(string $action) : void
    {
        if (!$this->current_role_can($action) ) {
            $Role = Coroutine::getContext()->CurrentUser->get_role();
            throw new PermissionDeniedException(sprintf(t::_('Role %s is not allowed to %s object %s:%s.'), $Role->role_name, $action, get_class($this), $this->get_id() ));
        }
    }

    public function current_role_can(string $action) : bool
    {
        $Role = Coroutine::getContext()->CurrentUser->get_role();
        return $this->role_can($Role , $action);
    }

    public function role_can(Role $Role, string $action) : bool
    {
        //get all operations that support that action
        return self::AuthorizationProvider()::role_can($Role, $action, $this);
    }

//    public static function uses_authorization() : bool
//    {
//        return self::uses_service('AuthorizationProvider');
//    }
}