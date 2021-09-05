<?php

declare(strict_types=1);

namespace Guzaba2\Authorization\Traits;

use Azonmedia\Reflection\ReflectionClass;
use Guzaba2\Authorization\Exceptions\PermissionDeniedException;
use Guzaba2\Authorization\Interfaces\AuthorizationProviderInterface;
use Guzaba2\Authorization\Interfaces\PermissionInterface;
use Guzaba2\Authorization\Role;
use Guzaba2\Authorization\User;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Di\Container;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Translator\Translator as t;

trait AuthorizationProviderTrait
{

    /**
     * @param int $permission_denied_reason
     * @return string[]
     */
    public static function get_permission_denied_reason(int $permission_denied_reason): array
    {
        $ret = [];
        foreach (AuthorizationProviderInterface::PERMISSION_DENIED as $key => $value) {
            if ($value & $permission_denied_reason) {
                $ret[] = $key;
            }
        }
        return $ret;
    }

    /**
     * Returns the name of the class that this class uses for the implementation of PermissionInterface
     * @return string
     */
    public static function get_permission_class(): string
    {
        return static::CONFIG_RUNTIME['class_dependencies'][PermissionInterface::class];
    }

    /**
     * Returns a boolean can the provided $role perform the $action on the object $ActiveRecord
     * @param string $action
     * @param ActiveRecordInterface $ActiveRecord
     * @return bool
     */
    public function current_role_can(string $action, ActiveRecordInterface $ActiveRecord, ?int &$permission_denied_reason = null): bool
    {

        //debug_print_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS);
        //do not check the permissions of the default user as this will trigger recursion
        //if the default user is 0 the permissions are not checked as this is a new object
        //if ($ActiveRecord instanceof User && $ActiveRecord->get_id() === Container::get_default_current_user_id()) {
            //return TRUE;
        //}
        //the above is no longer needed as the user instance used for currentUser now is created as readonly and without permissions checks

        $Role = self::get_service('CurrentUser')->get()->get_role();
        return $this->role_can($Role, $action, $ActiveRecord, $permission_denied_reason);
    }

    public function current_role_can_on_class(string $action, string $class, ?int &$permission_denied_reason = null): bool
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
        return $this->role_can_on_class($Role, $action, $class, $permission_denied_reason);
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
        if (!$this->current_role_can($action, $ActiveRecord, $permission_denied_reason)) {
            $Role = self::get_service('CurrentUser')->get()->get_role();
            $message = sprintf(
                t::_('Role %1$s is not allowed to perform action %2$s() on object %3$s:%4$s. Reason: %5$s'),
                $Role->role_name,
                $action,
                get_class($ActiveRecord),
                $ActiveRecord->get_uuid(),
                implode(',', self::get_permission_denied_reason($permission_denied_reason))
            );
            throw new PermissionDeniedException($message);
        }
    }

    public function check_class_permission(string $action, string $class_name): void
    {
        if (!$this->current_role_can_on_class($action, $class_name, $permission_denied_reason)) {
            $Role = self::get_service('CurrentUser')->get()->get_role();
            $message = sprintf(
                t::_('Role %1$s is not allowed to perform action %2$s() on class %3$s. Reason: %4$s.'),
                $Role->role_name,
                $action,
                $class_name,
                implode(',', self::get_permission_denied_reason($permission_denied_reason))
            );
            throw new PermissionDeniedException($message);
        }
    }
}
