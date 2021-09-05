<?php

declare(strict_types=1);

namespace Guzaba2\Authorization;

use Guzaba2\Authorization\Interfaces\AuthorizationProviderInterface;
use Guzaba2\Authorization\Interfaces\PermissionInterface;
use Guzaba2\Authorization\Traits\AuthorizationProviderTrait;
use Guzaba2\Base\Base;
use Guzaba2\Database\Sql\Interfaces\ConnectionInterface;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;

/**
 * Class TrueAuthorizationProvider
 * @package Guzaba2\Authorization\Rbac
 * This provider is used to bypass permission checks - it always returns TRUE.
 */
class BypassAuthorizationProvider extends Base implements AuthorizationProviderInterface
{


    protected const CONFIG_DEFAULTS = [
        'class_dependencies'        => [
            PermissionInterface::class      => BypassPermission::class,
        ],
    ];

    protected const CONFIG_RUNTIME = [];

    /**
     * Returns the name of the class that this class uses for the implementation of PermissionInterface
     * @return string
     */
    public static function get_permission_class(): string
    {
        return static::CONFIG_RUNTIME['class_dependencies'][PermissionInterface::class];
    }

    public function role_can(Role $Role, string $action, ActiveRecordInterface $ActiveRecord, ?int &$permission_denied_reason = null): bool
    {
        return true;
    }

    public function role_can_on_class(Role $Role, string $action, string $class, ?int &$permission_denied_reason = null): bool
    {
        return true;
    }

    /**
     * Returns a boolean can the provided $role perform the $action on the object $ActiveRecord
     * @param string $action
     * @param ActiveRecordInterface $ActiveRecord
     * @return bool
     */
    public function current_role_can(string $action, ActiveRecordInterface $ActiveRecord, ?int &$permission_denied_reason = null): bool
    {
        return true;
    }

    public function current_role_can_on_class(string $action, string $class, ?int &$permission_denied_reason = null): bool
    {
        return true;
    }

    /**
     * Checks can the provided $role perform the $action on the object $ActiveRecord and if cant a PermissionDeniedException is thrown
     * @throws PermissionDeniedException
     * @param string $action
     * @param ActiveRecordInterface $ActiveRecord
     * @return bool
     */
    public function check_permission(string $action, ActiveRecordInterface $ActiveRecord): void
    {
        return;
    }

    public function check_class_permission(string $action, string $class): void
    {
        return;
    }

    public function grant_permission(Role $Role, string $action, ActiveRecordInterface $ActiveRecord): ?PermissionInterface
    {
        return null;
    }

    public function grant_class_permission(Role $Role, string $action, string $class_name): ?PermissionInterface
    {
        return null;
    }

    public function revoke_permission(Role $Role, string $action, ActiveRecordInterface $ActiveRecord): void
    {
        return;
    }

    public function revoke_class_permission(Role $Role, string $action, string $class_name): void
    {
        return;
    }

    public function delete_permissions(ActiveRecordInterface $ActiveRecord): void
    {
        return;
    }

    public function delete_class_permissions(string $class_name): void
    {
        return;
    }

    public function get_permissions(ActiveRecordInterface $ActiveRecord): iterable
    {
        return [];
    }

    public function get_permissions_by_class(string $class_name): iterable
    {
        return [];
    }

    public static function get_used_active_record_classes(): array
    {
        return [];
    }

    public function add_sql_permission_checks(string &$sql, array &$parameters, string $action, ConnectionInterface $Connection): void
    {

    }

    public static function get_sql_permission_check(string $class, string $main_table = 'main_table', $action = 'read'): string
    {
        return '';
    }

    public static function checks_permissions(): bool
    {
        return false;
    }
}
