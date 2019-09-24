<?php
declare(strict_types=1);

namespace Guzaba2\Database;

use Guzaba2\Base\Base;
use Guzaba2\Database\ConnectionFactory;
use Guzaba2\Database\ConnectionProviders\Pool;
use Guzaba2\Coroutine\Coroutine;
use Azonmedia\Glog\Application\MysqlConnection;

class ConnectionMonitor extends Base
{
    protected const CONFIG_DEFAULTS = [
        'services'      => [
            'ConnectionFactory'
        ],
        'ping_time' => 3, // sec
    ];

    protected const CONFIG_RUNTIME = [];

    public function __construct()
    {
        parent::__construct();
    }

    public function monitor()
    {
        $stats = self::ConnectionFactory()->get_connections(MysqlConnection::class);

        if (!empty($stats['available_connections'])) {
            foreach ($stats['available_connections'] as $conn) {
                try {
                    $conn->ping();
                } catch (\Exception $exception) {
                    $conn->initialize();
                }
            }
        }

        \Swoole\Coroutine\System::sleep(self::CONFIG_RUNTIME['ping_time']);

        // recursion for forever running coroutine
        $this->monitor();
    }
}
