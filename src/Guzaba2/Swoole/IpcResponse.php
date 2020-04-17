<?php
declare(strict_types=1);

namespace Guzaba2\Swoole;


use Guzaba2\Base\Base;
use Guzaba2\Http\Response;
use Guzaba2\Swoole\Interfaces\IpcResponseInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

class IpcResponse extends Response implements IpcResponseInterface
{

    private string $request_id;

    private ?float $received_microtime = NULL;

    public function __construct(ResponseInterface $Response, string $request_id)
    {
        $this->request_id = $request_id;
        foreach ($Response as $property=>$value) {
            $this->{$property} = $value;
        }
    }

    /**
     * For which request is this response
     * @return string
     */
    public function get_request_id(): string
    {
        return $this->request_id;
    }

    public function get_response_id(): string
    {
        return $this->get_object_internal_id();
    }

    public function set_received_microtime(float $received_microtime): void
    {
        $this->received_microtime = $received_microtime;
    }

    public function get_received_microtime(): ?float
    {
        return $this->received_microtime;
    }
}