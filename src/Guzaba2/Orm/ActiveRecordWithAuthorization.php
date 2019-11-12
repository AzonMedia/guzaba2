<?php


namespace Guzaba2\Orm;

//todo
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Orm\Traits\ActiveRecordAuthorization;

class ActiveRecordWithAuthorization extends ActiveRecord
{
    use ActiveRecordAuthorization;

    protected const CONFIG_DEFAULTS = [
        'services'      => [
            'AuthorizationProvider',
            'CurrentUser',
        ],
        //only for non-sql stores
        'structure' => [

        ],
    ];
    
    protected function read( /* mixed */ $index) : void
    {
        //instead of setting the BypassAuthorizationProvider to bypass the authorization
        //it is possible not to set AuthorizationProvider at all (as this will save a lot of function calls
        if (static::uses_service('AuthorizationProvider')) {
            $this->check_permission('read');
        }
        parent::read($index);
    }

    public function save() : ActiveRecordInterface
    {
        //instead of setting the BypassAuthorizationProvider to bypass the authorization
        //it is possible not to set AuthorizationProvider at all (as this will save a lot of function calls
        if (static::uses_service('AuthorizationProvider')) {
            if ($this->is_new()) {
                $this->check_permission('create');
            } else {
                $this->check_permission('write');
            }
        }

        return parent::save();
    }

    public function delete() : void
    {
        //instead of setting the BypassAuthorizationProvider to bypass the authorization
        //it is possible not to set AuthorizationProvider at all (as this will save a lot of function calls
        if (static::uses_service('AuthorizationProvider')) {
            $this->check_permission('delete');
        }

        parent::delete();
    }


}