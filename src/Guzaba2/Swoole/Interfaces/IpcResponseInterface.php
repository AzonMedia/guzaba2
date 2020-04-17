<?php
declare(strict_types=1);

namespace Guzaba2\Swoole\Interfaces;

use Psr\Http\Message\ResponseInterface;

interface IpcResponseInterface extends ResponseInterface
{
    /**
     * For which request is this response
     * @return string
     */
    public function get_request_id(): string ;

    public function get_response_id(): string ;

    public function set_received_microtime(float $received_microtime): void ;

    public function get_received_microtime(): ?float ;
}