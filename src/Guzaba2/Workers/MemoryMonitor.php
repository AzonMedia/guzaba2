<?php

namespace Guzaba2\Workers;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Translator\Translator as t;

/**
 * Class MemoryMonitor
 * This object lives for the duretion of the worker.
 * @package Guzaba2\Workers
 */
class MemoryMonitor extends Base
{

    /**
     * @var array
     */
    protected $memory_usage_at_worker_start = [];

    protected $memory_usage_at_last_execution_end = [];

    protected const MEMORY_USAGE = 'usage';
    protected const MEMORY_USAGE_REAL = 'usage_real';
    protected const MEMORY_PEAK_USAGE = 'peak_usage';
    protected const MEMORY_PEAK_USAGE_REAL = 'peak_usage_real';

    protected const UNIT_BYTES = 'bytes';
    protected const UNIT_KILOBYTES = 'kilobytes';
    protected const UNIT_MEGABYTES = 'megabytes';

    protected function __construct()
    {
        parent::__construct();

        $this->memory_usage_at_worker_start = $this->get_memory_usage();
    }

    public function get_memory_usage(): array
    {
        $ret = [];
        $ret[self::MEMORY_USAGE] = memory_get_usage();
        $ret[self::MEMORY_USAGE_REAL] = memory_get_usage(TRUE);
        $ret[self::MEMORY_PEAK_USAGE] = memory_get_peak_usage();
        $ret[self::MEMORY_PEAK_USAGE_REAL] = memory_get_peak_usage(TRUE);
        return $ret;
    }

    public function get_memory_usage_metric(string $metric, string $unit = self::UNIT_BYTES) : float
    {
        $memory_usage = $this->get_memory_usage();
        if (!isset($memory_usage[$metric])) {
            throw new InvalidArgumentException(sprintf(t::_('The provided $metrix argument does not contain a valid value.')));
        }

        switch ($unit) {
            case self::UNIT_BYTES:
                break;
            case self::UNIT_KILOBYTES:
                break;
            case self::UNIT_MEGABYTES:
                break;
            default:
                throw new InvalidArgumentException(sprintf(t::_('The provided $units argument does not contain a valid value.')));
        }
    }

    /**
     * To be called at the end of each execution after the cleanup of all ExecutionSingletons
     * Compares the current memory ('usage_real') against the last execution (if there is such).
     * Returns the difference in bytes compared against the previous execution.
     *
     */
    public function check_memory() : int
    {
        $current_usage = $this->get_memory_usage();
        $last_usage = $this->memory_usage_at_last_execution_end;
        $this->memory_usage_at_last_execution_end = $current_usage;

        $ret = isset($last_usage['usage_real']) ? $current_usage['usage_real'] - $last_usage['usage_real'] : 0 ;

        //print_r($current_usage);
        //print $ret;

        return $ret;
    }
}
