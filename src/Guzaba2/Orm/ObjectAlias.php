<?php

declare(strict_types=1);

namespace Guzaba2\Orm;

use Guzaba2\Authorization\Exceptions\PermissionDeniedException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Orm\Exceptions\RecordNotFoundException;
use Guzaba2\Orm\Exceptions\ValidationFailedException;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Orm\Interfaces\ValidationFailedExceptionInterface;
use Guzaba2\Translator\Translator as t;

/**
 * Class ObjectAlias
 * @package Guzaba2\Orm
 *
 * Multiple aliases per object are supported.
 * The first one is the main one and will be used/preferred over the object UUID in the front-end.
 *
 * @property int object_alias_id
 * @property int object_alias_object_id
 * @property int object_alias_class_id
 * @property string object_alias_name
 */
class ObjectAlias extends ActiveRecord
{

    protected const CONFIG_DEFAULTS = [
        'main_table'                => 'object_aliases',
        'no_permissions'            => true,//the permissions of the main object will be used
        //'no_meta'                   => TRUE,//meta is good to have to know when an alias was added/modified

    ];
    protected const CONFIG_RUNTIME = [];

    /**
     * @param ActiveRecord $ActiveRecord
     * @param string $alias
     * @return ObjectAlias
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Base\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Base\Exceptions\LogicException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \Guzaba2\Kernel\Exceptions\ConfigurationException
     * @throws \ReflectionException
     */
    public static function create(ActiveRecord $ActiveRecord, string $alias): self
    {
        if ($ActiveRecord->is_new()) {
            throw new RunTimeException(sprintf(t::_('Object aliases can be added only to saved records.')));
        }

        $ObjectAlias = new static();
        $ObjectAlias->object_alias_object_id = $ActiveRecord->get_id();
        $ObjectAlias->object_alias_class_id = $ActiveRecord::get_class_id();
        $ObjectAlias->object_alias_name = $alias;
        $ObjectAlias->write();
        return $ObjectAlias;
    }

    protected function _after_read(): void
    {
        //SECURITY - check the permissions of the object to which this is an alias
        //it is not supposed to be readable is the object is not readable
        //$this->get_object();//will trigger the permission denied if there is no permission to read the target object
        //TODO - add permission check
        //instantiating the object here may trigger a recursion as the object itself may be creating the alias in its _after_read
    }

    protected function _validate_object_alias_name(): ?ValidationFailedExceptionInterface
    {
        if (!$this->object_alias_name) {
            return new ValidationFailedException($this, 'object_alias_name', sprintf(t::_('There is no alias name provided.')));
        }
        try {
            $ObjectAlias = new static(['object_alias_name' => $this->object_alias_name]);
            return new ValidationFailedException($this, 'object_alias_name', sprintf(t::_('There is already an alias named %s.'), $this->object_alias_name));
        } catch (RecordNotFoundException $Exception) {
            //OK
        } catch (PermissionDeniedException $Exception) {
            //SECURITY - since duplicate aliases are not allowed this will expose that there is already an alias with this name even if the current user has no permission to access the related object
            return new ValidationFailedException($this, 'object_alias_name', sprintf(t::_('There is already an alias named %s.'), $this->object_alias_name));
        }

        if (strpos('/', $this->object_alias_name) !== false) {
            return new ValidationFailedException($this, 'object_alias_name', sprintf(t::_('There is already an alias named %s.'), $this->object_alias_name));
        }

        return null;
    }

    //TODO implement:
    //protected function _validate_object_alias_object_id()
    //protected function _validate_object_alias_class_id()

    /**
     * Returns the target object of the alias.
     * Can not be invoked on new instances.
     * @return ActiveRecordInterface
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Base\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     */
    public function get_object(): ActiveRecordInterface
    {

        if ($this->is_new()) {
            throw new RunTimeException(sprintf(t::_('The method %s() can not be invoked on new instances.'), __METHOD__));
        }
        $class = ActiveRecord::get_class_name($this->object_alias_class_id);
        return new $class($this->object_alias_object_id);
    }
}
