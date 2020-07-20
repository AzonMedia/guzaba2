<?php
declare(strict_types=1);

namespace Guzaba2\Authorization\Acl;

use Guzaba2\Authorization\Exceptions\PermissionDeniedException;
use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Authorization\Role;
use Guzaba2\Orm\Exceptions\RecordNotFoundException;
use Guzaba2\Orm\Exceptions\ValidationFailedException;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Authorization\Interfaces\PermissionInterface;
use Guzaba2\Orm\Store\Sql\Mysql;
use Guzaba2\Orm\Store\Store;
use Guzaba2\Translator\Translator as t;

/**
 * Class Permission
 * @package Guzaba2\Authorization\Acl
 * @property scalar permission_id
 * @property scalar role_id
 * @property string class_id
 * @property scalar object_id
 * @property string action_name
 * @property string permission_description
 *
 * The permission can support directly controllers by setting the controller class and NULL to object_id.
 * The way the controllers are handled though is by creating a controller instance and checking the permission against the controller record.
 * This has the advantage that with a DB query all controller permissions can be pulled, while if the controller class is used directly in the permission record this will not be possible.
 * It will not be known is the permission record for controller or not at the time of the query.
 *
 * Because when publically exposed the Permission object must be able to accept class_name (instead of class_id), object_uuid (instead of object_id) and role_uuid (for role_id)
 * these are also defined as class properties.
 */
class Permission extends ActiveRecord implements PermissionInterface
{

    protected const CONFIG_DEFAULTS = [
        'main_table'            => 'acl_permissions',
        'route'                 => '/acl-permission',
        //instead the individual routes for the objects are to be used
        //'load_in_memory'        => TRUE,//testing
        'no_permissions'    => TRUE,//the permissions records themselves cant use permissions
        'services'          => [

        ],
    ];

    protected const CONFIG_RUNTIME = [];

    /**
     * Can be used instead of class_id
     * @var string
     */
    public string $class_name = '';

    /**
     * Can be used instead of object_id
     * @var string
     */
    public string $object_uuid = '';

    /**
     * Can be used instead of role_id
     * @var string
     */
    public string $role_uuid = '';

    protected function _after_read(): void
    {
        $this->class_name = self::get_class_name($this->class_id);
        /** @var Store $OrmStore */
        $OrmStore = self::get_service('OrmStore');
        $this->object_uuid = $OrmStore->get_meta_by_id($this->class_name, $this->object_id)['meta_object_uuid'];

        $this->role_uuid = (new Role($this->role_id))->get_uuid();
    }

    protected function _before_set_class_id(?int $class_id): ?int
    {
        if ($class_id) {
            $this->class_name = self::get_class_name($class_id);
        }
        return $class_id;
    }

    protected function _before_set_class_name(?string $class_name): ?string
    {
        if ($class_name) {
            $this->class_id = self::get_class_id($class_name);
        }
        return $class_name;
    }

    protected function _before_set_object_id(?int $object_id): ?int
    {
        if ($object_id) {
            /** @var Store $OrmStore */
            $OrmStore = self::get_service('OrmStore');
            $class_name = $this->class_name;
            if (!$class_name && $this->class_id) {
                $class_name = self::get_class_name($this->class_id);
            }
            if ($class_name) {
                $this->object_uuid = $OrmStore->get_meta_by_id($this->class_name, $object_id)['meta_object_uuid'];
            }
        }
        return $object_id;
    }

    protected function _before_set_object_uuid(?string $object_uuid): ?string
    {
        if ($object_uuid) {
            /** @var Store $OrmStore */
            $OrmStore = self::get_service('OrmStore');
            $this->object_id = $OrmStore->get_meta_by_uuid($object_uuid)['meta_object_id'];
        }
        return $object_uuid;
    }

    protected function _before_set_role_id(?int $role_id): ?int
    {
        if ($role_id) {
            $this->role_uuid = (new Role($role_id))->get_uuid();
        }
        return $role_id;
    }

    protected function _before_set_role_uuid(?string $role_uuid): ?string
    {
        if ($role_uuid) {
            $this->role_id = (new Role($role_uuid))->get_id();
        }
        return $role_uuid;
    }

    protected function _before_write() : void
    {
        if (!$this->class_id && $this->class_name) {
            $this->class_id = self::get_class_id($this->class_name);
        }

        if (!$this->class_name) {
            throw new ValidationFailedException($this, 'class_name', sprintf(t::_('No class name provided.')));
        }
        if (!class_exists($this->class_name)) {
            throw new ValidationFailedException($this, 'class_name', sprintf(t::_('The class %s does not exist.'), $this->class_name));
        }
        if (!$this->action_name) {
            throw new ValidationFailedException($this, 'action_name', sprintf(t::_('No action name provided.')));
        }
        if (!method_exists($this->class_name, $this->action_name) && $this->action_name !== 'create' ) {
            throw new ValidationFailedException($this, 'action_name', sprintf(t::_('The class %s does not have a method %s.'), $this->class_name, $this->action_name));
        }

        if (!$this->object_id && $this->object_uuid) {
            /** @var Store $OrmStore */
            $OrmStore = self::get_service('OrmStore');
            $this->object_id = $OrmStore->get_meta_by_uuid($this->object_uuid)['meta_object_id'];
        }

        if (!$this->role_id && $this->role_uuid) {
            $this->role_id = (new Role($this->role_uuid))->get_id();
        }

        //before creating a Permission record check does the object on which it is created has the appropriate grnat_permission permission
        try {
            if ($this->object_id === NULL) {
                $class_name = $this->class_name;
                $class_name::check_class_permission('grant_permission');
            } else {
                //(new $this->class_name($this->object_id))->check_permission('chmod');
                (new $this->class_name($this->object_id))->check_permission('grant_permission');
            }
        } catch (RecordNotFoundException $Exception) {
            throw new PermissionDeniedException(sprintf(t::_('You are not allowed to change the permissions on %s:%s.'), $this->class_name, $this->object_id));
        }


        if ( $this->action_name !== 'create') {
            if (! (new \ReflectionMethod($this->class_name, $this->action_name) )->isPublic() ) {
                throw new ValidationFailedException($this, 'action_name', sprintf(t::_('The method %s::%s is not public. The methods to which permissions are granted/associated must be public.'), $this->class_name, $this->action_name));
            }
        }

        //TODO - may add locking here that is released in after_save
        try {
            $Permission = new self( [
                'role_id'       => $this->role_id,
                'class_name'    => $this->class_name,
                'object_id'     => $this->object_id,
                'action_name'   => $this->action_name,
            ] );

            throw new ValidationFailedException($this, 'role_id,class_name,object_id,action_name', sprintf(t::_('There is already an ACL permission records for the same role, class, object_id and action.')));
        } catch (RecordNotFoundException $Exception) {
            //no duplicates
        }

    }

    protected function _before_delete() : void
    {
        try {
            //(new $this->class_name($this->object_id))->check_permission('chmod');
            if ($this->object_id === NULL) {
                $class_name = $this->class_name;
                $class_name::check_class_permission('revoke_permission');
            } else {
                (new $this->class_name($this->object_id))->check_permission('revoke_permission');
            }

        } catch (RecordNotFoundException $Exception) {
            throw new PermissionDeniedException(sprintf(t::_('You are not allowed to change the permissions on %s:%s.'), $this->class_name, $this->object_id));
        }
    }

    /**
     * @param Role $Role
     * @param string $action
     * @param ActiveRecordInterface $ActiveRecord
     * @param string $permission_description
     * @return ActiveRecord
     * @throws \Guzaba2\Base\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Base\Exceptions\RunTimeException
     * @throws \Guzaba2\Kernel\Exceptions\ConfigurationException
     */
    public static function create(Role $Role, string $action, ActiveRecordInterface $ActiveRecord, string $permission_description = '') : ActiveRecordInterface
    {
        $Permission = new self();
        $Permission->role_id = $Role->get_id();
        $Permission->class_name = get_class($ActiveRecord);
        $Permission->object_id = $ActiveRecord->get_id();
        $Permission->action_name = $action;
        $Permission->permission_description = $permission_description;
        $Permission->write();
        return $Permission;
    }

    /**
     * This is a permission valid for all objects from the given class.
     * To be used for "create" action on models and the controller actions.
     * Or it can be used like a privilege to grant the $action on all objects of the given $class_name.
     * @param Role $Role
     * @param string $action
     * @param string $class_name
     * @param string $permission_description
     * @return ActiveRecord
     * @throws \Guzaba2\Base\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Base\Exceptions\RunTimeException
     * @throws \Guzaba2\Kernel\Exceptions\ConfigurationException
     */
    public static function create_class_permission(Role $Role, string $action, string $class_name, string $permission_description='') : ActiveRecordInterface
    {
        $Permission = new self();
        $Permission->role_id = $Role->get_id();
        $Permission->class_name = $class_name;
        $Permission->object_id = NULL;
        $Permission->action_name = $action;
        $Permission->permission_description = $permission_description;
        $Permission->write();
        return $Permission;
    }
}