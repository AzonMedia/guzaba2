<?php
declare(strict_types=1);

namespace Guzaba2\Authorization\Acl;

use Guzaba2\Authorization\RolesHierarchy;
use Guzaba2\Authorization\Traits\AuthorizationProviderTrait;
use Guzaba2\Base\Base;
use Guzaba2\Mvc\Interfaces\ControllerInterface;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Authorization\Interfaces\AuthorizationProviderInterface;
use Guzaba2\Authorization\Role;

class AclAuthorizationProvider extends Base implements AuthorizationProviderInterface
{

    use AuthorizationProviderTrait;

    protected const CONFIG_DEFAULTS = [
        'services'      => [
            'CurrentUser',
        ],
    ];

    protected const CONFIG_RUNTIME = [];

    public function role_can(Role $Role, string $action, ActiveRecordInterface $ActiveRecord) : bool
    {
        $ret = FALSE;

        $roles_ids = $Role->get_all_inherited_roles_ids();

//        $permissions = Permission::get_data_by( [ 'class_name' => get_class($ActiveRecord), 'object_id'=> $ActiveRecord->get_id(), 'action_name' => $action] );
//        $class_permissions = Permission::get_data_by( [ 'class_name' => get_class($ActiveRecord), 'object_id'=> NULL, 'action_name' => $action] );
//        $permissions = array_merge($permissions, $class_permissions);
        //optimization
        if ($ActiveRecord instanceof ControllerInterface) {
            //usually we need the class permissions for the controllers (to execute a controller
            //only if there are no permissions found retrive the object permissions
            $class_permissions = Permission::get_data_by( [ 'class_name' => get_class($ActiveRecord), 'object_id'=> NULL, 'action_name' => $action] );
            $ret = self::check_permissions($roles_ids, $class_permissions);
            if (!$ret) {
                //check the object permission
                $permissions = Permission::get_data_by( [ 'class_name' => get_class($ActiveRecord), 'object_id'=> $ActiveRecord->get_id(), 'action_name' => $action] );
                $ret = self::check_permissions($roles_ids, $permissions);
            }
        } else {
            //on the rest of the objects usually we are looking for object permissions, not class permissions
            //class permission will be needed only when CREATE is needed or there is a privilege
            $permissions = Permission::get_data_by( [ 'class_name' => get_class($ActiveRecord), 'object_id'=> $ActiveRecord->get_id(), 'action_name' => $action] );
            $ret = self::check_permissions($roles_ids, $permissions);
            if (!$ret) {
                //check the class permission
                $class_permissions = Permission::get_data_by( [ 'class_name' => get_class($ActiveRecord), 'object_id'=> NULL, 'action_name' => $action] );
                $ret = self::check_permissions($roles_ids, $class_permissions);
            }
        }


        //this will trigger object instantiations
//        $permissions = Permission::get_by( [ 'class_name' => get_class($ActiveRecord), 'object_id'=> $ActiveRecord->get_id(), 'action_name' => $action] );
//        $class_permissions = Permission::get_by( [ 'class_name' => get_class($ActiveRecord), 'object_id'=> NULL, 'action_name' => $action] );
//        $permissions = array_merge($permissions, $class_permissions);





        return $ret;
    }

    private static function check_permissions(array $roles_ids, array $permissions) : bool
    {
        $ret = FALSE;
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

    public static function get_used_active_record_classes() : array
    {
        return [Permission::class, Role::class, RolesHierarchy::class];
    }
}