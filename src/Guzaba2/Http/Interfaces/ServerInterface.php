<?php

namespace Guzaba2\Http\Interfaces;

interface ServerInterface
{
    public function start();
    public function stop();
    public function on(string $event_name, callable $callable);
    public function get_worker_id() : int ;
    public function get_worker_pid() : int ;
    public function get_document_root() : ?string;
}