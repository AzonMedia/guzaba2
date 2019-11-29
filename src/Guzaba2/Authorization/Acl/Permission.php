<?php

namespace Guzaba2\Authorization\Acl;

use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Authorization\Role;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Authorization\Interfaces\PermissionInterface;

/**
 * Class Permission
 * @package Guzaba2\Authorization\Acl
 * @property scalar permission_id
 * @property scalar role_id
 * @property string class_name
 * @property scalar object_id
 * @property string action_name
 * @property string permission_description
 *
 * The permission can support directly controllers by setting the controller class and NULL to object_id.
 * The way the controllers are handled though is by creating a controller instance and checking the permission against the controller record.
 * This has the advantage that with a DB query all controller permissions can be pulled, while if the controller class is used directly in the permission record this will not be possible.
 * It will not be known is the permission record for controller or not at the time of the query.
 */
class Permission extends ActiveRecord implements PermissionInterface
{

    protected const CONFIG_DEFAULTS = [
        'main_table'            => 'acl_permissions',
        'route'                 => '/permission',

        'no_permissions'    => TRUE,//the permissions records themselves cant use permissions
    ];

    protected const CONFIG_RUNTIME = [];

    public static function create(Role $Role, string $action, ActiveRecordInterface $ActiveRecord, string $permission_description = '') : ActiveRecord
    {
        $Permission = new self();
        $Permission->role_id = $Role->get_id();
        $Permission->class_name = get_class($ActiveRecord);
        $Permission->object_id = $ActiveRecord->get_id();
        $Permission->action_name = $action;
        $Permission->permission_description = $permission_description;
        return $Permission;
    }

    /**
     * This is a permission valid for all objects from the given class. Liek a privilege.
     * @return ActiveRecord
     */
    public static function create_class_permission(Role $Role, string $action, string $class_name, string $permission_description='') : ActiveRecord
    {
        $Permission = new self();
        $Permission->role_id = $Role->get_id();
        $Permission->class_name = $class_name;
        $Permission->object_id = NULL;
        $Permission->action_name = $action;
        $Permission->permission_description = $permission_description;
        return $Permission;
    }
}