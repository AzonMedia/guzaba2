<?php
declare(strict_types=1);


namespace Guzaba2\Authorization\Interfaces;


interface UserInterface
{
    public function get_uuid(): string ;

    public function get_id()  /* int|string */ ;

    public function enable(): void ;

    public function disable(): void ;
}