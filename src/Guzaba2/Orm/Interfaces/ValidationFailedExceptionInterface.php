<?php

declare(strict_types=1);

namespace Guzaba2\Orm\Interfaces;

interface ValidationFailedExceptionInterface
{
    /**
     * @return string[]
     */
    public function getMessages(): array;

    /**
     * @return ValidationFailedExceptionInterface[]
     */
    public function getExceptions(): array;
}
