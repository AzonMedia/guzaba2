<?php
declare(strict_types=1);

namespace Guzaba2\Transaction;


use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Transaction\Interfaces\TransactionalResourceInterface;
use Guzaba2\Translator\Translator as t;

abstract class CompositeTransaction extends Transaction
{

    /** @var Transaction[] */
    private array $transactions = [];


    public function attach_transaction(Transaction $Transaction) : void
    {
        if ($Transaction->get_status() !== self::STATUS['CREATED']) {
            throw new InvalidArgumentException(sprintf(t::_('The transaction being attached is in status %1s. It is not allowed to attach transactions with status other than %2s.'), $Transaction->get_status(), self::STATUS['CREATED'] ));
        }
        if ($this->get_status() !== self::STATUS['CREATED']) {
            throw new RunTimeException(sprintf(t::_('The distributed transaction is in status %1s. It is not allowed to attach transactions to a distributed transaction that is in status different from %2s.'), $this->get_status(), self::STATUS['CREATED'] ));
        }

        $this->transactions[] = $Transaction;
    }

    /**
     * @overrides
     */
    protected function execute_begin() : void
    {
        foreach ($this->transactions as $Transaction) {
            $Transaction->begin();
        }
    }

    protected function execute_rollback(): void
    {
        foreach ($this->transactions as $Transaction) {
            $Transaction->rollback();
        }
    }

    protected function execute_save() : void
    {
        foreach ($this->transactions as $Transaction) {
            $Transaction->save();
        }
    }

    protected function execute_commit() : void
    {
        foreach ($this->transactions as $Transaction) {
            $Transaction->commit();
        }
    }

    protected function execute_create_savepoint(string $savepoint): void
    {
        foreach ($this->transactions as $Transaction) {
            $Transaction->create_savepoint($savepoint);
        }
    }

    protected function execute_rollback_to_savepoint(string $savepoint): void
    {
        foreach ($this->transactions as $Transaction) {
            $Transaction->rollback_to_savepoint($savepoint);
        }
    }

    protected function execute_release_savepoint(string $savepoint): void
    {
        foreach ($this->transactions as $Transaction) {
            $Transaction->release_savepoint($savepoint);
        }
    }

}