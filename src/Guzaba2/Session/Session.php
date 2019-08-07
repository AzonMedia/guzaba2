<?php
declare(strict_types=1);

namespace Guzaba2\Session;

use Guzaba2\Patterns\ExecutionSingleton;

class Session extends ExecutionSingleton
{
    protected function __construct()
    {
        parent::__construct();
    }
}
