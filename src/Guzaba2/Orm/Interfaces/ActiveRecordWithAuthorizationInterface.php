<?php


namespace Guzaba2\Orm\Interfaces;


use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Translator\Translator as t;

interface ActiveRecordWithAuthorizationInterface
{
    public function check_permission(string $action) : void ;

    public function current_role_can(string $action) : bool ;

    public function role_can(Role $Role, string $action) : bool ;
}