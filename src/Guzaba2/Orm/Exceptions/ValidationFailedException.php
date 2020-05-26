<?php
declare(strict_types=1);

namespace Guzaba2\Orm\Exceptions;

use Guzaba2\Base\Exceptions\BaseException;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Orm\Interfaces\ValidationFailedExceptionInterface;

class ValidationFailedException extends BaseException implements ValidationFailedExceptionInterface
{

    protected /* mixed */ $target;
    protected string $field_name;

    /**
     * ValidationFailedException constructor.
     * It is important to note that passing and holding a reference to the ActiveRecord object will prolong its life as the exception is passed between the scopes.
     * @param string|ActiveRecord|NULL $target Class name, ActiveRecordInterface or NULL (if thrown outside ORM context)
     * @param string $field_name
     * @param string $message
     * @param int $code
     * @param \Throwable|null $PreviousException
     * @throws InvalidArgumentException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Base\Exceptions\RunTimeException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     */
    public function __construct( /* mixed */ $target, string $field_name, string $message, int $code = 0, ?\Throwable $PreviousException = NULL)
    {
        if ( !( $target instanceof ActiveRecordInterface) && !is_string($target) && !is_null($target)) {
            throw new InvalidArgumentException(sprintf(t::_('An unsupported type $target is provided to %s. ActiveRecordInterface instance, valid class name or NULL are the supported types.'), __METHOD__));
        }
        if (is_string($target) && !class_exists($target)) {
            throw new InvalidArgumentException(sprintf(t::_('An invalida class name %s provided as $target to %s.'), $target, __METHOD__));
        }
        if ($target !== NULL && !strlen($field_name)) {
            throw new InvalidArgumentException(sprintf(t::_('It is required to provide $field_name to %s when $target is not NULL.'), __METHOD__));
        }
        $this->target = $target;
        $this->field_name = $field_name;
        parent::__construct($message, $code, $PreviousException);

    }

    /**
     * @return string|null
     */
    public function getClass() : ?string
    {
        $ret = NULL;
        if ($this->target instanceof ActiveRecordInterface) {
            $ret = get_class($this->target);
        } elseif (is_string($this->target)) {
            $ret = $this->target;
        }
        return $ret;
    }

    public function getTarget() /* mixed */
    {
        return $this->target;
    }

    public function getFieldName() : string
    {
        return $this->field_name;
    }

    public function getMessages(): array
    {
        return [$this->getMessage()];
    }

    public function getExceptions(): array
    {
        return [$this];
    }
}