<?php


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
    public static function create(Permission $Permission, Operation $Operation) /* scalar */
    {
        $PermissionOperation = new self();
        $PermissionOperation->permission_id = $Permission->get_id();
        $PermissionOperation->operation_id = $Operation->get_id();
        $PermissionOperation->save();
        return $PermissionOperation->get_id();
    }
}