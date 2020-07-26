<?php

declare(strict_types=1);

namespace Guzaba2\Transaction;

use Guzaba2\Base\Exceptions\LogicException;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Transaction\Interfaces\TransactionalResourceInterface;

/**
 * Class CompositeTransactionResource
 * @package Guzaba2\Transaction
 *
 * This class does not support actual transactional methods (neither its child classes are expected to)
 * It only supports get_resource_id() and the child class must implement new_transaction().
 */
abstract class CompositeTransactionResource implements TransactionalResourceInterface
{

    public function begin_transaction(): void
    {
        throw new LogicException(sprintf(t::_('The class %1$s does not support the %2$s method.'), __CLASS__, __FUNCTION__));
    }

    public function commit_transaction(): void
    {
        throw new LogicException(sprintf(t::_('The class %1$s does not support the %2$s method.'), __CLASS__, __FUNCTION__));
    }

    public function rollback_transaction(): void
    {
        throw new LogicException(sprintf(t::_('The class %1$s does not support the %2$s method.'), __CLASS__, __FUNCTION__));
    }

    public function create_savepoint(string $savepoint_name): void
    {
        throw new LogicException(sprintf(t::_('The class %1$s does not support the %2$s method.'), __CLASS__, __FUNCTION__));
    }

    public function rollback_to_savepoint(string $savepoint_name): void
    {
        throw new LogicException(sprintf(t::_('The class %1$s does not support the %2$s method.'), __CLASS__, __FUNCTION__));
    }

    public function release_savepoint(string $savepoint_name): void
    {
        throw new LogicException(sprintf(t::_('The class %1$s does not support the %2$s method.'), __CLASS__, __FUNCTION__));
    }

    abstract public function new_transaction(?ScopeReference &$ScopeReference, array $options = []): Transaction;

    public function get_resource_id(): string
    {
        return get_class($this) . ':' . Coroutine::getCid();
    }
}
