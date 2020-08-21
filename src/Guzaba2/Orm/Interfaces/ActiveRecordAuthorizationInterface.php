<?php

namespace Guzaba2\Orm\Interfaces;

use Guzaba2\Authorization\Interfaces\PermissionInterface;
use Guzaba2\Authorization\Role;

interface ActiveRecordAuthorizationInterface
{
    /**
     * Will throw if the current role is not allowed to perform the provided $action on this object
     * @param string $action
     */
    public function check_permission(string $action): void;

    /**
     * The current role if the primary role of the CurrentUser.
     * @param string $action
     * @return bool
     */
    public function current_role_can(string $action): bool;

    /**
     * Can the provided $Role perform the $action on this object.
     * @param Role $Role
     * @param string $action
     * @return bool
     */
    public function role_can(Role $Role, string $action): bool;

    /**
     * Whether the configuration of the AR class allows for permissions.
     * By default all classes use permissions, with the ones (the permission records them selves and the roles & roles hierarchy) do not by having CONFIG_RUNTIME['no_permissions'] = TRUE
     * @return bool
     */
    public static function uses_permissions(): bool;

    /**
     * Grant permission to $Role to perform $action on this object
     * @param Role $Role
     * @param string $action
     * @return PermissionInterface|null
     */
    public function grant_permission(Role $Role, string $action): ?PermissionInterface;

    /**
     * Grant permission to $Role to perform $action to all instances of the class of the object
     * @param Role $Role
     * @param string $action
     * @return PermissionInterface|null
     */
    public static function grant_class_permission(Role $Role, string $action): ?PermissionInterface;

    /**
     * Revoke permission of $Role to perform $action on this object
     * @param Role $Role
     * @param string $action
     */
    public function revoke_permission(Role $Role, string $action): void;

    /**
     * Revoke permission of $Role to perform $action on all instances of the class of the object
     * @param Role $Role
     * @param string $action
     */
    public static function revoke_class_permission(Role $Role, string $action): void;
}
