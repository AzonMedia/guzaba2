<?php
declare(strict_types=1);

namespace Guzaba2\Session;

use Guzaba2\Patterns\RequestSingleton;

class Session extends RequestSingleton
{
    protected function __construct()
    {
        parent::__construct();
    }
}
