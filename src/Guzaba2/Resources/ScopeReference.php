<?php


namespace Guzaba2\Resources;

use Guzaba2\Resources\Interfaces\ResourceInterface;

class ScopeReference extends \Azonmedia\Patterns\ScopeReference
{
    /**
     * @var ResourceInterface
     */
    protected $Resource;

    public function __construct(ResourceInterface $Resource)
    {
        $this->Resource = $Resource;
        $Function = static function () use ($Resource) { //if it is not declared as a satic function one more reference to $this is created and this defeats the whole purpose of the scopereference - to have a single reference to it. The destructor will not get called.
            //$Resource->free();
            $Resource->decrement_scope_counter();
        };
        parent::__construct($Function);
    }

    public function get_resource() : ResourceInterface
    {
        return $this->Resource;
    }
}
