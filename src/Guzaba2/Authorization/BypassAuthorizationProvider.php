<?php


namespace Guzaba2\Authorization;

use Guzaba2\Authorization\Interfaces\AuthorizationProviderInterface;
use Guzaba2\Authorization\Traits\AuthorizationProviderTrait;
use Guzaba2\Base\Base;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;

/**
 * Class TrueAuthorizationProvider
 * @package Guzaba2\Authorization\Rbac
 * This provider is used to bypass permission checks - it always returns TRUE.
 */
class BypassAuthorizationProvider extends Base implements AuthorizationProviderInterface
{

    public function role_can(Role $Role, string $action, ActiveRecordInterface $ActiveRecord) : bool
    {
        return TRUE;
    }

    /**
     * Returns a boolean can the provided $role perform the $action on the object $ActiveRecord
     * @param string $action
     * @param ActiveRecordInterface $ActiveRecord
     * @return bool
     */
    public function current_role_can(string $action, ActiveRecordInterface $ActiveRecord) : bool
    {
        return TRUE;
    }

    /**
     * Checks can the provided $role perform the $action on the object $ActiveRecord and if cant a PermissionDeniedException is thrown
     * @throws PermissionDeniedException
     * @param string $action
     * @param ActiveRecordInterface $ActiveRecord
     * @return bool
     */
    public function check_permission(string $action, ActiveRecordInterface $ActiveRecord) : void
    {
        return;
    }

    public static function get_used_active_record_classes() : array
    {
        return [];
    }
}