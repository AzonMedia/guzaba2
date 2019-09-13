<?php
namespace Swoole;

class ExitException
{
    protected $message;
    protected $code;
    protected $file;
    protected $line;
    private $flags;
    private $status;
    
    public function getFlags()
    {
    }

    public function getStatus()
    {
    }

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
