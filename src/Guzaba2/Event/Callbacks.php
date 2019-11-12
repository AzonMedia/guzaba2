<?php


namespace Guzaba2\Event;


use Azonmedia\Utilities\GeneralUtil;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\LogicException;
use Guzaba2\Base\Interfaces\ObjectInternalIdInterface;

class Callbacks extends Base
{
    public $object_callbacks = [];

    public $class_callbacks = [];


}