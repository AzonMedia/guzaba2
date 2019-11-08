<?php


namespace Guzaba2\Authorization;

use Guzaba2\Authorization\Interfaces\AuthorizationProviderInterface;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;

/**
 * Class TrueAuthorizationProvider
 * @package Guzaba2\Authorization\Rbac
 * This provider is used to bypass permission checks - it always returns TRUE.
 */
class BypassAuthorizationProvider implements AuthorizationProviderInterface
{
    public static function role_can(Role $Role, string $action, ActiveRecordInterface $ActiveRecord) : bool
    {
        return TRUE;
    }
}