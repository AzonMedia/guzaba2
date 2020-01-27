<?php
declare(strict_types=1);

namespace Guzaba2\Log;

use Guzaba2\Orm\ActiveRecord;

class LogEntry extends ActiveRecord
{
    protected const CONFIG_DEFAULTS = [
        'main_table'                => 'logs',
        'no_permissions'            => TRUE,
        'no_meta'                   => TRUE,
    ];
    protected const CONFIG_RUNTIME = [];
}