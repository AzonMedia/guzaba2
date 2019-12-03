<?php
declare(strict_types=1);

namespace Guzaba2\Authorization;

use Azonmedia\Di\Interfaces\CoroutineDependencyInterface;
use Azonmedia\Patterns\ScopeReference;
use Guzaba2\Authorization\Interfaces\UserInterface;
use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Orm\Interfaces\ValidationFailedExceptionInterface;

/**
 * Class User
 * @package Guzaba2\Authorization\Rbac
 * It MUST implement the CoroutineDependencyInterface as otherwise the DefaultCurrentUser will persist between the requests
 * @property user_id
 * @property role_id This is the primary role_id. Every user has his own unique role. This role may inherite may roles
 */
class User extends ActiveRecord implements UserInterface, CoroutineDependencyInterface
{

    protected const CONFIG_DEFAULTS = [
        'main_table'                => 'users',
        'route'                     => '/user',
        'validation'                => [
            'user_name'                 => [
                'required'              => TRUE,
                //'max_length'            => 200,//this comes from the DB
            ],
            'role_id'                   => [
                'required'              => TRUE,
                //'validation_method'     => [User::class, '_validate_role_id'],//can be sete explicitly or if there is a static method _validate_role_id it will be executed
            ],
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

    protected function _validate_role_id() : ?ValidationFailedExceptionInterface
    {
        //the primary role role cant be changed
        //the primary role must be a user role
    }

    protected function _validate_user_name() : ?ValidationFailedExceptionInterface
    {

    }

    protected function _before_save() : void
    {
        if ($this->is_new()) {
            //a new primary role needs to be created for this user
            $Role = Role::create($this->user_name);
            $this->role_id = $Role->get_id();
        }
    }

}