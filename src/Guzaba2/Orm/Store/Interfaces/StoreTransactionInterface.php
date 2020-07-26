<?php

declare(strict_types=1);

namespace Guzaba2\Orm\Store\Interfaces;

use Guzaba2\Orm\Interfaces\ActiveRecordInterface;

interface StoreTransactionInterface
{
    public function attach_object(ActiveRecordInterface $ActiveRecord): void;
}
