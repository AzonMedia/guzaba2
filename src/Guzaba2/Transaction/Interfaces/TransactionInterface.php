<?php

declare(strict_types=1);

namespace Guzaba2\Transaction\Interfaces;

use Throwable;

interface TransactionInterface
{
    public const STATUS = [
        'CREATED'       => 'CREATED',
        'STARTED'       => 'STARTED',
        'SAVED'         => 'SAVED',
        'ROLLEDBACK'    => 'ROLLEDBACK',
        'COMMITTED'     => 'COMMITTED',
    ];

    /**
     * A transaction in the following statuses can not be changed.
     */
    public const END_STATUSES = [
        self::STATUS['COMMITTED'],
        self::STATUS['ROLLEDBACK'],
    ];

    public const ROLLBACK_REASON = [
        'EXCEPTION'     => 'EXCEPTION',//the transaction was rolled back because an exception was thrown and the scope reference was destroyed
        'PARENT'        => 'PARENT',//the parent transaction was rolled back while the child one was saved
        'IMPLICIT'      => 'IMPLICIT',//the scope was left (return statement) but there is no exception, or the scope reference was overwritten (in a loop)
        'EXPLICIT'      => 'EXPLICIT',//the transaction was rolled back with a rollback() call
    ];

    /**
     * A list of events that can be fired by this class
     */
    public const EVENT = [
        '_before_begin'             => '_before_begin',
        '_after_begin'              => '_after_begin',
        '_before_create_savepoint'  => '_before_create_savepoint',//nested transactions use this instead of begin
        '_after_create_savepoint'   => '_after_create_savepoint',
        '_before_save'              => '_before_save',//nested transactions use this instead of commit
        '_after_save'               => '_after_save',
        '_before_commit'            => '_before_commit',
        '_after_commit'             => '_after_commit',
        '_before_rollback'          => '_before_rollback',
        '_after_rollback'           => '_after_rollback',
        '_before_destruct'          => '_before_destruct',
    ];

    public function get_interrupting_exception(): ?Throwable ;

    public function get_rollback_reason(): ?string ;

    public function add_callback(string $event_name, callable $callback): bool ;

    public function has_parent(): bool ;

    public function get_parent(): ?self ;

    public function is_master(): bool ;

    public function get_master(): self ;

    public function get_nesting(): int ;

    public function get_children(): array ;

    public function get_nested(): array ;

    public function begin(): void ;

    public function rollback(): void ;

    public function commit(): void ;

    public function is_rollback_initiator(): bool ;

    public function execute(callable $callable) /* mixed */ ;

    public function create_savepoint(string $savepoint_name): void ;

    public function get_status(): string ;

    public function set_status(string $status): void ;

    public function get_resource(): TransactionalResourceInterface;

}