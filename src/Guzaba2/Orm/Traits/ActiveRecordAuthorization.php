<?php
declare(strict_types=1);

namespace Guzaba2\Orm\Traits;

use Azonmedia\Reflection\ReflectionClass;
use Azonmedia\Reflection\ReflectionMethod;
use Guzaba2\Authorization\Interfaces\PermissionInterface;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Translator\Translator as t;
use Guzaba2\Authorization\Role;
use Monolog\Handler\MissingExtensionException;

trait ActiveRecordAuthorization
{

    /**
     * Will throw if the current role is not allowed to perform the provided $action on this object
     * @param string $action
     */
    public function check_permission(string $action) : void
    {
        if (static::uses_service('AuthorizationProvider') && static::uses_permissions() && !$this->are_permission_checks_disabled() ) {
            static::get_service('AuthorizationProvider')->check_permission($action, $this);
        }
        return;
    }

    public static function check_class_permission(string $action) : void
    {
        $class = get_called_class();
        if (static::uses_service('AuthorizationProvider') && static::uses_permissions() ) {
            static::get_service('AuthorizationProvider')->check_class_permission($action, $class );
        }
        return;
    }

    /**
     * The current role if the primary role of the CurrentUser.
     * @param string $action
     * @return bool
     */
    public function current_role_can(string $action) : bool
    {
        return static::uses_service('AuthorizationProvider') && static::uses_permissions() ? static::get_service('AuthorizationProvider')->current_role_can($action, $this) : TRUE;
    }

    public static function current_role_can_on_class(string $action) : bool
    {
        $class = get_called_class();
        return static::uses_service('AuthorizationProvider') && static::uses_permissions() ? static::get_service('AuthorizationProvider')->current_role_can_on_class($action, $class) : TRUE;
    }

    /**
     * Can the provided $Role perform the $action on this object.
     * @param Role $Role
     * @param string $action
     * @return bool
     */
    public function role_can(Role $Role, string $action) : bool
    {
        return static::uses_service('AuthorizationProvider') && static::uses_permissions() ? static::get_service('AuthorizationProvider')->role_can($Role, $action, $this) : TRUE;
    }

    /**
     * Whether the configuration of the AR class allows for permissions.
     * By default all classes use permissions, with the ones (the permission records them selves and the roles & roles hierarchy) do not by having CONFIG_RUNTIME['no_permissions'] = TRUE
     * @return bool
     */
    public static function uses_permissions() : bool
    {
        return empty(static::CONFIG_RUNTIME['no_permissions']);
    }

    /**
     * Deletes all permission records associated with this object.
     * To be used in self::delete()
     */
    private function delete_permissions() : void
    {
        if (static::uses_service('AuthorizationProvider') && static::uses_permissions() ) {
            self::get_service('AuthorizationProvider')->delete_permissions($this);
        }
    }

    /**
     * Grant permission to $Role to perform $action on this object
     * @param Role $Role
     * @param string $action
     * @return PermissionInterface|null
     */
    public function grant_permission(Role $Role, string $action) : ?PermissionInterface
    {
        $ret = NULL;
        if (static::uses_service('AuthorizationProvider') && static::uses_permissions() ) {
            $ret = self::get_service('AuthorizationProvider')->grant_permission($Role, $action, $this);
        }
        return $ret;
    }

    /**
     * Grant permission to $Role to perform $action to all instances of the class of the object
     * @param Role $Role
     * @param string $action
     * @return PermissionInterface|null
     */
    public function grant_class_permission(Role $Role, string $action) : ?PermissionInterface
    {
        $ret = NULL;
        if (static::uses_service('AuthorizationProvider') && static::uses_permissions() ) {
            $ret = self::get_service('AuthorizationProvider')->grant_class_permission($Role, $action, get_class($this));
        }
        return $ret;
    }

    /**
     * Revoke permission of $Role to perform $action on this object
     * @param Role $Role
     * @param string $action
     */
    public function revoke_permission(Role $Role, string $action) : void
    {
        if (static::uses_service('AuthorizationProvider') && static::uses_permissions() ) {
            self::get_service('AuthorizationProvider')->revoke_permission($Role, $action, $this);
        }
    }

    /**
     * Revoke permission of $Role to perform $action on all instances of the class of the object
     * @param Role $Role
     * @param string $action
     */
    public function revoke_class_permission(Role $Role, string $action) : void
    {
        if (static::uses_service('AuthorizationProvider') && static::uses_permissions() ) {
            self::get_service('AuthorizationProvider')->revoke_class_permission($Role, $action, get_class($this));
        }
    }


    //this is not yet supported
    public function change_owner() : void
    {

    }

    public static function get_class_actions() : array
    {
        $class = get_called_class();
        $ret = self::get_standard_actions();
        $RClass = new ReflectionClass($class);
        foreach ($RClass->getMethods(ReflectionMethod::IS_PUBLIC) as $RMethod) {
            if (self::is_method_action($RMethod)) { //static methods can have permissions
                $ret[] = $RMethod->getName();
            }
        }
        return $ret;
    }

    public static function get_object_actions() : array
    {
        $class = get_called_class();
        $ret = self::get_standard_actions();
        $RClass = new ReflectionClass($class);
        foreach ($RClass->getMethods(ReflectionMethod::IS_PUBLIC) as $RMethod) {
            if (self::is_method_action($RMethod) && !$RMethod->isStatic()) { //exclude the static methods
                $ret[] = $RMethod->getName();
            }
        }
        return $ret;
    }

    protected static function is_method_action(\ReflectionMethod $RMethod) : bool
    {
        $ret = FALSE;
        $doc_comment = $RMethod->getDocComment();
        if (
            $doc_comment
            && (
                strpos($doc_comment,'@check_permission') !== FALSE
                || strpos($doc_comment,'@check_permissions') !== FALSE
                || strpos($doc_comment, '@is_action') !== FALSE

            )
        ) {
            $ret = TRUE;
        }
        return $ret;
    }

}