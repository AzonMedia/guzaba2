<?php
declare(strict_types=1);


namespace Guzaba2\Authorization\Rbac;

use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;

/**
 * Class Operation
 * @package Guzaba2\Authorization
 *
 * Describes an action that can be performed on an object.
 *
 * @property scalar operation_id
 * @property string action_name
 * @property string class_name
 * @property scalar object_id
 */
class Operation extends ActiveRecord
{

    protected const CONFIG_DEFAULTS = [
        'main_table'            => 'rbac_operations',
    ];

    protected const CONFIG_RUNTIME = [];

    public static function create(ActiveRecordInterface $Object, string $action, string $operation_description = '') : ActiveRecord
    {

        if ($Object->is_new() || !$Object->get_id()) {
            //throw
        }

        $Operation = new self();
        $Operation->class_name = get_class($Object);
        $Operation->object_id = $Ojbect->get_id();//depending on the store this may return the primary key or the UUID
        $Operation->operation_description = $operation_description;
        $Operation->write();
        return $Operation;
    }

    /**
     * Creates an operation that allows the action to be performed on all objects of the given class
     * @param string $class_name
     * @param string $action
     */
    public static function create_class_operation(string $class_name, string $action, string $operation_description = '') : ActiveRecord
    {
        $Operation = new self();
        $Operation->class_name = $class_name;
        $Operation->object_id = NULL;
        $Operation->operation_description = $operation_description;
        $Operation->write();
        return $Operation;
    }
}