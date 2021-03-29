<?php

declare(strict_types=1);

namespace Guzaba2\Orm\Traits;

use Azonmedia\Reflection\ReflectionClass;
use Azonmedia\Reflection\ReflectionMethod;
use Guzaba2\Authorization\Interfaces\PermissionInterface;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Orm\Interfaces\ActiveRecordHistoryInterface;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Orm\Interfaces\ActiveRecordTemporalInterface;
use Guzaba2\Translator\Translator as t;
use Guzaba2\Authorization\Role;
use Monolog\Handler\MissingExtensionException;

trait ActiveRecordAuthorization
{

    /**
     * Throws PermissionDeniedException if the current role is not allowed to perform the provided $action on this object
     * @param string $action
     */
    public function check_permission(string $action): void
    {
        if (static::uses_service('AuthorizationProvider') && static::uses_permissions() && !$this->are_permission_checks_disabled()) {
            static::get_service('AuthorizationProvider')->check_permission($action, $this);
        }
        return;
    }

    /**
     * Throws PermissionDeniedException if the current role is not allowed to perform the provided $action on this class (LSB).
     * @param string $action
     * @throws RunTimeException
     */
    public static function check_class_permission(string $action): void
    {
        $class = get_called_class();
        if (static::uses_service('AuthorizationProvider') && static::uses_permissions() && !is_a($class, ActiveRecordTemporalInterface::class, true) ) {
            static::get_service('AuthorizationProvider')->check_class_permission($action, $class);
        }
        return;
    }

    /**
     * Returns true if the current role (the the primary role of the CurrentUser) can perform the $action on this object.
     * @param string $action
     * @return bool
     */
    public function current_role_can(string $action): bool
    {
        return static::uses_service('AuthorizationProvider') && static::uses_permissions() ? static::get_service('AuthorizationProvider')->current_role_can($action, $this) : true;
    }

    /**
     * Returns true if the current role (the the primary role of the CurrentUser) can perform the $action on this class (LSB).
     * @param string $action
     * @return bool
     * @throws RunTimeException
     */
    public static function current_role_can_on_class(string $action): bool
    {
        $class = get_called_class();
        return static::uses_service('AuthorizationProvider') && static::uses_permissions() ? static::get_service('AuthorizationProvider')->current_role_can_on_class($action, $class) : true;
    }

    /**
     * Can the provided $Role perform the $action on this object.
     * @param Role $Role
     * @param string $action
     * @return bool
     */
    public function role_can(Role $Role, string $action): bool
    {
        return static::uses_service('AuthorizationProvider') && static::uses_permissions() ? static::get_service('AuthorizationProvider')->role_can($Role, $action, $this) : true;
    }

    /**
     * Whether the configuration of the AR class allows for permissions.
     * By default all classes use permissions, with the ones (the permission records them selves and the roles & roles hierarchy) do not by having CONFIG_RUNTIME['no_permissions'] = TRUE
     * @return bool
     */
    public static function uses_permissions(): bool
    {
        return empty(static::CONFIG_RUNTIME['no_permissions']) && !is_a(get_called_class(), ActiveRecordTemporalInterface::class, true);
    }

    /**
     * Deletes all permission records associated with this object.
     * To be used in self::delete()
     */
    private function delete_permissions(): void
    {
        if (static::uses_service('AuthorizationProvider') && static::uses_permissions()) {
            self::get_service('AuthorizationProvider')->delete_permissions($this);
        }
    }

    /**
     * Grant permission to $Role to perform $action on this object
     * @param Role $Role
     * @param string $action
     * @return PermissionInterface|null
     */
    public function grant_permission(Role $Role, string $action): ?PermissionInterface
    {
        $ret = null;
        if (static::uses_service('AuthorizationProvider') && static::uses_permissions()) {
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
    public static function grant_class_permission(Role $Role, string $action): ?PermissionInterface
    {
        $ret = null;
        $class = get_called_class();
        if (static::uses_service('AuthorizationProvider') && static::uses_permissions()) {
            $ret = self::get_service('AuthorizationProvider')->grant_class_permission($Role, $action, $class);
        }
        return $ret;
    }

    /**
     * Revoke permission of $Role to perform $action on this object
     * @param Role $Role
     * @param string $action
     */
    public function revoke_permission(Role $Role, string $action): void
    {
        if (static::uses_service('AuthorizationProvider') && static::uses_permissions()) {
            self::get_service('AuthorizationProvider')->revoke_permission($Role, $action, $this);
        }
    }

    /**
     * Revoke permission of $Role to perform $action on all instances of the class of the object
     * @param Role $Role
     * @param string $action
     */
    public static function revoke_class_permission(Role $Role, string $action): void
    {
        $class = get_called_class();
        if (static::uses_service('AuthorizationProvider') && static::uses_permissions()) {
            self::get_service('AuthorizationProvider')->revoke_class_permission($Role, $action, $class);
        }
    }


    //this is not yet supported
    public function change_owner(): void
    {
    }

    /**
     * Checks does the class has the provided $action.
     * Not every method is an action - @see self::get_class_actions()
     * @param string $action
     * @return bool
     * @throws \ReflectionException
     */
    public static function class_has_action(string $action): bool
    {
        $class_actions = static::get_class_actions();
        return in_array($action, $class_actions);
    }

    /**
     * Returns a list of the actions that the given class supports.
     * These are the standard actions (@see self::get_standard_actions())
     * and also any public method that has an attribute @check_permission, @check_permissions, @is_action (@see self::is_method_action())
     * Unlike @see self::get_object_actions() the class actions include the static methods (if they have the appripraite attribute)
     * @return array
     * @throws \ReflectionException
     */
    public static function get_class_actions(): array
    {
        $class = get_called_class();
        $ret = self::get_standard_actions();
        $RClass = new ReflectionClass($class);
        //foreach ($RClass->getMethods(ReflectionMethod::IS_PUBLIC) as $RMethod) {
        foreach ($RClass->getOwnMethods(ReflectionMethod::IS_PUBLIC) as $RMethod) {
            if (self::is_method_action($RMethod)) { //static methods can have permissions
                $ret[] = $RMethod->getName();
            }
        }
        return $ret;
    }

    /**
     * Checks does an object of this class has the provided $action.
     * Not every method is an action - @see self::get_object_actions()
     * @param string $action
     * @return bool
     * @throws \ReflectionException
     */
    public static function object_has_action(string $action): bool
    {
        $class_actions = static::get_object_actions();
        return in_array($action, $class_actions);
    }

    /**
     * Returns a list of the actions that an object of this class supports.
     * These are the standard actions (@see self::get_standard_actions())
     * and also any public non-static method that has an attribute @check_permission, @check_permissions, @is_action (@see self::is_method_action())
     * @return array
     * @throws \ReflectionException
     */
    public static function get_object_actions(): array
    {
        $class = get_called_class();
        $ret = self::get_standard_actions();
        $RClass = new ReflectionClass($class);
        //foreach ($RClass->getMethods(ReflectionMethod::IS_PUBLIC) as $RMethod) {
        foreach ($RClass->getOwnMethods(ReflectionMethod::IS_PUBLIC) as $RMethod) {
            if (self::is_method_action($RMethod) && !$RMethod->isStatic()) { //exclude the static methods
                $ret[] = $RMethod->getName();
            }
        }
        return $ret;
    }

    /**
     * Checks is the provided method is a method requiring permissions check.
     * These are marked with any of the following attributes: @check_permission, @check_permissions, @is_action
     * @param \ReflectionMethod $RMethod
     * @return bool
     */
    protected static function is_method_action(\ReflectionMethod $RMethod): bool
    {
        $ret = false;
        $doc_comment = $RMethod->getDocComment();
        if (
            $doc_comment
            && (
                strpos($doc_comment, '@check_permission') !== false
                || strpos($doc_comment, '@check_permissions') !== false
                || strpos($doc_comment, '@is_action') !== false

            )
        ) {
            $ret = true;
        }
        return $ret;
    }
}
