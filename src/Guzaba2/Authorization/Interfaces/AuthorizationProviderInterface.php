<?php

namespace Guzaba2\Authorization\Interfaces;

use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Authorization\Role;

/**
 * Interface AuthorizationProviderInterface
 * @package Guzaba2\Authorization\Interfaces
 */
interface AuthorizationProviderInterface
{
    /**
     * Returns a boolean can the provided $role perform the $action on the object $ActiveRecord
     * @param Role $Role
     * @param string $action
     * @param ActiveRecordInterface $ActiveRecord
     * @return bool
     */
    public static function role_can(Role $Role, string $action, ActiveRecordInterface $ActiveRecord) : bool ;
}