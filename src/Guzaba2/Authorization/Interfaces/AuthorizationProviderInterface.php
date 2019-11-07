<?php

namespace Guzaba2\Authorization\Interfaces;

use Guzaba2\Orm\Interfaces\ActiveRecordInterface;

interface AuthorizationProviderInterface
{
    public static function role_can(Role $Role, string $action, ActiveRecordInterface $ActiveRecord) : bool ;
}