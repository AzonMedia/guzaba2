<?php
declare(strict_types=1);

namespace Guzaba2\Orm\Traits;

use Guzaba2\Event\Event;
use Guzaba2\Orm\Interfaces\ActiveRecordTemporalInterface;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;

/**
 * Trait ActiveRecordTemporal
 * @package Guzaba2\Orm\Traits
 *
 * @see https://stackoverflow.com/questions/31252905/how-to-implement-temporal-data-in-mysql
 * @see https://en.wikipedia.org/wiki/Temporal_database
 */
trait ActiveRecordTemporal
{
    public static function after_write_handler(Event $Event) : void
    {
        /**
         * @var ActiveRecordInterface
         */
        $ActiveRecord = $Event->get_subject();
        $event_microtime = (int) (microtime(TRUE) * 1_000_000);
        if ($ActiveRecord->is_new()) {
            //create a new record
        } else {
            //update the last record by setting
            self::update_last_temporal_record($ActiveRecord, $event_microtime);
            //create a new record
        }
        //in either case a new record is created
        self::create_new_temporal_record($ActiveRecord, $event_microtime);
    }

    public static function after_delete_handler(Event $Event) : void
    {
        /**
         * @var ActiveRecordInterface
         */
        $ActiveRecord = $Event->get_subject();
        $event_microtime = (int) (microtime(TRUE) * 1_000_000);
        self::update_last_temporal_record($ActiveRecord, $event_microtime);
    }

    private static function update_last_temporal_record(ActiveRecordInterface $ActiveRecord, int $event_microtime) : void
    {
        if ($ActiveRecord instanceof ActiveRecordTemporalInterface) {
            return;
        }
        $temporal_class = $ActiveRecord::get_temporal_class();
        $index = $ActiveRecord->get_primary_index();
        $index['>'] = 'temporal_record_id';// '>' means sorting by this column DESC, '<' - means sorting by this column ASC
        $Temporal = new $temporal_class($index);
        $Temporal->temporal_record_to_microtime = $event_microtime;
        $Temporal->write();
    }

    private static function create_new_temporal_record(ActiveRecordInterface $ActiveRecord, int $event_microtime) : void
    {
        if ($ActiveRecord instanceof ActiveRecordTemporalInterface) {
            return;
        }
        $temporal_class = $ActiveRecord::get_temporal_class();
        $CurrentUser = self::get_service('CurrentUser');
        $Temporal = new $temporal_class(0);//create new record
        $Temporal->set_record_data($ActiveRecord->get_record_data());
        $Temporal->temporal_record_from_microtime = $event_microtime;
        $Temporal->temporal_record_to_microtime = 0;//infinity
        $Temporal->temporal_record_role_id = $CurrentUser->get()->get_role()->get_id();//this is the primary role
        $Temporal->write();
    }

    //TODO add a method that checks does the temporal table exists and if it doesn to create it by copying the columns from the main table
}