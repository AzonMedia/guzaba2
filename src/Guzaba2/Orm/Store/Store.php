<?php
declare(strict_types=1);

namespace Guzaba2\Orm\Store;

use Guzaba2\Base\Base;
use Guzaba2\Orm\Exceptions\UnknownRecordTypeException;
use Guzaba2\Orm\Store\Interfaces\StoreInterface;
use Guzaba2\Translator\Translator as t;
use Guzaba2\Orm\Exceptions\RecordNotFoundException;

abstract class Store extends Base implements StoreInterface
{
    public const KEY_SEPARATOR = ':';

    /**
     * @var StoreInterface|null
     */
    protected $FallbackStore;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param string $class
     * @param string $lookup_index
     * @throws RecordNotFoundException
     */
    protected function throw_not_found_exception(string $class, string $lookup_index) : void
    {
        throw new RecordNotFoundException(sprintf(t::_('Record of class %s with lookup index %s does not exist.'), $class, $lookup_index));
    }

    /**
     * @param string $class
     * @throws UnknownRecordTypeException
     */
    protected function throw_unknown_record_type_exception(string $class) : void
    {
        throw new UnknownRecordTypeException(sprintf(t::_('The ORM Store %s has no knowledge for record of class %s.'), get_class($this), $class));
    }

    public static function get_record_structure(array $unified_column_structure) : array
    {
        $ret = [];
        foreach ($unified_column_structure as $column_data) {
            $ret[$column_data['name']] = $column_data['default_value'];
        }
        
        return $ret;
    }

    public function get_fallback_store() : ?StoreInterface
    {
        return $this->FallbackStore;
    }

    public static function form_lookup_index(array $primary_index) : string
    {
        return implode(self::KEY_SEPARATOR, $primary_index);
    }

    public static function get_root_coroutine_id() : int
    {
        if (\Swoole\Coroutine::getCid() === -1) {
            throw new \RuntimeException(sprintf('The %s must be used in Coroutine context.'), __METHOD__ );
        }
        do {
            $cid = \Swoole\Coroutine::getCid();
            $pcid = \Swoole\Coroutine::getPcid($cid);
            if ($pcid === -1) {
                break;
            }
            $cid = $pcid;
        } while (true);

        return $cid;
    }
}
