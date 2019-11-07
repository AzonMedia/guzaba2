<?php


namespace Guzaba2\Authorization\Rbac;


use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Orm\Exceptions\RecordNotFoundException;
use Guzaba2\Authorization\Rbac\Exceptions\RbacException;
use Guzaba2\Translator\Translator as t;

/**
 * Class Role
 * @package Guzaba2\Authorization\Rbac
 * @property scalar role_id
 * @property string role_name
 */
class Role extends ActiveRecord
{
    public static function create(string $name) /* scalar */
    {
        $Role = new self();
        $Role->role_name = $name;
        $Role->save();
        return $Role->get_id();
    }

    public function grant_role(Role $Role) /* scalar */
    {
        return RoleRoles::create($this, $Role);
    }

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

    public function grant_permission(Permission $Permission) /* scalar */
    {
        return RolePermission::create($this, $Permission);
    }

    public function revoke_permission(Permission $Permission): void
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
     * Returns an indexed array of all inherited roles and their inherited roles.
     * @return array
     */
    public function get_all_inherited_roles_ids() : array
    {
        $ret = [];
        $ret[] = $this->get_id();
        foreach ($this->get_inherited_roles() as $InheritedRole) {
            $ret[] = $InheritedRole->get_all_inherited_roles_ids();
        }
        return $ret;
    }

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