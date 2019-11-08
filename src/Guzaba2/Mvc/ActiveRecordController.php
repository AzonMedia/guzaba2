<?php


namespace Guzaba2\Mvc;


use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;

/**
 * Class ActiveRecordController
 * @package Guzaba2\Mvc
 * Will be used if the Controllers use permissions.
 * The permissions are based on actions and these match the method names.
 * The Executor when invokes the methods it invokes them with the AUTHZ_METHOD_PREFIX in front of the method name which triggers the method overloading and permissions check
 * @property scalar controller_id
 * @property string controller_name
 * @property string controller_description
 * @property string controller_class
 * @property string controller_routes
 */
class ActiveRecordController extends ActiveRecord
{
    protected const CONFIG_DEFAULTS = [
        'main_table'            => 'controllers',
    ];

    protected const CONFIG_RUNTIME = [];

    /**
     * This in fact overrides the ActiveRecord::get_routes() while here it is used in the context of Controller
     * @return array|null
     */
    public static function get_routes() : ?array
    {
        $ret = NULL;
        $called_class = get_called_class();
        $ActiveRecordController = new self( ['controller_class' => $called_class ] );
        $routes_serialized = $ActiveRecordController->controller_routes;
        if (strlen($routes_serialized)) {
            $ret = unserailize($routes_serialized);
        }
        return $ret;
    }

    public static function create() : ActiveRecordInterface
    {

    }

}