<?php

declare(strict_types=1);

namespace Guzaba2\Http;

use Guzaba2\Base\Base as Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Http\Interfaces\ServerInterface;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Translator\Translator as t;

/**
 * Class Server
 * A generic HTTP Server implementation
 * @package Guzaba2\Http
 */
abstract class Server extends Base implements ServerInterface
{
    protected string $host = '';

    protected int $port = 0;

    protected array $options = [];

    protected ?float $start_microtime = null;

    public function __construct(string $host, int $port, array $options = [])
    {
        parent::__construct();
        $this->host = $host;
        $this->port = $port;
        $this->options = $options;

        Kernel::set_http_server($this);
    }


    public function get_host(): string
    {
        return $this->host;
    }

    public function get_port(): int
    {
        return $this->port;
    }

    public function get_option(string $option) /* mixed */
    {
        if (!array_key_exists($option, $this->options)) {
            throw new InvalidArgumentException(sprintf(t::_('The option %1$s is not configured (provided to the class constructor).'), $option));
        }
        return $this->options[$option];
    }

    /**
     * Returns all options passed to the class constructor
     * @return array
     */
    public function get_options(): array
    {
        return $this->options;
    }


    /**
     * @return float|null
     */
    public function get_start_microtime(): ?float
    {
        return $this->start_microtime;
    }
}
