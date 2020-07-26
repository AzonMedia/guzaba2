<?php

declare(strict_types=1);

namespace Guzaba2\Database\Exceptions;

/**
 * This exception is to be thrown when the error returned from the DB is not a permanent one like a deadlock. The deadlock error can be solved by just retrying the transaction and is not a permanent one.
 */
class DatabaseTransientException extends QueryException
{
}
