<?php
declare(strict_types=1);

namespace Guzaba2\Transaction;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;


class CallbackContainer extends Base
{


//    public const MODE_BEFORE_ANY = 'before_any';
//    public const MODE_AFTER_ANY = 'after_any';
//
//    public const MODE_BEFORE_COMMIT = 'before_commit';
//    public const MODE_BEFORE_SAVE = 'before_save';
//    public const MODE_BEFORE_ROLLBACK = 'before_rollback';
//
//    public const MODE_AFTER_COMMIT = 'after_commit';
//    public const MODE_AFTER_SAVE = 'after_save';
//    public const MODE_AFTER_ROLLBACK = 'after_rollback';

    public const MODE = [
        'BEFORE_ANY'                => 'BEFORE_ANY',
        'AFTER_ANY'                 => 'AFTER_ANY',

        'BEFORE_COMMIT'             => 'BEFORE_COMMIT',
        'BEFORE_SAVE'               => 'BEFORE_SAVE',
        'BEFORE_ROLLBACK'           => 'BEFORE_ROLLBACK',

        'AFTER_COMMIT'              => 'AFTER_COMMIT',
        'AFTER_SAVE'                => 'AFTER_SAVE',
        'AFTER_ROLLBACK'            => 'AFTER_ROLLBACK',

        //'BEFORE_COMMIT_DEFERRED'    => 'BEFORE_COMMIT_DEFERRED',
    ];

    private Transaction $Transaction;

    /**
     * Associative array - key = $mode, value = Azonmedia\Patterns\CallbackContainer
     * @var array
     */
    private array $callbacks = [];

    public function __construct(Transaction $Transaction)
    {
        $this->Transaction = $Transaction;
        parent::__construct();
    }

    public function add_callback(callable $callback, string $mode, bool $once = FALSE) : bool
    {
        self::validate_mode($mode);
        if (!array_key_exists($mode, $this->callbacks)) {
            $this->callbacks[$mode] = new \Azonmedia\Patterns\CallbackContainer();
        }
        if ($once) {
            //check is it already added
            $mode_callbacks = $this->callbacks[$mode]->get_callables();
            foreach ($mode_callbacks as $mode_callback) {
                if ($mode_callback === $callback) {
                    return FALSE;
                }
            }
        }
        $this->callbacks[$mode]->add_callable($callback);
        return TRUE;
    }

    public function get_callback_container(string $mode) : \Azonmedia\Patterns\CallbackContainer
    {
        self::validate_mode($mode);
        if (!array_key_exists($mode, $this->callbacks)) {
            $this->callbacks[$mode] = new \Azonmedia\Patterns\CallbackContainer();
        }
        return $this->callbacks[$mode];
    }

    protected static function validate_mode(string $mode) : void
    {
        if (!$mode) {
            throw new InvalidArgumentException(sprintf(t::_('There is no mode provided.')));
        }
        if (!preg_match('/[A-Z_]*/',$mode)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided mode %1s does not consist of upper case letters and underscore.')));
        }
        if (!isset(self::MODE[$mode])) {
            throw new InvalidArgumentException(sprintf(t::_('The provided $mode %1s is not valid. The valid modes are %2s.'), $mode, implode(', ',array_keys(self::MODE) ) ));
        }
    }

}