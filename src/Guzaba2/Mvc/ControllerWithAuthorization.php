<?php


namespace Guzaba2\Mvc;


use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Translator\Translator as t;
use Psr\Http\Message\RequestInterface;

class ControllerWithAuthorization extends Controller
{

    private ActiveRecordController $ActiveRecordController;

    public function __construct(RequestInterface $Request)
    {
        $this->ActiveRecordController = new ActiveRecordController( ['controller_class' => get_class($this) ] );
    }

    public function get_active_record_controller() : ActiveRecordController
    {
        return $this->ActiveRecordController;
    }

    public static function get_routes() : ?array
    {
        //this triggers ActiveRecord instance creation before server start (not in coroutine context
        /*
        $called_class = get_called_class();
        $ActiveRecordController = new ActiveRecordController( ['controller_class' => $called_class ] );
        $ret = $ActiveRecordController::get_routes();
        if ($ret === NULL) {
            $ret = parent::get_routes();
        }
        */
        $ret = parent::get_routes();
        return $ret;
    }

    public function check_permission(string $action) : void
    {
        if (!$this->ActiveRecordController->current_role_can($action) ) {
            $Role = Coroutine::getContext()->CurrentUser->get_role();
            throw new PermissionDeniedException(sprintf(t::_('Role %s is not allowed to run action %s on controller %s.'), $Role->role_name, $action, get_class($this) ));
        }
    }

    public function current_role_can(string $action) : bool
    {
        $Role = Coroutine::getContext()->CurrentUser->get_role();
        return $this->ActiveRecordController->role_can($Role , $action);
    }

    public function role_can(Role $Role, string $action) : bool
    {
        //get all operations that support that action
        return static::get_service('AuthorizationProvider')::role_can($Role, $action, $this->ActiveRecordController);
    }
}