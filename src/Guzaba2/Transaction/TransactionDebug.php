<?php
declare(strict_types=1);

namespace Guzaba2\Transaction;


use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\LogicException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Event\Event;
use Guzaba2\Event\Events;
use Guzaba2\Kernel\Interfaces\ClassInitializationInterface;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Swoole\Handlers\Http\Request;
use Psr\Log\LogLevel;

class TransactionDebug extends Base implements ClassInitializationInterface
{
    protected const CONFIG_DEFAULTS = [
        'services'      => [
            'Events',
        ],
        'enable_debug'      => TRUE,
        'group_messages'    => TRUE,
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
        //register for all events
        //although only the _before_ones will be used
        $Events->add_class_callback(Transaction::class, '*', self::class.'::transaction_event_handler' );
        if (self::CONFIG_RUNTIME['group_messages']) {
            $Events->add_class_callback(Request::class, '_after_handle', self::class.'::print_debug_info');
        }
    }

    public static function transaction_event_handler(Event $Event) : void
    {
        /** @var Transaction $Transaction */
        $Transaction = $Event->get_subject();
        $event_name = $Event->get_event_name();
        if (strpos($event_name, '_before_') !== FALSE) {
            $message = str_repeat(' ',$Transaction->get_nesting() * 4).get_class($Transaction).' '.str_replace('_before', '', $event_name);
            if (self::CONFIG_RUNTIME['group_messages']) {
                $Context = Coroutine::getContext();
                if (!isset($Context->{self::class})) {
                    $Context->{self::class} = new \stdClass();
                }
                if (!isset($Context->{self::class}->transaction_debug_messages)) {
                    $Context->{self::class}->transaction_debug_messages = [];
                }
                $Context->{self::class}->transaction_debug_messages[] = $message;
            } else {
                Kernel::log($message, LogLevel::DEBUG);
            }

        }
    }

    public static function print_debug_info() : void
    {
        $Context = Coroutine::getContext();
        $message = '';
        if (!empty($Context->{self::class}->transaction_debug_messages)) {
            $message = PHP_EOL.'Transactions Debug Info:'.PHP_EOL.implode(PHP_EOL, $Context->{self::class}->transaction_debug_messages);
        }

        Kernel::log($message, LogLevel::DEBUG);
    }
}