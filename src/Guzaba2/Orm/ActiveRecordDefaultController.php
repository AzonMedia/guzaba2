<?php


namespace Guzaba2\Orm;

use Guzaba2\Mvc\Controller;
use Guzaba2\Orm\Exceptions\RecordNotFoundException;
use http\Exception\InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

/**
 * Class ActiveRecordDefaultController
 * Provides actions for performing the basic tasks on objects.
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
     * @param string $uuid
     * @return ResponseInterface|null
     */
    public function _init(string $uuid) : ?ResponseInterface
    {
        //it is a very bad idea to assign the ActiveRecord object to a property here and then used in the other methods
        //although it makes sense not to repeat code...
        //$this->ActiveRecord = new someclass($uuid);
        //if there is no circular reference it should be safe
        if (!$uuid) {
            $struct = [];
            $struct['message'] = sprintf(t::_('No UUID provided.'));
            $Response = parent::get_structured_badrequest_response($struct);
        }
        //if () validate UUID

        try {
            $this->ActiveRecord = $this->ActiveRecord::get_by_uuid($uuid);
        } catch (RecordNotFoundException $Exception) {
            $struct = [];
            $struct['message'] = sprintf(t::_('No object with the provided UUID is found.'));
            $Response = parent::get_structured_badrequest_response($struct);
        }
        return $Response ?? NULL;
    }

    /**
     * Used by the GET method - returns info about the object (all properties).
     * @param string $uuid
     * @return ResponseInterface
     */
    public function get(string $uuid) : ResponseInterface
    {
        $struct = [];
        foreach ($this->ActiveRecord as $property => $value) {
            $struct[$property] = $value;
        }
        $Response = parent::get_structured_ok_response($struct);
        return $Response;
    }

    /**
     * Used by the POST method - creates a new record.
     * @return ResponseInterface
     */
    public function create() : ResponseInterface
    {
    }

    /**
     * Used by the PATCH and PUT methods - updates the record.
     * @param string $uuid
     * @return ResponseInterface
     */
    public function update(string $uuid) : ResponseInterface
    {
    }

    /**
     * Used by the DELETE method
     * @param string $uuid
     * @return ResponseInterface
     */
    public function delete(string $uuid) : ResponseInterface
    {
        $this->ActiveRecord->delete();
        $struct['message'] = sprintf(t::_('The object was deleted successfully.'));
        $Response = parent::get_structured_ok_response($struct);
        return $Response;
    }
}
