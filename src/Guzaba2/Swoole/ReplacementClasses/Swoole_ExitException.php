<?php
namespace Swoole;
class ExitException extends \Swoole\Exception implements \Throwable
{
    protected $message;
    protected $code;
    protected $file;
    protected $line;
    private $flags;
    private $status;
    public function getFlags( ) { }

    public function getStatus( ) { }

    public function __construct( $message, $code, $previous) { }

    public function __wakeup( ) { }

    public function __toString( ) { }

}
