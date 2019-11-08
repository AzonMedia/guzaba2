<?php


namespace Guzaba2\Authorization;

use Azonmedia\Patterns\ScopeReference;
use Guzaba2\Orm\ActiveRecord;

/**
 * Class User
 * @package Guzaba2\Authorization\Rbac
 * @property user_id
 * @property role_id This is the primary role_id. Every user has his own unique role. This role may inherite may roles
 */
class User extends ActiveRecord
{

    protected const CONFIG_DEFAULTS = [
        'main_table'            => 'users',
    ];

    protected const CONFIG_RUNTIME = [];

    /**
     * Returns the primary role of the user
     * @return Role
     */
    public function get_role() : Role
    {
        return new Role( (int) $this->role_id);
    }

    /**
     * Returns FALSE if the user already has this permission.
     * @param Permission $Permission
     * @param ScopeReference $ScopeReference
     * @return bool
     */
    public function grant_temporary_permission(Permission $Permission, ScopeReference $ScopeReference) : bool
    {

    }

    public function revoke_temporary_permission(ScopeReference $ScopeReference) : void
    {

    }
}