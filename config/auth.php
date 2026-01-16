<?php
/**
 * MongoDB Admin Panel - Authentication Module
 * 
 * Handles user authentication, session management, password hashing,
 * and user registration. Provides secure login/logout functionality
 * with OWASP best practices.
 * 
 * @package MongoDB Admin Panel
 * @subpackage Authentication
 * @version 1.0.0
 * @author Development Team
 * @link https://github.com/frame-dev/MongoDBAdminPanel
 * @license MIT
 */

/**
 * Initialize authentication system
 * Sets up MongoDB users collection if needed
 */
function initializeAuth() {
    global $client, $database;
    
    try {
        if (!isset($database)) {
            return false;
        }
        
        // Create users collection if it doesn't exist
        $collections = $database->listCollections();
        $collectionNames = [];
        foreach ($collections as $collection) {
            $collectionNames[] = $collection->getName();
        }
        
        if (!in_array('_auth_users', $collectionNames)) {
            $database->createCollection('_auth_users');
            // Create unique index on username
            $usersCollection = $database->getCollection('_auth_users');
            $usersCollection->createIndex(['username' => 1], ['unique' => true]);
            $usersCollection->createIndex(['email' => 1], ['unique' => true]);
        }
        
        return true;
    } catch (Exception $e) {
        logSecurityEvent('auth_init_failed', ['error' => $e->getMessage()]);
        return false;
    }
}

/**
 * Hash a password using bcrypt
 * 
 * @param string $password Plain text password
 * @return string Hashed password
 */
function hashPassword($password) {
    // Use PHP's password_hash with BCRYPT algorithm
    // Cost parameter set to 12 for strong security
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify a password against a hash
 * 
 * @param string $password Plain text password
 * @param string $hash Password hash
 * @return bool True if password matches hash
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Register a new user
 * 
 * @param string $username Username (3-32 chars, alphanumeric + underscore)
 * @param string $email Email address
 * @param string $password Password (min 8 chars)
 * @param string $fullName Full name of user
 * @param string $role User role ('admin', 'editor', 'viewer')
 * @return array ['success' => bool, 'message' => string, 'user_id' => string|null]
 */
function registerUser($username, $email, $password, $fullName = '', $role = 'viewer') {
    global $database;
    
    // Check if database is connected
    if ($database === null) {
        logSecurityEvent('auth_database_not_connected', ['username' => $username]);
        return ['success' => false, 'message' => 'Database connection required. Please establish a MongoDB connection first.'];
    }
    
    // Validate inputs
    if (strlen($username) < 3 || strlen($username) > 32) {
        return ['success' => false, 'message' => 'Username must be 3-32 characters'];
    }
    
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        return ['success' => false, 'message' => 'Username can only contain letters, numbers, and underscores'];
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email address'];
    }
    
    if (strlen($password) < 8) {
        return ['success' => false, 'message' => 'Password must be at least 8 characters'];
    }
    
    // Validate role
    $validRoles = ['admin', 'editor', 'viewer', 'developer', 'analyst'];
    if (!in_array($role, $validRoles)) {
        $role = 'viewer';
    }
    
    try {
        $usersCollection = $database->getCollection('_auth_users');
        
        // Check if user already exists
        $existing = $usersCollection->findOne(['username' => $username]);
        if ($existing) {
            return ['success' => false, 'message' => 'Username already exists'];
        }
        
        $existingEmail = $usersCollection->findOne(['email' => $email]);
        if ($existingEmail) {
            return ['success' => false, 'message' => 'Email already registered'];
        }
        
        // Create user document
        $user = [
            'username' => $username,
            'email' => $email,
            'password_hash' => hashPassword($password),
            'full_name' => sanitizeInput($fullName),
            'role' => $role,
            'created_at' => new MongoDB\BSON\UTCDateTime(time() * 1000),
            'last_login' => null,
            'login_count' => 0,
            'is_active' => true,
            'failed_attempts' => 0,
            'locked_until' => null
        ];
        
        $result = $usersCollection->insertOne($user);
        
        logSecurityEvent('user_registered', ['username' => $username, 'email' => $email]);
        auditLog('user_registered', ['username' => $username, 'role' => $role, 'email' => $email], 'info', 'user');
        
        return [
            'success' => true,
            'message' => 'User registered successfully',
            'user_id' => (string)$result->getInsertedId()
        ];
    } catch (Exception $e) {
        logSecurityEvent('user_registration_failed', ['username' => $username, 'error' => $e->getMessage()]);
        return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
    }
}

/**
 * Authenticate user with username and password
 * 
 * @param string $username Username
 * @param string $password Password
 * @return array ['success' => bool, 'message' => string, 'user' => array|null]
 */
function authenticateUser($username, $password) {
    global $database;
    
    if (empty($username) || empty($password)) {
        logSecurityEvent('auth_empty_credentials', []);
        return ['success' => false, 'message' => 'Username and password required'];
    }
    
    // Check if database is connected
    if ($database === null) {
        logSecurityEvent('auth_database_not_connected', ['username' => $username]);
        return ['success' => false, 'message' => 'Database connection required. Please establish a MongoDB connection first.'];
    }
    
    try {
        $usersCollection = $database->getCollection('_auth_users');
        
        // Find user
        $user = $usersCollection->findOne(['username' => $username]);
        
        if (!$user) {
            logSecurityEvent('auth_user_not_found', ['username' => $username]);
            return ['success' => false, 'message' => 'Invalid username or password'];
        }
        
        // Check if user is active
        if (!$user['is_active']) {
            logSecurityEvent('auth_inactive_user', ['username' => $username]);
            return ['success' => false, 'message' => 'Account is inactive'];
        }
        
        // Check if account is locked
        if (isset($user['locked_until']) && $user['locked_until'] !== null) {
            $lockedTime = $user['locked_until']->toDateTime()->getTimestamp();
            if (time() < $lockedTime) {
                logSecurityEvent('auth_locked_account', ['username' => $username]);
                return ['success' => false, 'message' => 'Account is temporarily locked. Try again later.'];
            }
        }
        
        // Verify password
        if (!verifyPassword($password, $user['password_hash'])) {
            // Increment failed attempts
            $failedAttempts = ($user['failed_attempts'] ?? 0) + 1;
            
            // Lock account after 5 failed attempts for 15 minutes
            $lockData = [];
            if ($failedAttempts >= 5) {
                $lockData['locked_until'] = new MongoDB\BSON\UTCDateTime((time() + 900) * 1000);
                logSecurityEvent('auth_account_locked', ['username' => $username, 'attempts' => $failedAttempts]);
                auditLog('account_locked', ['username' => $username, 'attempts' => $failedAttempts], 'critical', 'security');
            }
            
            $usersCollection->updateOne(
                ['_id' => $user['_id']],
                ['$set' => array_merge(['failed_attempts' => $failedAttempts], $lockData)]
            );
            
            logSecurityEvent('auth_invalid_password', ['username' => $username]);
            auditLog('user_login_failed', ['username' => $username, 'attempts' => $failedAttempts], 'warning', 'auth');
            return ['success' => false, 'message' => 'Invalid username or password'];
        }
        
        // Reset failed attempts on successful login
        $usersCollection->updateOne(
            ['_id' => $user['_id']],
            ['$set' => [
                'failed_attempts' => 0,
                'locked_until' => null,
                'last_login' => new MongoDB\BSON\UTCDateTime(time() * 1000),
                'login_count' => ($user['login_count'] ?? 0) + 1
            ]]
        );
        
        logSecurityEvent('user_logged_in', ['username' => $username]);
        auditLog('user_login_success', ['username' => $username, 'login_count' => ($user['login_count'] ?? 0) + 1], 'info', 'auth');
        
        return [
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => (string)$user['_id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'full_name' => $user['full_name'] ?? '',
                'role' => $user['role']
            ]
        ];
    } catch (Exception $e) {
        logSecurityEvent('auth_error', ['error' => $e->getMessage()]);
        return ['success' => false, 'message' => 'Authentication error'];
    }
}

/**
 * Create user session after successful login
 * 
 * @param array $user User data from authentication
 */
function createUserSession($user) {
    $_SESSION['user'] = [
        'id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'full_name' => $user['full_name'],
        'role' => $user['role'],
        'login_time' => time()
    ];
    
    // Regenerate session ID for security
    session_regenerate_id(true);
}

/**
 * Check if user is logged in
 * 
 * @return bool True if user is authenticated
 */
function isUserLoggedIn() {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['username']);
}

/**
 * Get current user information
 * 
 * @return array|null User data if logged in, null otherwise
 */
function getCurrentUser() {
    if (isUserLoggedIn()) {
        return $_SESSION['user'];
    }
    return null;
}

/**
 * Get all available roles with their permissions
 * 
 * @return array Role definitions
 */
function getAllRoles() {
    return [
        'admin' => [
            'name' => 'Administrator',
            'description' => 'Full system access with user management capabilities',
            'level' => 3,
            'permissions' => [
                'view_data' => true,
                'create_data' => true,
                'edit_data' => true,
                'delete_data' => true,
                'bulk_operations' => true,
                'manage_users' => true,
                'manage_roles' => true,
                'view_settings' => true,
                'edit_settings' => true,
                'view_security' => true,
                'manage_security' => true,
                'view_analytics' => true,
                'export_data' => true,
                'import_data' => true,
                'manage_collections' => true,
                'manage_database' => true,
                'view_logs' => true,
                'clear_logs' => true,
                'create_backups' => true,
                'restore_backups' => true,
            ]
        ],
        'editor' => [
            'name' => 'Editor',
            'description' => 'Can view and modify data, but cannot manage users or system settings',
            'level' => 2,
            'permissions' => [
                'view_data' => true,
                'create_data' => true,
                'edit_data' => true,
                'delete_data' => true,
                'bulk_operations' => true,
                'manage_users' => false,
                'manage_roles' => false,
                'view_settings' => true,
                'edit_settings' => false,
                'view_security' => false,
                'manage_security' => false,
                'view_analytics' => true,
                'export_data' => true,
                'import_data' => true,
                'manage_collections' => false,
                'manage_database' => false,
                'view_logs' => false,
                'clear_logs' => false,
                'create_backups' => true,
                'restore_backups' => false,
            ]
        ],
        'viewer' => [
            'name' => 'Viewer',
            'description' => 'Read-only access to view data and basic analytics',
            'level' => 1,
            'permissions' => [
                'view_data' => true,
                'create_data' => false,
                'edit_data' => false,
                'delete_data' => false,
                'bulk_operations' => false,
                'manage_users' => false,
                'manage_roles' => false,
                'view_settings' => true,
                'edit_settings' => false,
                'view_security' => false,
                'manage_security' => false,
                'view_analytics' => true,
                'export_data' => true,
                'import_data' => false,
                'manage_collections' => false,
                'manage_database' => false,
                'view_logs' => false,
                'clear_logs' => false,
                'create_backups' => false,
                'restore_backups' => false,
            ]
        ],
        'analyst' => [
            'name' => 'Analyst',
            'description' => 'Can view data and analytics, with export capabilities',
            'level' => 1,
            'permissions' => [
                'view_data' => true,
                'create_data' => false,
                'edit_data' => false,
                'delete_data' => false,
                'bulk_operations' => false,
                'manage_users' => false,
                'manage_roles' => false,
                'view_settings' => true,
                'edit_settings' => false,
                'view_security' => false,
                'manage_security' => false,
                'view_analytics' => true,
                'export_data' => true,
                'import_data' => false,
                'manage_collections' => false,
                'manage_database' => false,
                'view_logs' => false,
                'clear_logs' => false,
                'create_backups' => false,
                'restore_backups' => false,
            ]
        ],
        'developer' => [
            'name' => 'Developer',
            'description' => 'Advanced access for development and testing, can manage collections',
            'level' => 2,
            'permissions' => [
                'view_data' => true,
                'create_data' => true,
                'edit_data' => true,
                'delete_data' => true,
                'bulk_operations' => true,
                'manage_users' => false,
                'manage_roles' => false,
                'view_settings' => true,
                'edit_settings' => false,
                'view_security' => true,
                'manage_security' => false,
                'view_analytics' => true,
                'export_data' => true,
                'import_data' => true,
                'manage_collections' => true,
                'manage_database' => false,
                'view_logs' => true,
                'clear_logs' => false,
                'create_backups' => true,
                'restore_backups' => true,
            ]
        ],
    ];
}

/**
 * Get role details by role name
 * 
 * @param string $roleName Role name
 * @return array|null Role details or null if not found
 */
function getRoleDetails($roleName) {
    $roles = getAllRoles();
    return $roles[$roleName] ?? null;
}

/**
 * Check if current user has specific role
 * 
 * @param string $role Role to check ('admin', 'editor', 'viewer')
 * @return bool True if user has role
 */
function userHasRole($role) {
    $user = getCurrentUser();
    if (!$user) {
        return false;
    }
    
    // Admin has all permissions
    if ($user['role'] === 'admin') {
        return true;
    }
    
    // Permission hierarchy: admin > editor > viewer
    $hierarchy = ['admin' => 3, 'editor' => 2, 'viewer' => 1, 'developer' => 2, 'analyst' => 1];
    $roleLevel = $hierarchy[$role] ?? 0;
    $userLevel = $hierarchy[$user['role']] ?? 0;
    
    return $userLevel >= $roleLevel;
}

/**
 * Check if user has permission to perform action
 * 
 * @param string $action Action name
 * @return bool True if user has permission
 */
function userHasPermission($action) {
    $user = getCurrentUser();
    if (!$user) {
        return false;
    }
    
    // Get role details
    $roleDetails = getRoleDetails($user['role']);
    if (!$roleDetails) {
        return false;
    }
    
    // Check if permission exists in role
    return $roleDetails['permissions'][$action] ?? false;
}

/**
 * Logout current user
 */
function logoutUser() {
    $user = getCurrentUser();
    if ($user) {
        logSecurityEvent('user_logged_out', ['username' => $user['username']]);
        auditLog('user_logout', ['username' => $user['username']], 'info', 'auth');
    }
    
    // Clear session
    $_SESSION['user'] = null;
    unset($_SESSION['user']);
    session_destroy();
}

/**
 * Change user password
 * 
 * @param string $userId User ID
 * @param string $oldPassword Current password
 * @param string $newPassword New password
 * @return array ['success' => bool, 'message' => string]
 */
function changeUserPassword($userId, $oldPassword, $newPassword) {
    global $database;
    
    if (strlen($newPassword) < 8) {
        return ['success' => false, 'message' => 'New password must be at least 8 characters'];
    }
    
    try {
        $usersCollection = $database->getCollection('_auth_users');
        $user = $usersCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);
        
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        if (!verifyPassword($oldPassword, $user['password_hash'])) {
            logSecurityEvent('password_change_failed', ['user_id' => $userId]);
            auditLog('password_change_failed', ['user_id' => $userId], 'warning', 'security');
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }
        
        $usersCollection->updateOne(
            ['_id' => $user['_id']],
            ['$set' => ['password_hash' => hashPassword($newPassword)]]
        );
        
        logSecurityEvent('password_changed', ['user_id' => $userId]);
        auditLog('password_changed', ['user_id' => $userId], 'warning', 'security');
        
        return ['success' => true, 'message' => 'Password changed successfully'];
    } catch (Exception $e) {
        logSecurityEvent('password_change_error', ['error' => $e->getMessage()]);
        return ['success' => false, 'message' => 'Error changing password'];
    }
}

/**
 * Get all users (admin only)
 * 
 * @return array List of users
 */
function getAllUsers() {
    global $database;
    
    if (!userHasRole('admin')) {
        return [];
    }
    
    // Check if database is connected
    if ($database === null) {
        return [];
    }
    
    try {
        $usersCollection = $database->getCollection('_auth_users');
        $users = $usersCollection->find([], ['projection' => ['password_hash' => 0]])->toArray();
        
        $result = [];
        foreach ($users as $user) {
            $result[] = [
                'id' => (string)$user['_id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'full_name' => $user['full_name'] ?? '',
                'role' => $user['role'],
                'is_active' => $user['is_active'],
                'last_login' => isset($user['last_login']) ? $user['last_login']->toDateTime()->format('Y-m-d H:i:s') : 'Never',
                'login_count' => $user['login_count'] ?? 0
            ];
        }
        
        return $result;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Update user role (admin only)
 * 
 * @param string $userId User ID
 * @param string $newRole New role
 * @return array ['success' => bool, 'message' => string]
 */
function updateUserRole($userId, $newRole) {
    global $database;
    
    if (!userHasRole('admin')) {
        return ['success' => false, 'message' => 'Permission denied'];
    }
    
    // Check if database is connected
    if ($database === null) {
        return ['success' => false, 'message' => 'Database connection required'];
    }
    
    // Validate role
    $validRoles = ['admin', 'editor', 'viewer', 'developer', 'analyst'];
    if (!in_array($newRole, $validRoles)) {
        return ['success' => false, 'message' => 'Invalid role'];
    }
    
    try {
        $usersCollection = $database->getCollection('_auth_users');
        $usersCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($userId)],
            ['$set' => ['role' => $newRole]]
        );
        
        logSecurityEvent('user_role_updated', ['user_id' => $userId, 'new_role' => $newRole]);
        
        return ['success' => true, 'message' => 'User role updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating role'];
    }
}

/**
 * Deactivate user account (admin only)
 * 
 * @param string $userId User ID
 * @return array ['success' => bool, 'message' => string]
 */
function deactivateUser($userId) {
    global $database;
    
    if (!userHasRole('admin')) {
        return ['success' => false, 'message' => 'Permission denied'];
    }
    
    // Check if database is connected
    if ($database === null) {
        return ['success' => false, 'message' => 'Database connection required'];
    }
    
    try {
        $usersCollection = $database->getCollection('_auth_users');
        $usersCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($userId)],
            ['$set' => ['is_active' => false]]
        );
        
        logSecurityEvent('user_deactivated', ['user_id' => $userId]);
        
        return ['success' => true, 'message' => 'User deactivated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error deactivating user'];
    }
}

/**
 * Activate user account (admin only)
 * 
 * @param string $userId User ID
 * @return array ['success' => bool, 'message' => string]
 */
function activateUser($userId) {
    global $database;
    
    if (!userHasRole('admin')) {
        return ['success' => false, 'message' => 'Permission denied'];
    }
    
    if ($database === null) {
        return ['success' => false, 'message' => 'Database connection required'];
    }
    
    try {
        $usersCollection = $database->getCollection('_auth_users');
        $usersCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($userId)],
            ['$set' => ['is_active' => true]]
        );
        
        logSecurityEvent('user_activated', ['user_id' => $userId]);
        
        return ['success' => true, 'message' => 'User activated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error activating user'];
    }
}

/**
 * Delete user account (admin only)
 * 
 * @param string $userId User ID
 * @return array ['success' => bool, 'message' => string]
 */
function deleteUser($userId) {
    global $database;
    
    if (!userHasRole('admin')) {
        return ['success' => false, 'message' => 'Permission denied'];
    }
    
    if ($database === null) {
        return ['success' => false, 'message' => 'Database connection required'];
    }
    
    // Prevent deleting own account
    $currentUser = getCurrentUser();
    if ((string)$currentUser['_id'] === $userId) {
        return ['success' => false, 'message' => 'Cannot delete your own account'];
    }
    
    try {
        $usersCollection = $database->getCollection('_auth_users');
        $result = $usersCollection->deleteOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);
        
        if ($result->getDeletedCount() > 0) {
            logSecurityEvent('user_deleted', ['user_id' => $userId]);
            return ['success' => true, 'message' => 'User deleted successfully'];
        } else {
            return ['success' => false, 'message' => 'User not found'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error deleting user: ' . $e->getMessage()];
    }
}

/**
 * Update user details (admin only)
 * 
 * @param string $userId User ID
 * @param array $data User data to update
 * @return array ['success' => bool, 'message' => string]
 */
function updateUser($userId, $data) {
    global $database;
    
    if (!userHasRole('admin')) {
        return ['success' => false, 'message' => 'Permission denied'];
    }
    
    if ($database === null) {
        return ['success' => false, 'message' => 'Database connection required'];
    }
    
    try {
        $usersCollection = $database->getCollection('_auth_users');
        
        // Check if username already exists for another user
        if (isset($data['username'])) {
            $existingUser = $usersCollection->findOne([
                'username' => $data['username'],
                '_id' => ['$ne' => new MongoDB\BSON\ObjectId($userId)]
            ]);
            if ($existingUser) {
                return ['success' => false, 'message' => 'Username already exists'];
            }
        }
        
        // Check if email already exists for another user
        if (isset($data['email'])) {
            $existingEmail = $usersCollection->findOne([
                'email' => $data['email'],
                '_id' => ['$ne' => new MongoDB\BSON\ObjectId($userId)]
            ]);
            if ($existingEmail) {
                return ['success' => false, 'message' => 'Email already exists'];
            }
        }
        
        // Prepare update data
        $updateData = [];
        if (isset($data['username'])) $updateData['username'] = $data['username'];
        if (isset($data['email'])) $updateData['email'] = $data['email'];
        if (isset($data['full_name'])) $updateData['full_name'] = $data['full_name'];
        $validRoles = ['admin', 'editor', 'viewer', 'developer', 'analyst'];
        if (isset($data['role']) && in_array($data['role'], $validRoles)) {
            $updateData['role'] = $data['role'];
        }
        
        if (empty($updateData)) {
            return ['success' => false, 'message' => 'No valid data to update'];
        }
        
        $usersCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($userId)],
            ['$set' => $updateData]
        );
        
        logSecurityEvent('user_updated', ['user_id' => $userId, 'fields' => array_keys($updateData)]);
        
        return ['success' => true, 'message' => 'User updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating user: ' . $e->getMessage()];
    }
}

/**
 * Admin reset user password (admin only)
 * 
 * @param string $userId User ID
 * @param string $newPassword New password
 * @return array ['success' => bool, 'message' => string]
 */
function adminResetPassword($userId, $newPassword) {
    global $database;
    
    if (!userHasRole('admin')) {
        return ['success' => false, 'message' => 'Permission denied'];
    }
    
    if ($database === null) {
        return ['success' => false, 'message' => 'Database connection required'];
    }
    
    if (strlen($newPassword) < 6) {
        return ['success' => false, 'message' => 'Password must be at least 6 characters'];
    }
    
    try {
        $usersCollection = $database->getCollection('_auth_users');
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
        
        $usersCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($userId)],
            ['$set' => ['password_hash' => $passwordHash]]
        );
        
        logSecurityEvent('admin_password_reset', ['user_id' => $userId]);
        
        return ['success' => true, 'message' => 'Password reset successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error resetting password: ' . $e->getMessage()];
    }
}
