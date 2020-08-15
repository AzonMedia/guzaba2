<?php

declare(strict_types=1);

namespace Guzaba2\Authorization\Interfaces;

use Guzaba2\Authorization\Acl\Permission;
use Guzaba2\Authorization\Exceptions\PermissionDeniedException;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Authorization\Role;

/**
 * Interface AuthorizationProviderInterface
 * @package Guzaba2\Authorization\Interfaces
 */
interface AuthorizationProviderInterface
{
    /**
     * Returns a boolean can the provided $role perform the $action on the object $ActiveRecord.
     * @param Role $Role
     * @param string $action
     * @param ActiveRecordInterface $ActiveRecord
     * @return bool
     */
    public function role_can(Role $Role, string $action, ActiveRecordInterface $ActiveRecord): bool;

    /**
     * Returns a boolean can the provided $role perform the $action on the ActiveRecord $class.
     * @param Role $Role
     * @param string $action
     * @param string $class
     * @return bool
     */
    public function role_can_on_class(Role $Role, string $action, string $class): bool;

    /**
     * Returns a boolean can role of the currently logged user perform the $action on the object $ActiveRecord.
     * @param string $action
     * @param ActiveRecordInterface $ActiveRecord
     * @return bool
     */
    public function current_role_can(string $action, ActiveRecordInterface $ActiveRecord): bool;

    /**
     * Returns a boolean can role of the currently logged user perform the $action on the the ActiveRecord $class.
     * @param string $action
     * @param string $class
     * @return bool
     */
    public function current_role_can_on_class(string $action, string $class): bool;

    /**
     * Checks can the provided $role perform the $action on the object $ActiveRecord and if not a PermissionDeniedException is thrown.
     * @throws PermissionDeniedException
     * @param string $action
     * @param ActiveRecordInterface $ActiveRecord
     * @return bool
     */
    public function check_permission(string $action, ActiveRecordInterface $ActiveRecord): void;

    /**
     * Checks can the provided $role perform the $action on the object ActiveRecord $class and if not a PermissionDeniedException is thrown.
     * @param string $action
     * @param string $class_name
     */
    public function check_class_permission(string $action, string $class_name): void;

    /**
     * Grants a new object permission.
     * Returns the newly created permission record or NULL if does not implement permissions.
     * @param Role $Role
     * @param string $action
     * @param ActiveRecordInterface $ActiveRecord
     * @return PermissionInterface
     */
    public function grant_permission(Role $Role, string $action, ActiveRecordInterface $ActiveRecord): ?PermissionInterface;

    /**
     * Grants a new class permission.
     * Returns the newly created permission record or NULL if does not implement permissions.
     * @param Role $Role
     * @param string $action
     * @param string $class_name
     * @return PermissionInterface
     */
    public function grant_class_permission(Role $Role, string $action, string $class_name): ?PermissionInterface;

    /**
     * Revokes an object permission.
     * @param Role $Role
     * @param string $action
     * @param ActiveRecordInterface $ActiveRecord
     */
    public function revoke_permission(Role $Role, string $action, ActiveRecordInterface $ActiveRecord): void;

    /**
     * Revokes a class permission.
     * @param Role $Role
     * @param string $action
     * @param string $class_name
     */
    public function revoke_class_permission(Role $Role, string $action, string $class_name): void;

    /**
     * Removes all permission records for the provided object.
     * To be used when the object is deleted.
     * @param ActiveRecordInterface $ActiveRecord
     */
    public function delete_permissions(ActiveRecordInterface $ActiveRecord): void;

    /**
     * Removes all permission records for the provided class.
     * @param string $class_name
     */
    public function delete_class_permissions(string $class_name): void;

    /**
     * Returns all permissions for the given ActiveRecord object.
     * @param ActiveRecordInterface $ActiveRecord
     * @return iterable
     */
    public function get_permissions(ActiveRecordInterface $ActiveRecord): iterable;

    /**
     * Returns all permissions for the given ActiveRecord class.
     * @param string $class_name
     * @return iterable
     */
    public function get_permissions_by_class(string $class_name): iterable;

    /**
     * Returns the class names of the ActiveRecord classes used by the Authorization implementation.
     * @return array
     */
    public static function get_used_active_record_classes(): array;

    /**
     * Returns the join chunk of the SQL query needed to enforce the permissions to be used in a custom query.
     * @param string $main_table The main table from the main query to which the join should be applied
     * @return string The join part of the stamement that needs to be included in the query
     */
    public static function get_sql_permission_check(string $class, string $main_table = 'main_table', string $action = 'read'): string;
}
