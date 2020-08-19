<?php

declare(strict_types=1);

namespace Guzaba2\Routing\Interfaces;

use Azonmedia\Routing\Interfaces\RouterInterface;

interface RoutingMiddlewareInterface
{
    public function get_router(): RouterInterface;
}