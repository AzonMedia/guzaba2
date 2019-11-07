<?php


namespace Guzaba2\Authorization;


use Guzaba2\Coroutine\Coroutine;

class ActiveRecord extends Guzaba2\Orm\ActiveRecord
{

//    protected const CONFIG_DEFAULTS = [
//        'services'      => [
//
//        ],
//    ];
//
//    protected const CONFIG_RUNTIME = [];

    public const AUTHZ_METHOD_PREFIX = 'authz_';

    protected function read() : void
    {
        //TODO check permissions for read
        parent::load();
    }

    public function save() : ActiveRecord
    {
        
        if ($this->is_new()) {
            //check permissions for create action
        } else {
            //check permissions for save action
        }
        
        return parent::save();
    }

    public function delete() : ActiveRecord
    {
        //TODO check permissions for delete action
        return parent::delete();
    }

    public function __call(string $method, array $args) /* mixed */
    {
        if (!strpos($method,self::RBAC_METHOD_PREFIX)===0) {
            //throw
        }
        $clean_method_name = substr($method, strlen(self::AUTHZ_METHOD_PREFIX));
        if (!method_exists($this, $action)) {
            //throw
        }

        //$Request = Coroutine::getContext()->Request;
        //object user_id from $Request
        //self::CurrentUser

        $this->check_permission($action);

        return [$this, $action](...$args);
    }

    /**
     * Will throw if the current role is not allowed to perform the provided $action on this object
     * @param string $action
     */
    public function check_permission(string $action) : void
    {
        if (!$this->current_role_can($action) ) {
            $Role = Coroutine::getContext()->CurrentUser->get_role();
            throw new PermissionDeniedException(sprintf(t::_('Role %s is not allowed to %s object %s:%s.'), $Role->role_name, $action, get_class($this), $this->get_id() ));
        }
    }

    public function current_role_can(string $action) : bool
    {
        $Role = Coroutine::getContext()->CurrentUser->get_role();
        return $this->role_can($Role , $action);
    }

    public function role_can(Role $Role, string $action) : bool
    {
        //get all operations that support that action
        return self::AuthorizationProvider()::role_can($Role, $action, $this);
    }
}