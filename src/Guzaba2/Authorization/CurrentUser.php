<?php


namespace Guzaba2\Authorization;

use Guzaba2\Base\Base;

class CurrentUser extends Base
{

    protected User $User;

    public function __construct(User $User)
    {
        $this->User;
    }

    public function get() : User
    {
        return $this->User;
    }

    public function substitute(User $User)
    {
        //TODO add ScopeReference too
    }

    public function restore()
    {

    }
}