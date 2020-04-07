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
            'ConnectionFactory',
            'Apm',
        ]
    ];

    protected const CONFIG_RUNTIME = [];

    protected array $options;
    protected ?string $connection_id = NULL;

//    protected $is_created_from_factory_flag = FALSE;

    public function __construct(?callable $after_connect_callback = NULL)
    {
        $ConnectionFactory = static::get_service('ConnectionFactory');
        parent::__construct($ConnectionFactory);
        if ($after_connect_callback) {
            $after_connect_callback($this);
        }
        $this->connection_id = $this->get_connection_id_from_db();
    }

    public function __destruct()
    {
        //$this->close();//avoid this - the connections should be close()d immediately
        //or have a separate flag $is_connected_flag
    }

    public function __toString() : string
    {
        return $this->get_resource_id();
    }

    abstract protected function get_connection_id_from_db() : string ;

    /**
     * To be invoked when the connection is returned to the pool or closed.
     */
    public function reset_connection() : void
    {

    }


    public function get_connection_id() : ?string
    {
        return $this->connection_id;
    }

    public function get_resource_id() : string
    {
        return get_class($this).':'.$this->get_connection_id();
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
