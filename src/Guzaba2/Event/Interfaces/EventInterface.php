<?php
declare(strict_types=1);


namespace Guzaba2\Event\Interfaces;


use Guzaba2\Base\Interfaces\ObjectInternalIdInterface;

interface EventInterface
{
    public function get_subject() : ObjectInternalIdInterface ;

    public function get_event_name() : string ;
}