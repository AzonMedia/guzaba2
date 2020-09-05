<?php

declare(strict_types=1);

namespace Guzaba2\Swoole;

use Guzaba2\Base\Base;
use Guzaba2\Base\Interfaces\ConfigInterface;
use Guzaba2\Base\Interfaces\ObjectInternalIdInterface;
use Guzaba2\Base\Interfaces\UsesServicesInterface;
use Guzaba2\Base\Traits\SupportsConfig;
use Guzaba2\Base\Traits\SupportsObjectInternalId;
use Guzaba2\Base\Traits\UsesServices;
use Guzaba2\Http\Response;
use Guzaba2\Swoole\Interfaces\IpcResponseInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

class IpcResponse extends Response implements IpcResponseInterface, ConfigInterface, ObjectInternalIdInterface, UsesServicesInterface
{

    use SupportsConfig;
    use SupportsObjectInternalId;
    use UsesServices;

    protected const CONFIG_DEFAULTS = [
        'services'      => [
            'Server',
        ]
    ];

    protected const CONFIG_RUNTIME = [];

    private string $request_id;

    private ?float $received_microtime = null;

    /**
     * The ID of the worker producing the response
     * @var int
     */
    private int $source_worker_id;

    public function __construct(ResponseInterface $Response, string $request_id)
    {
        $this->request_id = $request_id;
        /** @var Server $Server */
        $Server = self::get_service('Server');
        $this->source_worker_id = $Server->get_worker_id();
        foreach ($Response as $property => $value) {
            $this->{$property} = $value;
        }
    }

    public function get_source_worker_id(): int
    {
        return $this->source_worker_id;
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
