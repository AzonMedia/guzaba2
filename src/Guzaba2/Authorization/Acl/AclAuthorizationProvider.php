<?php


namespace Guzaba2\Authorization\Acl;


use Guzaba2\Orm\Exceptions\RecordNotFoundException;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Authorization\Interfaces\AuthorizationProviderInterface;
use Guzaba2\Authorization\Role;

class AclAuthorizationProvider implements AuthorizationProviderInterface
{
    public static function role_can(Role $Role, string $action, ActiveRecordInterface $ActiveRecord) : bool
    {
        if ($Role->is_new()) {
            //throw
        }
        $ret = FALSE;

        $permissions = Permission::get_data_by( [ 'class_name' => get_class($ActiveRecord), 'object_id'=> $ActiveRecord->get_id(), 'action_name' => $action] );
        $class_permissions = Permission::get_data_by( [ 'class_name' => get_class($ActiveRecord), 'object_id'=> NULL, 'action_name' => $action] );
        $permissions = array_merge($operations, $class_operations);

        $roles_ids = $Role->get_all_inherited_roles_ids();
        foreach ($permissions as $permission_data) {
            foreach ($roles_ids as $role_id) {
                if ($role_id === $permission_data['role_id']) {
                    $ret = TRUE;
                    break 2;
                }
            }
        }

        return $ret;
        
    }
}