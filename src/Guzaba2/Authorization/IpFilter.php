<?php
declare(strict_types=1);

namespace Guzaba2\Authorization;

class IpFilter
{
    /**
     * @var \Swoole\Table
     */
    private $Swoole_table = '';

    /**
     * @var \Swoole\Table rows
     */
    private const MAX_ROWS = 1024;

    /**
     * @var \Swoole\Table struct
     */
    private const DATA_STRUCT = [
        'offense_time'   => [\Swoole\Table::TYPE_INT, 10],
        'offense_type'   => [\Swoole\Table::TYPE_STRING, 20]
    ];

    public function __construct()
    {
        $this->SwooleTable = new \Swoole\Table(self::MAX_ROWS);

        foreach (self::DATA_STRUCT as $key => $type) {
            $this->SwooleTable->column($key, $type[0], $type[1]);
        }
        $this->SwooleTable->create();
    }

    public function ip_is_blacklisted(string $ip) : bool
    {
        // test with blacklisted ip
        // if (empty($this->SwooleTable->get($ip))) {

        //     $this->SwooleTable->set(
        //         $ip,
        //         [
        //             'offense_time' => time(),
        //             'offense_type' => 'ligin_failure'
        //         ]
        //     );
        // }

        // print_r($this->SwooleTable->get($ip));

        if (empty($this->SwooleTable->get($ip))) {
            return FALSE;
        } else {
            return TRUE;
        }
    }
}
