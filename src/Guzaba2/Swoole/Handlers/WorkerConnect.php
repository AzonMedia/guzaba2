<?php

declare(strict_types=1);

namespace Guzaba2\Swoole\Handlers;

use Guzaba2\Authorization\Ip\IpFilter;
use Guzaba2\Kernel\Kernel;

/**
 * Class WorkerConnect
 * Executed at worker connect.
 * Check for offending ips
 * @package Guzaba2\Swoole\Handlers
 */
class WorkerConnect extends HandlerBase
{
    /**
     *
     */
    public $IpFilter;

    public function __construct(\Guzaba2\Http\Server $HttpServer, IpFilter $IpFilter)
    {
        parent::__construct($HttpServer);
        $this->IpFilter = $IpFilter;
    }

    public function handle(\Swoole\Http\Server $Server, int $worker_id)
    {

//        print $worker_id.' '.\Co::getCid().PHP_EOL;
        $remote_ip = $Server->connection_info($worker_id)["remote_ip"];
        //print_r($Server->connection_info());

        if ($this->IpFilter->ip_is_blacklisted($remote_ip)) {
            // echo "ip: {$remote_ip} is blacklisted => close connection\n";
            $Server->close($worker_id);
        } else {
            // echo "ip: {$remote_ip} is OK\n";
            // do nothing; connection may proceed
        }
    }

    public function __invoke(\Swoole\Http\Server $Server, int $worker_id): void
    {
        $this->handle($Server, $worker_id);
    }
}
