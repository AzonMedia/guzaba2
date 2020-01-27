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
use Guzaba2\Translator\Translator as t;

/**
 * Class Permission
 * @package Guzaba2\Authorization\Acl
 * @property scalar permission_id
 * @property scalar role_id
 * @property string class_name
 * @property scalar object_id
 * @property string action_name
 * @property string permission_description
 *
 * The permission can support directly controllers by setting the controller class and NULL to object_id.
 * The way the controllers are handled though is by creating a controller instance and checking the permission against the controller record.
 * This has the advantage that with a DB query all controller permissions can be pulled, while if the controller class is used directly in the permission record this will not be possible.
 * It will not be known is the permission record for controller or not at the time of the query.
 */
class Permission extends ActiveRecord implements PermissionInterface
{

    protected const CONFIG_DEFAULTS = [
        'main_table'            => 'acl_permissions',
        'route'                 => '/acl-permissions',//no longer possible directly to grant permissions
        //instead the individual routes for the objects are to be used
        //'load_in_memory'        => TRUE,//testing
        'no_permissions'    => TRUE,//the permissions records themselves cant use permissions
    ];

    protected const CONFIG_RUNTIME = [];

    protected function _before_write() : void
    {
        //before creating a Permission record check does the object on which it is created has the appropriate CHOWN permission
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
            print $Permission->role_id.' '.$Permission->class_name.' '.$Permission->object_id.' '.$Permission->action_name.PHP_EOL;
            print gettype($Permission->object_id).PHP_EOL;
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
    public static function create(Role $Role, string $action, ActiveRecordInterface $ActiveRecord, string $permission_description = '') : ActiveRecord
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
    public static function create_class_permission(Role $Role, string $action, string $class_name, string $permission_description='') : ActiveRecord
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