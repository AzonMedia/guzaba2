<?php

declare(strict_types=1);

namespace Guzaba2\Authorization;

use Azonmedia\Exceptions\InvalidArgumentException;
use Azonmedia\Patterns\ScopeReference;
use Guzaba2\Authorization\Exceptions\PermissionDeniedException;
use Guzaba2\Authorization\Interfaces\PermissionInterface;
use Guzaba2\Authorization\Interfaces\UserInterface;
use Guzaba2\Base\Exceptions\LogicException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Kernel\Exceptions\ConfigurationException;
use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Orm\Exceptions\MultipleValidationFailedException;
use Guzaba2\Orm\Exceptions\RecordNotFoundException;
use Guzaba2\Orm\Exceptions\ValidationFailedException;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Orm\Interfaces\ValidationFailedExceptionInterface;
use Guzaba2\Translator\Translator as t;
use ReflectionException;

/**
 * Class User
 * @package Guzaba2\Authorization\Rbac
 * @property int user_id
 * @property string user_name
 * @property string user_email
 * @property string user_password
 * @property int role_id This is the primary role_id. Every user has his own unique role. This role may inherite may roles
 */
class User extends ActiveRecord implements UserInterface
{

    protected const CONFIG_DEFAULTS = [
        'main_table'                => 'users',
        'route'                     => '/user',
        'validation'                => [
            'user_name'                 => [
                'required'              => true,
                //'max_length'            => 200,//this comes from the DB
            ],
            'role_id'                   => [
                'required'              => true,
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

    protected Role $Role;


    /**
     * Returns the primary role of the user
     * @return Role
     * @throws InvalidArgumentException
     * @throws \Guzaba2\Base\Exceptions\InvalidArgumentException
     * @throws LogicException
     * @throws RunTimeException
     * @throws ConfigurationException
     * @throws ReflectionException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     */
    public function get_role(): Role
    {
        //return new Role((int) $this->role_id);
        //the below is faster (as it happens get_role() to get tens or even hundreds of times) but creates an additional reference
        //as long as this reference is not cyclic it should be fine for the GC
        if (!isset($this->Role)) {
            $this->Role = new Role((int) $this->role_id, true);//must be a read only as otherwise there will be a lock and an error thrown when the role object is destroyed.. the coroutine context is no longer available.
        }
        return $this->Role;
    }

//    /**
//     * Returns FALSE if the user already has this permission.
//     * @param Permission $Permission
//     * @param ScopeReference $ScopeReference
//     * @return bool
//     */
//    public function grant_temporary_permission(PermissionInterface $Permission, ScopeReference $ScopeReference) : bool
//    {
//
//    }
//
//    public function revoke_temporary_permission(ScopeReference $ScopeReference) : void
//    {
//
//    }

    /**
     * @check_permissions
     * @param Role $Role
     * @return RolesHierarchy Returns the newly created RolesHierarchy (roles relation) record
     * @throws ConfigurationException
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws ReflectionException
     * @throws RunTimeException
     * @throws \Guzaba2\Base\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     */
    public function grant_role(Role $Role): RolesHierarchy
    {
        $this->check_permission('grant_role');
        return $this->get_role()->grant_role($Role);
    }

    /**
     * @check_permissions
     * @param Role $Role
     * @throws ConfigurationException
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws ReflectionException
     * @throws RunTimeException
     * @throws \Guzaba2\Base\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     */
    public function revoke_role(Role $Role): void
    {
        $this->check_permission('revoke_role');
        $this->get_role()->revoke_role($Role);
    }

    /**
     * Alias of self::inherits_role()
     * @param Role $Role
     * @return bool
     */
    public function is_member_of(Role $Role): bool
    {
        return $this->inherits_role($Role);
    }

    public function inherits_role(Role $Role): bool
    {
        return $this->get_role()->inherits_role($Role);
    }

    /**
     * @return ValidationFailedExceptionInterface|null
     * @throws ConfigurationException
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws ReflectionException
     * @throws RunTimeException
     * @throws \Guzaba2\Base\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     */
    protected function _validate_role_id(): ?ValidationFailedExceptionInterface
    {
        //the primary role role cant be changed
        //the primary role must be a user role
        if (!$this->role_id) {
            return new ValidationFailedException($this, 'role_id', sprintf(t::_('There is no role_id set for user %1$s.'), $this->user_name));
        } else {
            try {
                $Role = new Role($this->role_id);
                if (!$Role->role_is_user) {
                    return new ValidationFailedException($this, 'role_id', sprintf(t::_('The role %1$s set for user %2$s is not a user role (role_us_user must be set to true).'), $Role->role_name, $this->user_name));
                }
                return null;
            } catch (RecordNotFoundException $Exception) {
                return new ValidationFailedException($this, 'role_id', sprintf(t::_('The role_id %1$s set for user %2$s does not exist.'), $this->role_id, $this->user_name));
            } //Roles do not use permissions so no need to catch PermissionDeniedException
        }
    }

    /**
     * @return ValidationFailedExceptionInterface|null
     * @throws ConfigurationException
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws ReflectionException
     * @throws RunTimeException
     * @throws \Guzaba2\Base\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     */
    protected function _validate_user_name(): ?ValidationFailedExceptionInterface
    {
        if ($this->is_new() || $this->is_property_modified('user_name')) {
            try {
                $User = new static(index: ['user_name' => $this->user_name], permission_checks_disabled: true);
                if ($User->get_id() !== $this->get_id()) {
                    return new ValidationFailedException($this, 'user_name', sprintf(t::_('There is already a user with user name "%1$s".'), $this->user_name));
                }
            } catch (RecordNotFoundException $Exception) {
                return null;
            } catch (PermissionDeniedException $Exception) {
                return new ValidationFailedException($this, 'user_name', sprintf(t::_('There is already a user with user name "%1$s".'), $this->user_name));
            }
        }
        return null;
    }

    /**
     * @return ValidationFailedExceptionInterface|null
     * @throws ConfigurationException
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws ReflectionException
     * @throws RunTimeException
     * @throws \Guzaba2\Base\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     */
    protected function _validate_user_email(): ?ValidationFailedExceptionInterface
    {
        if (!filter_var($this->user_email, FILTER_VALIDATE_EMAIL)) {
            return new ValidationFailedException($this, 'user_email', sprintf(t::_('The provided email "%1$s" is not valid.'), $this->user_email));
        } elseif ($this->is_new() || $this->is_property_modified('user_email')) {
            try {
                $User = new static(index: ['user_email' => $this->user_email], permission_checks_disabled: true);
                if ($User->get_id() !== $this->get_id()) {
                    return new ValidationFailedException($this, 'user_email', sprintf(t::_('There is already a user with email "%1$s".'), $this->user_email));
                }
            } catch (RecordNotFoundException $Exception) {
                return null;
            } catch (PermissionDeniedException $Exception) {
                return new ValidationFailedException($this, 'user_email', sprintf(t::_('There is already a user with email "%1$s".'), $this->user_email));
            }
        }
        return null;
    }

    /**
     * @throws ConfigurationException
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws ReflectionException
     * @throws RunTimeException
     * @throws \Guzaba2\Base\Exceptions\InvalidArgumentException
     * @throws MultipleValidationFailedException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     */
    protected function _before_write(): void
    {
        if ($this->is_new()) {
            //a new primary role needs to be created for this user
            $Role = Role::create($this->user_name, true);
            $this->role_id = $Role->get_id();
        }
        if ($this->is_property_modified('user_name')) {
            //needs to update the role name as well
            //as this is part of a transaction it doesnt really matter if it is done before or after the user is saved
            //it will all get rolled back on failure
            //$Role = $this->get_role();
            $Role = new Role((int) $this->role_id);
            $Role->role_name = $this->user_name;
            $Role->write();
        }
    }


    /**
     * @check_permissions
     * @throws InvalidArgumentException
     * @throws MultipleValidationFailedException
     * @throws RunTimeException
     * @throws \Guzaba2\Base\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     */
    public function enable(): void
    {
        $Transaction = ActiveRecord::new_transaction($TR);
        $Transaction->begin();

        $this->check_permission('enable');
        $this->user_is_disabled = false;
        $this->write();

        $this->add_log_entry('enable', sprintf(t::_('The user %1$s was enabled.'), $this->user_name));

        $Transaction->commit();
    }

    /**
     * @check_permissions
     * @throws InvalidArgumentException
     * @throws MultipleValidationFailedException
     * @throws RunTimeException
     * @throws \Guzaba2\Base\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     */
    public function disable(): void
    {
        $Transaction = ActiveRecord::new_transaction($TR);
        $Transaction->begin();

        $this->check_permission('disable');
        $this->user_is_disabled = true;
        $this->write();

        $this->add_log_entry('enable', sprintf(t::_('The user %1$s was enabled.'), $this->user_name));

        $Transaction->commit();
    }
}
