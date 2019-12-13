<?php
declare(strict_types=1);


namespace Guzaba2\Base\Interfaces;

interface ObjectInternalIdInterface
{

    public const DEFAULT_CHARACTERS_LIST = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public const UNIQUE_ID_LENGTH = 4;

    /**
     * Returns the unique object id.
     * @return string
     */
    public function get_object_internal_id() : string;
}
