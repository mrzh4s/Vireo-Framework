<?php

/**
 * Global helper functions for permissions
 */

use Framework\Security\Permission;
use Framework\Http\Session;

if (!function_exists('can')) {
    /**
     * Check if user can perform action
     */
    function can($permission, $value = null, $attribute = null, $attributeValue = null) {
        return Permission::getInstance()->can($permission, $value, $attribute, $attributeValue);
    }
}

if (!function_exists('cannot')) {
    /**
     * Check if user cannot perform action
     */
    function cannot($permission, $value = null, $attribute = null, $attributeValue = null) {
        return !can($permission, $value, $attribute, $attributeValue);
    }
}

if (!function_exists('can_any')) {
    /**
     * Check multiple permissions (OR logic)
     */
    function can_any($permissions) {
        return Permission::getInstance()->canAny($permissions);
    }
}

if (!function_exists('can_all')) {
    /**
     * Check multiple permissions (AND logic)
     */
    function can_all($permissions) {
        return Permission::getInstance()->canAll($permissions);
    }
}

if (!function_exists('is_admin')) {
    /**
     * Check if user is admin
     */
    function is_admin() {
        return Permission::getInstance()->isAdmin();
    }
}

if (!function_exists('is_super_admin')) {
    /**
     * Check if user is super admin
     */
    function is_super_admin() {
        return Permission::getInstance()->isSuperAdmin();
    }
}

if (!function_exists('user_roles')) {
    /**
     * Get user's effective roles
     */
    function user_roles() {
        $session = Session::getInstance();
        $userRole = $session->get('user.role') ?: $session->get('role');
        
        if (!$userRole) {
            return [];
        }
        
        return Permission::getInstance()->getUserRoles($userRole);
    }
}

if (!function_exists('user_permissions')) {
    /**
     * Get user's permissions
     */
    function user_permissions() {
        $session = Session::getInstance();
        $userRole = $session->get('user.role') ?: $session->get('role');

        if (!$userRole) {
            return [];
        }

        return Permission::getInstance()->getRolePermissions($userRole);
    }
}

// ============== DATABASE PERMISSION HELPERS ==============

if (!function_exists('permission_source')) {
    /**
     * Get current permission source (config, database, or both)
     *
     * Usage:
     * $source = permission_source();  // Returns: 'config', 'database', or 'both'
     *
     * @return string
     */
    function permission_source() {
        return Permission::getInstance()->getSource();
    }
}

if (!function_exists('set_permission_source')) {
    /**
     * Set permission source and reload
     *
     * Usage:
     * set_permission_source('database');  // Use database
     * set_permission_source('config');    // Use config file
     * set_permission_source('both');      // Use both (merged)
     *
     * @param string $source 'config', 'database', or 'both'
     * @return bool
     */
    function set_permission_source($source) {
        try {
            Permission::getInstance()->setSource($source);
            return true;
        } catch (Exception $e) {
            if (app_debug()) {
                error_log("Failed to set permission source: " . $e->getMessage());
            }
            return false;
        }
    }
}

if (!function_exists('create_role')) {
    /**
     * Create new role in database
     *
     * Usage:
     * create_role('editor', 'Editor', 'Can edit content');
     *
     * @param string $name Role name (lowercase, no spaces)
     * @param string $displayName Display name
     * @param string|null $description Optional description
     * @return int|false Role ID or false on failure
     */
    function create_role($name, $displayName, $description = null) {
        try {
            return Permission::getInstance()->createRole($name, $displayName, $description);
        } catch (Exception $e) {
            if (app_debug()) {
                error_log("Failed to create role: " . $e->getMessage());
            }
            return false;
        }
    }
}

if (!function_exists('create_permission')) {
    /**
     * Create new permission in database
     *
     * Usage:
     * create_permission('articles.publish', 'Publish Articles', 'Can publish articles to live site', 'articles');
     *
     * @param string $name Permission name (dot notation)
     * @param string $displayName Display name
     * @param string|null $description Optional description
     * @param string|null $module Module name (e.g., 'users', 'projects')
     * @param bool $isPublic If true, all authenticated users have this permission
     * @return int|false Permission ID or false on failure
     */
    function create_permission($name, $displayName, $description = null, $module = null, $isPublic = false) {
        try {
            return Permission::getInstance()->createPermission($name, $displayName, $description, $module, $isPublic);
        } catch (Exception $e) {
            if (app_debug()) {
                error_log("Failed to create permission: " . $e->getMessage());
            }
            return false;
        }
    }
}

if (!function_exists('grant_permission')) {
    /**
     * Grant permission to role
     *
     * Usage:
     * grant_permission('editor', 'articles.publish');
     *
     * @param string $roleName Role name
     * @param string $permissionName Permission name
     * @param int|null $grantedBy Optional user ID who granted this
     * @return bool
     */
    function grant_permission($roleName, $permissionName, $grantedBy = null) {
        try {
            return Permission::getInstance()->grantPermission($roleName, $permissionName, $grantedBy);
        } catch (Exception $e) {
            if (app_debug()) {
                error_log("Failed to grant permission: " . $e->getMessage());
            }
            return false;
        }
    }
}

if (!function_exists('revoke_permission')) {
    /**
     * Revoke permission from role
     *
     * Usage:
     * revoke_permission('editor', 'articles.publish');
     *
     * @param string $roleName Role name
     * @param string $permissionName Permission name
     * @return bool
     */
    function revoke_permission($roleName, $permissionName) {
        try {
            return Permission::getInstance()->revokePermission($roleName, $permissionName);
        } catch (Exception $e) {
            if (app_debug()) {
                error_log("Failed to revoke permission: " . $e->getMessage());
            }
            return false;
        }
    }
}

if (!function_exists('assign_role')) {
    /**
     * Assign role to user
     *
     * Usage:
     * assign_role(123, 'editor');                    // Assign editor role to user 123
     * assign_role(123, 'admin', session('user.id')); // With assigned_by tracking
     *
     * @param int $userId User ID
     * @param string $roleName Role name
     * @param int|null $assignedBy Optional user ID who assigned this role
     * @return bool
     */
    function assign_role($userId, $roleName, $assignedBy = null) {
        try {
            return Permission::getInstance()->assignRole($userId, $roleName, $assignedBy);
        } catch (Exception $e) {
            if (app_debug()) {
                error_log("Failed to assign role: " . $e->getMessage());
            }
            return false;
        }
    }
}

if (!function_exists('remove_role')) {
    /**
     * Remove role from user
     *
     * Usage:
     * remove_role(123, 'editor');  // Remove editor role from user 123
     *
     * @param int $userId User ID
     * @param string $roleName Role name
     * @return bool
     */
    function remove_role($userId, $roleName) {
        try {
            return Permission::getInstance()->removeRole($userId, $roleName);
        } catch (Exception $e) {
            if (app_debug()) {
                error_log("Failed to remove role: " . $e->getMessage());
            }
            return false;
        }
    }
}

if (!function_exists('get_user_roles')) {
    /**
     * Get user's roles from database
     *
     * Usage:
     * $roles = get_user_roles(123);
     * foreach ($roles as $role) {
     *     echo $role->name . ': ' . $role->display_name;
     * }
     *
     * @param int $userId User ID
     * @return array Array of role objects with name and display_name
     */
    function get_user_roles($userId) {
        return Permission::getInstance()->getUserRolesFromDB($userId);
    }
}

if (!function_exists('has_role')) {
    /**
     * Check if user has specific role
     *
     * Usage:
     * if (has_role('admin')) {
     *     // User is admin
     * }
     *
     * @param string $roleName Role name to check
     * @return bool
     */
    function has_role($roleName) {
        return can('role', $roleName);
    }
}

if (!function_exists('get_all_roles')) {
    /**
     * Get all defined roles
     *
     * Usage:
     * $roles = get_all_roles();
     * foreach ($roles as $role) {
     *     echo $role;
     * }
     *
     * @return array Array of role names
     */
    function get_all_roles() {
        return Permission::getInstance()->getRoles();
    }
}

if (!function_exists('get_all_permissions')) {
    /**
     * Get all defined permissions
     *
     * Usage:
     * $permissions = get_all_permissions();
     * foreach ($permissions as $perm) {
     *     echo $perm;
     * }
     *
     * @return array Array of permission names
     */
    function get_all_permissions() {
        return Permission::getInstance()->getPermissions();
    }
}

if (!function_exists('role_exists')) {
    /**
     * Check if a role exists in configuration
     *
     * Usage:
     * if (role_exists('editor')) {
     *     echo "Editor role exists";
     * }
     *
     * @param string $roleName Role name
     * @return bool
     */
    function role_exists($roleName) {
        return Permission::getInstance()->roleExists($roleName);
    }
}

if (!function_exists('permission_exists')) {
    /**
     * Check if a permission exists in configuration
     *
     * Usage:
     * if (permission_exists('articles.publish')) {
     *     echo "Permission exists";
     * }
     *
     * @param string $permissionName Permission name
     * @return bool
     */
    function permission_exists($permissionName) {
        return Permission::getInstance()->permissionExists($permissionName);
    }
}

if (!function_exists('reload_permissions')) {
    /**
     * Reload permissions from source (config or database)
     * Useful after making changes to permissions
     *
     * Usage:
     * grant_permission('editor', 'articles.publish');
     * reload_permissions();  // Refresh permissions
     *
     * @return void
     */
    function reload_permissions() {
        Permission::getInstance()->reload();
    }
}

if (!function_exists('permission_debug')) {
    /**
     * Get debug information about permission system
     *
     * Usage:
     * $debug = permission_debug();
     * print_r($debug);
     *
     * @return array Debug information
     */
    function permission_debug() {
        return Permission::getInstance()->getDebugInfo();
    }
}

if (!function_exists('get_role_permissions')) {
    /**
     * Get all permissions for a specific role
     *
     * Usage:
     * $permissions = get_role_permissions('editor');
     * foreach ($permissions as $perm) {
     *     echo $perm . "\n";
     * }
     *
     * @param string $roleName Role name
     * @return array Array of permission names
     */
    function get_role_permissions($roleName) {
        return Permission::getInstance()->getRolePermissions($roleName);
    }
}

if (!function_exists('get_permission_groups')) {
    /**
     * Get permission groups from config
     *
     * Usage:
     * $groups = get_permission_groups();
     * foreach ($groups as $groupName => $permissions) {
     *     echo "$groupName: " . implode(', ', $permissions);
     * }
     *
     * @return array Permission groups
     */
    function get_permission_groups() {
        return Permission::getInstance()->getPermissionGroups();
    }
}

if (!function_exists('get_group_permissions')) {
    /**
     * Get permissions in a specific group
     *
     * Usage:
     * $userPerms = get_group_permissions('user_management');
     *
     * @param string $groupName Group name
     * @return array Array of permission names in the group
     */
    function get_group_permissions($groupName) {
        return Permission::getInstance()->getGroupPermissions($groupName);
    }
}
