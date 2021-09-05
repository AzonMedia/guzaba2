<?php
declare(strict_types=1);

namespace Guzaba2\Orm\Traits;


use Azonmedia\Reflection\ReflectionClass;
use Azonmedia\Utilities\ArrayUtil;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Orm\Interfaces\ActiveRecordTemporalInterface;

trait ActiveRecordMeta
{

    /**
     * Returns all interfaces that extends the ActiveRecordInterface.
     * These are presumable interfaces describing models.
     * @param array $ns_prefixes
     * @return array
     */
    public static function get_active_record_interfaces(array $ns_prefixes = []): array
    {
        if (!$ns_prefixes) {
            $ns_prefixes = array_keys(Kernel::get_registered_autoloader_paths());
        }
        static $active_record_interfaces = [];

        $args_hash = md5(ArrayUtil::array_as_string($ns_prefixes));
        if (!array_key_exists($args_hash, $active_record_interfaces)) {
            $interfaces = Kernel::get_interfaces($ns_prefixes, ActiveRecordInterface::class);
            $interfaces = array_filter($interfaces, fn(string $interface): bool => !in_array($interface, [ ActiveRecordInterface::class]) );
            $active_record_interfaces[$args_hash] = $interfaces;
        }
        return $active_record_interfaces[$args_hash];

        return [];
    }

    /**
     * Returns the active record interface this class implements.
     * An active record interface is an interface that extends ActiveRecordInterface
     * @return array
     */
    public static function get_class_active_record_interface($class = ''): ?string
    {
        if (!$class) {
            $class = get_called_class();
        }

        $ret = null;

        $active_record_interfaces = self::get_active_record_interfaces();
        foreach ($active_record_interfaces as $active_record_interface) {
            if (is_a($class, $active_record_interface, true)) {
                $ret = $active_record_interface;
                break;
            }
        }
        return $ret;
    }

    /**
     * Returns a class implementing the provided activeRecord interfaces.
     * @param $interface
     * @return string|null
     * @throws InvalidArgumentException
     */
    public static function get_active_record_interface_implementation($interface): ?string
    {
        $implementing_classes = Kernel::get_classes([], $interface);
        $ret = null;
        foreach ($implementing_classes as $implementing_class) {
            if (class_exists($implementing_class)) {
                $ret = $implementing_class;
                break;//only one implementing class is needed
            }
        }
        return $ret;
    }

    /**
     * Returns all ActiveRecord classes that are loaded by the Kernel in the provided namespace prefixes.
     * Usually the array from Kernel::get_registered_autoloader_paths() is provided to $ns_prefixes
     * @param array $ns_prefixes
     * @return array Indexed array with class names
     * @throws InvalidArgumentException
     */
    public static function get_active_record_classes(array $ns_prefixes = []): array
    {
        if (!$ns_prefixes) {
            $ns_prefixes = array_keys(Kernel::get_registered_autoloader_paths());
        }
        static $active_record_classes = [];
        $args_hash = md5(ArrayUtil::array_as_string($ns_prefixes));
        if (!array_key_exists($args_hash, $active_record_classes)) {
            $classes = Kernel::get_classes($ns_prefixes, ActiveRecordInterface::class);
            $classes = array_filter($classes, fn(string $class): bool => !in_array($class, [ActiveRecord::class, ActiveRecordInterface::class]) && ( new ReflectionClass($class) )->isInstantiable());
            $active_record_classes[$args_hash] = $classes;
        }
        return $active_record_classes[$args_hash];
    }

    /**
     * Returns the classes that implement the ActiveRecordTemporalInterface
     * @param array $ns_prefixes
     * @return array
     * @throws InvalidArgumentException
     */
    public static function get_active_record_temporal_classes(array $ns_prefixes = []): array
    {
        if (!$ns_prefixes) {
            $ns_prefixes = array_keys(Kernel::get_registered_autoloader_paths());
        }
        static $active_record_temporal_classes = [];
        $args_hash = md5(ArrayUtil::array_as_string($ns_prefixes));
        if (!array_key_exists($args_hash, $active_record_temporal_classes)) {
            $active_record_temporal_classes[$args_hash] = Kernel::get_classes($ns_prefixes, ActiveRecordTemporalInterface::class);
        }
        return $active_record_temporal_classes[$args_hash];
    }

    /**
     * The table must be provided without any prefixes.
     * @return string[]
     */
    public static function get_classes_by_table(string $table): array
    {
        $ret = [];
        $active_record_classes = self::get_active_record_classes();
        foreach ($active_record_classes as $active_record_class) {
            if ($active_record_class::get_main_table() === $table) {
                $ret[] = $active_record_class;
            }
        }
        return $ret;
    }
}