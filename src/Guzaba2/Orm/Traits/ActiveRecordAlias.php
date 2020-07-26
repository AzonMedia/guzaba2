<?php

declare(strict_types=1);

namespace Guzaba2\Orm\Traits;

use Guzaba2\Authorization\Exceptions\PermissionDeniedException;
use Guzaba2\Orm\Exceptions\RecordNotFoundException;
use Guzaba2\Orm\ObjectAlias;

trait ActiveRecordAlias
{

    /**
     * Adds an alias to the object
     * @param string $alias
     * @return ObjectAlias
     * @throws \Guzaba2\Base\Exceptions\RunTimeException
     */
    public function add_alias(string $alias): ObjectAlias
    {
        return ObjectAlias::create($this, $alias);
    }

    /**
     * Removes an alias by the provided $alias.
     * Will remove it only if the alias is to this object (if the alias matches but for another object RecordNotFoundException will be thrown
     * @param string $alias
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Base\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Base\Exceptions\LogicException
     * @throws \Guzaba2\Base\Exceptions\RunTimeException
     * @throws RecordNotFoundException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \Guzaba2\Kernel\Exceptions\ConfigurationException
     * @throws \ReflectionException
     */
    public function delete_alias(string $alias): void
    {
        (new ObjectAlias(['object_alias_object_id' => $this->get_id(), 'object_alias_class_id' => $this::get_class_id(), 'object_alians_name' => $alias ]))->delete();
    }

    /**
     * Removes all aliases for this object.
     * @throws \Guzaba2\Base\Exceptions\RunTimeException
     */
    public function delete_all_aliases(): void
    {
        $aliases = ObjectAlias::get_by(['object_alias_object_id' => $this->get_id(), 'object_alias_class_id' => $this::get_class_id()]);
        foreach ($aliases as $ObjectAlias) {
            $ObjectAlias->delete();
        }
    }

    /**
     * Returns the primary alias.
     * @return string
     */
    public function get_alias(): ?string
    {
        $alias = null;
        try {
            $ObjectAlias = new ObjectAlias(['object_alias_object_id' => $this->get_id(), 'object_alias_class_id' => $this::get_class_id()]);
            //another way would be is to use get_data_by with ORDER argument by object_alias_id ASC
            $alias = $ObjectAlias->object_alias_name;
        } catch (RecordNotFoundException $Exception) {
        }// PermissionDeniedExceptino is not expected here as the target object is already instantiated (meaning there is permission to read it)
        return $alias;
    }

    /**
     * Returns all aliases
     * @return string[]
     */
    public function get_all_aliases(): array
    {
        return ObjectAlias::get_data_by(['object_alias_object_id' => $this->get_id(), 'object_alias_class_id' => $this::get_class_id()]);
    }

    /**
     * This is similar to @see ActiveRecord::get_by_uuid().
     * It is supposed to be invoked on the ActiveRecord class (not a child class) and will return an object no matter the class if the alias matches.
     * @throws PermissionDeniedException
     * @throws RecordNotFoundException
     * @param string $alias
     * @return ActiveRecordAlias
     */
    final public static function get_by_alias(string $alias): self
    {
        return (new ObjectAlias(['object_alias_name' => $alias]))->get_object();
    }
}
