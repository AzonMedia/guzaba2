<?php


namespace Guzaba2\Patterns;


use Guzaba2\Base\Base;

class ScopeReference extends Base
{

    /**
     * These callbacks will be executed on object destruction
     * @var array Array of callbacks
     */
    protected $callbacks = [];

    public function __construct(callable $callback)
    {
        parent::__construct();
        $this->add_callback($callback);
    }

    public function __destruct()
    {
        $this->execute_callbacks();
        parent::__destruct();
    }

    public function add_callback(callable $callback) : void
    {
        $this->callbacks[] = $callback;
    }

    private function execute_callbacks() : void
    {
        foreach ($this->callbacks as $callback) {
            $callback();
        }
    }
}