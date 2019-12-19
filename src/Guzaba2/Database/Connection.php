<?php
declare(strict_types=1);

namespace Guzaba2\Database;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Database\Interfaces\ConnectionInterface;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Resources\GenericResource;
use Guzaba2\Resources\Resource;
use Guzaba2\Translator\Translator as t;

abstract class Connection extends GenericResource implements ConnectionInterface
{
    protected const CONFIG_DEFAULTS = [
        'services'      => [
            'ConnectionFactory'
        ]
    ];

    protected array $options;

    protected const CONFIG_RUNTIME = [];

//    protected $is_created_from_factory_flag = FALSE;

    public function __construct()
    {
        $ConnectionFactory = static::get_service('ConnectionFactory');
        parent::__construct($ConnectionFactory);
    }

    public function __destruct()
    {
        //$this->close();//avoid this - the connections should be close()d immediately
        //or have a separate flag $is_connected_flag
    }

    public function get_options() : array
    {
        return $this->options;
    }

    /**
     * Returns table prefix.
     * @return string
     */
    public static function get_tprefix() : string
    {
        return static::CONFIG_RUNTIME['tprefix'] ?? '';
    }
    
    public static function validate_options(array $options) : void
    {
        $called_class = get_called_class();
        foreach ($options as $key=>$value) {
            if (!in_array($key, static::get_supported_options() )) {
                throw new InvalidArgumentException(sprintf(t::_('An invalid connection option %s is provided to %s. The valid options are %s.'), $key, get_called_class(), implode(', ', static::get_supported_options() ) ));
            }
        }
    }

    public static function get_supported_options(): array
    {
        return static::SUPPORTED_OPTIONS;
    }

}
