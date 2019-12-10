<?php
declare(strict_types=1);


namespace Guzaba2\Authorization\Rbac;

use Guzaba2\Authorization\Interfaces\PermissionInterface;
use Guzaba2\Authorization\Role;
use Guzaba2\Authorization\Traits\AuthorizationProviderTrait;
use Guzaba2\Base\Base;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Authorization\Interfaces\AuthorizationProviderInterface;

class RbacAuthorizationProvider extends Base implements AuthorizationProviderInterface
{

    use AuthorizationProviderTrait;

    public function grant_permission(Role $Role, string $action, ActiveRecordInterface $ActiveRecord) : ?PermissionInterface
    {
        //TODO implement
        return NULL;
    }

    public function grant_class_permission(Role $Role, string $action, string $class_name) : ?PermissionInterface
    {
        //TODO implement
        return NULL;
    }

    public function revoke_permission(Role $Role, string $action, ActiveRecordInterface $ActiveRecord) : void
    {
        //TODO implement
        return;
    }

    public function revoke_class_permission(Role $Role, string $action, string $class_name) : void
    {
        //TODO implement
        return;
    }

    public function delete_permissions(ActiveRecordInterface $ActiveRecord) : void
    {
        //TODO implement
        return;
    }

    public function delete_class_permissions(string $class_name) : void
    {
        //TODO implement
        return;
    }

    public function role_can(Role $Role, string $action, ActiveRecordInterface $ActiveRecord) : bool
    {
        $ret = FALSE;
        $operations = Operation::get_data_by( ['action_name' => $action, 'class_name' => get_class($ActiveRecord), 'object_id' => $ActiveRecord->get_id() ] );
        $class_operations = Operation::get_data_by( ['action_name' => $action, 'class_name' => get_class($ActiveRecord), 'object_id' => NULL ] );
        $operations = array_merge($operations, $class_operations);
        $roles_ids = $Role->get_all_inherited_roles_ids();
        foreach ($operations as $operation_data) {
            $permissions_operations = PermissionOperation::get_data_by( ['operation_id' => $operation_data['operation_id']] );
            foreach ($permissions_operations as $permission_operation_data) {
                $roles_permissions = RolePermission::get_data_by( ['permission_id' => $permission_operation_data['permission_data']] );
                foreach ($roles_permissions as $roles_permission_data) {
                    foreach ($roles_ids as $role_id) {
                        if ($role_id === $roles_permission_data['role_id']) {
                            $ret = TRUE;
                            break 4;
                        }
                    }
                }
            }
        }
    }

    public static function get_used_active_record_classes() : array
    {
        return [Permission::class, Operation::class, PermissionOperation::class, RolePermission::class, Role::class];
    }
}