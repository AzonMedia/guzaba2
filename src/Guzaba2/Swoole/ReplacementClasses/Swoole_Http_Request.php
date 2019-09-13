<?php
namespace Swoole\Http;

class Request
{
    public $fd;
    public $streamId;
    public $header;
    public $server;
    public $cookie;
    public $get;
    public $files;
    public $post;
    public $tmpfiles;
    public function rawContent()
    {
    }

    public function getData()
    {
    }

    public function __destruct()
    {
    }
}
