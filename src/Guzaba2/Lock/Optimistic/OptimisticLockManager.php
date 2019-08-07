<?php

namespace Guzaba2\Lock;

use Guzaba2\Base\Base;
use Guzaba2\Lock\Backends\Interfaces\BackendInterface;
use Guzaba2\Lock\Interfaces\LockInterface;

/**
 * Class OptimisticLockManager
 * In optimistick lock verison/modtime is obtained athe beginning of the write operation.
 * At the very end of the write operation but just before commit the update time is updated.
 * The time is set to current time + the maximum timeout for the commit operation can take (timeouts)
 * If the commit succeeds the timestamp is updated to the current one. If it fails the timestamp is left unmodified (as nother thread may have updated it)
 * @package Guzaba2\Lock
 */
class OptimisticLockManager extends Base
{

    /**
     * @var BackendInterface
     */
    protected $Backend;

    public function __construct(BackendInterface $Backend)
    {
        parent::__construct();
        $this->Backend = $Backend;
    }
}
