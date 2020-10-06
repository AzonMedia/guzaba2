<?php

declare(strict_types=1);

namespace Guzaba2\Transaction\Interfaces;


interface TransactionManagerInterface
{
    /**
     * @param TransactionInterface|null $Transaction
     * @param string $transaction_type
     */
    public function set_current_transaction(?TransactionInterface $Transaction, string $transaction_type = ''): void ;

    /**
     * @param string $transaction_type
     * @return TransactionInterface|null
     */
    public function get_current_transaction(string $transaction_type): ?TransactionInterface ;

    /**
     * @return TransactionInterface[]
     */
    public function get_all_current_transactions(): array ;
}