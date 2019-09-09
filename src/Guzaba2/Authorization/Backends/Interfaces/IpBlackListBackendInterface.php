<?php

namespace Guzaba2\Authorization\Backends\Interfaces;

interface IpBlackListBackendInterface
{
    public function getBlacklistedIps() : array ;
}
