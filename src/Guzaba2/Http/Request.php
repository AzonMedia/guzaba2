<?php
declare(strict_types=1);

namespace Guzaba2\Http;

use Guzaba2\Http\Interfaces\ServerInterface;

class Request extends \Azonmedia\Http\Request
{
    /**
     * @var ServerInterface|null
     */
    protected ?ServerInterface $Server = null;

    public function setServer(ServerInterface $Server): void
    {
        $this->Server = $Server;
    }

    public function set_server(ServerInterface $Server): void
    {
        $this->setServer($Server);
    }

    public function getServer(): ?ServerInterface
    {
        return $this->Server;
    }

    public function get_server(): ?ServerInterface
    {
        return $this->getServer();
    }


    public function __debugInfo(): array
    {
        $data = get_object_vars($this);
        unset($data['Server']);
        return $data;
    }
}