<?php

namespace Guzaba2\Authorization\Interfaces;

use Guzaba2\Authorization\Exceptions\PermissionDeniedException;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Authorization\Role;

/**
 * Interface AuthorizationProviderInterface
 * @package Guzaba2\Authorization\Interfaces
 */
interface AuthorizationProviderInterface
{
    /**
     * Returns a boolean can the provided $role perform the $action on the object $ActiveRecord.
     * @param Role $Role
     * @param string $action
     * @param ActiveRecordInterface $ActiveRecord
     * @return bool
     */
    public function role_can(Role $Role, string $action, ActiveRecordInterface $ActiveRecord) : bool ;

    /**
     * Returns a boolean can the provided $role perform the $action on the object $ActiveRecord.
     * @param string $action
     * @param ActiveRecordInterface $ActiveRecord
     * @return bool
     */
    public function current_role_can(string $action, ActiveRecordInterface $ActiveRecord) : bool ;

    /**
     * Checks can the provided $role perform the $action on the object $ActiveRecord and if cant a PermissionDeniedException is thrown.
     * @throws PermissionDeniedException
     * @param string $action
     * @param ActiveRecordInterface $ActiveRecord
     * @return bool
     */
    public function check_permission(string $action, ActiveRecordInterface $ActiveRecord) : void ;

    /**
     * Returns the class names of the ActiveRecord classes used by the Authorization implementation.
     * @return array
     */
    public static function get_used_active_record_classes() : array ;
}