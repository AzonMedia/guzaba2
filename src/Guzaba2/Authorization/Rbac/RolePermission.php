<?php


namespace Guzaba2\Authorization\Rbac;


use Guzaba2\Orm\ActiveRecord;

/**
 * Class RolePermission
 * @package Guzaba2\Authorization\Rbac
 * Represents a permission granted to a role
 * @property scalar role_permission_id
 * @property scalar role_id
 * @property scalar permission_id
 */
class RolePermission extends ActiveRecord
{

    protected const CONFIG_DEFAULTS = [
        'main_table'            => 'rbac_roles_permissions',
    ];

    protected const CONFIG_RUNTIME = [];

    public static function create(Role $Role, Permission $Permission) : ActiveRecord
    {

        if ($Role->is_new() || !$Role->get_id()) {
            //throw
        }
        if ($Permission->is_new() || !$Permission->get_id()) {
            //throw
        }

        $RolePermission = new self();
        $RolePermission->role_id = $Role->get_id();
        $RolePermission->permission_id = $Permission->get_id();
        $RolePermission->save();
        return $RolePermission;
    }
}