<?php
declare(strict_types=1);

namespace Guzaba2\Transaction;


use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\LogicException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Event\Event;
use Guzaba2\Event\Events;
use Guzaba2\Kernel\Interfaces\ClassInitializationInterface;
use Guzaba2\Kernel\Kernel;
use Psr\Log\LogLevel;

class TransactionDebug extends Base implements ClassInitializationInterface
{
    protected const CONFIG_DEFAULTS = [
        'services'      => [
            'Events',
        ],
        'enable_debug'  => TRUE,
    ];

    protected const CONFIG_RUNTIME = [];


    /**
     * Must return an array of the initialization methods (method names or description) that were run.
     * @return array
     * @throws LogicException
     * @throws RunTimeException
     */
    public static function run_all_initializations(): array
    {
        if (self::CONFIG_RUNTIME['enable_debug']) {
            self::register_transaction_event_handler();
            return ['register_transaction_event_handler'];
        }
        return [];
    }

    public static function register_transaction_event_handler() : void
    {
        /** @var Events $Events */
        $Events = self::get_service('Events');
        //$Events->add_class_callback(Transaction::class, '_before_any', );
        $Events->add_class_callback(Transaction::class, '*', self::class.'::transaction_event_handler' );
    }

    public static function transaction_event_handler(Event $Event) : void
    {
        /** @var Transaction $Transaction */
        $Transaction = $Event->get_subject();
        $event_name = $Event->get_event_name();
        Kernel::printk('"'.$event_name.'"'.PHP_EOL);
        $message = str_repeat(' ',$Transaction->get_nesting() * 5).' '.$Transaction->get_nesting().' '.get_class($Transaction).' '.$event_name;
        Kernel::log($message, LogLevel::DEBUG);
    }
}