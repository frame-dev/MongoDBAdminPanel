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
    
    if (!in_array($role, ['admin', 'editor', 'viewer'])) {
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
            }
            
            $usersCollection->updateOne(
                ['_id' => $user['_id']],
                ['$set' => array_merge(['failed_attempts' => $failedAttempts], $lockData)]
            );
            
            logSecurityEvent('auth_invalid_password', ['username' => $username]);
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
    $hierarchy = ['admin' => 3, 'editor' => 2, 'viewer' => 1];
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
    
    // Define permissions by role
    $permissions = [
        'admin' => ['*'], // All permissions
        'editor' => [
            'view_documents',
            'add_document',
            'edit_document',
            'delete_document',
            'execute_query',
            'export_data',
            'import_data',
            'view_analytics',
            'manage_templates'
        ],
        'viewer' => [
            'view_documents',
            'execute_query',
            'export_data',
            'view_analytics'
        ]
    ];
    
    $userPermissions = $permissions[$user['role']] ?? [];
    
    // Admin has all permissions
    if (in_array('*', $userPermissions)) {
        return true;
    }
    
    return in_array($action, $userPermissions);
}

/**
 * Logout current user
 */
function logoutUser() {
    $user = getCurrentUser();
    if ($user) {
        logSecurityEvent('user_logged_out', ['username' => $user['username']]);
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
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }
        
        $usersCollection->updateOne(
            ['_id' => $user['_id']],
            ['$set' => ['password_hash' => hashPassword($newPassword)]]
        );
        
        logSecurityEvent('password_changed', ['user_id' => $userId]);
        
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
    
    if (!in_array($newRole, ['admin', 'editor', 'viewer'])) {
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
