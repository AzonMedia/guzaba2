<?php

declare(strict_types=1);

namespace Guzaba2\Authorization;

use Guzaba2\Authorization\Interfaces\AuthorizationProviderInterface;
use Guzaba2\Authorization\Interfaces\PermissionInterface;
use Guzaba2\Authorization\Traits\AuthorizationProviderTrait;
use Guzaba2\Base\Base;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;

/**
 * Class TrueAuthorizationProvider
 * @package Guzaba2\Authorization\Rbac
 * This provider is used to bypass permission checks - it always returns TRUE.
 */
class BypassAuthorizationProvider extends Base implements AuthorizationProviderInterface
{

    public function role_can(Role $Role, string $action, ActiveRecordInterface $ActiveRecord): bool
    {
        return true;
    }

    public function role_can_on_class(Role $Role, string $action, string $class): bool
    {
        return true;
    }

    /**
     * Returns a boolean can the provided $role perform the $action on the object $ActiveRecord
     * @param string $action
     * @param ActiveRecordInterface $ActiveRecord
     * @return bool
     */
    public function current_role_can(string $action, ActiveRecordInterface $ActiveRecord): bool
    {
        return true;
    }

    public function current_role_can_on_class(string $action, string $class): bool
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

    public function get_permissions(?ActiveRecordInterface $ActiveRecord): iterable
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

    public static function get_sql_permission_check(string $class, string $main_table = 'main_table'): string
    {
        return '';
    }
}
