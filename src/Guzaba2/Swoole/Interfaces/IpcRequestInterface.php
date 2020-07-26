<?php

declare(strict_types=1);

namespace Guzaba2\Swoole\Interfaces;

use Psr\Http\Message\RequestInterface;

interface IpcRequestInterface extends RequestInterface
{
    public function get_source_worker_id(): int;

    public function get_request_id(): string;

    public function requires_response(): bool;

    public function set_requires_response(bool $requires): void;
}
