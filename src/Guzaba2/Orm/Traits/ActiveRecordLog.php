<?php

declare(strict_types=1);

namespace Guzaba2\Orm\Traits;

use Guzaba2\Log\LogEntry;

trait ActiveRecordLog
{
    public function add_log_entry(string $log_action, string $log_message): LogEntry
    {
        return LogEntry::create($this, $log_action, $log_message);
    }

    public static function add_class_log_entry(string $log_action, string $log_message): LogEntry
    {
        return LogEntry::create_for_class(get_called_class(), $log_action, $log_message);
    }
}
