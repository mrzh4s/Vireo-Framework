<?php
/**
 * Enhanced Permission Manager with Dynamic Configuration
 * File: apps/core/Permission.php
 *
 * FEATURES:
 * - Auto-loads from config/Permissions.php
 * - Dynamic role hierarchy
 * - Unlimited custom roles and permissions
 * - Role inheritance
 * - Attribute-based access control (ABAC)
 * - Custom permission rules
 * - Backward compatible
 */
namespace Vireo\Framework\Security;
use Vireo\Framework\Database\DB;
use Vireo\Framework\Http\Session;
use Exception;

class Permission {
    private static $instance = null;
    private $config = [];
    private $permissions = [];
    private $roleHierarchy = [];
    private $customRules = [];
    private $attributeCheckers = [];
    private $permissionSource = 'config'; // 'config', 'database', or 'both'
    private $cache = [];
    private $cacheEnabled = false;
    private $cacheTTL = 3600;

    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - Load configuration
     */
    public function __construct() {
        $this->loadConfiguration();
        $this->registerDefaultAttributeCheckers();
    }

    /**
     * Load permission configuration
     * Supports both config file and database sources
     */
    private function loadConfiguration() {
        // First check if we should use database
        $this->permissionSource = $this->getPermissionSource();

        switch ($this->permissionSource) {
            case 'database':
                $this->loadFromDatabase();
                break;

            case 'both':
                $this->loadFromConfig();
                $this->mergeFromDatabase();
                break;

            case 'config':
            default:
                $this->loadFromConfig();
                break;
        }

        // Load cache settings
        $this->cacheEnabled = $this->getSetting('cache_enabled', false);
        $this->cacheTTL = (int) $this->getSetting('cache_ttl', 3600);
    }

    /**
     * Determine permission source from database or config
     */
    private function getPermissionSource() {
        try {
            // Check database for permission_settings table
            $db = DB::connection();
            $stmt = $db->query("SELECT setting_value FROM permission_settings WHERE setting_key = 'source' LIMIT 1");
            $result = $stmt->fetch();

            if ($result) {
                return $result->setting_value;
            }
        } catch (Exception $e) {
            // Database not available or table doesn't exist, use config
        }

        // Check config file
        $configPath = ROOT_PATH . '/config/Permissions.php';
        if (file_exists($configPath)) {
            return 'config';
        }

        // Fallback to legacy
        return 'config';
    }

    /**
     * Load permissions from config file
     */
    private function loadFromConfig() {
        $configPath = ROOT_PATH . '/config/Permissions.php';

        if (file_exists($configPath)) {
            $this->config = require $configPath;
        } else {
            // Fallback to legacy hardcoded configuration
            $this->config = $this->getLegacyConfiguration();
        }

        // Load role hierarchy
        $this->roleHierarchy = $this->config['roles'] ?? [];

        // Load permissions
        $this->permissions = $this->config['permissions'] ?? [];
    }

    /**
     * Load permissions from database
     */
    private function loadFromDatabase() {
        try {
            $db = DB::connection();

            // Load roles and hierarchies
            $this->loadRolesFromDatabase($db);

            // Load permissions
            $this->loadPermissionsFromDatabase($db);

        } catch (Exception $e) {
            // If database fails, fallback to config
            if (app_debug()) {
                error_log("Failed to load permissions from database: " . $e->getMessage());
            }
            $this->loadFromConfig();
        }
    }

    /**
     * Load roles and hierarchies from database
     */
    private function loadRolesFromDatabase($db) {
        // Get all roles
        $stmt = $db->query("SELECT id, name FROM roles WHERE 1=1 ORDER BY name");
        $roles = $stmt->fetchAll();

        // Initialize role hierarchy
        foreach ($roles as $role) {
            $this->roleHierarchy[$role->name] = [];
        }

        // Load hierarchies
        $stmt = $db->query("
            SELECT
                pr.name as parent_role,
                cr.name as child_role
            FROM role_hierarchies rh
            JOIN roles pr ON rh.parent_role_id = pr.id
            JOIN roles cr ON rh.child_role_id = cr.id
        ");

        $hierarchies = $stmt->fetchAll();

        foreach ($hierarchies as $hierarchy) {
            if (!isset($this->roleHierarchy[$hierarchy->parent_role])) {
                $this->roleHierarchy[$hierarchy->parent_role] = [];
            }
            $this->roleHierarchy[$hierarchy->parent_role][] = $hierarchy->child_role;
        }
    }

    /**
     * Load permissions from database
     */
    private function loadPermissionsFromDatabase($db) {
        // Load all permissions with their assigned roles
        $stmt = $db->query("
            SELECT DISTINCT
                p.name as permission_name,
                p.is_public,
                r.name as role_name
            FROM permissions p
            LEFT JOIN role_permissions rp ON p.id = rp.permission_id
            LEFT JOIN roles r ON rp.role_id = r.id
            ORDER BY p.name
        ");

        $results = $stmt->fetchAll();

        // Group by permission
        $permissionMap = [];
        foreach ($results as $row) {
            $permName = $row->permission_name;

            if (!isset($permissionMap[$permName])) {
                $permissionMap[$permName] = [
                    'is_public' => (bool) $row->is_public,
                    'roles' => []
                ];
            }

            if ($row->role_name) {
                $permissionMap[$permName]['roles'][] = $row->role_name;
            }
        }

        // Convert to permissions array
        foreach ($permissionMap as $permName => $data) {
            if ($data['is_public']) {
                $this->permissions[$permName] = '*';
            } else {
                $this->permissions[$permName] = $data['roles'];
            }
        }
    }

    /**
     * Merge database permissions with config permissions
     */
    private function mergeFromDatabase() {
        try {
            $db = DB::connection();

            // Load database roles and merge
            $this->loadRolesFromDatabase($db);

            // Load database permissions and merge
            $dbPermissions = [];
            $this->loadPermissionsFromDatabase($db);

            // Config permissions take precedence
            $this->permissions = array_merge($this->permissions, $dbPermissions);

        } catch (Exception $e) {
            if (app_debug()) {
                error_log("Failed to merge database permissions: " . $e->getMessage());
            }
        }
    }

    /**
     * Get setting value from database
     */
    private function getSetting($key, $default = null) {
        try {
            $db = DB::connection();
            $stmt = $db->prepare("SELECT setting_value FROM permission_settings WHERE setting_key = ? LIMIT 1");
            $stmt->execute([$key]);
            $result = $stmt->fetch();

            if ($result) {
                $value = $result->setting_value;
                // Convert string booleans
                if ($value === 'true') return true;
                if ($value === 'false') return false;
                return $value;
            }
        } catch (Exception $e) {
            // Ignore database errors
        }

        return $default;
    }

    /**
     * Register default attribute checkers
     */
    private function registerDefaultAttributeCheckers() {
        // Department checker
        $this->addAttributeChecker('department', function($value, $userData) {
            return strtolower($userData['department'] ?? '') === strtolower($value);
        });

        // Location checker
        $this->addAttributeChecker('location', function($value, $userData) {
            return strtolower($userData['location'] ?? '') === strtolower($value);
        });

        // Role checker
        $this->addAttributeChecker('role', function($value, $userData) {
            return $this->hasRole($userData['role'] ?? '', $value);
        });

        // Username checker
        $this->addAttributeChecker('username', function($value, $userData) {
            return strtolower($userData['username'] ?? '') === strtolower($value);
        });

        // Own/ownership checker
        $this->addAttributeChecker('own', function($value, $userData) {
            return strtolower($userData['username'] ?? '') === strtolower($value);
        });
    }

    /**
     * Check if user can perform action
     *
     * @param string $permission Permission to check
     * @param mixed $value Optional value for permission
     * @param string|null $attribute Attribute to check (department, location, etc.)
     * @param mixed $attributeValue Value to check against attribute
     * @return bool
     */
    public function can($permission, $value = null, $attribute = null, $attributeValue = null) {
        $session = Session::getInstance();

        // Get current user data
        $userRole = $session->get('user.role') ?: $session->get('role');
        $userDepartment = $session->get('user.department') ?: $session->get('department');
        $userLocation = $session->get('user.location') ?: $session->get('location');
        $username = $session->get('user.username') ?: $session->get('username');

        // No role means no permissions (unless guest permissions are defined)
        if (!$userRole) {
            return $this->checkGuestPermission($permission);
        }

        // Check if user is super admin (bypass all checks)
        if ($this->isSuperAdmin($userRole)) {
            $superAdminConfig = $this->config['super_admin'] ?? [];
            if ($superAdminConfig['bypass_all'] ?? true) {
                return true;
            }
        }

        // Build user data array
        $userData = [
            'role' => $userRole,
            'department' => $userDepartment,
            'location' => $userLocation,
            'username' => $username
        ];

        // Log permission check if debugging enabled
        if ($this->config['debug']['log_permission_checks'] ?? false) {
            error_log("Permission Check: {$permission} for role: {$userRole}");
        }

        // Check direct permission
        if ($this->checkDirectPermission($permission, $userRole, $value)) {
            // If attribute check is required
            if ($attribute && $attributeValue) {
                return $this->checkAttributePermission($attribute, $attributeValue, $userData);
            }
            return true;
        }

        // Check custom rules
        return $this->checkCustomRules($permission, $userRole, $value, $attribute, $attributeValue);
    }

    /**
     * Check guest permission (for unauthenticated users)
     */
    private function checkGuestPermission($permission) {
        $guestConfig = $this->config['guest'] ?? [];
        $guestPermissions = $guestConfig['permissions'] ?? [];

        return in_array($permission, $guestPermissions);
    }

    /**
     * Check direct permission
     */
    private function checkDirectPermission($permission, $userRole, $value) {
        // Special case: role permission check
        if (strtolower($permission) === 'role' && $value) {
            return $this->hasRole($userRole, $value);
        }

        // Check if permission exists in definitions
        if (isset($this->permissions[$permission])) {
            $allowedRoles = $this->permissions[$permission];

            // Universal access
            if ($allowedRoles === '*') {
                return true;
            }

            // Check if user role is in allowed roles
            if (is_array($allowedRoles)) {
                return $this->hasAnyRole($userRole, $allowedRoles);
            }
        }

        return false;
    }

    /**
     * Check if user has specific role or inherits it
     */
    private function hasRole($userRole, $requiredRole) {
        $userRole = strtolower($userRole);
        $requiredRole = strtolower($requiredRole);

        // Direct match
        if ($userRole === $requiredRole) {
            return true;
        }

        // Check role hierarchy
        if (isset($this->roleHierarchy[$userRole])) {
            return in_array($requiredRole, array_map('strtolower', $this->roleHierarchy[$userRole]));
        }

        return false;
    }

    /**
     * Check if user has any of the required roles
     */
    private function hasAnyRole($userRole, $allowedRoles) {
        foreach ($allowedRoles as $allowedRole) {
            if ($this->hasRole($userRole, $allowedRole)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check attribute-based permission
     */
    private function checkAttributePermission($attribute, $value, $userData) {
        $attribute = strtolower($attribute);

        // Check if custom attribute checker exists
        if (isset($this->attributeCheckers[$attribute])) {
            return call_user_func($this->attributeCheckers[$attribute], $value, $userData);
        }

        // Fallback to legacy attribute checks
        return $this->legacyAttributeCheck($attribute, $value, $userData);
    }

    /**
     * Legacy attribute check for backward compatibility
     */
    private function legacyAttributeCheck($attribute, $value, $userData) {
        switch ($attribute) {
            case 'department':
                return strtolower($userData['department'] ?? '') === strtolower($value);

            case 'location':
                return strtolower($userData['location'] ?? '') === strtolower($value);

            case 'role':
                return $this->hasRole($userData['role'] ?? '', $value);

            case 'username':
                return strtolower($userData['username'] ?? '') === strtolower($value);

            case 'own':
                return strtolower($userData['username'] ?? '') === strtolower($value);

            default:
                return false;
        }
    }

    /**
     * Check custom permission rules
     */
    private function checkCustomRules($permission, $userRole, $value, $attribute, $attributeValue) {
        if (isset($this->customRules[$permission])) {
            $rule = $this->customRules[$permission];

            if (is_callable($rule)) {
                return $rule($userRole, $value, $attribute, $attributeValue);
            }
        }

        return false;
    }

    /**
     * Check multiple permissions (OR logic)
     */
    public function canAny($permissions) {
        foreach ($permissions as $permission => $value) {
            if (is_numeric($permission)) {
                // Simple permission list
                if ($this->can($value)) {
                    return true;
                }
            } else {
                // Permission => value pairs
                if ($this->can($permission, $value)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Check multiple permissions (AND logic)
     */
    public function canAll($permissions) {
        foreach ($permissions as $permission => $value) {
            if (is_numeric($permission)) {
                // Simple permission list
                if (!$this->can($value)) {
                    return false;
                }
            } else {
                // Permission => value pairs
                if (!$this->can($permission, $value)) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Add custom permission rule
     */
    public function addRule($permission, $callback) {
        $this->customRules[$permission] = $callback;
    }

    /**
     * Add attribute checker
     */
    public function addAttributeChecker($attribute, $callback) {
        $this->attributeCheckers[strtolower($attribute)] = $callback;
    }

    /**
     * Add permission definition dynamically
     */
    public function addPermission($permission, $roles) {
        $this->permissions[$permission] = $roles;
    }

    /**
     * Remove permission
     */
    public function removePermission($permission) {
        unset($this->permissions[$permission]);
    }

    /**
     * Set role hierarchy
     */
    public function setRoleHierarchy($hierarchy) {
        $this->roleHierarchy = $hierarchy;
    }

    /**
     * Get role hierarchy
     */
    public function getRoleHierarchy() {
        return $this->roleHierarchy;
    }

    /**
     * Get all defined roles
     */
    public function getRoles() {
        return array_keys($this->roleHierarchy);
    }

    /**
     * Get all defined permissions
     */
    public function getPermissions() {
        return array_keys($this->permissions);
    }

    /**
     * Check if a role exists
     */
    public function roleExists($role) {
        return isset($this->roleHierarchy[strtolower($role)]);
    }

    /**
     * Check if a permission exists
     */
    public function permissionExists($permission) {
        return isset($this->permissions[$permission]);
    }

    /**
     * Get user's effective roles (including inherited)
     */
    public function getUserRoles($userRole) {
        $roles = [strtolower($userRole)];

        if (isset($this->roleHierarchy[strtolower($userRole)])) {
            $inheritedRoles = array_map('strtolower', $this->roleHierarchy[strtolower($userRole)]);
            $roles = array_merge($roles, $inheritedRoles);
        }

        return array_unique($roles);
    }

    /**
     * Get all permissions for a role
     */
    public function getRolePermissions($role) {
        $rolePermissions = [];

        foreach ($this->permissions as $permission => $allowedRoles) {
            if ($allowedRoles === '*' ||
                (is_array($allowedRoles) && $this->hasAnyRole($role, $allowedRoles))) {
                $rolePermissions[] = $permission;
            }
        }

        return $rolePermissions;
    }

    /**
     * Check if user is admin
     */
    public function isAdmin($userRole = null) {
        $session = Session::getInstance();
        $role = $userRole ?: $session->get('user.role') ?: $session->get('role');

        return $this->hasAnyRole($role, ['corridor', 'admin', 'manager']);
    }

    /**
     * Check if user is super admin
     */
    public function isSuperAdmin($userRole = null) {
        $session = Session::getInstance();
        $role = $userRole ?: $session->get('user.role') ?: $session->get('role');

        $superAdminConfig = $this->config['super_admin'] ?? [];
        $superAdminRoles = $superAdminConfig['roles'] ?? ['superadmin'];

        return $this->hasAnyRole($role, $superAdminRoles);
    }

    /**
     * Get permission groups from config
     */
    public function getPermissionGroups() {
        return $this->config['groups'] ?? [];
    }

    /**
     * Get permissions in a group
     */
    public function getGroupPermissions($group) {
        $groups = $this->getPermissionGroups();
        return $groups[$group] ?? [];
    }

    /**
     * Reload configuration from file
     * Useful for testing or runtime updates
     */
    public function reload() {
        $this->loadConfiguration();
        $this->registerDefaultAttributeCheckers();
    }

    /**
     * Get debug information
     */
    public function getDebugInfo() {
        $session = Session::getInstance();
        $userRole = $session->get('user.role') ?: $session->get('role');

        return [
            'current_role' => $userRole,
            'effective_roles' => $userRole ? $this->getUserRoles($userRole) : [],
            'is_admin' => $this->isAdmin($userRole),
            'is_super_admin' => $this->isSuperAdmin($userRole),
            'total_permissions' => count($this->permissions),
            'total_roles' => count($this->roleHierarchy),
            'role_permissions' => $userRole ? count($this->getRolePermissions($userRole)) : 0,
            'custom_rules' => array_keys($this->customRules),
            'custom_attribute_checkers' => array_keys($this->attributeCheckers),
            'permission_source' => $this->permissionSource,
            'config_loaded_from' => file_exists(ROOT_PATH . '/config/Permissions.php') ? 'config/Permissions.php' : 'legacy (hardcoded)',
        ];
    }

    /**
     * Legacy configuration for backward compatibility
     */
    private function getLegacyConfiguration() {
        return [
            'roles' => [
                'superadmin' => ['system'],
                'corridor' => ['executive', 'manager', 'officer', 'assistant', 'admin', 'user'],
                'manager' => ['executive', 'geospatial', 'technology', 'officer'],
                'executive' => ['officer', 'assistant'],
                'authority' => ['officer'],
                'geospatial' => ['user'],
                'technology' => ['user'],
                'officer' => [],
                'assistant' => [],
                'admin' => ['user'],
                'user' => []
            ],
            'permissions' => [
                'system.admin' => ['corridor', 'admin'],
                'system.config' => ['corridor', 'manager'],
                'system.logs' => ['corridor', 'manager', 'technology'],
                'users.view' => ['corridor', 'manager', 'executive'],
                'users.create' => ['corridor', 'manager'],
                'users.edit' => ['corridor', 'manager'],
                'users.delete' => ['corridor'],
                'projects.view' => '*',
                'projects.create' => ['corridor', 'manager', 'executive', 'officer'],
                'projects.edit' => ['corridor', 'manager', 'executive', 'officer'],
                'projects.delete' => ['corridor', 'manager'],
                'projects.approve' => ['corridor', 'manager', 'authority'],
                'reports.view' => '*',
                'reports.export' => ['corridor', 'manager', 'executive', 'officer'],
                'reports.admin' => ['corridor', 'manager'],
                'geospatial.view' => '*',
                'geospatial.edit' => ['corridor', 'manager', 'geospatial'],
                'geospatial.admin' => ['corridor', 'geospatial'],
                'authority.approve' => ['corridor', 'authority', 'manager'],
                'authority.reject' => ['corridor', 'authority', 'manager'],
            ],
            'super_admin' => [
                'enabled' => true,
                'roles' => ['superadmin'],
                'bypass_all' => true,
            ]
        ];
    }

    // ============== DATABASE MANAGEMENT METHODS ==============

    /**
     * Get permission source
     */
    public function getSource() {
        return $this->permissionSource;
    }

    /**
     * Set permission source and reload
     */
    public function setSource($source) {
        if (!in_array($source, ['config', 'database', 'both'])) {
            throw new Exception("Invalid permission source: {$source}");
        }

        try {
            $db = DB::connection();
            $stmt = $db->prepare("UPDATE permission_settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = 'source'");
            $stmt->execute([$source]);

            $this->permissionSource = $source;
            $this->reload();
        } catch (Exception $e) {
            throw new Exception("Failed to set permission source: " . $e->getMessage());
        }
    }

    /**
     * Assign role to user
     */
    public function assignRole($userId, $roleName, $assignedBy = null) {
        try {
            $db = DB::connection();

            // Get role ID
            $stmt = $db->prepare("SELECT id FROM roles WHERE name = ? LIMIT 1");
            $stmt->execute([$roleName]);
            $role = $stmt->fetch();

            if (!$role) {
                throw new Exception("Role '{$roleName}' not found");
            }

            // Insert user role
            $stmt = $db->prepare("
                INSERT INTO user_roles (user_id, role_id, assigned_by)
                VALUES (?, ?, ?)
                ON CONFLICT(user_id, role_id) DO NOTHING
            ");
            $stmt->execute([$userId, $role->id, $assignedBy]);

            return true;
        } catch (Exception $e) {
            throw new Exception("Failed to assign role: " . $e->getMessage());
        }
    }

    /**
     * Remove role from user
     */
    public function removeRole($userId, $roleName) {
        try {
            $db = DB::connection();

            $stmt = $db->prepare("
                DELETE FROM user_roles
                WHERE user_id = ?
                AND role_id = (SELECT id FROM roles WHERE name = ? LIMIT 1)
            ");
            $stmt->execute([$userId, $roleName]);

            return true;
        } catch (Exception $e) {
            throw new Exception("Failed to remove role: " . $e->getMessage());
        }
    }

    /**
     * Get user's roles from database
     */
    public function getUserRolesFromDB($userId) {
        try {
            $db = DB::connection();

            $stmt = $db->prepare("
                SELECT r.name, r.display_name
                FROM user_roles ur
                JOIN roles r ON ur.role_id = r.id
                WHERE ur.user_id = ?
                AND (ur.expires_at IS NULL OR ur.expires_at > CURRENT_TIMESTAMP)
            ");
            $stmt->execute([$userId]);

            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Grant permission to role
     */
    public function grantPermission($roleName, $permissionName, $grantedBy = null) {
        try {
            $db = DB::connection();

            // Get role and permission IDs
            $stmt = $db->prepare("SELECT id FROM roles WHERE name = ? LIMIT 1");
            $stmt->execute([$roleName]);
            $role = $stmt->fetch();

            if (!$role) {
                throw new Exception("Role '{$roleName}' not found");
            }

            $stmt = $db->prepare("SELECT id FROM permissions WHERE name = ? LIMIT 1");
            $stmt->execute([$permissionName]);
            $permission = $stmt->fetch();

            if (!$permission) {
                throw new Exception("Permission '{$permissionName}' not found");
            }

            // Insert role permission
            $stmt = $db->prepare("
                INSERT INTO role_permissions (role_id, permission_id, granted_by)
                VALUES (?, ?, ?)
                ON CONFLICT(role_id, permission_id) DO NOTHING
            ");
            $stmt->execute([$role->id, $permission->id, $grantedBy]);

            // Reload permissions
            $this->reload();

            return true;
        } catch (Exception $e) {
            throw new Exception("Failed to grant permission: " . $e->getMessage());
        }
    }

    /**
     * Revoke permission from role
     */
    public function revokePermission($roleName, $permissionName) {
        try {
            $db = DB::connection();

            $stmt = $db->prepare("
                DELETE FROM role_permissions
                WHERE role_id = (SELECT id FROM roles WHERE name = ? LIMIT 1)
                AND permission_id = (SELECT id FROM permissions WHERE name = ? LIMIT 1)
            ");
            $stmt->execute([$roleName, $permissionName]);

            // Reload permissions
            $this->reload();

            return true;
        } catch (Exception $e) {
            throw new Exception("Failed to revoke permission: " . $e->getMessage());
        }
    }

    /**
     * Create new role in database
     */
    public function createRole($name, $displayName, $description = null) {
        try {
            $db = DB::connection();

            $stmt = $db->prepare("
                INSERT INTO roles (name, display_name, description)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$name, $displayName, $description]);

            // Reload permissions
            $this->reload();

            return $db->lastInsertId();
        } catch (Exception $e) {
            throw new Exception("Failed to create role: " . $e->getMessage());
        }
    }

    /**
     * Create new permission in database
     */
    public function createPermission($name, $displayName, $description = null, $module = null, $isPublic = false) {
        try {
            $db = DB::connection();

            $stmt = $db->prepare("
                INSERT INTO permissions (name, display_name, description, module, is_public)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $displayName, $description, $module, $isPublic ? 1 : 0]);

            // Reload permissions
            $this->reload();

            return $db->lastInsertId();
        } catch (Exception $e) {
            throw new Exception("Failed to create permission: " . $e->getMessage());
        }
    }
}
