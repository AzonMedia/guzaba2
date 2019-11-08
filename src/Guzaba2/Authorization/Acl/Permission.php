<?php

namespace Guzaba2\Authorization\Acl;

use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Authorization\Role;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Authorization\Interfaces\PermissionInterface;

/**
 * Class Permission
 * @package Guzaba2\Authorization\Acl
 * @property scalar permission_id
 * @property scalar role_id
 * @property string class_name
 * @property scalar object_id
 * @property string action_name
 * @property string permission_description
 */
class Permission extends ActiveRecord implements PermissionInterface
{
    public static function create(Role $Role, string $action, ActiveRecordInterface $ActiveRecord, string $permission_description = '') : ActiveRecord
    {
        $Permission = new self();
        return $Permission;
    }

    public static function create_class_permission() : ActiveRecord
    {

    }
}