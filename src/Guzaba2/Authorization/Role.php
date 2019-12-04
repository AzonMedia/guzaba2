<?php


namespace Guzaba2\Authorization;


use Guzaba2\Coroutine\Cache;
use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Orm\Exceptions\RecordNotFoundException;
use Guzaba2\Authorization\Rbac\Exceptions\RbacException;
use Guzaba2\Translator\Translator as t;
use Guzaba2\Authorization\Interfaces\PermissionInterface;

/**
 * Class Role
 * @package Guzaba2\Authorization\Rbac
 * @property scalar role_id
 * @property string role_name
 */
class Role extends ActiveRecord
{

    protected const CONFIG_DEFAULTS = [
        'main_table'            => 'roles',
        'route'                 => '/role',

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
     * Creates a new Role and returns it.
     * @param string $name
     * @return ActiveRecord
     */
    public static function create(string $name) : ActiveRecord
    {
        $Role = new self();
        $Role->role_name = $name;
        $Role->save();
        return $Role;
    }

    /**
     * Grants a role (this role inherits the provided role).
     * Returns the new RolesHierarchy object of created relation.
     * @param Role $Role
     * @return ActiveRecord
     */
    public function grant_role(Role $Role) : ActiveRecord
    {
        return RolesHierarchy::create($this, $Role);
    }

    /**
     * @param Role $Role
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

    /**
     * Grants a role (this role inherits the provided role).
     * Returns the new RolePermission object of created relation.
     * @param Permission $Permission
     * @return ActiveRecord
     */
    public function grant_permission(PermissionInterface $Permission) : ActiveRecord
    {
        return RolePermission::create($this, $Permission);
    }

    /**
     * @param Permission $Permission
     */
    public function revoke_permission(PermissionInterface $Permission): void
    {
        try {
            $RolePermission = new RolePermission(['role_id' => $this->get_index(), 'permission_id' => $Permission->get_id()]);
        } catch (RecordNotFoundException $Exception) {
            throw new RbacException(sprintf(t::_('The role %s does not have permission %s.'), $this->role_name, $Permission->permision_name));
        }
        $RoleRoles->delete();
    }

    public function get_roles_tree(): array
    {
        $ret = [];
        $ret[$this->get_id()] = [];
    }

    /**
     * Returns the inherited roles of this role
     * @return array
     */
    public function get_inherited_roles_ids(): array
    {
        return array_column( RolesHierarchy::get_data_by( ['role_id' => $this->get_id() ] ), 'inherited_role_id' );
    }

    /**
     * Returns an array of roles
     * @return array
     */
    public function get_inherited_roles(): array
    {
        $ret = [];
        $ids = $this->get_inherited_roles_ids();
        foreach ($ids as $id) {
            $ret[] = new self($id);
        }
        return $ret;
    }

    /**
     * Returns an indexed array of all inherited roles recursively.
     * @return array
     */
    public function get_all_inherited_roles_ids() : array
    {
        $ret = [];
        $role_id = $this->get_id();
        $ret = self::get_service('ContextCache')->get('all_inherited_roles', $role_id);
        if ($ret === NULL) {
            $ret[] = $role_id;
            foreach ($this->get_inherited_roles() as $InheritedRole) {
                $ret[] = $InheritedRole->get_all_inherited_roles_ids();
            }
            self::get_service('ContextCache')->set('all_inherited_roles', $role_id, $ret);
        }

        return $ret;
    }

    /**
     * Returns an array of all inherited roles recursively.
     * @return array
     */
    public function get_all_inherited_roles() : array
    {
        $ret = [];
        $ids = $this->get_all_inherited_roles_ids();
        foreach ($ids as $id) {
            $ret[] = new self($id);
        }
        return $ret;
    }
}