<?php
declare(strict_types=1);


namespace Guzaba2\Base\Traits;

trait ContextAware
{
    protected $created_in_coroutine_id = 0;

    protected function set_created_coroutine_id() : void
    {
        $cid = \Swoole\Coroutine::getCid();
        if ($cid > 0) {
            $this->created_in_coroutine_id = $cid;
        }
    }

    /**
     * Returns the coroutine where the object was created
     * @return int
     */
    public function get_created_coroutine_id() : int
    {
        return $this->created_in_coroutine_id;
    }

    /**
     * Returns true if the object has been passed between contexts/coroutines
     * @return bool
     */
    public function has_switched_context() : bool
    {
        $current_cid = \Swoole\Coroutine::getCid();
        return $current_cid !== $this->get_created_coroutine_id();
    }
}
