<?php

declare(strict_types=1);

namespace Guzaba2\Orm\Interfaces;

use Guzaba2\Authorization\Role;
use Guzaba2\Base\Interfaces\BaseInterface;

interface ActiveRecordInterface extends BaseInterface, ActiveRecordAliasInterface, ActiveRecordAuthorizationInterface
{

    public const PROPERTY_VALIDATION_SUPPORTED_RULES = [
        'required'      => 'bool',
        'max_length'    => 'int',
        'min_length'    => 'int',
    ];

    public const CRUD_HOOKS = [
        '_before_read', '_after_read',
        '_before_write', '_after_write',
        '_before_delete', '_after_delete',
    ];

    public const META_TABLE_COLUMNS = [
        'meta_object_uuid_binary',//TODO - mysql specific - remove
        'meta_object_uuid',
        //'meta_class_name',
        'meta_class_id',
        'meta_object_id',
        'meta_object_create_microtime',
        'meta_object_last_update_microtime',
        'meta_object_create_transaction_id',
        'meta_object_last_update_transaction_id',
    ];

    public const AUTHZ_METHOD_PREFIX = 'authz_';

    public const INDEX_NEW = 0;

    /**
     * Returns a two-dimensional array containing all routes to manage an instance from this class.
     * The routes by default use ActiveRecordDefaultController unless there is a custom controller overriding a route.
     * @return iterable|null
     */
    public static function get_routes(): ?iterable;

    public static function get_main_table(): string;

    public static function get_temporal_class(): string;

    public function is_new(): bool;

    public function get_uuid(): string;

    public function get_id();

    public function get_primary_index(): array;

    public function read($index) : void;

    public function change_owner(): void;

    public function write(bool $force_write = false, bool $disable_validation = false, bool $permission_checks_disabled = false): ActiveRecordInterface;

    public function delete(): void;


    public function check_permission(string $action): void;

    public function current_role_can(string $action): bool;

    public function role_can(Role $Role, string $action): bool;
    
    public static function get_data_by(array $index, int $offset = 0, int $limit = 0, bool $use_like = false, ?string $sort_by = null, bool $sort_desc = false, ?int &$total_found_rows = null): array;

    public function disable_method_hooks(): void;

    public function enable_method_hooks(): void;

    public function are_method_hooks_disabled(): bool;

    public function is_read_only(): bool;

    public function are_permission_checks_disabled(): bool;

    public function is_modified_data_tracking_disabled(): bool;

    public function disable_modified_data_tracking(): void;

    public function enable_modified_data_tracking(): void;

    /**
     * @param iterable $data
     * @param bool $execute_after_read
     * @return ActiveRecordInterface
     */
    public static function get_from_record(iterable $data, bool $execute_after_read = true): ActiveRecordInterface;

}
