<?php
declare(strict_types=1);

namespace Guzaba2\Authorization\Acl;

use Guzaba2\Authorization\Interfaces\PermissionInterface;
use Guzaba2\Authorization\RolesHierarchy;
use Guzaba2\Authorization\Traits\AuthorizationProviderTrait;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Mvc\Interfaces\ControllerInterface;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Authorization\Interfaces\AuthorizationProviderInterface;
use Guzaba2\Authorization\Role;
use Guzaba2\Translator\Translator as t;

class AclAuthorizationProvider extends Base implements AuthorizationProviderInterface
{

    use AuthorizationProviderTrait;

    protected const CONFIG_DEFAULTS = [
        'services'      => [
            'CurrentUser',
        ],
    ];

    protected const CONFIG_RUNTIME = [];

    public function grant_permission(Role $Role, string $action, ActiveRecordInterface $ActiveRecord) : PermissionInterface
    {
        return Permission::create($Role, $action, $this);
    }

    public function grant_class_permission(Role $Role, string $action, string $class_name) : PermissionInterface
    {
        return Permission::create_class_permission($Role, $action, $class_name);
    }

    public function revoke_permission(Role $Role, string $action, ActiveRecordInterface $ActiveRecord) : void
    {
        (new Permission( [ 'role_id' => $Role->get_id(), 'action_name' => $action, 'class_name' => get_class($ActiveRecord), 'object_id' => $ActiveRecord->get_id() ] ) )->delete();
    }

    public function revoke_class_permission(Role $Role, string $action, string $class_name) : void
    {
        (new Permission( [ 'role_id' => $Role->get_id(), 'action_name' => $action, 'class_name' => $class_name, 'object_id' => NULL ] ) )->delete();
    }

    public function delete_permissions(ActiveRecordInterface $ActiveRecord) : void
    {
        //this will trigger object instantiations
        $permissions = Permission::get_by( [ 'class_name' => get_class($ActiveRecord), 'object_id'=> $ActiveRecord->get_id() ] );
        foreach ($permissions as $Permission) {
            $Permission->delete();
        }
    }

    public function delete_class_permissions(string $class_name) : void
    {
        $class_permissions = Permission::get_by( [ 'class_name' => $class_name, 'object_id'=> NULL ] );
        foreach ($class_permissions as $Permission) {
            $Permission->delete();
        }
    }


    public function get_permissions(?ActiveRecordInterface $ActiveRecord) : iterable
    {
        return Permission::get_data_by( ['class_name' => get_class($ActiveRecord), 'object_id' => $ActiveRecord->get_id() ] );
    }

    public function get_permissions_by_class(string $class_name) : iterable
    {
        if (!class_exists($class_name)) {
            throw new InvalidArgumentException(sprintf(t::_('')));
        }
        return Permission::get_data_by( ['class_name' => get_class($ActiveRecord), 'object_id' => NULL ] );
    }

    public function current_role_can(string $action, ActiveRecordInterface $ActiveRecord): bool
    {
        $Role = self::get_service('CurrentUser')->get()->get_role();
        return $this->role_can($Role, $action, $ActiveRecord);
    }

    public function current_role_can_on_class(string $action, string $class): bool
    {
        $Role = self::get_service('CurrentUser')->get()->get_role();
        return $this->role_can_on_class($Role, $action, $class);
    }

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

    /**
     * @param Role $Role
     * @param string $action
     * @param string $class
     * @return bool
     */
    public function role_can_on_class(Role $Role, string $action, string $class) : bool
    {
        $ret = FALSE;

        $roles_ids = $Role->get_all_inherited_roles_ids();
        $class_permissions = Permission::get_data_by( [ 'class_name' => $class, 'object_id'=> NULL, 'action_name' => $action] );
        $ret = self::check_permissions($roles_ids, $class_permissions);

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