<?php

namespace Guzaba2\Orm\Traits;

use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Translator\Translator as t;
use Guzaba2\Authorization\Role;

trait ActiveRecordAuthorization
{
//    public function __call(string $method, array $args) /* mixed */
//    {
//        if (!strpos($method,ActiveRecordInterface::AUTHZ_METHOD_PREFIX)===0) {
//            //throw
//        }
//        $action = substr($method, strlen(ActiveRecordInterface::AUTHZ_METHOD_PREFIX));
//        if (!method_exists($this, $action)) {
//            //throw
//        }
//
//        if (static::uses_service('AuthorizationProvider')) {
//            $this->check_permission($action);
//        }
//
//        return [$this, $action](...$args);
//    }

    /**
     * Will throw if the current role is not allowed to perform the provided $action on this object
     * @param string $action
     */
    public function check_permission(string $action) : void
    {
        if (static::uses_service('AuthorizationProvider') && static::uses_permissions() ) {
            static::get_service('AuthorizationProvider')->check_permission($action, $this);
        }
        return;
    }

    public function current_role_can(string $action) : bool
    {
        return static::uses_service('AuthorizationProvider') && static::uses_permissions() ? static::get_service('AuthorizationProvider')->current_role_can($action, $this) : TRUE;
    }

    public function role_can(Role $Role, string $action) : bool
    {
        return static::uses_service('AuthorizationProvider') && static::uses_permissions() ? static::get_service('AuthorizationProvider')->role_can($Role, $action, $this) : TRUE;
    }

    public static function uses_permissions() : bool
    {
        return empty(static::CONFIG_RUNTIME['no_permissions']);
    }
}