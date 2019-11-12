<?php

namespace Guzaba2\Event\Interfaces;

use Azonmedia\Utilities\GeneralUtil;
use Guzaba2\Base\Exceptions\LogicException;
use Guzaba2\Base\Interfaces\ObjectInternalIdInterface;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Event\Callbacks;

interface EventsInterface
{
    public static function create_event(ObjectInternalIdInterface $Subject, string $event_name) : EventInterface ;

    public function add_object_callback(ObjectInternalIdInterface $Subject, string $event_name, callable $callback) : bool ;

    public function remove_object_callback(ObjectInternalIdInterface $Subject, string $event_name, ?callable $callback) : void ;

    public function get_object_callbacks(ObjectInternalIdInterface $Subject, string $event_name = '') : array ;

    public function add_class_callback(string $class, string $event_name, callable $callback) : bool ;

    public function remove_class_callback(string $class, string $event_name, ?callable $callback) : void ;

    public function get_class_callbacks(string $class, string $event_name = '') : array ;
}