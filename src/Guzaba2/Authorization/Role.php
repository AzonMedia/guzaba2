<?php
declare(strict_types=1);


namespace Guzaba2\Authorization;


use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\LogicException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Coroutine\Cache;
use Guzaba2\Kernel\Exceptions\ConfigurationException;
use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Orm\Exceptions\MultipleValidationFailedException;
use Guzaba2\Orm\Exceptions\RecordNotFoundException;
use Guzaba2\Authorization\Rbac\Exceptions\RbacException;
use Guzaba2\Translator\Translator as t;
use Guzaba2\Authorization\Interfaces\PermissionInterface;
use ReflectionException;

/**
 * Class Role
 * @package Guzaba2\Authorization\Rbac
 * @property int role_id
 * @property bool role_is_user
 * @property string role_name
 */
class Role extends ActiveRecord
{

    protected const CONFIG_DEFAULTS = [
        'main_table'            => 'roles',
        'route'                 => '/roles',

        //'load_in_memory'        => TRUE,

        'no_permissions'        => TRUE,//the roles do not use permissions

        'services'              => [
            'ContextCache'
        ],

        'structure' => [
            [
                'name' => 'object_uuid',
                'native_type' => 'varchar',
                'php_type' => 'string',
                'size' => 1,
                'nullable' => false,
                'column_id' => 1,
                'primary' => true,
                'autoincrement' => false,
                'default_value' => 0,
            ],
            [
                'name' => 'role_name_name',
                'native_type' => 'varchar',
                'php_type' => 'string',
                'size' => 200,
                'nullable' => false,
                'column_id' => 2,
                'primary' => false,
                'default_value' => '',
            ]
        ],


    ];

    protected const CONFIG_RUNTIME = [];

    /**
     * @return Role[]
     * @throws RunTimeException
     */
    public static function get_system_roles(): array
    {
        return array_map(fn(array $record): self => new self($record['role_id']), self::get_system_roles_data() );
    }

    /**
     * @return array
     * @throws RunTimeException
     */
    public static function get_system_roles_data(): array
    {
        return self::get_data_by( ['role_is_user' => 0] );
    }

    /**
     * @return int[]
     * @throws RunTimeException
     */
    public static function get_system_roles_ids(): iterable
    {
        return array_map(fn(array $record): string => $record['role_id'], self::get_system_roles_data() );
    }

    /**
     * @return string[]
     * @throws RunTimeException
     */
    public static function get_system_roles_uuids(): iterable
    {
        return array_map(fn(array $record): string => $record['meta_object_uuid'], self::get_system_roles_data() );
    }

    /**
     * Creates a new Role and returns it.
     * @param string $role_name
     * @param bool $role_is_user Is this a user role
     * @return ActiveRecord
     * @throws ConfigurationException
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws MultipleValidationFailedException
     * @throws ReflectionException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    public static function create(string $role_name, bool $role_is_user) : ActiveRecord
    {
        $Role = new self();
        $Role->role_name = $role_name;
        $Role->role_is_user = $role_is_user;
        $Role->write();
        return $Role;
    }

    /**
     * Grants a role (this role inherits the provided role).
     * Returns the new RolesHierarchy object of created relation.
     * @param Role $Role
     * @return ActiveRecord
     * @throws InvalidArgumentException
     */
    public function grant_role(Role $Role) : ActiveRecord
    {
        return RolesHierarchy::create($this, $Role);
    }

    /**
     * @param Role $Role
     * @throws RbacException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws RunTimeException
     * @throws ReflectionException
     */
    public function revoke_role(Role $Role): void
    {
        try {
            $RoleHierarchy = new RoleHierarchy(['role_id' => $this->get_index(), 'inherited_role_id' => $Role->get_id()]);
        } catch (RecordNotFoundException $Exception) {
            throw new RbacException(sprintf(t::_('The role %s does not inherit role %s.'), $this->role_name, $Role->role_name));
        }
        //TODO add a check for circular reference
        //the roles graph must be an acyclic graph
        $RoleHierarchy->delete();

    }

    public function get_roles_tree(): array
    {
        $ret = [];
        $ret[$this->get_id()] = [];
    }

    /**
     * Returns the inherited roles of this role
     * @return int[]
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws RunTimeException
     * @throws ReflectionException
     */
    public function get_inherited_roles_ids(): array
    {
        return array_column( RolesHierarchy::get_data_by( ['role_id' => $this->get_id() ] ), 'inherited_role_id' );
    }

    /**
     * @return string[]
     * @throws ConfigurationException
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws ReflectionException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    public function get_inherited_roles_uuids(): array
    {
        //this is not that slow considering there will be not many roles that are inherited
        //and that these objects will be cached in memory
//        $uuids = [];
//        $roles = $this->get_inherited_roles();
//        foreach ($roles as $Role) {
//            $uuids[] = $Role->get_uuid();
//        }
//        return $uuids;
        return array_map(fn (Role $Role) : string => $Role->get_uuid(), $this->get_inherited_roles() );
    }

    /**
     * Returns an array of roles
     * @return Role[]
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws RunTimeException
     * @throws ConfigurationException
     * @throws ReflectionException
     */
    public function get_inherited_roles(): array
    {
//        $ret = [];
//        $ids = $this->get_inherited_roles_ids();
//        foreach ($ids as $id) {
//            $ret[] = new static($id);
//        }
//        return $ret;
        return array_map(fn (int $role_id) : Role => new static($role_id), $this->get_inherited_roles() );
    }

    /**
     * Returns an indexed array of all inherited roles recursively (including this role id).
     * @return int[]
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws RunTimeException
     * @throws ConfigurationException
     * @throws ReflectionException
     */
    public function get_all_inherited_roles_ids(): array
    {
        $role_id = $this->get_id();
        $ret = self::get_service('ContextCache')->get('all_inherited_roles', (string) $role_id);
        if ($ret === NULL) {
            $ret[] = $role_id;
            foreach ($this->get_inherited_roles() as $InheritedRole) {
                $ret[] = $InheritedRole->get_all_inherited_roles_ids();
            }
            self::get_service('ContextCache')->set('all_inherited_roles', (string) $role_id, $ret);
        }

        return $ret;
    }

    /**
     * @return string[]
     * @throws ConfigurationException
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws ReflectionException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    public function get_all_inherited_roles_uuids(): array
    {
//        $uuids = [];
//        $roles = $this->get_all_inherited_roles();
//        foreach ($roles as $Role) {
//            $uuids[] = $Role->get_uuid();
//        }
//        return $uuids;

        return array_map(fn (Role $Role) : string => $Role->get_uuid(), $this->get_all_inherited_roles() );
    }

    /**
     * Returns an array of all inherited roles recursively.
     * @return Role[]
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws RunTimeException
     * @throws ConfigurationException
     * @throws ReflectionException
     */
    public function get_all_inherited_roles() : array
    {
//        $ret = [];
//        $ids = $this->get_all_inherited_roles_ids();
//        foreach ($ids as $id) {
//            $ret[] = new static($id);
//        }
//        return $ret;
        return array_map(fn (Role $Role) : string => $Role->get_uuid(), $this->get_all_inherited_roles() );
    }


    public function get_inheriting_roles(): array
    {
        return array_map(fn (int $role_id) : Role => new static($role_id), $this->get_inheriting_roles() );
    }

    /**
     * @return int[]
     * @throws ConfigurationException
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws ReflectionException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    public function get_inheriting_roles_ids(): array
    {
        //return array_column( RolesHierarchy::get_data_by( ['inherited_role_id' => $this->get_id() ] ), 'role_id' );
        //we need to filter out the user roles
        //in this basic class this will be done by using the ORM classes & methods only without direct (storage dependent) queries
        //if faster implementation is needed it is to be provided by a child class
        $ids = [];
        $all_ids = array_column( RolesHierarchy::get_data_by( ['inherited_role_id' => $this->get_id() ] ), 'role_id' );
        foreach ($all_ids as $id) {
            $Role = new Role($id);
            if (!$Role->role_is_user) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    /**
     * @return string[]
     */
    public function get_inheriting_roles_uuids(): array
    {
        return array_map(fn (Role $Role) : string => $Role->get_uuid(), $this->get_inheriting_roles() );
    }

    /**
     * @return Role[]
     * @throws ReflectionException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    public function get_all_inheriting_roles(): array
    {
        return array_map(fn (int $role_id) : Role => new static($role_id), $this->get_all_inheriting_roles_ids() );
    }

    /**
     * Returns all roles (excluding user roles) that inherit this role
     * @return array
     * @throws ReflectionException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    public function get_all_inheriting_roles_ids(): array
    {
        $role_id = $this->get_id();
        $ret = self::get_service('ContextCache')->get('all_inheriting_roles', (string) $role_id);
        if ($ret === NULL) {
            $ret = [];
            if (!$this->role_is_user) {
                $ret[] = $role_id;
                foreach ($this->get_inheriting_roles() as $InheritedRole) {
                    $ret[] = $InheritedRole->get_all_inheriting_roles_ids();
                }
            }
            self::get_service('ContextCache')->set('all_inheriting_roles', (string) $role_id, $ret);
        }
        return $ret;
    }

    public function get_all_inheriting_roles_uuids(): array
    {
        return array_map(fn (Role $Role) : string => $Role->get_uuid(), $this->get_inheriting_roles() );
    }
}