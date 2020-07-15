<?php
declare(strict_types=1);

namespace Guzaba2\Orm\Traits;


use Guzaba2\Authorization\Exceptions\PermissionDeniedException;
use Guzaba2\Orm\Exceptions\RecordNotFoundException;
use Guzaba2\Orm\ObjectAlias;

trait ActiveRecordAlias
{
    public function add_alias(string $alias): ObjectAlias
    {
        return ObjectAlias::create($this, $alias);
    }

    public function delete_alias(string $alias): void
    {
        (new ObjectAlias( ['object_alias_object_id' => $this->get_id(), 'object_alias_class_id' => $this::get_class_id(), 'object_alians_name' => $alias ] ))->delete();
    }

    public function delete_all_aliases(): void
    {
        $aliases = ObjectAlias::get_by( ['object_alias_object_id' => $this->get_id(), 'object_alias_class_id' => $this::get_class_id()] );
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
        $alias = NULL;
        try {
            $ObjectAlias = new ObjectAlias( ['object_alias_object_id' => $this->get_id(), 'object_alias_class_id' => $this::get_class_id()] );
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
        return ObjectAlias::get_data_by( ['object_alias_object_id' => $this->get_id(), 'object_alias_class_id' => $this::get_class_id()] );
    }

    /**
     * This is similar to @see ActiveRecord::get_by_uuid().
     * It is supposed to be invoked on the ActiveRecord class (not a child class) and will return an object no matter the class if the alias matches.
     * @throws PermissionDeniedException
     * @throws RecordNotFoundException
     * @param string $alias
     * @return ActiveRecordAlias
     */
    public static final function get_by_alias(string $alias): self
    {
        return (new ObjectAlias( ['object_alias_name' => $alias] ))->get_object();
    }
}