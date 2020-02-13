<?php
declare(strict_types=1);

namespace Guzaba2\Orm;

use Guzaba2\Authorization\Exceptions\PermissionDeniedException;
use Guzaba2\Authorization\Role;
use Guzaba2\Http\Body\Structured;
use Guzaba2\Http\Response;
use Guzaba2\Http\StatusCode;
use Guzaba2\Mvc\ActiveRecordController;
use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Orm\Exceptions\RecordNotFoundException;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Translator\Translator as t;
use Guzaba2\Http\Method;
use http\Exception\InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Guzaba2\Kernel\Kernel;


/**
 * Class ActiveRecordDefaultController
 * Provides CRUD actions for performing the basic tasks on objects.
 * The objects are retrived by their UUID.
 * The routes are set by the @see ActiveRecord::get_default_routes() and these are to be provided to the Router (merged with the application specific routes).
 * @package Guzaba2\Orm
 */
class ActiveRecordDefaultController extends ActiveRecordController
{

    protected const CONFIG_DEFAULTS = [
        'route'                 => '/admin/crud-operations',
        //'structure' => []//TODO add structure
        //'controllers_use_db'    => FALSE,
    ];

    protected const CONFIG_RUNTIME = [];

    /**
     * @var ActiveRecord
     */
    protected ActiveRecord $ActiveRecord;

    /**
     * Instantiates the ActiveRecord object.
     * May return Response if there is an error.
     * @param string|null $uuid
     * @param string|null $class_name
     * @return ResponseInterface|null
     */
    public function _init(?string $uuid = NULL, ?string $crud_class_name = NULL, ?string $language = NULL) : ?ResponseInterface
    //public function _init(?string $uuid = NULL) : ?ResponseInterface
    {

        if ($language) {
            t::set_target_language($language);
        }

        $route_meta_data = $this->get_request()->getAttribute('route_meta_data');

        if (!$uuid) {

            if ($crud_class_name) {
                if (strpos($crud_class_name, '-')) {
                    $crud_class_name = str_replace('-','\\', $crud_class_name);
                }
            } elseif (!empty($route_meta_data['orm_class'])) {
                $crud_class_name = $route_meta_data['orm_class'];
            }

            if (!$crud_class_name) {
                $struct = [];
                $struct['message'] = sprintf(t::_('The accessed route %s does not correspond to an ActiveRecord class and no $class_name was provided.'), $this->get_request()->getUri()->getPath());
                //$struct['message'] = sprintf(t::_('The accessed route %s does not correspond to an ActiveRecord class.'), $this->get_request()->getUri()->getPath());
                $Response = parent::get_structured_badrequest_response($struct);
                $Response = $Response->withHeader('data-origin','orm-specific');
                return $Response;
            }

            if ( in_array($this->get_request()->getMethodConstant(), [Method::HTTP_POST, Method::HTTP_GET, Method::HTTP_OPTIONS ] , TRUE)  ) {
                //$this->ActiveRecord = new $route_meta_data['orm_class']();
                $this->ActiveRecord = new $crud_class_name();
            } else {
                //manipulation of an existing record is requested but no UUID is provided
                $struct = [];
                $struct['message'] = sprintf(t::_('No UUID provided.'));
                $Response = parent::get_structured_badrequest_response($struct);
                $Response = $Response->withHeader('data-origin','orm-specific');
                return $Response;
            }

        } else {
            try {
                $this->ActiveRecord = ActiveRecord::get_by_uuid($uuid);
            } catch (RecordNotFoundException $Exception) {
                $struct = [];
                $struct['message'] = sprintf(t::_('No object with the provided UUID %s is found.'), $uuid);
                $Response = parent::get_structured_badrequest_response($struct);
                $Response = $Response->withHeader('data-origin','orm-specific');
                return $Response;
            } catch (PermissionDeniedException $Exception) {
                $struct = [];
                $struct['message'] = sprintf(t::_('You are not allowed to read the object with UUID %s.'), $uuid);
                $Response = parent::get_structured_badrequest_response($struct);
                $Response = $Response->withHeader('data-origin','orm-specific');
                return $Response;
            }
        }
        return NULL;
    }

    /**
     * Used by the OPTIONS method without uuid - returns true
     * @return ResponseInterface
     */
    public function options() : ResponseInterface
    {
        $struct = [true];
        $Response = parent::get_structured_ok_response($struct);
        return $Response;
    }

    /**
     * Used by the GET method - returns info about the object (all properties).
     * @param string $uuid
     * @return ResponseInterface
     */
    public function crud_action_read(string $uuid) : ResponseInterface
    {

        $struct = [];

        $struct = $this->ActiveRecord->as_array();
        //$struct = $this->ActiveRecord;//also works
        $Response = parent::get_structured_ok_response($struct);
        $Response = $Response->withHeader('data-origin','orm-specific');
        return $Response;
    }

    /**
     * Used by the POST method - creates a new record.
     * Does not declare any arguments as these vary for each AcrtiveRecord class.
     * Instead these are obtained internally with $this->get_request()->getParsedBody();
     * @return ResponseInterface
     */
    public function crud_action_create() : ResponseInterface
    {

        //because this method handles multiple types of records the expected params can not be listed in the method signature
        $body_arguments = $this->get_request()->getParsedBody();

        if ($body_arguments === NULL) {
            $struct = [];
            $struct['message'] = sprintf(t::_('The provided request could not be parsed.'));
            $Response = parent::get_structured_badrequest_response($struct);
            return $Response;
        }

        $primary_index = $this->ActiveRecord::get_primary_index_columns();
        //Kernel::dump($body_arguments);
        $body_arguments = $this->ActiveRecord::fix_data_arr_empty_values_type($body_arguments);
        //Kernel::dump($body_arguments);
        $columns_data = $this->ActiveRecord::get_columns_data();

        foreach ($body_arguments as $property_name=>$property_value) {
            if ($property_name === 'crud_class_name') {
                continue;
            }
            if (!$this->ActiveRecord::has_property($property_name) ) {
                $message = sprintf(t::_('The ActiveRecord class %s has no property %s.'), get_class($this->ActiveRecord), $property_name);
                $Response = self::get_structured_badrequest_response(['message' => $message]);
                return $Response;
            }

            if (in_array($property_name, $primary_index) && empty($property_value)) {
                continue;
            }

            $this->ActiveRecord->{$property_name} = $property_value;
        }

        $this->ActiveRecord->write();
        $id = $this->ActiveRecord->get_id();
        $uuid = $this->ActiveRecord->get_uuid();
        $message = sprintf(t::_('A new object of class %s was created with ID %s and UUID %s.'), get_class($this->ActiveRecord), $id, $uuid );
        $struct = [
            'message'   => $message,
            //'class'     => get_class($this->ActiveRecord),
            //'id'        => $id,
            //'uuid'      => $uuid,
            'operation' => 'create',
        ];
        $struct += self::form_object_struct($this->ActiveRecord);
        //$Response = self::get_structured_ok_response( $struct );
        $Response = new Response(StatusCode::HTTP_CREATED, [], new Structured($struct));
        return $Response;
    }

    /**
     * Used by the PATCH and PUT methods - updates the record.
     * Does not declare any arguments besides the $uuid as these vary for each AcrtiveRecord class.
     * Instead these are obtained internally with $this->get_request()->getParsedBody();
     * @param string $uuid
     * @return ResponseInterface
     */
    public function crud_action_update(string $uuid) : ResponseInterface
    {
        $body_arguments = $this->get_request()->getParsedBody();
        $body_arguments = $this->ActiveRecord::fix_data_arr_empty_values_type($body_arguments);
        $columns_data = $this->ActiveRecord::get_columns_data();
        foreach ($body_arguments as $property_name=>$property_value) {
            if ($property_name === 'crud_class_name') {
                continue;
            }
            if (!$this->ActiveRecord::has_property($property_name)) {
                $message = sprintf(t::_('The ActiveRecord class %s has no property %s.'), get_class($this->ActiveRecord), $property_name);
                $Response = self::get_structured_badrequest_response(['message' => $message]);
                return $Response;
            }

            if ($columns_data[$property_name]['php_type'] == "integer") {
                $property_value = (int) $property_value;
            }

            $this->ActiveRecord->{$property_name} = $property_value;
        }

        $this->ActiveRecord->write();
        $id = $this->ActiveRecord->get_id();
        $uuid = $this->ActiveRecord->get_uuid();
        $message = sprintf(t::_('The object with ID %s and UUID %s of class %s was updated.'), $id, $uuid, get_class($this->ActiveRecord) );
        $struct = [
            'message' => $message,
            //'id' => $id,
            //'uuid' => $uuid,
            'operation' => 'update'
        ];
        $struct += self::form_object_struct($this->ActiveRecord);
        $Response = self::get_structured_ok_response( $struct );
        return $Response;

    }

    /**
     * Used by the DELETE method
     * @param string $uuid
     * @return ResponseInterface
     */
    public function crud_action_delete(string $uuid) : ResponseInterface
    {
        //$uuid = $this->ActiveRecord->get_uuid();
        //$id = $this->ActiveRecord->get_id();

        $message = sprintf(t::_('The object with ID %s and UUID %s of class %s was deleted.'), $this->ActiveRecord->get_id(), $this->ActiveRecord->get_uuid(), get_class($this->ActiveRecord) );
        $struct = [
            'message'       => $message,
            //'id'            => $id,
            //'uuid'          => $uuid,
            'operation'     => 'delete'
        ];
        $struct += self::form_object_struct($this->ActiveRecord);
        $this->ActiveRecord->delete();
        $Response = parent::get_structured_ok_response( $struct );
        return $Response;
    }

    public function crud_grant_permission(string $role_uuid, string $action_name) : ResponseInterface
    {
        $Role = new Role($role_uuid);
        $Permission = $this->ActiveRecord->grant_permission($Role, $action_name);
        if ($Permission) {
            $message = sprintf(t::_('The permission to execute %s on object of class %s with ID %s and UUID %s was granted.'), $action_name, get_class($this->ActiveRecord), $this->ActiveRecord->get_id(), $this->ActiveRecord->get_uuid() );
            $struct = [
                'message'           => $message,
//                'class'             => get_class($this->ActiveRecord),
//                'id'                => $this->ActiveRecord->get_id(),
//                'uuid'              => $this->ActiveRecord->get_uuid(),
                'operation'         => 'grant_permission',
                //'action'    => $action,
                //'role_uuid' => $role_uuid,
                'permission_id'     => $Permission->get_id(),
                'permission_uuid'   => $Permission->get_uuid(),

            ];
        } else {
            $message = sprintf(t::_('The class %s does not use permissions, no action was taken.'), get_class($this->ActiveRecord) );
            $struct = [
                'message'           => $message,
//                'class'             => get_class($this->ActiveRecord),
//                'id'                => $this->ActiveRecord->get_id(),
//                'uuid'              => $this->ActiveRecord->get_uuid(),
                'operation'         => 'grant_permission',
            ];
        }
        $struct += self::form_object_struct($this->ActiveRecord);
        $Response = parent::get_structured_ok_response($struct);
        return $Response;
    }

    public function crud_grant_class_permission(string $role_uuid, string $action_name) : ResponseInterface
    {
        $Role = new Role($role_uuid);
        $Permission = $this->ActiveRecord->grant_class_permission($Role, $action_name);
        if ($Permission) {
            $message = sprintf(t::_('The permission to execute %s on all objects of class %s was granted.'), $action_name, get_class($this->ActiveRecord) );
            $struct = [
                'message'           => $message,
                //'class'             => get_class($this->ActiveRecord),
                'operation'         => 'grant_permission',
                'permission_id'     => $Permission->get_id(),
                'permission_uuid'   => $Permission->get_uuid(),

            ];
        } else {
            $message = sprintf(t::_('The class %s does not use permissions, no action was taken.'), get_class($this->ActiveRecord) );
            $struct = [
                'message'           => $message,
                //'class'             => get_class($this->ActiveRecord),
                'operation'         => 'grant_permission',
            ];
        }
        $struct += self::form_object_struct($this->ActiveRecord);
        $Response = parent::get_structured_ok_response($struct);
        return $Response;
    }

    public function crud_revoke_permission(string $role_uuid, string $action_name): ResponseInterface
    {
        $Role = new Role($role_uuid);
        $this->ActiveRecord->revoke_permission($Role, $action_name);
        if ( $this->ActiveRecord::uses_service('AuthorizationProvider') && $this->ActiveRecord::uses_permissions() ) {
            $message = sprintf(t::_('The permission to execute %s on object of class %s with ID %s and UUID %s was revoked.'), $action_name, get_class($this->ActiveRecord), $this->ActiveRecord->get_id(), $this->ActiveRecord->get_uuid() );
            $struct = [
                'message'           => $message,
                //'class'             => get_class($this->ActiveRecord),
                //'id'                => $this->ActiveRecord->get_id(),
                //'uuid'              => $this->ActiveRecord->get_uuid(),
                'operation'         => 'revoke_permission',

            ];
        } else {
            $message = sprintf(t::_('The class %s does not use permissions, no action was taken.'), get_class($this->ActiveRecord) );
            $struct = [
                'message'           => $message,
                //'class'             => get_class($this->ActiveRecord),
                //'id'                => $this->ActiveRecord->get_id(),
                //'uuid'              => $this->ActiveRecord->get_uuid(),
                'operation'         => 'revoke_permission',
            ];
        }
        $struct += self::form_object_struct($this->ActiveRecord);
        $Response = parent::get_structured_ok_response($struct);
        return $Response;
    }

    public function crud_revoke_class_permission() : ResponseInterface
    {
        $Role = new Role($role_uuid);
        $this->ActiveRecord->revoke_class_permission($Role, $action_name);
        if ( $this->ActiveRecord::uses_service('AuthorizationProvider') && $this->ActiveRecord::uses_permissions() ) {
            $message = sprintf(t::_('The permission to execute %s on all objects of class %s was revoked.'), $action_name, get_class($this->ActiveRecord) );
            $struct = [
                'message'           => $message,
                //'class'             => get_class($this->ActiveRecord),
                'operation'         => 'revoke_permission',

            ];
        } else {
            $message = sprintf(t::_('The class %s does not use permissions, no action was taken.'), get_class($this->ActiveRecord) );
            $struct = [
                'message'           => $message,
                //'class'             => get_class($this->ActiveRecord),
                'operation'         => 'revoke_permission',
            ];
        }
        $struct += self::form_object_struct($this->ActiveRecord);
        $Response = parent::get_structured_ok_response($struct);
        return $Response;
    }

    //TODO implement pagination
    public function list(int $offset = 0, int $limit = 0) : ResponseInterface
    {
        $data = $this->ActiveRecord::get_data_by([]);
        $Response = parent::get_structured_ok_response($data);
        return $Response;
    }

    private static function form_object_struct(ActiveRecordInterface $ActiveRecord) : array
    {
        $ret = [];
        $ret['class'] = get_class($ActiveRecord);
        if (!$ActiveRecord->is_new()) {
            $ret['id'] = $ActiveRecord->get_id();
            $ret['uuid'] = $ActiveRecord->get_uuid();
        }
        return $ret;
    }

    public function __destruct()
    {
        //$this->ActiveRecord = NULL;
        // unset($this->ActiveRecord);
        parent::__destruct(); // TODO: Change the autogenerated stub
    }
}
