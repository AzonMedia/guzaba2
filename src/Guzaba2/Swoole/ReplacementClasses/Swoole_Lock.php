<?php
namespace Swoole;
class Lock
{
    public $errCode;
    public const FILELOCK = 2;
    public const MUTEX = 3;
    public const SEM = 4;
    public const RWLOCK = 1;
    public const SPINLOCK = 5;
    public function __construct( $type, $filename) { }

    public function __destruct( ) { }

    public function lock( ) { }

    public function lockwait( $timeout) { }

    public function trylock( ) { }

    public function lock_read( ) { }

    public function trylock_read( ) { }

    public function unlock( ) { }

    public function destroy( ) { }

}
