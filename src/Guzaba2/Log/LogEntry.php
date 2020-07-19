<?php
declare(strict_types=1);

namespace Guzaba2\Log;

use Azonmedia\Reflection\ReflectionClass;
use Guzaba2\Authorization\CurrentUser;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\LogicException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Kernel\Exceptions\ConfigurationException;
use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Orm\Exceptions\MultipleValidationFailedException;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Orm\Store\Interfaces\StructuredStoreInterface;
use Guzaba2\Translator\Translator as t;

/**
 * Class LogEntry
 * @package Guzaba2\Log
 *
 * If log_object_id is null this means an entry is created for the class (not a specific object/record)
 *
 * @property int log_id
 * @property int log_class_id
 * @property int log_object_id
 * @property string log_action
 * @property string log_content
 * @property int log_create_microtime
 * @property int role_id
 *
 * This class unlike the Acl\permission one does not provide alternatives for class_id -> class_name, object_id -> object_uuid and role_id -> role_uuid
 * because no records of this class can be created by the API.
 * Records can be created only by other instances.
 */
class LogEntry extends ActiveRecord
{
    protected const CONFIG_DEFAULTS = [
        'main_table'                => 'logs',
        'no_permissions'            => TRUE,
        'no_meta'                   => TRUE,
        'services'                  => [
            'MysqlOrmStore',
            'CurrentUser',
        ],
    ];
    protected const CONFIG_RUNTIME = [];

    /**
     * @param ActiveRecordInterface $ActiveRecord
     * @param string $log_action This would be the method name that was executed on the ORM object (but can be something else)
     * @param string $log_content
     * @return static
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws LogicException
     * @throws ConfigurationException
     * @throws MultipleValidationFailedException
     * @throws \ReflectionException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     */
    public static function create(ActiveRecordInterface $ActiveRecord, string $log_action, string $log_content): self
    {
        if ($ActiveRecord->is_new()) {
            throw new InvalidArgumentException(sprintf(t::_('It is not allowed to add log entries for %1$s classes that are not saved. If you need to add a log entry for the %2$s class please use %3$s().'), ActiveRecordInterface::class, get_class($ActiveRecord), __CLASS__.'::create_for_class' ));
        }
        /** @var StructuredStoreInterface $StructuredOrmStore */
        $StructuredOrmStore = self::get_service('MysqlOrmStore');
        $log_class_id = $StructuredOrmStore->get_class_id(get_class($ActiveRecord));
        return self::execute_create($log_class_id, $ActiveRecord->get_id(), $log_action, $log_content);
    }

    /**
     * @param string $class
     * @param string $log_action This would be the method name that was executed on the ORM class (but can be something else)
     * @param string $log_content
     * @return LogEntry
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws LogicException
     * @throws ConfigurationException
     * @throws MultipleValidationFailedException
     * @throws \ReflectionException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     */
    public static function create_for_class(string $class, string $log_action, string $log_content): self
    {
        if (!$class) {
            throw new InvalidArgumentException(sprintf(t::_('No $class is provided.')));
        }
        if (!class_exists($class)) {
            throw new InvalidArgumentException(spritf(t::_('There is no class %1$s.'), $class));
        }
        if (! (new ReflectionClass($class))->isInstantiable()) {
            throw new InvalidArgumentException(spritf(t::_('The provided class %1$s is not instantiable.'), $class));
        }
        /** @var StructuredStoreInterface $StructuredOrmStore */
        $StructuredOrmStore = self::get_service('MysqlOrmStore');
        $log_class_id = $StructuredOrmStore->get_class_id($class);
        return self::execute_create($log_class_id, NULL, $log_action, $log_content);
    }

    /**
     * @param int $log_class_id
     * @param int $log_object_id
     * @param string $log_action
     * @param string $log_content
     * @return static
     * @throws ConfigurationException
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws MultipleValidationFailedException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     */
    protected static function execute_create(int $log_class_id, ?int $log_object_id, string $log_action, string $log_content): self
    {
        if (!$log_action) {
            throw new InvalidArgumentException(sprintf(t::_('No $log_action is provided.')));
        }
        if (!$log_content) {
            throw new InvalidArgumentException(sprintf(t::_('No $log_content is provided.')));
        }
        /** @var CurrentUser $CurrentUser */
        $CurrentUser = self::get_service('CurrentUser');
        $LogEntry = new static();
        $LogEntry->log_class_id = $log_class_id;
        $LogEntry->log_object_id = $log_object_id;
        $LogEntry->log_action = $log_action;
        $LogEntry->log_content = $log_content;
        $LogEntry->log_create_microtime = microtime(TRUE) * 1_000_000;
        $LogEntry->role_id = $CurrentUser->get()->get_role()->get_id();
        $LogEntry->write();
        return $LogEntry;
    }
}