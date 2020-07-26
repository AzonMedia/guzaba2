<?php

declare(strict_types=1);

namespace Guzaba2\Authorization\Rbac;

use Guzaba2\Orm\ActiveRecord;

/**
 * Class PermissionOperation
 * @package Guzaba2\Authorization\Rbac
 * @property scalar permission_operation_id
 * @property scalar permission_id
 * @property scalar operation_id
 */
class PermissionOperation extends ActiveRecord
{

    protected const CONFIG_DEFAULTS = [
        'main_table'            => 'rbac_permissions_operations',
    ];

    protected const CONFIG_RUNTIME = [];

    public static function create(Permission $Permission, Operation $Operation): ActiveRecord
    {
        $PermissionOperation = new self();
        $PermissionOperation->permission_id = $Permission->get_id();
        $PermissionOperation->operation_id = $Operation->get_id();
        $PermissionOperation->write();
        return $PermissionOperation;
    }
}
