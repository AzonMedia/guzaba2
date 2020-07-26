<?php

declare(strict_types=1);

namespace Guzaba2\Authorization;

use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Orm\Exceptions\RecordNotFoundException;
use Guzaba2\Orm\Exceptions\ValidationFailedException;
use Guzaba2\Orm\Interfaces\ValidationFailedExceptionInterface;
use Guzaba2\Translator\Translator as t;

/**
 * Class RoleRoles
 * Represents the roles hierarchy (self reference)
 * @package Guzaba2\Authorization\Rbac
 * @property int role_hierarchy_id
 * @property int role_id
 * @property int inherited_role_id
 */
class RolesHierarchy extends ActiveRecord
{

    protected const CONFIG_DEFAULTS = [
        'main_table'            => 'roles_hierarchy',
        'route'                 => '/roles-hierarchy',//temporary route

        'load_in_memory'        => true,

        'no_permissions'        => true,//the roles do not use permissions

    ];

    protected const CONFIG_RUNTIME = [];

    public static function create(Role $Role, Role $InheritedRole): self
    {
        if ($Role->is_new() || !$Role->get_id()) {
            throw new InvalidArgumentException(sprintf(t::_('The first argument of %s() is a role that is new or has no ID.'), __METHOD__));
        }
        if ($InheritedRole->is_new() || !$InheritedRole->get_id()) {
            throw new InvalidArgumentException(sprintf(t::_('The seconds argument of %s() is a role that is new or has no ID.'), __METHOD__));
        }
        $RoleRoles = new static();
        $RoleRoles->role_id = $Role->get_id();
        $RoleRoles->inherited_role_id = $InheritedRole->get_id();
        $RoleRoles->write();
        return $RoleRoles;
    }

    public function get_role(): Role
    {
        return new Role($this->role_id);
    }

    public function get_inherited_role(): Role
    {
        return new Role($this->inherited_role_id);
    }

    protected function _validate_role_id(): ?ValidationFailedExceptionInterface
    {
        if (!$this->role_id) {
            return new ValidationFailedException($this, 'role_id', sprintf(t::_('No role_id is provided.'), $this->role_id));
        }
        try {
            $Role = new Role($this->role_id);
        } catch (RecordNotFoundException $Exception) {
            return new ValidationFailedException($this, 'role_id', sprintf(t::_('The provided role_id %1$s does not exist.'), $this->role_id));
        }
        //validate for duplicate record
        try {
            $RolesHierarchy = new static(['role_id' => $this->role_id, 'inherited_role_id' => $this->inherited_role_id]);
            //return new ValidationFailedException($this, 'role_id', sprintf(t::_('The role %1$s already inherits role %2$s.'), $this->role_id, $this->inherited_role_id ));
            return new ValidationFailedException($this, 'role_id', sprintf(t::_('The role %1$s already inherits role %2$s.'), $this->get_role()->role_name, $this->get_inherited_role()->role_name));
        } catch (RecordNotFoundException $Exception) {
            //it is OK
        }
        if ($this->role_id === $this->inherited_role_id) {
            return new ValidationFailedException($this, 'role_id', sprintf(t::_('The role can not inherit itself.')));
        }
        return null;
    }

    protected function _validate_inherited_role_id(): ?ValidationFailedExceptionInterface
    {
        if (!$this->inherited_role_id) {
            return new ValidationFailedException($this, 'inherited_role_id', sprintf(t::_('No inherited_role_id is provided.')));
        }
        try {
            $Role = new Role($this->inherited_role_id);
        } catch (RecordNotFoundException $Exception) {
            return new ValidationFailedException($this, 'role_id', sprintf(t::_('The provided inherited_role_id %1$s does not exist.'), $this->inherited_role_id));
        }
        return null;
    }
}
