<?php

declare(strict_types=1);

namespace Guzaba2\Cache;

use Guzaba2\Base\Base;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Translator\Translator as t;
use Guzaba2\Cache\Interfaces\IntCacheInterface;
use Guzaba2\Cache\Interfaces\ProcessCacheInterface;
use Swoole\Table;
use Psr\Log\LogLevel;

class SwooleTableIntCache extends Base implements IntCacheInterface, ProcessCacheInterface
{

    protected const CONFIG_DEFAULTS = [
        'max_rows'                      => 100000,
        'cleanup_at_percentage_usage'   => 95,//when the cleanup should be triggered
        'cleanup_percentage_records'    => 20,//the percentage of records to be removed
        'services'      => [
            'Events',
        ]
    ];

    protected const CONFIG_RUNTIME = [];

    private Table $SwooleTable;

    protected const CHECK_SWOOLE_TABLE_CACHE_MILLISECONDS = 10000;

    protected ?int $cleanup_timer_id = null;

    public function __construct()
    {
        $this->SwooleTable = new \Swoole\Table(static::CONFIG_RUNTIME['max_rows']);
        $this->SwooleTable->column('int_val', \Swoole\Table::TYPE_INT, 8);
        $this->SwooleTable->create();

        //$ServerInstance = \Swoole\Server::getInstance();
        //if ($ServerInstance) {
        $Server = Kernel::get_http_server();
        if ($Server) {
            $this->start_cleanup_timer();
        } else {
            self::get_service('Events')->add_class_callback(WorkerStart::class, '_after_start', [$this, 'start_cleanup_timer']);
        }
    }

    /**
     * Adds/overwrites data in the cache
     * @param string $prefix
     * @param string $key
     * @param int $data
     */
    public function set(string $prefix, string $key, int $data): void
    {
        $key = $prefix . '.' . $key;
        $data = ['int_val' => $data];
        $this->SwooleTable->set($key, $data);
    }

    /**
     * If no $key is provided everything from the given prefix will be deleted.
     * @param string $prefix
     * @param string $key
     * @throws RunTimeException
     */
    public function delete(string $prefix, string $key): void
    {
        $key = $prefix . '.' . $key;
        $this->SwooleTable->del($key);
    }

    /**
     * Returns NULL if the key is not found.
     * @param string $prefix
     * @param string $key
     * @return int|null
     */
    public function get(string $prefix, string $key): ?int
    {
        $key = $prefix . '.' . $key;
        $ret = $this->SwooleTable->get($key, 'int_val');
        if (!$ret) {
            $ret = null;
        }
        return $ret;
    }

    public function exists(string $prefix, string $key): bool
    {
        $key = $prefix . '.' . $key;
        return $this->SwooleTable->exist($key);
    }

    /**
     *
     */
    public function start_cleanup_timer(): void
    {
        if (null === $this->cleanup_timer_id || (!\Swoole\Timer::exists($this->cleanup_timer_id))) {
            $CleanupFunction = function () {
                $count = $this->SwooleTable->count();
                $cleanedup = 0;

                if ($count >= self::CONFIG_RUNTIME['max_rows'] || ($count / self::CONFIG_RUNTIME['max_rows'] * 100.0 >= self::CONFIG_RUNTIME['cleanup_at_percentage_usage'])) {
                    foreach ($this->SwooleTable as $key => $value) {
                        $this->SwooleTable->del($key);
                        $cleanedup++;
                        $cleanup_percentage = $cleanedup / $count * 100.0;
                        if ($cleanup_percentage >= self::CONFIG_RUNTIME['cleanup_percentage_records']) {
                            break;
                        }
                    }

                    // log only if any cleanup performed
                    $message_log = sprintf(t::_('SwooleTableIntCache %d records found, %d records cleaned up. Records left count: %d'), $count, $cleanedup, $this->SwooleTable->count());
                    Kernel::log($message_log, LogLevel::INFO);
                }
            };

            $this->cleanup_timer_id = \Swoole\Timer::tick(self::CHECK_SWOOLE_TABLE_CACHE_MILLISECONDS, $CleanupFunction);
        }
    }
}
