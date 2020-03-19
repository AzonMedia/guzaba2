<?php
declare(strict_types=1);

namespace Guzaba2\Transaction\Interfaces;

//use Guzaba2\Resources\ScopeReference;
use Guzaba2\Transaction\ScopeReference;
use Guzaba2\Transaction\Transaction;

interface TransactionalResourceInterface
{

    //low level method for directly executing transactional actions
    //these methods do not support automatic nesting and rollback

    public function begin_transaction() : void ;

    public function commit_transaction() : void ;

    public function rollback_transaction() : void ;

    public function create_savepoint(string $savepoint_name) : void ;

    public function rollback_to_savepoint(string $savepoint_name) : void ;

    public function release_savepoint(string $savepoint_name) : void ;

    public function new_transaction(?ScopeReference &$ScopeReference, array $options = []) : Transaction ;
}