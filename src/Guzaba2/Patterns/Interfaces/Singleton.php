<?php

namespace Guzaba2\Patterns\Interfaces;

interface Singleton
{

    public static function &get_instance() : self;

    public static function get_instances() : array ;

    public function destroy() : void;

}