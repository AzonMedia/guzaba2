<?php

declare(strict_types=1);

namespace Guzaba2\Authorization\Acl;

/**
 * Class AclAutomaticCreateAuthorizationProvider
 * @package Guzaba2\Authorization\Acl
 *
 * This AuthorizatoinProvider automatically creates a permission entry for every authorization request if such permission entry doesnt exist.
 * To be used in the initial stages of system development to create the needed permission sets.
 */
class AclAutomaticCreateAuthorizationProvider extends AclCreateAuthorizationProvider
{

}
