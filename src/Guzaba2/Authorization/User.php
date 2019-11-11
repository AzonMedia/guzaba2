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
        'main_table'                => 'users',
        'default_current_user_id'   => 0,//can be ID or UUID
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
                'name' => 'user_name',
                'native_type' => 'varchar',
                'php_type' => 'string',
                'size' => 255,
                'nullable' => false,
                'column_id' => 2,
                'primary' => false,
                'default_value' => '',
            ],
            [
                'name' => 'user_email',
                'native_type' => 'varchar',
                'php_type' => 'string',
                'size' => 255,
                'nullable' => false,
                'column_id' => 3,
                'primary' => false,
                'default_value' => '',
            ],
            [
                'name' => 'user_password',
                'native_type' => 'varchar',
                'php_type' => 'string',
                'size' => 255,
                'nullable' => false,
                'column_id' => 3,
                'primary' => false,
                'default_value' => '',
            ],
            [
                'name' => 'role_id',
                'native_type' => 'int',
                'php_type' => 'integer',
                'size' => 255,
                'nullable' => false,
                'column_id' => 3,
                'primary' => false,
                'default_value' => '',
            ]
        ]
    ];

    protected const CONFIG_RUNTIME = [];

    public static function get_default_current_user_id() /* scalar */
    {
        return self::CONFIG_RUNTIME['default_current_user_id'];
    }

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