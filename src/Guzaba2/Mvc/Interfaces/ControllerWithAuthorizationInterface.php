<?php


namespace Guzaba2\Mvc\Interfaces;

/**
 * Interface ControllerWithAuthorizationInterface
 * @package Guzaba2\Mvc\Interfaces
 * It is similar to ActiveRecordWithAuthorizationInterface
 */
interface ControllerWithAuthorizationInterface extends ControllerInterface
{
    public function check_permission(string $action) : void ;

    public function current_role_can(string $action) : bool ;

    public function role_can(Role $Role, string $action) : bool ;
}