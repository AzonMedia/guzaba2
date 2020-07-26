<?php

declare(strict_types=1);

namespace Guzaba2\Authorization\Traits;

use Azonmedia\Reflection\ReflectionClass;
use Guzaba2\Authorization\Exceptions\PermissionDeniedException;
use Guzaba2\Authorization\Role;
use Guzaba2\Authorization\User;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Di\Container;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Translator\Translator as t;

trait AuthorizationProviderTrait
{
    /**
     * Returns a boolean can the provided $role perform the $action on the object $ActiveRecord
     * @param string $action
     * @param ActiveRecordInterface $ActiveRecord
     * @return bool
     */
    public function current_role_can(string $action, ActiveRecordInterface $ActiveRecord): bool
    {

        //debug_print_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS);
        //do not check the permissions of the default user as this will trigger recursion
        //if the default user is 0 the permissions are not checked as this is a new object
        //if ($ActiveRecord instanceof User && $ActiveRecord->get_id() === Container::get_default_current_user_id()) {
            //return TRUE;
        //}
        //the above is no longer needed as the user instance used for currentUser now is created as readonly and without permissions checks

        $Role = self::get_service('CurrentUser')->get()->get_role();
        return $this->role_can($Role, $action, $ActiveRecord);
    }

    public function current_role_can_on_class(string $action, string $class): bool
    {
        if (!class_exists($class)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided class %s does not exist.')));
        }
        $RClass = new ReflectionClass($class);
        if (!$RClass->hasOwnMethod($action)) {
            throw new InvalidArgumentException(sprintf(t::_('The class %s has no method %s.'), $action));
        }
        //TODO add more validation

        $Role = self::get_service('CurrentUser')->get()->get_role();
        return $this->role_can_on_class($Role, $action, $class);
    }

    /**
     * Checks can the provided $role perform the $action on the object $ActiveRecord and if cant a PermissionDeniedException is thrown
     * @throws PermissionDeniedException
     * @param string $action
     * @param ActiveRecordInterface $ActiveRecord
     * @return bool
     */
    public function check_permission(string $action, ActiveRecordInterface $ActiveRecord): void
    {
        if (!$this->current_role_can($action, $ActiveRecord)) {
            $Role = self::get_service('CurrentUser')->get()->get_role();
            throw new PermissionDeniedException(sprintf(t::_('Role %s is not allowed to perform action %s() on object %s:%s.'), $Role->role_name, $action, get_class($ActiveRecord), $ActiveRecord->get_uuid()));
        }
    }

    public function check_class_permission(string $action, string $class_name): void
    {
        if (!$this->current_role_can_on_class($action, $class_name)) {
            $Role = self::get_service('CurrentUser')->get()->get_role();
            throw new PermissionDeniedException(sprintf(t::_('Role %s is not allowed to perform action %s() on class %s.'), $Role->role_name, $action, $class_name));
        }
    }
}
