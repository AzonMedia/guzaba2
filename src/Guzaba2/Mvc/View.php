<?php
declare(strict_types=1);


namespace Guzaba2\Mvc;

use Guzaba2\Base\Base;
use Psr\Http\Message\ResponseInterface;

class View extends Base
{
    /**
     * @var ResponseInterface
     */
    protected $Response;

    public function __construct(ResponseInterface $Response)
    {
        parent::__construct();

        $this->Response = $Response;
    }

    public function get_response() : ResponseInterface
    {
        return $this->Response;
    }

    public function get_structure() : array
    {
        return $this->get_response()->getBody()->getStructure();
    }
}
