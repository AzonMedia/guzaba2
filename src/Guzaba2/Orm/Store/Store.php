<?php
declare(strict_types=1);

namespace Guzaba2\Orm\Store;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\BadMethodCallException;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Orm\Exceptions\UnknownRecordTypeException;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Orm\Store\Interfaces\StoreInterface;
use Guzaba2\Orm\Store\Interfaces\StructuredStoreInterface;
use Guzaba2\Translator\Translator as t;
use Guzaba2\Orm\Exceptions\RecordNotFoundException;

abstract class Store extends Base implements StoreInterface
{
    public const KEY_SEPARATOR = ':';

    /**
     * @var StoreInterface|null
     */
    protected ?StoreInterface $FallbackStore = NULL;

    /**
     * Cached structures
     * @var array
     */
    protected array $unified_columns_data = [];

    /**
     * Cached structures
     * @var array
     */
    protected array $storage_columns_data = [];

    public function get_fallback_store() : ?StoreInterface
    {
        return $this->FallbackStore;
    }

    /**
     * Default implementation (useful for NonStructured stores)
     * @param string $class
     * @return array
     * @throws BadMethodCallException
     * @throws RunTimeException
     */
    public function get_unified_columns_data(string $class) : array
    {

        if (!isset($this->unified_columns_data[$class])) {
            // TODO check deeper for a structured store
            if ($this->FallbackStore instanceof StructuredStoreInterface) {
                $this->unified_columns_data[$class] = $this->FallbackStore->get_unified_columns_data($class);
            } else {
                if (!is_a($class, ActiveRecordInterface::class, TRUE)) {
                    throw new InvalidArgumentException(sprintf(t::_('The provided $class %s does not implement %s.'), $class, ActiveRecordInterface::class));
                }
                $this->unified_columns_data[$class] = $class::get_structure();
            }
        }
        if (empty($this->unified_columns_data[$class])) {
            throw new RunTimeException(sprintf(t::_('No columns information was obtained for class %s.'), $class));
        }

        return $this->unified_columns_data[$class];
    }

    /**
     * Nonstructured Stores have this method as alias of self::get_unified_columns_data()
     * @param string $class
     * @return array
     * @throws BadMethodCallException
     * @throws RunTimeException
     */
    public function get_storage_columns_data(string $class) : array
    {
        return $this->get_storage_columns_data($class);
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

    protected function throw_not_found_exception_by_uuid(string $uuid) : void
    {
        throw new RecordNotFoundException(sprintf(t::_('No record with UUID %s exists'), $uuid ));
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

    public static function form_lookup_index(array $primary_index) : string
    {
        return implode(self::KEY_SEPARATOR, $primary_index);
    }

    public static function parse_lookup_index(string $lookup_index) : array
    {
        return explode(self::KEY_SEPARATOR, $lookup_index);
    }

    /**
     * Returns the primary index array with data based on the provided $class and $lookup_index
     * @param string $class
     * @param string $lookup_index
     * @return array
     */
    public static function restore_primary_index(string $class, string $lookup_index) : array
    {
        $primary_index_columns = $class::get_primary_index_columns();
        $primary_index_data = self::parse_lookup_index($lookup_index);
        $primary_index = [];
        for ($aa=0; $aa<count($primary_index_columns); $aa++) {
            $primary_index[$primary_index_columns[$aa]] = $primary_index_data[$aa];
        }
        return $primary_index;
    }

    public static function get_root_coroutine_id() : int
    {
        if (\Swoole\Coroutine::getCid() === -1) {
            throw new \RuntimeException(sprintf(t::_('The %s must be used in Coroutine context.'), __METHOD__));
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
