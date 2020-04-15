<?php


namespace Guzaba2\Swoole;


use Guzaba2\Http\Method;
use Guzaba2\Swoole\Interfaces\IcpRequestWithResponseInterface;
use Guzaba2\Swoole\Interfaces\IpcResponseInterface;

class IpcRequestWithResponse extends IpcRequest implements IcpRequestWithResponseInterface
{

    private IpcResponseInterface $IpcResponse;

    public function __construct(IpcResponseInterface $IpcResponse)
    {
        $this->IpcResponse = $IpcResponse;
        $method = Method::HTTP_POST;
        $route = '/';
        parent::__construct($method, $route);
    }

    public function get_response(): IpcResponseInterface
    {
        return $this->IpcResponse;
    }
}