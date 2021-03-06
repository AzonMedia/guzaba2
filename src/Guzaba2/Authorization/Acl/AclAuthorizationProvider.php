<?php

declare(strict_types=1);

namespace Guzaba2\Authorization\Acl;

use Guzaba2\Authorization\CurrentUser;
use Guzaba2\Authorization\Interfaces\PermissionInterface;
use Guzaba2\Authorization\RolesHierarchy;
use Guzaba2\Authorization\Traits\AuthorizationProviderTrait;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Mvc\Interfaces\ControllerInterface;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Authorization\Interfaces\AuthorizationProviderInterface;
use Guzaba2\Authorization\Role;
use Guzaba2\Orm\Store\Sql\Mysql;
use Guzaba2\Translator\Translator as t;

class AclAuthorizationProvider extends Base implements AuthorizationProviderInterface
{
    use AuthorizationProviderTrait;

    protected const CONFIG_DEFAULTS = [
        'services'      => [
            'CurrentUser',
            'MysqlOrmStore',//needed because the get_class_id() method is used
        ],
        'class_dependencies'        => [
            PermissionInterface::class      => Permission::class,
        ],
    ];

    protected const CONFIG_RUNTIME = [];

    /**
     * {@inheritDoc}
     * @param Role $Role
     * @param string $action
     * @param ActiveRecordInterface $ActiveRecord
     * @return PermissionInterface
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Guzaba2\Kernel\Exceptions\ConfigurationException
     */
    public function grant_permission(Role $Role, string $action, ActiveRecordInterface $ActiveRecord): PermissionInterface
    {
        return self::get_permission_class()::create($Role, $action, $ActiveRecord);
    }

    /**
     * {@inheritDoc}
     * @param Role $Role
     * @param string $action
     * @param string $class_name
     * @return PermissionInterface
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Guzaba2\Kernel\Exceptions\ConfigurationException
     */
    public function grant_class_permission(Role $Role, string $action, string $class_name): PermissionInterface
    {
        return self::get_permission_class()::create_class_permission($Role, $action, $class_name);
    }

    /**
     * {@inheritDoc}
     * @param Role $Role
     * @param string $action
     * @param ActiveRecordInterface $ActiveRecord
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Base\Exceptions\LogicException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \Guzaba2\Kernel\Exceptions\ConfigurationException
     * @throws \ReflectionException
     */
    public function revoke_permission(Role $Role, string $action, ActiveRecordInterface $ActiveRecord): void
    {
        $class = self::get_permission_class();
        (new $class([ 'role_id' => $Role->get_id(), 'action_name' => $action, 'class_name' => get_class($ActiveRecord), 'object_id' => $ActiveRecord->get_id() ]) )->delete();
    }

    /**
     * {@inheritDoc}
     * @param Role $Role
     * @param string $action
     * @param string $class_name
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Base\Exceptions\LogicException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \Guzaba2\Kernel\Exceptions\ConfigurationException
     * @throws \ReflectionException
     */
    public function revoke_class_permission(Role $Role, string $action, string $class_name): void
    {
        $class = self::get_permission_class();
        (new $class([ 'role_id' => $Role->get_id(), 'action_name' => $action, 'class_name' => $class_name, 'object_id' => null ]) )->delete();
    }

    /**
     * {@inheritDoc}
     * @param ActiveRecordInterface $ActiveRecord
     * @throws RunTimeException
     */
    public function delete_permissions(ActiveRecordInterface $ActiveRecord): void
    {
        //this will trigger object instantiations
        /** @var Permission[] $permissions */
        $permissions = self::get_permission_class()::get_by([ 'class_name' => get_class($ActiveRecord), 'object_id' => $ActiveRecord->get_id() ]);

        //deleting the permissions in random order will not work
        //instead the revoke_permission one must be the very last
        usort($permissions, fn(Permission $P1, Permission $P2): int => $P1->action_name === 'revoke_permission' ? 1 : -1 );

        /** @var PermissionInterface $Permission */
        foreach ($permissions as $Permission) {
            $Permission->delete();
        }
    }

    /**
     * {@inheritDoc}
     * @param string $class_name
     * @throws RunTimeException
     */
    public function delete_class_permissions(string $class_name): void
    {
        $class_permissions = self::get_permission_class()::get_by([ 'class_name' => $class_name, 'object_id' => null ]);
        foreach ($class_permissions as $Permission) {
            $Permission->delete();
        }
    }

    /**
     * {@inheritDoc}
     * @param ActiveRecordInterface $ActiveRecord
     * @return iterable
     * @throws RunTimeException
     */
    public function get_permissions(ActiveRecordInterface $ActiveRecord): iterable
    {
        return self::get_permission_class()::get_data_by(['class_name' => get_class($ActiveRecord), 'object_id' => $ActiveRecord->get_id() ]);
    }

    public function get_permissions_by_class(string $class_name): iterable
    {
        if (!class_exists($class_name)) {
            throw new InvalidArgumentException(sprintf(t::_('')));
        }
        return self::get_permission_class()::get_data_by(['class_name' => get_class($ActiveRecord), 'object_id' => null ]);
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

    public function role_can(Role $Role, string $action, ActiveRecordInterface $ActiveRecord): bool
    {
        $ret = false;

        $roles_ids = $Role->get_all_inherited_roles_ids();

//        $permissions = Permission::get_data_by( [ 'class_name' => get_class($ActiveRecord), 'object_id'=> $ActiveRecord->get_id(), 'action_name' => $action] );
//        $class_permissions = Permission::get_data_by( [ 'class_name' => get_class($ActiveRecord), 'object_id'=> NULL, 'action_name' => $action] );
//        $permissions = array_merge($permissions, $class_permissions);
        //optimization
        if ($ActiveRecord instanceof ControllerInterface) {
            //usually we need the class permissions for the controllers (to execute a controller
            //only if there are no permissions found retrive the object permissions
            $class_permissions = self::get_permission_class()::get_data_by([ 'class_name' => get_class($ActiveRecord), 'object_id' => null, 'action_name' => $action]);
            $ret = self::check_permissions($roles_ids, $class_permissions);
            if (!$ret) {
                //check the object permission
                $permissions = self::get_permission_class()::get_data_by([ 'class_name' => get_class($ActiveRecord), 'object_id' => $ActiveRecord->get_id(), 'action_name' => $action]);
                $ret = self::check_permissions($roles_ids, $permissions);
            }
        } else {
            //on the rest of the objects usually we are looking for object permissions, not class permissions
            //class permission will be needed only when CREATE is needed or there is a privilege
            $permissions = self::get_permission_class()::get_data_by([ 'class_name' => get_class($ActiveRecord), 'object_id' => $ActiveRecord->get_id(), 'action_name' => $action]);
            $ret = self::check_permissions($roles_ids, $permissions);
            if (!$ret) {
                //check the class permission
                $class_permissions = self::get_permission_class()::get_data_by([ 'class_name' => get_class($ActiveRecord), 'object_id' => null, 'action_name' => $action]);
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
     * {@inheritDoc}
     * @param Role $Role
     * @param string $action
     * @param string $class
     * @return bool
     */
    public function role_can_on_class(Role $Role, string $action, string $class): bool
    {
        $ret = false;

        $roles_ids = $Role->get_all_inherited_roles_ids();
        $class_permissions = self::get_permission_class()::get_data_by([ 'class_name' => $class, 'object_id' => null, 'action_name' => $action]);
        $ret = self::check_permissions($roles_ids, $class_permissions);

        return $ret;
    }

    /**
     * @param array $roles_ids
     * @param array $permissions
     * @return bool
     */
    private static function check_permissions(array $roles_ids, array $permissions): bool
    {
        $ret = false;
        foreach ($permissions as $permission_data) {
            foreach ($roles_ids as $role_id) {
                if ($role_id === $permission_data['role_id']) {
                    $ret = true;
                    break 2;
                }
            }
        }
        return $ret;
    }

    /**
     * Returns the classes used by this implementation
     * @return array
     */
    public static function get_used_active_record_classes(): array
    {
        return [self::get_permission_class(), Role::class, RolesHierarchy::class];
    }


    /**
     * Returns the join chunk of the SQL query needed to enforce the permissions to be used in a custom query.
     * @param string $main_table The main table from the main query to which the join should be applied
     * @return string The join part of the stamement that needs to be included in the query
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Base\Exceptions\RunTimeException
     * @throws \ReflectionException
     */
    public static function get_sql_permission_check(string $class, string $main_table = 'main_table', string $action = 'read'): string
    {

        if (!is_a($class, ActiveRecordInterface::class, true)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided class must be of class %s. A %s is provided instead.'), ActiveRecordInterface::class, $class));
        }
        if (!$main_table) {
            throw new InvalidArgumentException(sprintf(t::_('No main_table is provided.')));
        }
        if (!$action) {
            throw new InvalidArgumentException(sprintf(t::_('No action is provided.')));
        }
        if (!$class::class_has_action($action)) {
            throw new InvalidArgumentException(sprintf(t::_('The class %s does not have an action %s.'), $class, $action));
        }


        /** @var Mysql $MysqlOrmStore */
        $MysqlOrmStore = self::get_service('MysqlOrmStore');
        $connection_class = $MysqlOrmStore->get_connection_class();
        $table_prefix = $connection_class::get_tprefix();
        $acl_table = $table_prefix . self::get_permission_class()::get_main_table();
        $acl_permission_class_id = $MysqlOrmStore->get_class_id($class);
        $primary_index_columns = $class::get_primary_index_columns();
        if (count($primary_index_columns) > 1) {
            throw new RunTimeException(sprintf(t::_('The class %s has a compound primary index. Compound primary index is not supported with ACL permissions. Please use a single column integer index (preferrably autoincrement one).'), $class));
        }
        $main_table_column = $primary_index_columns[0];
        //the JOIN is INNER as the permissions must be enforced. Rows not matching a permission record must not be returned.
        //the query does not use binding as this would meanthe bind array to be passed by reference as well
        //there is no need either to use binding as the value is generated internally by the framework and not an exernally provided one
        //if binding is to be used it will be best to have a method that accepts the whole assembed query so far, along with the bound parameters and the method to amend both the query and tha array
        //action is validated against the supported class actions - no sql injection is possible
        //the same for $role_ids
        /** @var CurrentUser $CurrentUser */
        $CurrentUser = self::get_service('CurrentUser');
        $roles_ids = $CurrentUser->get()->get_role()->get_all_inherited_roles_ids();
        $roles_ids = implode(',', $roles_ids);
        //this query returns multiple rows because of the IN query (for exmaple when the user has multiple roles that can read the given record)
        /*
        $q = "
INNER JOIN
    `$acl_table` AS acl_table 
    ON 
        acl_table.class_id = {$acl_permission_class_id} 
        AND acl_table.object_id = {$main_table}.{$main_table_column}
        AND acl_table.action_name = '{$action}'
        AND acl_table.role_id IN ({$roles_ids})
        ";
        */
        $q = "
SELECT
    acl_table.object_id
FROM
    `$acl_table` AS acl_table 
WHERE
    acl_table.class_id = {$acl_permission_class_id} 
    AND acl_table.object_id = {$main_table}.{$main_table_column}
    AND acl_table.action_name = '{$action}'
    AND acl_table.role_id IN ({$roles_ids})  
LIMIT
    1
        ";
        return $q;
    }

    /**
     * {@inheritDoc}
     * @return bool
     */
    public static function checks_permissions(): bool
    {
        return true;
    }
}
