<?php

declare(strict_types=1);

namespace Guzaba2\Base\Interfaces;

interface ContextAwareInterface
{

    /**
     * Returns the coroutine where the object was created
     * @return int
     */
    public function get_created_coroutine_id(): int;

    /**
     * Returns true if the object has been passed between contexts/coroutines
     * @return bool
     */
    public function has_switched_context(): bool;
}
