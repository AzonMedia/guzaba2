<?php


namespace Guzaba2\Object;

use Guzaba2\Base\Base;
use Guzaba2\Transaction\Interfaces\TransactionTargetInterface;

abstract class GenericObject extends Base
implements TransactionTargetInterface
{
    /**
     * Returns all properties of the object irregardless of their visibility.
     * Returns only the dynamic properties. In general it is not supposed static properties to be set outside _initialize_class or __construct() and even if they are set they should not affect transactions.
     * To be used when starting an objects\Transaction and to be called by objects\Transaction::setTrackedObjects
     * @return mixed[]
     */
    public function _get_all_properties(): array
    {
        return get_object_vars($this);
    }

    /**
     * Resets the properties of the object as provided in the array.
     * To be used only by the object\transaction
     * @param array $properties
     * @return void
     */
    public function _set_all_properties(array $properties): void
    {
        foreach ($properties as $key => $value) {
            $this->{$key} = $value;
        }
    }
}
