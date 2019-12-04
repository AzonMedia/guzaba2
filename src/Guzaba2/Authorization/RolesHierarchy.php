<?php


namespace Guzaba2\Authorization;


use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Translator\Translator as t;

/**
 * Class RoleRoles
 * Represents the roles hierarchy (self reference)
 * @package Guzaba2\Authorization\Rbac
 * @property scalar role_hierarchy_id
 * @property scalar role_id
 * @property scalar inherited_role_id
 */
class RolesHierarchy extends ActiveRecord
{

    protected const CONFIG_DEFAULTS = [
        'main_table'            => 'roles_hierarchy',

        'load_in_memory'        => TRUE,

        'no_permissions'        => TRUE,//the roles do not use permissions

    ];

    protected const CONFIG_RUNTIME = [];

    public static function create(Role $Role, Role $InheritedRole) : ActiveRecord
    {
        if ($Role->is_new() || !$Role->get_id()) {
            throw new InvalidArgumentException(sprintf(t::_('The first argument of %s() is a role that is new or has no ID.'), __METHOD__ ));
        }
        if ($InheritedRole->is_new() || !$InheritedRole->get_id()) {
            throw new InvalidArgumentException(sprintf(t::_('The seconds argument of %s() is a role that is new or has no ID.'), __METHOD__ ));
        }
        $RoleRoles = new self();
        $RoleRoles->role_id = $Role->get_id();
        $RoleRoles->inherited_role_id = $InheritedRole->get_id();
        $RoleRoles->save();
        return $RoleRoles;
    }
}