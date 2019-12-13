<?php
declare(strict_types=1);


namespace Guzaba2\Cache;


use Guzaba2\Base\Base;
use Guzaba2\Cache\Interfaces\IntCacheInterface;
use Guzaba2\Cache\Interfaces\ProcessCacheInterface;
use Swoole\Table;

class SwooleTableIntCache extends Base implements IntCacheInterface, ProcessCacheInterface
{

    protected const CONFIG_DEFAULTS = [
        'max_rows'                      => 100000,
        'cleanup_at_percentage_usage'   => 95,//when the cleanup should be triggered
        'cleanup_percentage_records'    => 20,//the percentage of records to be removed
    ];

    protected const CONFIG_RUNTIME = [];

    private Table $SwooleTable;

    public function __construct()
    {
        $this->SwooleTable = new \Swoole\Table(static::CONFIG_RUNTIME['max_rows']);
        $this->SwooleTable->column('int_val', \Swoole\Table::TYPE_INT, 8);
        $this->SwooleTable->create();
    }

    /**
     * Adds/overwrites data in the cache
     * @param string $key
     * @param $data
     * @throws RunTimeException
     */
    public function set(string $prefix, string $key, int $data) : void
    {
        $key = $prefix.'.'.$key;
        $data = ['int_val' => $data];
        $this->SwooleTable->set($key, $data);
    }

    /**
     * If no $key is provided everything from the given prefix will be deleted.
     * @param string $prefix
     * @param string $key
     * @throws RunTimeException
     */
    public function delete(string $prefix, string $key ) : void
    {
        $key = $prefix.'.'.$key;
        $this->SwooleTable->del($key);
    }

    /**
     * Returns NULL if the key is not found.
     * @param string $key
     * @throws RunTimeException
     */
    public function get(string $prefix, string $key) : ?int
    {
        $key = $prefix.'.'.$key;
        $ret = $this->SwooleTable->get($key, 'int_val');
        if (!$ret) {
            $ret = NULL;
        }
        return $ret;
    }

    public function exists(string $prefix, string $key) : bool
    {
        $key = $prefix.'.'.$key;
        return $this->SwooleTable->exist($key);
    }
}