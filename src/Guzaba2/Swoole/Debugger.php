<?php


namespace Guzaba2\Swoole;

use Guzaba2\Base\Base;

class Debugger extends Base
{
    public function __construct()
    {
        parent::__construct();

        ob_implicit_flush();
    }
}
