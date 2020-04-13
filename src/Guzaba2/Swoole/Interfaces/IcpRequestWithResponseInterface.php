<?php
declare(strict_types=1);

namespace Guzaba2\Swoole\Interfaces;


interface IcpRequestWithResponseInterface extends IpcRequestInterface
{
    public function get_response(): IpcResponseInterface ;
}