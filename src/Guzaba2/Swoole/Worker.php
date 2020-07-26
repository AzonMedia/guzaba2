<?php

declare(strict_types=1);

namespace Guzaba2\Swoole;

use Guzaba2\Base\Base;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Http\Interfaces\WorkerInterface;

/**
 * Class Worker
 * @package Guzaba2\Swoole
 */
class Worker extends Base implements WorkerInterface
{

    private int $worker_id;

    private int $worker_pid;

    private float $start_microtime;

    private int $served_requests = 0;

    private int $served_pipe_requests = 0;

    private int $served_console_requests = 0;

    private bool $is_task_worker_flag;

    private int $debug_port;

    public function __construct(int $worker_id, int $worker_pid, bool $is_task_worker, int $debug_port)
    {
        $this->worker_id = $worker_id;
        $this->worker_pid = $worker_pid;
        $this->is_task_worker_flag = $is_task_worker;
        $this->debug_port = $debug_port;
        $this->start_microtime = microtime(true);
    }

    public function get_worker_id(): int
    {
        return $this->worker_id;
    }

    public function get_worker_pid(): int
    {
        return $this->worker_pid;
    }

    public function get_start_microtime(): float
    {
        return $this->start_microtime;
    }

    /**
     * For normal workers these are the onRequest while for task workers these are the onTask
     * @return int
     */
    public function get_served_requests(): int
    {
        return $this->served_requests;
    }

    public function get_served_pipe_requests(): int
    {
        return $this->served_pipe_requests;
    }

    public function get_served_console_requests(): int
    {
        return $this->served_console_requests;
    }

    public function is_task_worker(): bool
    {
        return $this->is_task_worker_flag;
    }

    public function get_debug_port(): int
    {
        return $this->debug_port;
    }

    /**
     * Returns a list of requests currently being served by this worker.
     * @return array
     */
    public function get_current_requests(): array
    {
        return Coroutine::getCoroutineRequestsStatus();
    }

    public function increment_served_requests(): void
    {
        $this->served_requests++;
    }

    public function increment_served_pipe_requests(): void
    {
        $this->served_pipe_requests++;
    }

    public function increment_served_console_requests(): void
    {
        $this->served_console_requests++;
    }
}
