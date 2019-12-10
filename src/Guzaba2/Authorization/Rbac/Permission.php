<?php
declare(strict_types=1);

namespace Guzaba2\Authorization\Rbac;

use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Orm\Exceptions\RecordNotFoundException;

/**
 * Class Permission
 * @package Guzaba2\Authorization\Rbac
 * @property scalar permission_id
 * @property string permission_name
 */
class Permission extends ActiveRecord
{
    protected const CONFIG_DEFAULTS = [
        'main_table'            => 'rbac_operations',
    ];

    protected const CONFIG_RUNTIME = [];

    public static function create(string $permission_name) : ActiveRecord
    {
        $Permission = new self();
        $Permission->permission_name = $permission_name;
        $Permission->save();
        return $Permission;
    }

    protected function _before_save() : void
    {
        //check for duplicate
        if (!$this->permission_name) {
            //throw validation
        }
        try {
            $Permission = new self( ['permission_name' => $this->permission_name]);
            //throw validaton
        } catch (RecordNotFoundException $Exception) {
            //there is no duplicate
        }
    }
}