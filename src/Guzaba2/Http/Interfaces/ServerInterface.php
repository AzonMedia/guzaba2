<?php

declare(strict_types=1);

namespace Guzaba2\Http\Interfaces;

interface ServerInterface
{
    public function start(): void;

    public function stop(): void;

    public function on(string $event_name, callable $callable): void;

    public function is_task_worker(): bool;

    public function get_worker_id(): int;

    public function get_worker_pid(): int;

    public function get_worker(): WorkerInterface;

    public function get_document_root(): ?string;

    public function get_host(): string;

    public function get_port(): int;

    public function get_option(string $option) /* mixed */ ;

    /**
     * Returns all options passed to the Swoole\Http\Server
     * @return array
     */
    public function get_options(): array;
}
