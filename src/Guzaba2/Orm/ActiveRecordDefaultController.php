<?php


namespace Guzaba2\Orm;

use Guzaba2\Http\Body\Structured;
use Guzaba2\Http\Response;
use Guzaba2\Http\StatusCode;
use Guzaba2\Mvc\Controller;
use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Orm\Exceptions\RecordNotFoundException;
use Guzaba2\Translator\Translator as t;
use Guzaba2\Http\Method;
use http\Exception\InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;


/**
 * Class ActiveRecordDefaultController
 * Provides CRUD actions for performing the basic tasks on objects.
 * The objects are retrived by their UUID.
 * The routes are set by the @see ActiveRecord::get_default_routes() and these are to be provided to the Router (merged with the application specific routes).
 * @package Guzaba2\Orm
 */
class ActiveRecordDefaultController extends Controller
{

    /**
     * @var ActiveRecord
     */
    protected ActiveRecord $ActiveRecord;

    /**
     * Instantiates the ActiveRecord object.
     * May return Response if there is an error.
     * @param string|null $uuid
     * @return ResponseInterface|null
     */
    public function _init(?string $uuid = NULL) : ?ResponseInterface
    {

        $route_meta_data = $this->get_request()->getAttribute('route_meta_data');

        if (!$uuid) {
            if ($this->get_request()->getMethodConstant() === Method::HTTP_POST) {
                //means a new record is to be created
                if (!empty($route_meta_data['orm_class'])) {
                    $this->ActiveRecord = new $route_meta_data['orm_class'](0);
                } else {
                    $struct = [];
                    $struct['message'] = sprintf(t::_('The accessed route %s does not correspond to an ActiveRecord class.'), $this->get_request()->getUri()->getPath());
                    $Response = parent::get_structured_badrequest_response($struct);
                    return $Response;
                }

            } elseif ($this->get_request()->getMethodConstant() !== Method::HTTP_OPTIONS) {
                //manipulation of an existing record is requested but no UUID is provided
                $struct = [];
                $struct['message'] = sprintf(t::_('No UUID provided.'));
                $Response = parent::get_structured_badrequest_response($struct);
                return $Response;
            }

        } else {
            try {
                $this->ActiveRecord = ActiveRecord::get_by_uuid($uuid);
            } catch (RecordNotFoundException $Exception) {
                $struct = [];
                $struct['message'] = sprintf(t::_('No object with the provided UUID %s is found.'), $uuid);
                $Response = parent::get_structured_badrequest_response($struct);
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
    public function read(string $uuid) : ResponseInterface
    {

        $struct = [];

        $struct = $this->ActiveRecord->as_array();
        //$struct = $this->ActiveRecord;//also works
        $Response = parent::get_structured_ok_response($struct);
        return $Response;
    }

    /**
     * Used by the POST method - creates a new record.
     * Does not declare any arguments as these vary for each AcrtiveRecord class.
     * Instead these are obtained internally with $this->get_request()->getParsedBody();
     * @return ResponseInterface
     */
    public function create() : ResponseInterface
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
        $body_arguments = $this->ActiveRecord::cast_data_to_property_types($body_arguments);
        foreach ($body_arguments as $property_name=>$property_value) {
            if (!$this->ActiveRecord::has_property($property_name)) {
                $message = sprintf(t::_('The ActiveRecord class %s has no property %s.'), get_class($this->ActiveRecord), $property_name);
                $Response = self::get_structured_badrequest_response(['message' => $message]);
                return $Response;
            }

            if (in_array($property_name, $primary_index) && empty($property_value)) {
                continue;
            }

            $this->ActiveRecord->{$property_name} = $property_value;
        }

        $this->ActiveRecord->save();
        $id = $this->ActiveRecord->get_id();
        $uuid = $this->ActiveRecord->get_uuid();
        $message = sprintf(t::_('A new object of class %s was created with ID %s and UUID %s.'), get_class($this->ActiveRecord), $id, $uuid );
        $struct = [ 'message' => $message, 'id' => $id, 'uuid' => $uuid, 'operation' => 'created' ];
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
    public function update(string $uuid) : ResponseInterface
    {
        $body_arguments = $this->get_request()->getParsedBody();
        $body_arguments = $this->ActiveRecord::cast_data_to_property_types($body_arguments);
        foreach ($body_arguments as $property_name=>$property_value) {
            if (!$this->ActiveRecord::has_property($property_name)) {
                $message = sprintf(t::_('The ActiveRecord class %s has no property %s.'), get_class($this->ActiveRecord), $property_name);
                $Response = self::get_structured_badrequest_response(['message' => $message]);
                return $Response;
            }
            $this->ActiveRecord->{$property_name} = $property_value;
        }

        $this->ActiveRecord->save();
        $id = $this->ActiveRecord->get_id();
        $uuid = $this->ActiveRecord->get_uuid();
        $message = sprintf(t::_('The object with ID %s and UUID %s of class %s was updated.'), $id, $uuid, get_class($this->ActiveRecord) );
        $struct = [ 'message' => $message, 'id' => $id, 'uuid' => $uuid, 'operation' => 'updated' ];
        $Response = self::get_structured_ok_response( $struct );
        return $Response;

    }

    /**
     * Used by the DELETE method
     * @param string $uuid
     * @return ResponseInterface
     */
    public function delete(string $uuid) : ResponseInterface
    {
        $uuid = $this->ActiveRecord->get_uuid();
        $id = $this->ActiveRecord->get_id();
        $this->ActiveRecord->delete();
        $message = sprintf(t::_('The object with ID %s and UUID %s of class %s was deleted.'), $id, $uuid, get_class($this->ActiveRecord) );
        $Response = parent::get_structured_ok_response( ['message' => $message, 'id' => $id, 'uuid' => $uuid, 'operation' => 'deleted'] );
        return $Response;
    }

    public function __destruct()
    {
        //$this->ActiveRecord = NULL;
        unset($this->ActiveRecord);
        parent::__destruct(); // TODO: Change the autogenerated stub
    }
}
