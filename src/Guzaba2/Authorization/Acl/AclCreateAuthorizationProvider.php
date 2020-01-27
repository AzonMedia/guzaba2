<?php
declare(strict_types=1);

namespace Guzaba2\Authorization\Acl;

use Guzaba2\Authorization\Role;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;

/**
 * Class AclCreateAuthorizationProvider
 * @package Guzaba2\Authorization\Acl
 * Used to create ACL permissions.
 * The AuthorizationProvider interface is to be set to this one when the ACL permissions are setup initially.
 * Or when a new controller is added.
 * It overrides the AclAuthorizationProvider so that all actions are allowed (similar to the BypassAuthorizationProvider but unlike it it allows new permissions to be created).
 */
class AclCreateAuthorizationProvider extends AclAuthorizationProvider
{
    /**
     * @overrides
     * @param Role $Role
     * @param string $action
     * @param ActiveRecordInterface $ActiveRecord
     * @return bool
     */
    public function role_can(Role $Role, string $action, ActiveRecordInterface $ActiveRecord) : bool
    {
        return TRUE;
    }

    public function role_can_on_class(Role $Role, string $action, string $class) : bool
    {
        $ret = TRUE;
    }

    /**
     * @overrides
     * @param string $action
     * @param ActiveRecordInterface $ActiveRecord
     * @return bool
     */
    public function current_role_can(string $action, ActiveRecordInterface $ActiveRecord) : bool
    {
        return TRUE;
    }

    public function current_role_can_on_class(string $action, string $class) : bool
    {
        return TRUE;
    }

    /**
     * @overrides
     * @throws PermissionDeniedException
     * @param string $action
     * @param ActiveRecordInterface $ActiveRecord
     * @return bool
     */
    public function check_permission(string $action, ActiveRecordInterface $ActiveRecord) : void
    {
        return;
    }
}