<?php


namespace Guzaba2\Mvc;

use Guzaba2\Base\Base;
use Psr\Http\Message\ResponseInterface;

class View extends Base
{
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
}
