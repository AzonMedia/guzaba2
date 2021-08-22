<?php

declare(strict_types=1);

namespace Guzaba2\Authorization\Interfaces;

use Guzaba2\Authorization\Role;
use Guzaba2\Authorization\RolesHierarchy;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;

interface UserInterface extends ActiveRecordInterface
{
    public function get_uuid(): string;

    public function get_id()  /* int|string */;

    public function get_role(): Role ;

    public function enable(): void;

    public function disable(): void;

    public function grant_role(Role $Role): RolesHierarchy;

    public function revoke_role(Role $Role): void;

    /**
     * Alias of self::inherits_role()
     * @param Role $Role
     * @return bool
     */
    public function is_member_of(Role $Role): bool;

    public function inherits_role(Role $Role): bool;
}
