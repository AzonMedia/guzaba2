<?php


namespace Guzaba2\Patterns\Interfaces;


interface MultitonInterface
{
    public static function &get_instance( /* mixed*/ $index) : self;
}