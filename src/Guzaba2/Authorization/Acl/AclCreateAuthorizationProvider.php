<?php

declare(strict_types=1);

namespace Guzaba2\Authorization\Acl;

use Guzaba2\Authorization\Role;
use Guzaba2\Database\Sql\Interfaces\ConnectionInterface;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;

/**
 * Class AclCreateAuthorizationProvider
 * @package Guzaba2\Authorization\Acl
 * Used to create ACL permissions.
 * The AuthorizationProvider interface is to be set to this one when the ACL permissions are setup initially.
 * Or when a new controller is added.
 * It overrides the AclAuthorizationProvider so that all actions are allowed
 * (similar to the BypassAuthorizationProvider but unlike it it allows new permissions to be created).
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
    public function role_can(
        Role $Role,
        string $action,
        ActiveRecordInterface $ActiveRecord,
        ?int &$permission_denied_reason = null
    ): bool {
        return true;
    }

    public function role_can_on_class(
        Role $Role,
        string $action,
        string $class,
        ?int &$permission_denied_reason = null
    ): bool {
        $ret = true;
    }

    /**
     * @overrides
     * @param string $action
     * @param ActiveRecordInterface $ActiveRecord
     * @return bool
     */
    public function current_role_can(
        string $action,
        ActiveRecordInterface $ActiveRecord,
        ?int &$permission_denied_reason = null
    ): bool {
        return true;
    }

    public function current_role_can_on_class(
        string $action,
        string $class,
        ?int &$permission_denied_reason = null
    ): bool {
        return true;
    }

    /**
     * @overrides
     * @param string $action
     * @param ActiveRecordInterface $ActiveRecord
     * @return bool
     * @throws PermissionDeniedException
     */
    public function check_permission(string $action, ActiveRecordInterface $ActiveRecord): void
    {
        return;
    }

    public static function get_sql_permission_check(
        string $class,
        string $main_table = 'main_table',
        string $action = 'read'
    ): string {
        return '';
    }

    public function add_sql_permission_checks(
        string &$_sql,
        array &$_parameters,
        string $action,
        ConnectionInterface $Connection
    ): void {
        //do nothing
    }

    /**
     * @overrides
     * {@inheritDoc}
     * @return bool
     */
    public static function checks_permissions(): bool
    {
        return false;
    }
}
