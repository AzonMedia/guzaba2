<?php

declare(strict_types=1);

namespace Guzaba2\Authorization\Acl;

use Guzaba2\Authorization\CurrentUser;
use Guzaba2\Authorization\Interfaces\PermissionInterface;
use Guzaba2\Authorization\RolesHierarchy;
use Guzaba2\Authorization\Traits\AuthorizationProviderTrait;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\DeprecatedException;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Database\Sql\Interfaces\ConnectionInterface;
use Guzaba2\Mvc\Interfaces\ControllerInterface;
use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Authorization\Interfaces\AuthorizationProviderInterface;
use Guzaba2\Authorization\Role;
use Guzaba2\Orm\Store\Sql\Mysql;
use Guzaba2\Translator\Translator as t;
use PhpMyAdmin\SqlParser\Components\Condition;
use PhpMyAdmin\SqlParser\Components\Expression;
use PhpMyAdmin\SqlParser\Components\JoinKeyword;

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

//    public function current_role_can(string $action, ActiveRecordInterface $ActiveRecord): bool
//    {
//        $Role = self::get_service('CurrentUser')->get()->get_role();
//        return $this->role_can($Role, $action, $ActiveRecord);
//    }

    public function current_role_can_on_class(string $action, string $class, ?int &$permission_denied_reason = null): bool
    {
        $Role = self::get_service('CurrentUser')->get()->get_role();
        return $this->role_can_on_class($Role, $action, $class, $permission_denied_reason);
    }

    /**
     * @param Role $Role
     * @param string $action
     * @param ActiveRecordInterface $ActiveRecord
     * @param int $permission_denied_reason
     * @return bool
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Base\Exceptions\LogicException
     * @throws \Guzaba2\Kernel\Exceptions\ConfigurationException
     * @throws \ReflectionException
     */
    public function role_can(Role $Role, string $action, ActiveRecordInterface $ActiveRecord, ?int &$permission_denied_reason = null): bool
    {
        $ret = false;

        $roles_ids = $Role->get_all_inherited_roles_ids();

//        $permissions = Permission::get_data_by( [ 'class_name' => get_class($ActiveRecord), 'object_id'=> $ActiveRecord->get_id(), 'action_name' => $action] );
//        $class_permissions = Permission::get_data_by( [ 'class_name' => get_class($ActiveRecord), 'object_id'=> NULL, 'action_name' => $action] );
//        $permissions = array_merge($permissions, $class_permissions);

        //optimization
//        if ($ActiveRecord instanceof ControllerInterface) {
//            //usually we need the class permissions for the controllers (to execute a controller
//            //only if there are no permissions found retrive the object permissions
//            $class_permissions = self::get_permission_class()::get_data_by([ 'class_name' => get_class($ActiveRecord), 'object_id' => null, 'action_name' => $action]);
//            $ret = self::check_permissions($roles_ids, $class_permissions);
//            if (!$ret) {
//                //check the object permission
//                $permissions = self::get_permission_class()::get_data_by([ 'class_name' => get_class($ActiveRecord), 'object_id' => $ActiveRecord->get_id(), 'action_name' => $action]);
//                $ret = self::check_permissions($roles_ids, $permissions);
//            }
//        } else {
//            //on the rest of the objects usually we are looking for object permissions, not class permissions
//            //class permission will be needed only when CREATE is needed or there is a privilege
//            $permissions = self::get_permission_class()::get_data_by([ 'class_name' => get_class($ActiveRecord), 'object_id' => $ActiveRecord->get_id(), 'action_name' => $action]);
//            $ret = self::check_permissions($roles_ids, $permissions);
//            if (!$ret) {
//                //check the class permission
//                $class_permissions = self::get_permission_class()::get_data_by([ 'class_name' => get_class($ActiveRecord), 'object_id' => null, 'action_name' => $action]);
//                $ret = self::check_permissions($roles_ids, $class_permissions);
//            }
//        }

        //if the object is a controller - look only for class permissions
        //the controllers' instances are not treated as ActiveRecord instances in this case because they have no corresponding records in the DB
        //otherwise for the regular ActiveRecords check both class permission and object permission
        if ($ActiveRecord instanceof ControllerInterface) {
            $class_permissions = self::get_permission_class()::get_data_by([ 'class_name' => get_class($ActiveRecord), 'object_id' => null, 'action_name' => $action]);
            $ret = self::check_permissions($roles_ids, $class_permissions);
            if (!$ret) {
                $permission_denied_reason |= AuthorizationProviderInterface::PERMISSION_DENIED['METHOD'];
            }
        } else {
            $permissions = self::get_permission_class()::get_data_by([ 'class_name' => get_class($ActiveRecord), 'object_id' => $ActiveRecord->get_id(), 'action_name' => $action]);
            $class_permissions = self::get_permission_class()::get_data_by([ 'class_name' => get_class($ActiveRecord), 'object_id' => null, 'action_name' => $action]);
            //$ret = self::check_permissions($roles_ids, $permissions) && self::check_permissions($roles_ids, $class_permissions);
            //lets check bot heven if the first one fails - in order to be able to return the proper reason (as bitmask)
            $ret_class = self::check_permissions($roles_ids, $class_permissions);
            $ret_record = self::check_permissions($roles_ids, $permissions);
            if (!$ret_class) {
                $permission_denied_reason |= AuthorizationProviderInterface::PERMISSION_DENIED['METHOD'];
            }
            if (!$ret_record) {
                $permission_denied_reason |= AuthorizationProviderInterface::PERMISSION_DENIED['RECORD'];
            }
            $ret = $ret_class && $ret_record;
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
     * @param int $permission_denied_reason Bitmask with the reason why the permission was denied.
     * @return bool
     */
    public function role_can_on_class(Role $Role, string $action, string $class, ?int &$permission_denied_reason = null): bool
    {
        $ret = false;

        $roles_ids = $Role->get_all_inherited_roles_ids();
        $class_permissions = self::get_permission_class()::get_data_by([ 'class_name' => $class, 'object_id' => null, 'action_name' => $action]);
        $ret = self::check_permissions($roles_ids, $class_permissions);
        if (!$ret) {
            $permission_denied_reason |= AuthorizationProviderInterface::PERMISSION_DENIED['METHOD'];
        }
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
     * Adds the necessary permission checks
     * @param string $sql
     * @return string
     */
    public function add_sql_permission_checks(string &$_sql, array &$_parameters, string $action, ConnectionInterface $Connection): void
    {

        static $cache = [];
        $sql_hash = md5($_sql);
        if (!empty($cache[$sql_hash])) {
            $_sql = $cache[$sql_hash]['sql'];
            $_parameters += $cache[$sql_hash]['params'];
            return;
        }

        $Parser = new \PhpMyAdmin\SqlParser\Parser($_sql);

        if (count($Parser->statements) > 1) {
            $message = sprintf(
                t::_('"%1$s" supports only a single-statement SQL. %2$s statements were provided.'),
                __METHOD__,
                count($parser['statements'])
            );
            throw new RunTimeException();
        }
        $Statement = $Parser->statements[0];
        if (!($Statement instanceof \PhpMyAdmin\SqlParser\Statements\SelectStatement)) {
            $message = sprintf(
                t::_('%1$s() supports adding permissions only to SELECT statements. "%2$s" statement was provided. Updating records should be done through the respective ActiveRecord or if mass update is needed with UPDATE statement the permissions must be checked manually.'),
                __METHOD__,
                get_class($Statement)
            );
            throw new RunTimeException($message);
        }
        //the tables array contains only tables that use permissions
        $tables = [];
        $tprefix = $Connection::get_tprefix();
        foreach ($Statement->from as $From) {
            $table = $From->table;
            $table = str_replace($tprefix, '', $table);
            if ($class = self::get_class_with_permissions_by_table($table)) {
                if (count($class::get_primary_index_columns()) > 1) {
                    $message = sprintf(
                        t::_('%1$s() supports only classes with a single primary column. "%2$s" has %3$s primary columns.'),
                        __METHOD__,
                        $class,
                        count($class::get_primary_index_columns())
                    );
                }
                //$tables[$class] = $table;
                $tables[] = [
                    'class' => $class,
                    'table' => $table,
                    'alias' => $From->alias,
                ];
            }
        }
        foreach ($Statement->join as $Join) {
            $table = $Join->expr->table;
            $table = str_replace($tprefix, '', $table);
            if ($class = self::get_class_with_permissions_by_table($table)) {
                //check is the join left or right - in this case because the join on the permissions will be INNER
                //it will change the nature of the statement
                if ($Join->type !== $Join::$JOINS['INNER JOIN']) {
                    $message = sprintf(
                        t::_('The table "%1$s" for class "%2$s" which uses permissions is joined by using %3$s JOIN which is not supported. %4$s() supports only %5$s JOIN.'),
                        $table,
                        $class,
                        $Join->type,
                        __METHOD__,
                        $Join::$JOINS['INNER JOIN']
                    );
                    throw new RunTimeException($message);
                }
                //$tables[$class] = $table;
                $tables[] = [
                    'class' => $class,
                    'table' => $table,//without prefix
                    'alias' => $Join->expr->alias,
                ];
            }

        }

        $permission_class = static::get_permission_class();
        $permissions_table = $tprefix.$permission_class::get_main_table();
        /*
        $append_join = '';
        foreach ($tables as $class => $table) {
            $primary_column = $class::get_primary_index_columns()[0];
            $append_join .= "
INNER JOIN
    {$permissions_table} AS permissions_{$table}
    ON permissions_{$table}.object_id = {$tprefix}{$table}.{$primary_column}
    AND permissions_{$table}.class_id = :{$table}_class_id
";
            $b[$table.'_class_id'] = $class::get_class_id();
        }
        */
        $new_params = [];
        foreach ($tables as ['class' => $class, 'table' => $table, 'alias' => $alias]) {
            $primary_column = $class::get_primary_index_columns()[0];
            $Expression = new Expression(null, $permissions_table, null, 'permissions_'.$table);
            $on_arr = [
                new Condition("permissions_{$table}.object_id = {$alias}.{$primary_column}"),
                new Condition("AND"),
                new Condition("permissions_{$table}.class_id = :{$table}_class_id"),
            ];
            $Join = new JoinKeyword(JoinKeyword::$JOINS['INNER JOIN'], $Expression, $on_arr, null);
            $Statement->join[] = $Join;
            //$_parameters[$table.'_class_id'] = $class::get_class_id();
            $new_params[$table.'_class_id'] = $class::get_class_id();
        }
        $new_sql = $Statement->build();

        $cache[$sql_hash]['sql'] = $new_sql;
        $cache[$sql_hash]['params'] = $new_params;

        $_sql = $cache[$sql_hash]['sql'];
        $_parameters += $cache[$sql_hash]['params'];
    }

    private static function get_class_with_permissions_by_table(string $table): ?string
    {
        $ret = null;
        $classes = ActiveRecord::get_classes_by_table($table);
        foreach ($classes as $class) { //these should be only ActiveRecord classes
            if ($class::get_main_table() === $table) {
                if ($class::uses_permissions()) {
                    $ret = $class;
                    break;
                }
            }
        }
        return $ret;
    }

    /**
     * This method should not be used since it is too slow - it executes a subquery for every record that is returned from the main table
     * Returns the join chunk of the SQL query needed to enforce the permissions to be used in a custom query.
     * @param string $main_table The main table from the main query to which the join should be applied
     * @return string The join part of the stamement that needs to be included in the query
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Base\Exceptions\RunTimeException
     * @throws \ReflectionException
     */
    public static function get_sql_permission_check(string $class, string $main_table = 'main_table', string $action = 'read'): string
    {
        throw new DeprecatedException(sprintf(t::_('%1$s is deprecated. Please %2$s::add_permission_checks()'), __METHOD__, __CLASS__ ));

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
