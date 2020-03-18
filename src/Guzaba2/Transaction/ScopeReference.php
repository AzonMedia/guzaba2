<?php
declare(strict_types=1);

namespace Guzaba2\Transaction;


class ScopeReference extends \Azonmedia\Patterns\ScopeReference
{
    /**
     * @var Transaction
     */
    protected Transaction $Transaction;

    public function __construct(Transaction $Transaction)
    {
        $this->Transaction = $Transaction;
        $Function = static function () use ($Transaction) { //if it is not declared as a static function one more reference to $this is created and this defeats the whole purpose of the scopereference - to have a single reference to it. The destructor will not get called.
            $Transaction->rollback();
        };
        parent::__construct($Function);
    }

    public function get_transaction() : Transaction
    {
        return $this->Transaction;
    }
}