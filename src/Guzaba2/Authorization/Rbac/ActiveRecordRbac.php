<?php


namespace Guzaba2\Authorization\Rbac;


use Guzaba2\Orm\ActiveRecord;

class ActiveRecordRbac extends ActiveRecord
{

    public const RBAC_METHOD_PREFIX = 'rbac_';

    protected function load() : void
    {
        parent::load();
    }

    public function save() : ActiveRecord
    {
        return parent::save();
    }

    public function delete() : ActiveRecord
    {
        //return
    }

    public function __call(string $method, array $args) /* mixed */
    {
        if (!strpos($method,self::RBAC_METHOD_PREFIX)===0) {
            //throw
        }
        $clean_method_name = substr($method, strlen(self::RBAC_METHOD_PREFIX));
        if (!method_exists($this, $clean_method_name)) {
            //throw
        }
        
        return [$this, $method](...$args);
    }

    public function role_can(Role $Role, string $action) : bool
    {
        //get all operations that support that action
        $ret = FALSE;
        $operations = Operation::get_data_by( ['action_name' => $action] );
        foreach ($operations as $operation_data) {
            $permissions_operations = PermissionOperation::get_data_by( ['operation_id' => $operation_data['operation_id']] );
            foreach ($permissions_operations as $permission_operation_data) {
                $roles_permissions = RolePermission::get_data_by( ['permission_id' => $permission_operation_data['permission_data']] );
                foreach ($roles_permissions as $roles_permission_data) {
                    $roles_ids = $Role->get_all_inherited_roles_ids();
                    foreach ($roles_ids as $role_id) {
                        if ($role_id === $roles_permission_data['role_id']) {
                            $ret = TRUE;
                            break 4;
                        }
                    }
                }
            }
        }
        return $ret;
    }
}