<?php
declare(strict_types=1);
namespace Swoole\Coroutine\Http2\Client;

class Exception
{
    protected $message;
    protected $code;
    protected $file;
    protected $line;

    public function __construct($message, $code, $previous)
    {
        $new_message = $message;
        $new_code = $code;
        $new_previous = $previous;
    }

    public function __wakeup()
    {
    }

    public function __toString()
    {
    }
}
