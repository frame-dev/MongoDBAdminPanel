<?php
/**
 * MongoDB Admin Panel - Main Index File
 * 
 * This file serves as the entry point and orchestrates all the modular components.
 * It handles all tab views, form submissions, and core application logic.
 * 
 * @package MongoDB Admin Panel
 * @version 1.0.0
 * @author Development Team
 * @link https://github.com/frame-dev/MongoDBAdminPanel
 * @license MIT
 */

session_start();
require 'vendor/autoload.php';
require_once 'config/bson-stubs.php';

use MongoDB\Client;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

// Load security functions
require_once 'config/security.php';

// Load authentication functions
require_once 'config/auth.php';

// Load database configuration first (needed for authentication)
require_once 'config/database.php';

function isAjaxRequest(): bool {
    $header = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    if (strcasecmp($header, 'XMLHttpRequest') === 0) {
        return true;
    }
    return (isset($_POST['ajax']) && $_POST['ajax'] === '1');
}

// Load global settings (if available) and apply runtime limits
$loadedFromDb = false;
if ($database !== null) {
    $loadedFromDb = loadGlobalSettingsFromDb();
}
if (empty($_SESSION['settings'])) {
    $_SESSION['settings'] = getDefaultSettings();
    if ($database !== null && !$loadedFromDb && !hasGlobalSettingsDoc()) {
        markSettingsUpdated();
        saveGlobalSettingsToDb($_SESSION['settings']);
    }
}
$memoryLimitSetting = (int) getSetting('memory_limit', 256);
$memoryLimitSetting = max(128, min(2048, $memoryLimitSetting));
ini_set('memory_limit', $memoryLimitSetting . 'M');

// Load backup and audit logging utilities (needed early for auditLog function)
require_once 'includes/backup.php';

// Load helper fixes used across tabs
require_once 'fixes/query-operator-fixes.php';
require_once 'fixes/value-type-fixes.php';
require_once 'fixes/select-options-fixes.php';
require_once 'fixes/pagination-fixes.php';
require_once 'fixes/quick-filters-fixes.php';
require_once 'fixes/document-row-fixes.php';
require_once 'fixes/projection-fixes.php';
require_once 'fixes/index-display-fixes.php';
require_once 'fixes/schema-fixes.php';

// Handle authentication requests (now that database is loaded)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $authAction = $_POST['action'];
    
    // Handle login
    if ($authAction === 'login') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        $result = authenticateUser($username, $password);
        if ($result['success']) {
            createUserSession($result['user']);
            $_SESSION['auth_message'] = $result['message'];
            $_SESSION['auth_success'] = true;
            $redirectUrl = $_SERVER['PHP_SELF'];
            if (!empty($_GET['collection'])) {
                $redirectUrl .= '?collection=' . urlencode($_GET['collection']);
            }
            header('Location: ' . $redirectUrl);
            exit;
        } else {
            $_SESSION['auth_message'] = $result['message'];
            $_SESSION['auth_success'] = false;
        }
    }
    // Handle registration
    elseif ($authAction === 'register') {
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        $fullName = $_POST['full_name'] ?? '';
        
        // Validate password match
        if ($password !== $passwordConfirm) {
            $_SESSION['auth_message'] = 'Passwords do not match';
            $_SESSION['auth_success'] = false;
        } else {
            // Determine if this is first user (should be admin)
            // Default to 'viewer' role, only if database is connected can we check for existing users
            $role = 'viewer';
            if ($database !== null) {
                try {
                    $usersCollection = $database->getCollection('_auth_users');
                    $userCount = $usersCollection->countDocuments();
                    $role = ($userCount === 0) ? 'admin' : 'viewer';
                } catch (Exception $e) {
                    error_log("Error checking user count: " . $e->getMessage());
                }
            }
            
            $result = registerUser($username, $email, $password, $fullName, $role);
            $_SESSION['auth_message'] = $result['message'];
            $_SESSION['auth_success'] = $result['success'];
            
            if ($result['success']) {
                $_SESSION['auth_message'] = 'Account created! You can now login.';
            }
        }
        // Always redirect after registration to prevent resubmission
        if (ob_get_length()) ob_clean();
        $redirectUrl = $_SERVER['PHP_SELF'];
        if (!empty($_GET['collection'])) {
            $redirectUrl .= '?collection=' . urlencode($_GET['collection']);
        }
        header('Location: ' . $redirectUrl);
        exit;
    }
    // Handle logout
    elseif ($authAction === 'logout') {
        logoutUser();
        $_SESSION['auth_message'] = 'You have been logged out.';
        $_SESSION['auth_success'] = true;
        if (ob_get_length()) ob_clean();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Initialize variables needed for templates
$connectionError = $_SESSION['connection_error'] ?? '';
// Clear the one-time error message after displaying it
if (isset($_SESSION['connection_error'])) {
    unset($_SESSION['connection_error']);
}

// Handle connection changes BEFORE checking if connected
$disconnect = $_GET['disconnect'] ?? '';
if ($disconnect) {
    $oldConnection = $_SESSION['mongo_connection'] ?? [];
    auditLog('database_disconnected', ['database' => $oldConnection['database'] ?? 'unknown'], 'info', 'system');
    unset($_SESSION['mongo_connection']);
    if (ob_get_length()) ob_clean();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Handle new connection request BEFORE checking if connected
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['connect'])) {
    try {
        $hostName = $_POST['hostname'] ?? '';
        $port = $_POST['port'] ?? '27017';
        $dbName = $_POST['database'] ?? '';
        $user = $_POST['username'] ?? '';
        $pass = $_POST['password'] ?? '';
        $collectionName = $_POST['collection'] ?? '';

        if (!$hostName || !$dbName || !$collectionName) {
            throw new Exception('Hostname, database, and collection are required');
        }

        // Test connection
        if ($user && $pass) {
            $uri = "mongodb://$user:$pass@$hostName:$port/$dbName?authSource=$dbName";
        } else {
            $uri = "mongodb://$hostName:$port/$dbName";
        }

        $client = new Client($uri, [], ['serverSelectionTimeoutMS' => 5000]);
        $client->selectDatabase($dbName)->command(['ping' => 1]);

        // Save connection to session
        $_SESSION['mongo_connection'] = [
            'hostname' => $hostName,
            'port' => $port,
            'database' => $dbName,
            'username' => $user,
            'password' => $pass,
            'collection' => $collectionName
        ];

        auditLog('database_connected', ['hostname' => $hostName, 'database' => $dbName, 'collection' => $collectionName], 'info', 'system');

        header('Location: ' . $_SERVER['PHP_SELF'] . '?collection=' . urlencode($collectionName));
        exit;
    } catch (Exception $e) {
        $connectionError = 'Connection failed: ' . $e->getMessage();
        $_SESSION['connection_error'] = $connectionError;
        error_log("MongoDB Connection Error: " . $e->getMessage());
    }
}

// Check if MongoDB connection exists - if not, show connection form instead of login
if ($database === null) {
    include 'templates/connection.php';
    include 'templates/footer.php';
    exit;
}

// Initialize auth collections when connected (once per session)
if (!isset($_SESSION['auth_initialized'])) {
    initializeAuth();
    $_SESSION['auth_initialized'] = true;
}

// Enforce idle session timeout if enabled
$enableIdleTimeout = (bool) getSetting('enable_idle_timeout', false);
if ($enableIdleTimeout && isUserLoggedIn()) {
    $idleMinutes = (int) getSetting('idle_timeout_minutes', 30);
    $idleMinutes = max(5, min(240, $idleMinutes));
    $lastActivity = $_SESSION['last_activity'] ?? time();

    if ((time() - $lastActivity) > ($idleMinutes * 60)) {
        $user = getCurrentUser();
        logSecurityEvent('idle_timeout', ['username' => $user['username'] ?? 'unknown']);
        auditLog('session_idle_timeout', ['username' => $user['username'] ?? 'unknown'], 'warning', 'security');
        unset($_SESSION['user']);
        $_SESSION['auth_message'] = 'Session expired due to inactivity.';
        $_SESSION['auth_success'] = false;
        if (ob_get_length()) ob_clean();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}
// Enforce session validation if enabled
if (isUserLoggedIn()) {
    $validateSession = (bool) getSetting('session_validation_enabled', true);
    $validateIp = (bool) getSetting('ip_tracking_enabled', true);
    $currentUa = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $currentIp = $_SERVER['REMOTE_ADDR'] ?? '';

    if ($validateSession) {
        if (!isset($_SESSION['session_ua'])) {
            $_SESSION['session_ua'] = $currentUa;
        } elseif ($_SESSION['session_ua'] !== $currentUa) {
            logSecurityEvent('session_validation_failed', ['reason' => 'user_agent_mismatch']);
            unset($_SESSION['user']);
            $_SESSION['auth_message'] = 'Session validation failed. Please login again.';
            $_SESSION['auth_success'] = false;
            if (ob_get_length()) ob_clean();
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }

    if ($validateIp) {
        if (!isset($_SESSION['session_ip'])) {
            $_SESSION['session_ip'] = $currentIp;
        } elseif ($_SESSION['session_ip'] !== $currentIp) {
            logSecurityEvent('session_validation_failed', ['reason' => 'ip_mismatch']);
            unset($_SESSION['user']);
            $_SESSION['auth_message'] = 'Session validation failed. Please login again.';
            $_SESSION['auth_success'] = false;
            if (ob_get_length()) ob_clean();
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}
$_SESSION['last_activity'] = time();

// Check if user is authenticated (for non-auth actions)
if (!isUserLoggedIn() && (!isset($_POST['action']) || !in_array($_POST['action'], ['login', 'register']))) {
    include 'templates/login.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Exceptions: actions that bypass CSRF verification or have their own handling
    $action = $_POST['action'] ?? '';
    
    // Authentication actions and connection don't require CSRF tokens
    // (they can be called before full page initialization)
    $bypassCsrf = isset($_POST['connect']) || 
                  $action === 'export_settings' || 
                  $action === 'import_settings' ||
                  $action === 'login' ||
                  $action === 'register' ||
                  $action === 'logout';
    
    if (!$bypassCsrf && getSetting('csrf_enabled', true)) {
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!verifyCSRFToken($csrfToken)) {
            logSecurityEvent('csrf_failed', ['action' => $action]);
            http_response_code(403);
            die('CSRF token validation failed. Request blocked.');
        }
    }
}

// Clear any invalid session data with incomplete objects
if (isset($_SESSION['field_stats']['data']) && is_array($_SESSION['field_stats']['data'])) {
    foreach ($_SESSION['field_stats']['data'] as $item) {
        if (is_object($item) && get_class($item) === '__PHP_Incomplete_Class') {
            unset($_SESSION['field_stats']);
            break;
        }
    }
}

// Database configuration already loaded at the top (config/database.php)

// Load search/filter and form handlers
include 'includes/handlers.php';

// Load collection statistics (only if connected)
if ($database && isset($collectionName) && $collectionName) {
    include 'includes/statistics.php';
}

// FIX: Load button handler fixes for missing form logic (only if connected)
if ($database && isset($collection) && $collection) {
    include 'config/button-fixes.php';
}

// Initialize Query History in session
if (!isset($_SESSION['query_history'])) {
    $_SESSION['query_history'] = [];
}

/**
 * Add query to history (both session and database)
 * 
 * @param array $queryData The query data to store
 */
function addToQueryHistory($queryData) {
    global $database;
    
    // Keep session history for backward compatibility
    if (!isset($_SESSION['query_history'])) {
        $_SESSION['query_history'] = [];
    }
    
    $historyLimit = (int) getSetting('query_history_limit', 50);
    $historyLimit = max(5, min(200, $historyLimit));
    
    // Limit session history to configured size
    if (count($_SESSION['query_history']) >= $historyLimit) {
        array_shift($_SESSION['query_history']);
    }
    
    $entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'type' => $queryData['type'] ?? 'manual',
        'query' => $queryData['query'] ?? [],
        'results_count' => $queryData['results_count'] ?? 0,
        'execution_time' => $queryData['execution_time'] ?? 0,
        'status' => $queryData['status'] ?? 'success'
    ];
    
    $_SESSION['query_history'][] = $entry;
    
    // Save to database if connected
    if ($database !== null) {
        try {
            $historyCollection = $database->getCollection('_query_history');
            
            // Create indexes if they don't exist (only on first run)
            if (!isset($_SESSION['query_history_indexed'])) {
                try {
                    $historyCollection->createIndex(['user_id' => 1, 'created_at' => -1]);
                    $historyCollection->createIndex(['created_at' => -1], ['expireAfterSeconds' => 2592000]); // 30 days TTL
                    $_SESSION['query_history_indexed'] = true;
                } catch (Exception $e) {
                    // Index may already exist
                }
            }
            
            // Get current user ID
            $user = getCurrentUser();
            $userId = $user['_id'] ?? 'anonymous';
            
            $historyEntry = [
                'user_id' => $userId,
                'collection' => $_SESSION['mongo_connection']['collection'] ?? 'unknown',
                'database' => $_SESSION['mongo_connection']['database'] ?? 'unknown',
                'type' => $entry['type'],
                'query' => $entry['query'],
                'results_count' => $entry['results_count'],
                'execution_time' => $entry['execution_time'],
                'status' => $entry['status'],
                'created_at' => new UTCDateTime(time() * 1000)
            ];
            
            $historyCollection->insertOne($historyEntry);
        } catch (Exception $e) {
            // Log error but don't break functionality
            error_log("Error saving query history: " . $e->getMessage());
        }
    }
}

/**
 * Get query history from database (with session fallback)
 * 
 * @param int $limit Maximum number of queries to return
 * @return array Query history entries
 */
function getQueryHistory($limit = null) {
    global $database;
    
    if ($limit === null) {
        $limit = (int) getSetting('query_history_limit', 50);
    }
    $limit = max(5, min(200, (int) $limit));
    
    // Try to get from database if connected
    if ($database !== null) {
        try {
            $historyCollection = $database->getCollection('_query_history');
            $user = getCurrentUser();
            $userId = $user['_id'] ?? 'anonymous';
            
            $history = $historyCollection->find(
                ['user_id' => $userId],
                ['sort' => ['created_at' => -1], 'limit' => $limit]
            )->toArray();
            
            $result = [];
            foreach ($history as $entry) {
                $result[] = [
                    'timestamp' => $entry['created_at']->toDateTime()->format('Y-m-d H:i:s'),
                    'type' => $entry['type'],
                    'query' => $entry['query'],
                    'results_count' => $entry['results_count'],
                    'execution_time' => $entry['execution_time'],
                    'status' => $entry['status']
                ];
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Error retrieving query history: " . $e->getMessage());
            // Fall through to session history
        }
    }
    
    // Fall back to session history
    if (!isset($_SESSION['query_history'])) {
        return [];
    }
    
    return array_slice(array_reverse($_SESSION['query_history']), 0, $limit);
}

/**
 * Clear query history (both session and database)
 */
function clearQueryHistory() {
    global $database;
    
    // Clear session history
    $_SESSION['query_history'] = [];
    
    // Clear database history if connected
    if ($database !== null) {
        try {
            $historyCollection = $database->getCollection('_query_history');
            $user = getCurrentUser();
            $userId = $user['_id'] ?? 'anonymous';
            
            $historyCollection->deleteMany(['user_id' => $userId]);
        } catch (Exception $e) {
            error_log("Error clearing query history: " . $e->getMessage());
        }
    }
}

// Handle clear query history
if (isset($_GET['action']) && $_GET['action'] === 'clear_query_history') {
    clearQueryHistory();
    $message = '✅ Query history cleared successfully';
    $messageType = 'success';
    auditLog('query_history_cleared', [], 'info', 'data');
}

// Handle Settings and Security tab form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $ajaxSettingsActions = [
        'save_display_settings',
        'save_performance_settings',
        'save_editor_settings',
        'save_notification_settings',
        'save_export_settings',
        'save_security_settings',
        'import_settings',
        'clear_cache',
        'reset_settings'
    ];
    $settingsRedirectActions = [
        'save_display_settings',
        'save_performance_settings',
        'save_editor_settings',
        'save_notification_settings',
        'save_export_settings',
        'save_security_settings',
        'clear_cache',
        'reset_settings'
    ];
    
    // Handle Display Settings
    if ($action === 'save_display_settings') {
        $_SESSION['settings'] = $_SESSION['settings'] ?? [];
        $_SESSION['settings']['items_per_page'] = (int) ($_POST['items_per_page'] ?? 50);
        $_SESSION['settings']['date_format'] = sanitizeInput($_POST['date_format'] ?? 'Y-m-d H:i:s');
        $_SESSION['settings']['theme'] = sanitizeInput($_POST['theme'] ?? 'light');
        $_SESSION['settings']['default_sort_field'] = sanitizeInput($_POST['default_sort_field'] ?? '_id');
        $_SESSION['settings']['default_sort_order'] = ($_POST['default_sort_order'] ?? '-1') === '1' ? '1' : '-1';
        $viewMode = $_POST['default_view_mode'] ?? 'table';
        $_SESSION['settings']['default_view_mode'] = in_array($viewMode, ['table', 'grid'], true) ? $viewMode : 'table';
        $_SESSION['settings']['syntax_highlighting'] = isset($_POST['syntax_highlighting']);
        $_SESSION['settings']['pretty_print'] = isset($_POST['pretty_print']);
        $_SESSION['settings']['show_objectid_as_string'] = isset($_POST['show_objectid_as_string']);
        $_SESSION['settings']['collapsible_json'] = isset($_POST['collapsible_json']);
        $_SESSION['settings']['zebra_stripes'] = isset($_POST['zebra_stripes']);
        $_SESSION['settings']['row_hover'] = isset($_POST['row_hover']);
        $_SESSION['settings']['fixed_header'] = isset($_POST['fixed_header']);
        $_SESSION['settings']['compact_mode'] = isset($_POST['compact_mode']);
        $previewLength = (int) ($_POST['preview_length'] ?? 80);
        $_SESSION['settings']['preview_length'] = max(20, min(200, $previewLength));
        $_SESSION['settings']['key_fields_priority'] = sanitizeInput($_POST['key_fields_priority'] ?? 'name,title,email,status,type,category');
        markSettingsUpdated();
        saveGlobalSettingsToDb($_SESSION['settings']);
        
        $message = '✅ Display settings saved successfully';
        $messageType = 'success';
        auditLog('display_settings_saved', ['items_per_page' => $_SESSION['settings']['items_per_page']], 'info', 'system');
    }
    
    // Handle Performance Settings
    elseif ($action === 'save_performance_settings') {
        $_SESSION['settings'] = $_SESSION['settings'] ?? [];
        $_SESSION['settings']['query_timeout'] = max(5, min(300, (int) ($_POST['query_timeout'] ?? 30)));
        $_SESSION['settings']['max_results'] = max(100, min(10000, (int) ($_POST['max_results'] ?? 1000)));
        $_SESSION['settings']['query_default_limit'] = max(10, min(10000, (int) ($_POST['query_default_limit'] ?? 50)));
        $_SESSION['settings']['query_history_limit'] = max(5, min(200, (int) ($_POST['query_history_limit'] ?? 50)));
        $_SESSION['settings']['memory_limit'] = max(128, min(2048, (int) ($_POST['memory_limit'] ?? 256)));
        $_SESSION['settings']['cache_ttl'] = max(1, min(1440, (int) ($_POST['cache_ttl'] ?? 15)));
        $_SESSION['settings']['query_cache'] = isset($_POST['query_cache']);
        $_SESSION['settings']['auto_indexes'] = isset($_POST['auto_indexes']);
        $_SESSION['settings']['schema_cache'] = isset($_POST['schema_cache']);
        $_SESSION['settings']['lazy_load'] = isset($_POST['lazy_load']);
        $_SESSION['settings']['schema_sample_size'] = max(10, min(500, (int) ($_POST['schema_sample_size'] ?? 100)));
        markSettingsUpdated();
        saveGlobalSettingsToDb($_SESSION['settings']);
        
        $message = '✅ Performance settings saved successfully';
        $messageType = 'success';
        auditLog('performance_settings_saved', ['query_timeout' => $_SESSION['settings']['query_timeout']], 'info', 'system');
    }
    
    // Handle Editor Settings
    elseif ($action === 'save_editor_settings') {
        $_SESSION['settings'] = $_SESSION['settings'] ?? [];
        $_SESSION['settings']['editor_theme'] = sanitizeInput($_POST['editor_theme'] ?? 'monokai');
        $_SESSION['settings']['editor_font_size'] = max(10, min(24, (int) ($_POST['editor_font_size'] ?? 14)));
        $_SESSION['settings']['line_numbers'] = isset($_POST['line_numbers']);
        $_SESSION['settings']['auto_format'] = isset($_POST['auto_format']);
        $_SESSION['settings']['validate_on_type'] = isset($_POST['validate_on_type']);
        $_SESSION['settings']['auto_refresh'] = isset($_POST['auto_refresh']);
        $_SESSION['settings']['refresh_interval'] = max(5, min(300, (int) ($_POST['refresh_interval'] ?? 30)));
        $_SESSION['settings']['confirm_deletions'] = isset($_POST['confirm_deletions']);
        $_SESSION['settings']['show_tooltips'] = isset($_POST['show_tooltips']);
        $_SESSION['settings']['keyboard_shortcuts'] = isset($_POST['keyboard_shortcuts']);
        $_SESSION['settings']['save_scroll_position'] = isset($_POST['save_scroll_position']);
        markSettingsUpdated();
        saveGlobalSettingsToDb($_SESSION['settings']);
        
        $message = '✅ Editor settings saved successfully';
        $messageType = 'success';
        auditLog('editor_settings_saved', ['editor_theme' => $_SESSION['settings']['editor_theme']], 'info', 'system');
    }
    
    // Handle Notification Settings
    elseif ($action === 'save_notification_settings') {
        $_SESSION['settings'] = $_SESSION['settings'] ?? [];
        $_SESSION['settings']['show_success_messages'] = isset($_POST['show_success_messages']);
        $_SESSION['settings']['show_error_messages'] = isset($_POST['show_error_messages']);
        $_SESSION['settings']['show_warning_messages'] = isset($_POST['show_warning_messages']);
        $_SESSION['settings']['auto_dismiss_alerts'] = isset($_POST['auto_dismiss_alerts']);
        $_SESSION['settings']['alert_duration'] = max(2, min(30, (int) ($_POST['alert_duration'] ?? 5)));
        $_SESSION['settings']['enable_sounds'] = isset($_POST['enable_sounds']);
        $_SESSION['settings']['desktop_notifications'] = isset($_POST['desktop_notifications']);
        $_SESSION['settings']['animation_effects'] = isset($_POST['animation_effects']);
        $_SESSION['settings']['loading_indicators'] = isset($_POST['loading_indicators']);
        $_SESSION['settings']['progress_bars'] = isset($_POST['progress_bars']);
        markSettingsUpdated();
        saveGlobalSettingsToDb($_SESSION['settings']);
        
        $message = '✅ Notification settings saved successfully';
        $messageType = 'success';
        auditLog('notification_settings_saved', ['alert_duration' => $_SESSION['settings']['alert_duration']], 'info', 'system');
    }
    
    // Handle Export Settings
    elseif ($action === 'save_export_settings') {
        $_SESSION['settings'] = $_SESSION['settings'] ?? [];
        $_SESSION['settings']['default_export_format'] = sanitizeInput($_POST['default_export_format'] ?? 'json');
        $_SESSION['settings']['export_filename_prefix'] = sanitizeInput($_POST['export_filename_prefix'] ?? 'export');
        $csvDelimiter = $_POST['csv_delimiter'] ?? ';';
        $allowedCsvDelimiters = [';', ',', 'tab', '|'];
        if (!in_array($csvDelimiter, $allowedCsvDelimiters, true)) {
            $csvDelimiter = ';';
        }
        $_SESSION['settings']['csv_delimiter'] = $csvDelimiter;
        $_SESSION['settings']['include_metadata'] = isset($_POST['include_metadata']);
        $_SESSION['settings']['csv_include_bom'] = isset($_POST['csv_include_bom']);
        $_SESSION['settings']['compress_exports'] = isset($_POST['compress_exports']);
        $_SESSION['settings']['timestamp_exports'] = isset($_POST['timestamp_exports']);
        $_SESSION['settings']['auto_backup'] = isset($_POST['auto_backup']);
        $_SESSION['settings']['backup_frequency'] = sanitizeInput($_POST['backup_frequency'] ?? 'weekly');
        $_SESSION['settings']['backup_retention'] = max(1, min(365, (int) ($_POST['backup_retention'] ?? 30)));
        markSettingsUpdated();
        saveGlobalSettingsToDb($_SESSION['settings']);
        
        $message = '✅ Export settings saved successfully';
        $messageType = 'success';
        auditLog('export_settings_saved', ['default_export_format' => $_SESSION['settings']['default_export_format']], 'info', 'system');
    }
    
    // Handle Security Settings
    elseif ($action === 'save_security_settings') {
        $_SESSION['settings'] = $_SESSION['settings'] ?? [];
        $_SESSION['settings']['csrf_enabled'] = isset($_POST['csrf_enabled']);
        $_SESSION['settings']['csrf_token_lifetime'] = max(10, min(1440, (int) ($_POST['csrf_token_lifetime'] ?? 60)));
        $_SESSION['settings']['session_validation_enabled'] = isset($_POST['session_validation_enabled']);
        $_SESSION['settings']['ip_tracking_enabled'] = isset($_POST['ip_tracking_enabled']);
        $_SESSION['settings']['rate_limit_enabled'] = isset($_POST['rate_limit_enabled']);
        $_SESSION['settings']['rate_limit_requests'] = max(10, min(1000, (int) ($_POST['rate_limit_requests'] ?? 30)));
        $_SESSION['settings']['rate_limit_lockout'] = max(30, min(3600, (int) ($_POST['rate_limit_lockout'] ?? 60)));
        $_SESSION['settings']['enable_idle_timeout'] = isset($_POST['enable_idle_timeout']);
        $_SESSION['settings']['idle_timeout_minutes'] = max(5, min(240, (int) ($_POST['idle_timeout_minutes'] ?? 30)));
        $_SESSION['settings']['log_all_actions'] = isset($_POST['log_all_actions']);
        $_SESSION['settings']['log_failed_logins'] = isset($_POST['log_failed_logins']);
        $_SESSION['settings']['log_security_events'] = isset($_POST['log_security_events']);
        markSettingsUpdated();
        saveGlobalSettingsToDb($_SESSION['settings']);
        
        $message = '✅ Security settings saved successfully';
        $messageType = 'success';
        auditLog('security_settings_saved', ['rate_limit_requests' => $_SESSION['settings']['rate_limit_requests']], 'info', 'security');
    }
    
    // Handle Export Settings
    elseif ($action === 'export_settings') {
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!verifyCSRFToken($csrfToken)) {
            $message = '❌ CSRF token validation failed';
            $messageType = 'error';
        } else {
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="app_settings_' . date('Ymd_His') . '.json"');
            echo json_encode($_SESSION['settings'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    // Handle Import Settings
    elseif ($action === 'import_settings' && isset($_FILES['settings_file'])) {
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!verifyCSRFToken($csrfToken)) {
            $message = '❌ CSRF token validation failed';
            $messageType = 'error';
        } else {
            try {
                validateUpload($_FILES['settings_file'], ['application/json', 'text/plain']);
                $fileContent = file_get_contents($_FILES['settings_file']['tmp_name']);
                $importedSettings = json_decode($fileContent, true);
                
                if ($importedSettings === null) {
                    $message = '❌ Invalid JSON format in settings file';
                    $messageType = 'error';
                } else {
                    $_SESSION['settings'] = array_merge($_SESSION['settings'] ?? [], $importedSettings);
                    markSettingsUpdated();
                    saveGlobalSettingsToDb($_SESSION['settings']);
                    $message = '✅ Settings imported successfully';
                    $messageType = 'success';
                    auditLog('settings_imported', ['settings_count' => count($importedSettings)], 'warning', 'system');
                }
            } catch (Exception $e) {
                $message = '❌ ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    
    // Handle Cache Clear
    elseif ($action === 'clear_cache') {
        $_SESSION['cache'] = [];
        $message = '✅ Application cache cleared successfully';
        $messageType = 'success';
        auditLog('cache_cleared', [], 'info', 'system');
    }
    
    // Handle Settings Reset
    elseif ($action === 'reset_settings') {
        $_SESSION['settings'] = getDefaultSettings();
        markSettingsUpdated();
        saveGlobalSettingsToDb($_SESSION['settings']);
        $message = '✅ All settings reset to defaults';
        $messageType = 'success';
        auditLog('settings_reset', [], 'warning', 'system');
    }
    
    // Handle Clear Logs
    elseif ($action === 'clear_logs') {
        $logFile = __DIR__ . '/logs/security.log';
        if (file_exists($logFile)) {
            file_put_contents($logFile, '');
        }
        $message = '✅ Security logs cleared successfully';
        $messageType = 'success';
        auditLog('logs_cleared', [], 'warning', 'security');
    }
    
    // Handle User Management Actions
    elseif ($action === 'create_user') {
        $username = sanitizeInput($_POST['username'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        $fullName = sanitizeInput($_POST['full_name'] ?? '');
        $role = $_POST['role'] ?? 'viewer';
        
        // Validate password match
        if ($password !== $passwordConfirm) {
            $message = '❌ Passwords do not match';
            $messageType = 'error';
        } else {
            $result = registerUser($username, $email, $password, $fullName, $role);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'error';
            if ($result['success']) {
                auditLog('user_created', ['username' => $username, 'role' => $role, 'email' => $email], 'info', 'user');
            } else {
                auditLog('user_creation_failed', ['username' => $username, 'reason' => $result['message']], 'warning', 'user');
            }
        }
    }
    
    elseif ($action === 'update_user') {
        $userId = $_POST['user_id'] ?? '';
        $userData = [
            'username' => sanitizeInput($_POST['username'] ?? ''),
            'email' => sanitizeInput($_POST['email'] ?? ''),
            'full_name' => sanitizeInput($_POST['full_name'] ?? ''),
            'role' => $_POST['role'] ?? 'viewer'
        ];
        
        $result = updateUser($userId, $userData);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
        if ($result['success']) {
            auditLog('user_updated', ['user_id' => $userId, 'updated_fields' => array_keys($userData)], 'info', 'user');
        }
    }
    
    elseif ($action === 'delete_user') {
        $userId = $_POST['user_id'] ?? '';
        $result = deleteUser($userId);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
        if ($result['success']) {
            auditLog('user_deleted', ['user_id' => $userId], 'warning', 'user');
        }
    }
    
    elseif ($action === 'activate_user') {
        $userId = $_POST['user_id'] ?? '';
        $result = activateUser($userId);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
        if ($result['success']) {
            auditLog('user_activated', ['user_id' => $userId], 'info', 'user');
        }
    }
    
    elseif ($action === 'deactivate_user') {
        $userId = $_POST['user_id'] ?? '';
        $result = deactivateUser($userId);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
        if ($result['success']) {
            auditLog('user_deactivated', ['user_id' => $userId], 'warning', 'user');
        }
    }
    
    elseif ($action === 'reset_user_password') {
        $userId = $_POST['user_id'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        
        $result = adminResetPassword($userId, $newPassword);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
        
        if ($result['success']) {
            auditLog('admin_password_reset', ['user_id' => $userId], 'warning', 'security');
        }
    }

    elseif ($action === 'change_password') {
        $currentUser = getCurrentUser();
        $oldPassword = $_POST['old_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['new_password_confirm'] ?? '';
        
        if (!$currentUser) {
            $message = '❌ You must be logged in to change your password';
            $messageType = 'error';
        } elseif ($newPassword !== $confirmPassword) {
            $message = '❌ New passwords do not match';
            $messageType = 'error';
        } else {
            $result = changeUserPassword($currentUser['id'], $oldPassword, $newPassword);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'error';
        }
    }

    elseif ($action === 'save_filter') {
        $filterName = sanitizeInput($_POST['filter_name'] ?? '');
        $collectionKey = sanitizeInput($_POST['collection'] ?? ($collectionName ?? ''));
        if ($filterName === '') {
            $message = '❌ Filter name is required';
            $messageType = 'error';
        } else {
            $_SESSION['saved_filters'] = $_SESSION['saved_filters'] ?? [];
            $_SESSION['saved_filters'][$collectionKey] = $_SESSION['saved_filters'][$collectionKey] ?? [];

            $newFilter = [
                'id' => uniqid('filter_', true),
                'name' => $filterName,
                'params' => [
                    'search' => sanitizeInput($_POST['search'] ?? ''),
                    'sort' => sanitizeInput($_POST['sort'] ?? ''),
                    'order' => ($_POST['order'] ?? '-1') === '1' ? '1' : '-1',
                    'filter' => trim((string) ($_POST['filter'] ?? ''))
                ],
                'created_at' => date('c')
            ];

            $_SESSION['saved_filters'][$collectionKey][] = $newFilter;
            $_SESSION['saved_filters'][$collectionKey] = array_slice($_SESSION['saved_filters'][$collectionKey], -20);
            $message = '✅ Filter saved';
            $messageType = 'success';
            auditLog('filter_saved', ['collection' => $collectionKey, 'name' => $filterName], 'info', 'system');
        }
    }

    elseif ($action === 'delete_filter') {
        $collectionKey = sanitizeInput($_POST['collection'] ?? ($collectionName ?? ''));
        $filterId = sanitizeInput($_POST['filter_id'] ?? '');
        $filters = $_SESSION['saved_filters'][$collectionKey] ?? [];
        if ($filterId === '' || empty($filters)) {
            $message = '❌ Filter not found';
            $messageType = 'error';
        } else {
            $filters = array_values(array_filter($filters, function ($item) use ($filterId) {
                return ($item['id'] ?? '') !== $filterId;
            }));
            $_SESSION['saved_filters'][$collectionKey] = $filters;
            $message = '✅ Filter removed';
            $messageType = 'success';
            auditLog('filter_deleted', ['collection' => $collectionKey], 'warning', 'system');
        }
    }

    elseif ($action === 'export_query_history') {
        $history = getQueryHistory(getSetting('query_history_limit', 50));
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="query_history_' . date('Ymd_His') . '.json"');
        echo json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Handle Audit Log Actions
    elseif ($action === 'export_audit_log') {
        $filters = [];
        if (!empty($_POST['filter_action'])) $filters['action'] = $_POST['filter_action'];
        if (!empty($_POST['filter_user'])) $filters['user'] = $_POST['filter_user'];
        if (!empty($_POST['filter_category'])) $filters['category'] = $_POST['filter_category'];
        if (!empty($_POST['filter_severity'])) $filters['severity'] = $_POST['filter_severity'];
        if (!empty($_POST['filter_date_from'])) $filters['date_from'] = $_POST['filter_date_from'];
        if (!empty($_POST['filter_date_to'])) $filters['date_to'] = $_POST['filter_date_to'];
        
        $logs = getAuditLogs($filters, 10000);
        
        auditLog('audit_log_exported', ['filters' => $filters, 'count' => count($logs)], 'info', 'security');
        
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="audit_log_' . date('Ymd_His') . '.json"');
        echo json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    elseif ($action === 'clear_old_audit_logs') {
        $daysToKeep = max(1, min(365, (int)($_POST['days_to_keep'] ?? 90)));
        $deleted = clearOldAuditLogs($daysToKeep);
        $message = "✅ Cleared $deleted old audit log entries";
        $messageType = 'success';
        auditLog('audit_logs_cleared', ['days_kept' => $daysToKeep, 'deleted_count' => $deleted], 'warning', 'system');
    }
    
    // Catch-all: If we processed a POST action but didn't redirect yet, redirect now to prevent form resubmission
    if ($action && isset($message)) {
        if (isAjaxRequest() && in_array($action, $ajaxSettingsActions, true)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'message' => $message,
                'messageType' => $messageType ?? 'info',
                'settings' => $_SESSION['settings'] ?? []
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $_SESSION['message'] = $message;
        $_SESSION['messageType'] = $messageType ?? 'info';
        if (ob_get_length()) ob_clean();
        $redirectUrl = $_SERVER['PHP_SELF'];
        if (isset($collectionName) && $collectionName) {
            $redirectUrl .= '?collection=' . urlencode($collectionName);
        }
        if (in_array($action, $settingsRedirectActions, true)) {
            $redirectUrl .= (strpos($redirectUrl, '?') === false ? '?' : '&') . 'tab=settings';
        }
        header('Location: ' . $redirectUrl);
        exit;
    }
}

// Export query results (must run before any HTML output)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['export_query_json', 'export_query_csv'])) {
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!verifyCSRFToken($csrfToken)) {
            http_response_code(403);
            echo 'Invalid CSRF token';
            exit;
        }

        try {
            $queryResults = [];
            $maxResultsSetting = (int) getSetting('max_results', 1000);
            $maxResultsSetting = max(100, min(10000, $maxResultsSetting));
            $queryTimeoutMs = (int) getSetting('query_timeout', 30) * 1000;
            $queryTimeoutMs = max(5000, min(300000, $queryTimeoutMs));
            $exportPrefix = sanitizeInput(getSetting('export_filename_prefix', 'export'));
            if ($exportPrefix === '') {
                $exportPrefix = 'export';
            }
            $timestampExports = (bool) getSetting('timestamp_exports', true);
            $compressExports = (bool) getSetting('compress_exports', false);
            $includeMetadata = (bool) getSetting('include_metadata', true);
            $delimiterSetting = getSetting('csv_delimiter', ';');
            $includeCsvBom = (bool) getSetting('csv_include_bom', true);

            $sortField = sanitizeInput($_POST['sort'] ?? '_id');
            $sortOrder = ($_POST['sort_order'] ?? 'desc') === 'asc' ? 1 : -1;
            $limit = (int) ($_POST['limit'] ?? 100);
            if ($limit < 1) {
                $limit = 1;
            }
            $limit = min($limit, $maxResultsSetting);

            // Projection (comma-separated fields)
            $projection = null;
            $projectionRaw = trim((string) ($_POST['projection'] ?? ''));
            if ($projectionRaw !== '') {
                $fields = array_filter(array_map('trim', explode(',', $projectionRaw)));
                $proj = [];
                foreach ($fields as $f) {
                    $f = sanitizeInput($f);
                    if ($f !== '' && validateFieldName($f)) {
                        $proj[$f] = 1;
                    }
                }
                if (!empty($proj)) {
                    $projection = $proj;
                }
            }

            // Determine whether we export a quick query or a custom JSON query
            if (isset($_POST['custom_query']) && trim((string) $_POST['custom_query']) !== '') {
                $customQuery = (string) $_POST['custom_query'];
                if (!validateJSON($customQuery)) {
                    throw new Exception('Invalid JSON or dangerous patterns detected');
                }
                $query = json_decode($customQuery, true);
                $sanitizedQuery = sanitizeMongoQuery($query);

                $findOptions = [
                    'limit' => $limit,
                    'sort' => [$sortField => $sortOrder],
                    'maxTimeMS' => $queryTimeoutMs
                ];
                if ($projection) {
                    $findOptions['projection'] = $projection;
                }

                $queryResults = $collection->find($sanitizedQuery, $findOptions)->toArray();
            } else {
                $field = sanitizeInput($_POST['query_field'] ?? '');
                $rawValue = (string) ($_POST['query_value'] ?? '');
                $operator = $_POST['query_op'] ?? 'equals';
                $valueType = $_POST['value_type'] ?? 'string';

                if ($field === '' || !validateFieldName($field)) {
                    throw new Exception('Invalid field name');
                }

                // Type-coerce value
                $value = $rawValue;
                switch ($valueType) {
                    case 'number':
                        if (!is_numeric($rawValue)) {
                            throw new Exception('Value is not numeric');
                        }
                        $value = (strpos($rawValue, '.') !== false) ? (float) $rawValue : (int) $rawValue;
                        break;
                    case 'bool':
                        $v = strtolower(trim($rawValue));
                        $value = in_array($v, ['1', 'true', 'yes', 'y', 'on'], true);
                        break;
                    case 'null':
                        $value = null;
                        break;
                    case 'objectid':
                        $value = new ObjectId($rawValue);
                        break;
                    case 'date':
                        $ts = strtotime($rawValue);
                        if ($ts === false) {
                            throw new Exception('Invalid date value');
                        }
                        $value = new UTCDateTime($ts * 1000);
                        break;
                    case 'string':
                    default:
                        $value = $rawValue;
                        break;
                }

                $mongoQuery = [];
                switch ($operator) {
                    case 'equals':
                        $mongoQuery[$field] = $value;
                        break;
                    case 'contains':
                        $mongoQuery[$field] = ['$regex' => (string) $rawValue, '$options' => 'i'];
                        break;
                    case 'starts':
                        $mongoQuery[$field] = ['$regex' => '^' . (string) $rawValue, '$options' => 'i'];
                        break;
                    case 'ends':
                        $mongoQuery[$field] = ['$regex' => (string) $rawValue . '$', '$options' => 'i'];
                        break;
                    case 'gt':
                        $mongoQuery[$field] = ['$gt' => $value];
                        break;
                    case 'lt':
                        $mongoQuery[$field] = ['$lt' => $value];
                        break;
                    case 'gte':
                        $mongoQuery[$field] = ['$gte' => $value];
                        break;
                    case 'lte':
                        $mongoQuery[$field] = ['$lte' => $value];
                        break;
                    default:
                        $mongoQuery[$field] = $value;
                        break;
                }

                $findOptions = [
                    'limit' => $limit,
                    'sort' => [$sortField => $sortOrder],
                    'maxTimeMS' => $queryTimeoutMs
                ];
                if ($projection) {
                    $findOptions['projection'] = $projection;
                }

                $queryResults = $collection->find($mongoQuery, $findOptions)->toArray();
            }

            $baseFile = $exportPrefix . '_' . $collectionName;
            if ($timestampExports) {
                $baseFile .= '_' . date('Ymd_His');
            }

            if ($action === 'export_query_json') {
                auditLog('query_exported_json', ['count' => count($queryResults), 'collection' => $collectionName], 'info', 'data');
                $payload = $queryResults;
                if ($includeMetadata) {
                    $payload = [
                        'metadata' => [
                            'collection' => $collectionName,
                            'exported_at' => date('c'),
                            'count' => count($queryResults)
                        ],
                        'data' => $queryResults
                    ];
                }
                $jsonOutput = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                if ($compressExports) {
                    $gzipOutput = gzencode($jsonOutput, 9);
                    header('Content-Type: application/gzip');
                    header('Content-Encoding: gzip');
                    header('Content-Disposition: attachment; filename="' . $baseFile . '.json.gz"');
                    echo $gzipOutput;
                } else {
                    header('Content-Type: application/json; charset=utf-8');
                    header('Content-Disposition: attachment; filename="' . $baseFile . '.json"');
                    echo $jsonOutput;
                }
                exit;
            }

            // CSV export
            auditLog('query_exported_csv', ['count' => count($queryResults), 'collection' => $collectionName], 'info', 'data');
            $out = fopen('php://temp', 'r+');
            if ($out === false) {
                throw new Exception('Unable to open output stream');
            }

            $delimiter = ';';
            if ($delimiterSetting === ',') {
                $delimiter = ',';
            } elseif ($delimiterSetting === 'tab') {
                $delimiter = "\t";
            } elseif ($delimiterSetting === '|') {
                $delimiter = '|';
            }

            if ($includeCsvBom) {
                fwrite($out, "\xEF\xBB\xBF");
            }

            // Collect top-level keys across documents
            $columns = [];
            foreach ($queryResults as $doc) {
                $arr = json_decode(json_encode($doc), true);
                foreach (array_keys($arr) as $k) {
                    if (!in_array($k, $columns, true)) {
                        $columns[] = $k;
                    }
                }
            }

            if (empty($columns)) {
                $columns = ['_id'];
            }

            if ($includeMetadata) {
                fwrite($out, '# Exported at: ' . date('c') . PHP_EOL);
                fwrite($out, '# Collection: ' . $collectionName . PHP_EOL);
                fwrite($out, '# Count: ' . count($queryResults) . PHP_EOL);
            }
            fputcsv($out, $columns, $delimiter, '"', '\\');

            foreach ($queryResults as $doc) {
                $arr = json_decode(json_encode($doc), true);
                $row = [];
                foreach ($columns as $col) {
                    $val = $arr[$col] ?? '';
                    if (is_array($val) || is_object($val)) {
                        $val = json_encode($val, JSON_UNESCAPED_UNICODE);
                    }
                    $row[] = $val;
                }
                fputcsv($out, $row, $delimiter, '"', '\\');
            }

            rewind($out);
            $csvOutput = stream_get_contents($out);
            fclose($out);

            if ($compressExports) {
                $gzipOutput = gzencode($csvOutput, 9);
                header('Content-Type: application/gzip');
                header('Content-Encoding: gzip');
                header('Content-Disposition: attachment; filename="' . $baseFile . '.csv.gz"');
                echo $gzipOutput;
            } else {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $baseFile . '.csv"');
                echo $csvOutput;
            }
            exit;
        } catch (Exception $e) {
            http_response_code(400);
            echo 'Export error: ' . htmlspecialchars($e->getMessage());
            exit;
        }
    }
}

// Load and display main UI
include 'templates/header.php';
?>
<div class="container">
    <header>
        <button id="themeToggle" class="theme-toggle" onclick="toggleTheme()" title="Toggle Dark Mode">🌙</button>
        <h1>🗂️ MongoDB Admin Panel</h1>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <p style="color: var(--text-secondary); margin: 0;">Database: <strong
                        style="color: var(--text-primary);"><?php echo htmlspecialchars($db); ?></strong></p>
                <a href="?disconnect=1"
                    style="background: #dc3545; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 12px; cursor: pointer;">🔌
                    Change Connection</a>
                
                <!-- User Info & Logout -->
                <?php $currentUser = getCurrentUser(); ?>
                <div style="display: flex; align-items: center; gap: 10px; margin-left: auto; padding-left: 15px; border-left: 2px solid #ddd;">
                    <div style="text-align: right; font-size: 12px;">
                        <p style="color: var(--text-secondary); margin: 0;">Logged in as</p>
                        <p style="color: var(--text-primary); font-weight: 600; margin: 0;">
                            👤 <?php echo htmlspecialchars($currentUser['full_name'] ?: $currentUser['username']); ?>
                        </p>
                        <p style="color: #667eea; font-size: 11px; margin: 0;">
                            <?php echo ucfirst($currentUser['role']); ?>
                        </p>
                    </div>
                    <form method="POST" style="margin: 0;">
                        <input type="hidden" name="action" value="logout">
                        <button type="submit" class="btn" style="background: #6c757d; color: white; padding: 6px 12px; border-radius: 4px; border: none; cursor: pointer; font-size: 12px;">
                            🚪 Logout
                        </button>
                    </form>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <label for="collectionSelect" style="color: var(--text-secondary); font-weight: 500;">Switch
                    Collection:</label>
                <select id="collectionSelect"
                    style="padding: 8px 12px; border: 2px solid #667eea; border-radius: 6px; background: var(--input-bg); color: var(--text-primary); cursor: pointer; font-size: 14px; min-width: 150px;"
                    onchange="switchCollection(this.value)">
                    <?php foreach ($allCollectionNames as $cname): ?>
                        <option value="<?php echo htmlspecialchars($cname); ?>" <?php echo $cname === $collectionName ? 'selected' : ''; ?>>
                            📦 <?php echo htmlspecialchars($cname); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="stats">
            <div class="stat-card">
                <p>Total Documents</p>
                <p><?php echo $documentCount; ?></p>
            </div>
            <div class="stat-card">
                <p>Avg Document Size</p>
                <p><?php echo number_format($avgDocSize / 1024, 2); ?> KB</p>
            </div>
            <div class="stat-card">
                <p>Collection Name</p>
                <p><?php echo htmlspecialchars($collectionName); ?></p>
            </div>
            <div class="stat-card">
                <p>Last Updated</p>
                <p><?php echo date('H:i:s'); ?></p>
            </div>
        </div>
    </header>

    <?php
    $showMessage = true;
    if ($messageType === 'success' && !getSetting('show_success_messages', true)) {
        $showMessage = false;
    }
    if ($messageType === 'error' && !getSetting('show_error_messages', true)) {
        $showMessage = false;
    }
    if ($messageType === 'warning' && !getSetting('show_warning_messages', true)) {
        $showMessage = false;
    }
    ?>
    <?php if ($message && $showMessage): ?>
        <div class="alert alert-<?php echo $messageType; ?>" id="alertMessage">
            <div class="alert-content">
                <span class="alert-icon">
                    <?php
                    $icons = [
                        'success' => '✅',
                        'error' => '❌',
                        'warning' => '⚠️',
                        'info' => 'ℹ️'
                    ];
                    echo $icons[$messageType] ?? 'ℹ️';
                    ?>
                </span>
                <span class="alert-text"><?php echo htmlspecialchars($message); ?></span>
                <button type="button" class="alert-close" onclick="this.parentElement.parentElement.remove()"
                    aria-label="Close">×</button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tab Navigation -->
    <div class="tabs">
        <!-- Dashboard - All roles -->
        <?php if (userHasPermission('view_data')): ?>
        <button type="button" class="tab-btn" data-tab="dashboard"
            onclick="switchTab('dashboard', this); return false;">🎯 Dashboard</button>
        <?php endif; ?>
        
        <!-- Browse - All roles -->
        <?php if (userHasPermission('view_data')): ?>
        <button type="button" class="tab-btn" data-tab="browse" onclick="switchTab('browse', this); return false;">📋
            Browse</button>
        <?php endif; ?>
        
        <!-- Query Builder - All roles -->
        <?php if (userHasPermission('view_data')): ?>
        <button type="button" class="tab-btn" data-tab="query" onclick="switchTab('query', this); return false;">🔍
            Query Builder</button>
        <?php endif; ?>
        
        <!-- Add Document - Requires create_data -->
        <?php if (userHasPermission('create_data')): ?>
        <button type="button" class="tab-btn" data-tab="add" onclick="switchTab('add', this); return false;">➕ Add
            Document</button>
        <?php endif; ?>
        
        <!-- Bulk Operations - Requires bulk_operations -->
        <?php if (userHasPermission('bulk_operations')): ?>
        <button type="button" class="tab-btn" data-tab="bulk" onclick="switchTab('bulk', this); return false;">📦 Bulk
            Operations</button>
        <?php endif; ?>
        
        <!-- Tools - Requires edit_data or manage_collections -->
        <?php if (userHasPermission('edit_data') || userHasPermission('manage_collections')): ?>
        <button type="button" class="tab-btn" data-tab="tools" onclick="switchTab('tools', this); return false;">🛠️
            Tools</button>
        <?php endif; ?>
        
        <!-- Advanced - Requires manage_collections -->
        <?php if (userHasPermission('manage_collections')): ?>
        <button type="button" class="tab-btn" data-tab="advanced"
            onclick="switchTab('advanced', this); return false;">🔬 Advanced</button>
        <?php endif; ?>
        
        <!-- Performance - Requires manage_collections or view_settings -->
        <?php if (userHasPermission('manage_collections') || userHasRole('admin')): ?>
        <button type="button" class="tab-btn" data-tab="performance"
            onclick="switchTab('performance', this); return false;">⚡ Performance</button>
        <?php endif; ?>
        
        <!-- Analytics - Requires view_analytics -->
        <?php if (userHasPermission('view_analytics')): ?>
        <button type="button" class="tab-btn" data-tab="stats" onclick="switchTab('stats', this); return false;">📊
            Analytics</button>
        <?php endif; ?>
        
        <!-- Schema - Requires manage_collections or view_data -->
        <?php if (userHasPermission('manage_collections') || userHasPermission('view_data')): ?>
        <button type="button" class="tab-btn" data-tab="schema" onclick="switchTab('schema', this); return false;">📐
            Schema</button>
        <?php endif; ?>
        
        <!-- Security - Requires view_security or manage_security -->
        <?php if (userHasPermission('view_security') || userHasPermission('manage_security')): ?>
        <button type="button" class="tab-btn" data-tab="security"
            onclick="switchTab('security', this); return false;">🔒 Security</button>
        <?php endif; ?>
        
        <!-- Audit Log - Requires view_logs (admin only) -->
        <?php if (userHasPermission('view_logs')): ?>
        <button type="button" class="tab-btn" data-tab="audit" onclick="switchTab('audit', this); return false;">📜
            Audit Log</button>
        <?php endif; ?>
        
        <!-- User Management - Requires manage_users (admin only) -->
        <?php if (userHasPermission('manage_users')): ?>
        <button type="button" class="tab-btn" data-tab="users" onclick="switchTab('users', this); return false;">👥
            Users</button>
        <?php endif; ?>
        
        <!-- Settings - All roles can view -->
        <?php if (userHasPermission('view_settings')): ?>
        <button type="button" class="tab-btn" data-tab="settings"
            onclick="switchTab('settings', this); return false;">⚙️ Settings</button>
        <?php endif; ?>
    </div>

    <!-- Dashboard Tab -->
    <?php require __DIR__ . '/includes/tabs/dashboard.php'; ?>

    <!-- Query Builder Tab -->
    <?php require __DIR__ . '/includes/tabs/query.php'; ?>

    <!-- Browse Tab -->
    <?php require __DIR__ . '/includes/tabs/browse.php'; ?>

    <!-- Add New Document Tab -->
    <?php require __DIR__ . '/includes/tabs/add.php'; ?>

    <!-- Bulk Operations Tab -->
    <?php require __DIR__ . '/includes/tabs/bulk.php'; ?>

    <!-- Tools Tab -->
    <?php require __DIR__ . '/includes/tabs/tools.php'; ?>

<!-- Advanced Tab -->
<?php require __DIR__ . '/includes/tabs/advanced.php'; ?>

<!-- Performance Tab -->
<?php require __DIR__ . '/includes/tabs/performance.php'; ?>

<!-- Analytics Tab -->
<?php require __DIR__ . '/includes/tabs/stats.php'; ?>

<!-- Schema Explorer Tab -->
<?php require __DIR__ . '/includes/tabs/schema.php'; ?>

<!-- Security & Backup Tab -->
<?php require __DIR__ . '/includes/tabs/security.php'; ?>

<!-- Audit Log Tab -->
<?php if (userHasRole('admin')): ?>
    <?php require __DIR__ . '/includes/tabs/audit.php'; ?>
<?php endif; ?>

<!-- User Management Tab -->
<?php if (userHasRole('admin')): ?>
    <?php require __DIR__ . '/includes/tabs/users.php'; ?>
<?php endif; ?>

    <!-- Settings Tab -->
    <?php require __DIR__ . '/includes/tabs/settings.php'; ?>
    <!-- View Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>📄 View Full Document</h2>
                <button type="button" class="close-btn" onclick="closeViewModal(); return false;">&times;</button>
            </div>
            <div class="json-highlight" id="viewJsonContainer">
                <pre><code id="viewJsonContent" class="language-json"></code></pre>
            </div>
            <div style="margin-top: 25px; display: flex; gap: 12px; flex-wrap: wrap;">
                <button type="button" class="btn" onclick="switchViewToEdit(); return false;"
                    style="background: #667eea; color: white; flex: 1; min-width: 120px; padding: 14px;">✏️ Edit
                    JSON</button>
                <button type="button" class="btn" onclick="copyToClipboard(); return false;"
                    style="background: #17a2b8; color: white; flex: 1; min-width: 120px; padding: 14px;">📋 Copy
                    JSON</button>
                <button type="button" class="btn" onclick="closeViewModal(); return false;"
                    style="background: #6c757d; color: white; flex: 1; min-width: 120px; padding: 14px;">Close</button>
            </div>
        </div>
    </div>

    <!-- Edit from View Modal -->
    <div id="editFromViewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>✏️ Edit Document</h2>
                <button type="button" class="close-btn"
                    onclick="closeEditFromViewModal(); return false;">&times;</button>
            </div>
            <form method="POST" id="editFromViewForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="doc_id" id="editFromViewDocId">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div class="form-group">
                    <label>JSON Preview (Read-only with syntax highlighting):</label>
                    <div class="json-highlight" style="max-height: 250px; margin-bottom: 15px;">
                        <pre><code id="editFromViewJsonPreview" class="language-json"></code></pre>
                    </div>
                </div>
                <div class="form-group">
                    <label>Edit JSON Data:</label>
                    <textarea name="json_data" id="editFromViewJsonData" class="json-textarea"
                        style="min-height: 200px; font-family: 'Courier New', monospace; font-size: 12px;"></textarea>
                </div>
                <div class="form-group" style="display: flex; gap: 12px; margin-top: 30px;">
                    <button type="submit" class="btn"
                        style="flex: 1; background: #28a745; color: white; padding: 14px;">💾
                        Save Changes</button>
                    <button type="button" class="btn" style="flex: 1; background: #6c757d; color: white; padding: 14px;"
                        onclick="closeEditFromViewModal(); return false;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>✏️ Edit Document</h2>
                <button type="button" class="close-btn" onclick="closeEditModal(); return false;">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="doc_id" id="editDocId">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div class="form-group">
                    <label>JSON Preview (Read-only with syntax highlighting):</label>
                    <div class="json-highlight" style="max-height: 250px; margin-bottom: 15px;">
                        <pre><code id="editJsonPreview" class="language-json"></code></pre>
                    </div>
                </div>
                <div class="form-group">
                    <label>Edit JSON Data:</label>
                    <textarea name="json_data" id="editJsonData" class="json-textarea"
                        style="min-height: 200px; font-family: 'Courier New', monospace; font-size: 12px;"></textarea>
                </div>
                <div class="form-group" style="display: flex; gap: 12px; margin-top: 30px;">
                    <button type="submit" class="btn"
                        style="flex: 1; background: #28a745; color: white; padding: 14px;">💾
                        Save Changes</button>
                    <button type="button" class="btn" style="flex: 1; background: #6c757d; color: white; padding: 14px;"
                        onclick="closeEditModal(); return false;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Auto-dismiss alert messages after 5 seconds -->
    <style>
        .tab-content {
            display: none !important;
        }
        .tab-content.active {
            display: block !important;
        }
    </style>
    <script>
        console.log('Script started, defining functions...');
        // Auto-dismiss alerts after 5 seconds
        const alertMessage = document.getElementById('alertMessage');
        const autoDismissAlerts = <?php echo getSetting('auto_dismiss_alerts', true) ? 'true' : 'false'; ?>;
        const alertDurationMs = <?php echo (int) getSetting('alert_duration', 5); ?> * 1000;
        if (alertMessage && autoDismissAlerts) {
            setTimeout(function () {
                alertMessage.style.animation = 'fadeOut 0.5s ease-out';
                setTimeout(function () {
                    alertMessage.remove();
                }, 500);
            }, alertDurationMs);
        }
        const confirmDeletes = <?php echo getSetting('confirm_deletions', true) ? 'true' : 'false'; ?>;
        const refreshIntervalSeconds = <?php echo (int) getSetting('refresh_interval', 30); ?>;
        const autoRefreshDefault = <?php echo getSetting('auto_refresh', false) ? 'true' : 'false'; ?>;
        const defaultViewMode = <?php echo json_encode(getSetting('default_view_mode', 'table')); ?>;

        // Browse Tab Functions
        function performSearch() {
            const search = document.getElementById('searchInput').value;
            const sort = document.getElementById('sortField').value;
            const order = document.getElementById('sortOrder').value;
            const jsonFilter = document.getElementById('jsonFilter')?.value || '';

            let url = window.location.pathname + '?collection=' + encodeURIComponent('<?php echo $collectionName; ?>');
            if (search) url += '&search=' + encodeURIComponent(search);
            if (sort) url += '&sort=' + encodeURIComponent(sort);
            if (order) url += '&order=' + encodeURIComponent(order);
            if (jsonFilter) url += '&filter=' + encodeURIComponent(jsonFilter);

            window.location.href = url;
        }

        function resetFilters() {
            document.getElementById('searchInput').value = '';
            if (document.getElementById('jsonFilter')) document.getElementById('jsonFilter').value = '';
            document.getElementById('sortField').selectedIndex = 0;
            document.getElementById('sortOrder').selectedIndex = 0;
            window.location.href = window.location.pathname + '?collection=' + encodeURIComponent('<?php echo $collectionName; ?>');
        }

        function executeQuickQuery() {
            const form = document.getElementById('quickQueryForm');
            const field = form.querySelector('input[name="query_field"]').value;
            const value = form.querySelector('input[name="query_value"]').value;
            
            if (!field || !value) {
                alert('Please fill in Field Name and Field Value');
                return false;
            }
            
            // Add action and submit
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'action';
            input.value = 'execute_query';
            form.appendChild(input);
            
            form.submit();
            return false;
        }

        function executeCustomQuery() {
            const form = document.getElementById('customQueryForm');
            const query = form.querySelector('textarea[name="custom_query"]').value.trim();
            
            if (!query) {
                alert('Please enter a MongoDB query');
                return false;
            }
            
            // Validate JSON
            try {
                JSON.parse(query);
            } catch (e) {
                alert('Invalid JSON: ' + e.message);
                return false;
            }
            
            // Add action and submit
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'action';
            input.value = 'execute_custom_query';
            form.appendChild(input);
            
            form.submit();
            return false;
        }

        function applyQuickFilter(type, field = '', value = '') {
            const jsonFilter = document.getElementById('jsonFilter');
            if (!jsonFilter) return;

            let filter = '';
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            switch (type) {
                case 'all':
                    filter = '{}';
                    break;
                case 'today':
                    filter = '{"' + field + '": {"$gte": "' + today.toISOString() + '"}}';
                    break;
                case 'week':
                    const weekAgo = new Date(today);
                    weekAgo.setDate(weekAgo.getDate() - 7);
                    filter = '{"' + field + '": {"$gte": "' + weekAgo.toISOString() + '"}}';
                    break;
                case 'month':
                    const monthAgo = new Date(today);
                    monthAgo.setMonth(monthAgo.getMonth() - 1);
                    filter = '{"' + field + '": {"$gte": "' + monthAgo.toISOString() + '"}}';
                    break;
                case 'has_field':
                    filter = '{"' + field + '": {"$exists": true, "$ne": null, "$ne": ""}}';
                    break;
                case 'empty_field':
                    filter = '{"$or": [{"' + field + '": {"$exists": false}}, {"' + field + '": null}, {"' + field + '": ""}]}';
                    break;
                case 'status_value':
                    filter = '{"' + field + '": "' + value + '"}';
                    break;
            }

            jsonFilter.value = filter;
            // Auto-apply the filter
            performSearch();
        }

        function saveCurrentFilter() {
            const name = prompt('Save filter as:');
            if (!name) {
                return;
            }
            const search = document.getElementById('searchInput')?.value || '';
            const sort = document.getElementById('sortField')?.value || '';
            const order = document.getElementById('sortOrder')?.value || '';
            const jsonFilter = document.getElementById('jsonFilter')?.value || '';
            const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = window.location.pathname;
            form.innerHTML = `
                <input type="hidden" name="action" value="save_filter">
                <input type="hidden" name="csrf_token" value="${csrfToken}">
                <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                <input type="hidden" name="filter_name" value="${escapeHtml(name)}">
                <input type="hidden" name="search" value="${escapeHtml(search)}">
                <input type="hidden" name="sort" value="${escapeHtml(sort)}">
                <input type="hidden" name="order" value="${escapeHtml(order)}">
                <input type="hidden" name="filter" value="${escapeHtml(jsonFilter)}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        function applyViewMode(mode) {
            const tableView = document.getElementById('tableView');
            const gridView = document.getElementById('gridView');
            const viewIcon = document.getElementById('viewIcon');
            const viewText = document.getElementById('viewText');
            if (!tableView || !gridView || !viewIcon || !viewText) {
                return;
            }

            if (mode === 'grid') {
                tableView.style.display = 'none';
                gridView.style.display = 'block';
                viewIcon.textContent = '📋';
                viewText.textContent = 'Table View';
            } else {
                tableView.style.display = 'block';
                gridView.style.display = 'none';
                viewIcon.textContent = '📊';
                viewText.textContent = 'Grid View';
            }
        }

        function toggleView() {
            const tableView = document.getElementById('tableView');
            if (!tableView) {
                return;
            }
            const nextMode = tableView.style.display === 'none' ? 'table' : 'grid';
            applyViewMode(nextMode);
            try {
                localStorage.setItem('viewMode', nextMode);
            } catch (e) {}
        }

        (function initViewMode() {
            let storedMode = null;
            try {
                storedMode = localStorage.getItem('viewMode');
            } catch (e) {}
            const initialMode = storedMode || defaultViewMode;
            applyViewMode(initialMode);
        })();

        function changePerPage(value) {
            window.location.href = window.location.pathname + '?collection=' + encodeURIComponent('<?php echo $collectionName; ?>') + '&per_page=' + value;
        }

        function jumpToPage(page) {
            if (page < 1 || page > <?php echo $totalPages; ?>) return;
            const search = document.getElementById('searchInput').value;
            const sort = document.getElementById('sortField').value;
            const order = document.getElementById('sortOrder').value;

            let url = window.location.pathname + '?collection=' + encodeURIComponent('<?php echo $collectionName; ?>') + '&page=' + page;
            if (search) url += '&search=' + encodeURIComponent(search);
            if (sort) url += '&sort=' + encodeURIComponent(sort);
            if (order) url += '&order=' + encodeURIComponent(order);

            window.location.href = url;
        }

        // Bulk Selection Functions
        let bulkSelectionMode = false;

        function toggleBulkSelection() {
            bulkSelectionMode = !bulkSelectionMode;
            const checkboxes = document.querySelectorAll('.doc-checkbox');
            const selectAll = document.getElementById('selectAll');

            if (bulkSelectionMode) {
                checkboxes.forEach(cb => cb.style.display = 'block');
                if (selectAll) selectAll.style.display = 'block';
                alert('Bulk selection mode enabled. Select documents using checkboxes.');
            } else {
                checkboxes.forEach(cb => {
                    cb.checked = false;
                    cb.style.display = 'none';
                });
                if (selectAll) {
                    selectAll.checked = false;
                    selectAll.style.display = 'none';
                }
                updateBulkBar();
            }
        }

        function toggleSelectAll(checkbox) {
            const checkboxes = document.querySelectorAll('.doc-checkbox');
            checkboxes.forEach(cb => cb.checked = checkbox.checked);
            updateBulkBar();
        }

        function updateBulkBar() {
            const selected = document.querySelectorAll('.doc-checkbox:checked').length;
            const bulkBar = document.getElementById('bulkActionsBar');
            const selectedCount = document.getElementById('selectedCount');

            if (selected > 0) {
                bulkBar.style.display = 'block';
                selectedCount.textContent = selected;
            } else {
                bulkBar.style.display = 'none';
            }
        }

        function clearSelection() {
            const checkboxes = document.querySelectorAll('.doc-checkbox');
            checkboxes.forEach(cb => cb.checked = false);
            document.getElementById('selectAll').checked = false;
            updateBulkBar();
        }

        function bulkDelete() {
            const selected = Array.from(document.querySelectorAll('.doc-checkbox:checked')).map(cb => cb.value);
            if (selected.length === 0) {
                alert('No documents selected');
                return;
            }

            if (!confirmDeletes || confirm(`Delete ${selected.length} selected documents? This cannot be undone!`)) {
                const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
                const collectionName = new URLSearchParams(window.location.search).get('collection') || '';
                
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = window.location.pathname;
                form.innerHTML = `
                    <input type="hidden" name="action" value="bulk_delete_selected">
                    <input type="hidden" name="csrf_token" value="${csrfToken}">
                    <input type="hidden" name="doc_ids" value="${selected.join(',')}">
                    <input type="hidden" name="collection" value="${collectionName}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function bulkExport() {
            const selected = Array.from(document.querySelectorAll('.doc-checkbox:checked'));
            if (selected.length === 0) return;

            const documents = selected.map(cb => {
                const row = cb.closest('tr') || cb.closest('.document-card');
                return JSON.parse(row.dataset.json);
            });

            const dataStr = JSON.stringify(documents, null, 2);
            const dataBlob = new Blob([dataStr], { type: 'application/json' });
            const url = URL.createObjectURL(dataBlob);
            const link = document.createElement('a');
            link.href = url;
            link.download = '<?php echo $collectionName; ?>_bulk_export_' + new Date().toISOString().slice(0, 10) + '.json';
            link.click();
            URL.revokeObjectURL(url);
        }

        function bulkUpdate() {
            const selected = Array.from(document.querySelectorAll('.doc-checkbox:checked')).map(cb => cb.value);
            if (selected.length === 0) {
                alert('No documents selected');
                return;
            }

            const updateJson = prompt(`Update ${selected.length} documents with JSON (e.g., {"status": "updated"}):`);
            if (!updateJson) return;

            try {
                JSON.parse(updateJson);
                const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
                const collectionName = new URLSearchParams(window.location.search).get('collection') || '';
                
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = window.location.pathname;
                form.innerHTML = `
                    <input type="hidden" name="action" value="bulk_update_selected">
                    <input type="hidden" name="csrf_token" value="${csrfToken}">
                    <input type="hidden" name="doc_ids" value="${selected.join(',')}">
                    <input type="hidden" name="update_data" value="${updateJson.replace(/"/g, '&quot;')}">
                    <input type="hidden" name="collection" value="${collectionName}">
                `;
                document.body.appendChild(form);
                form.submit();
            } catch (e) {
                alert('Invalid JSON: ' + e.message);
            }
        }

        function duplicateDoc(docId) {
            if (confirm('Duplicate this document?')) {
                const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
                const collectionName = new URLSearchParams(window.location.search).get('collection') || '';
                
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = window.location.pathname;
                form.innerHTML = `
                    <input type="hidden" name="action" value="duplicate">
                    <input type="hidden" name="csrf_token" value="${csrfToken}">
                    <input type="hidden" name="doc_id" value="${docId}">
                    <input type="hidden" name="collection" value="${collectionName}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteDoc(docId) {
            if (!confirmDeletes || confirm('Delete this document? This cannot be undone!')) {
                const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
                const collectionName = new URLSearchParams(window.location.search).get('collection') || '';
                
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = window.location.pathname;
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="csrf_token" value="${csrfToken}">
                    <input type="hidden" name="doc_id" value="${docId}">
                    <input type="hidden" name="collection" value="${collectionName}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function exportSingle(docId) {
            const row = document.querySelector(`tr[data-doc-id="${docId}"]`) || document.querySelector(`.document-card[data-doc-id="${docId}"]`);
            if (!row) return;

            const docJson = row.dataset.json;
            if (!docJson) return;

            const dataBlob = new Blob([JSON.stringify(JSON.parse(docJson), null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(dataBlob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'document_' + docId.slice(-8) + '.json';
            link.click();
            URL.revokeObjectURL(url);
        }

        function exportVisible() {
            const rows = document.querySelectorAll('tr[data-json], .document-card[data-json]');
            const documents = Array.from(rows).map(row => JSON.parse(row.dataset.json));

            const dataStr = JSON.stringify(documents, null, 2);
            const dataBlob = new Blob([dataStr], { type: 'application/json' });
            const url = URL.createObjectURL(dataBlob);
            const link = document.createElement('a');
            link.href = url;
            link.download = '<?php echo $collectionName; ?>_page_<?php echo $page; ?>_export.json';
            link.click();
            URL.revokeObjectURL(url);
        }

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('Copied to clipboard!');
            }).catch(err => {
                console.error('Failed to copy:', err);
            });
        }

        // Auto-refresh functionality
        let autoRefreshInterval = null;
        let autoRefreshEnabled = false;

        function toggleAutoRefresh() {
            const btn = document.getElementById('autoRefreshBtn');
            const status = document.getElementById('autoRefreshStatus');

            autoRefreshEnabled = !autoRefreshEnabled;

            if (autoRefreshEnabled) {
                autoRefreshInterval = setInterval(() => {
                    window.location.reload();
                }, refreshIntervalSeconds * 1000);
                btn.textContent = '⏸️ Stop Auto-Refresh';
                btn.style.background = '#dc3545';
                status.style.display = 'flex';
            } else {
                if (autoRefreshInterval) {
                    clearInterval(autoRefreshInterval);
                    autoRefreshInterval = null;
                }
                btn.textContent = '▶️ Auto-Refresh';
                btn.style.background = '#28a745';
                status.style.display = 'none';
            }
        }

        document.getElementById('refreshInterval')?.textContent = refreshIntervalSeconds;
        if (autoRefreshDefault) {
            toggleAutoRefresh();
        }

        // Enter key to search
        document.getElementById('searchInput')?.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                performSearch();
            }
        });

        document.getElementById('jsonFilter')?.addEventListener('keypress', function (e) {
            if (e.key === 'Enter' && e.ctrlKey) {
                performSearch();
            }
        });

        document.getElementById('jumpPageInput')?.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                jumpToPage(this.value);
            }
        });

        // View and Edit Document Functions
        function viewDocument(docId, event) {
            console.log('viewDocument function is defined and called');
            console.log('viewDocument called with docId:', docId);
            if (event) event.preventDefault();

            // Find the document data
            const row = document.querySelector(`tr[data-doc-id="${docId}"]`) || document.querySelector(`.document-card[data-doc-id="${docId}"]`);
            console.log('Found row:', row);
            if (!row) {
                alert('Document not found');
                return;
            }

            const docJson = row.dataset.json;
            if (!docJson) {
                alert('Document data not available');
                return;
            }

            try {
                const doc = JSON.parse(docJson);
                const formatted = JSON.stringify(doc, null, 2);

                // Create modal
                const modal = document.createElement('div');
                modal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10000; display: flex; align-items: center; justify-content: center; padding: 20px;';
                modal.onclick = function (e) {
                    if (e.target === modal) modal.remove();
                };

                const content = document.createElement('div');
                content.style.cssText = 'background: white; border-radius: 12px; padding: 30px; max-width: 800px; max-height: 80vh; overflow: auto; box-shadow: 0 10px 40px rgba(0,0,0,0.3);';
                content.innerHTML = `
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="margin: 0; color: #333;">📄 View Document</h2>
                        <button onclick="this.closest('div[style*=fixed]').remove()" style="background: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 14px;">✖️ Close</button>
                    </div>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                        <strong style="color: #495057;">Document ID:</strong>
                        <code style="background: white; padding: 4px 8px; border-radius: 4px; margin-left: 8px;">${docId}</code>
                        <button onclick="copyToClipboard('${docId}')" style="background: #6c757d; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; margin-left: 8px; font-size: 11px;">📋 Copy</button>
                    </div>
                    <pre style="background: #1e1e1e; color: #d4d4d4; padding: 20px; border-radius: 8px; overflow-x: auto; max-height: 50vh; font-family: 'Courier New', monospace; font-size: 13px; line-height: 1.5;"><code>${escapeHtml(formatted)}</code></pre>
                    <div style="margin-top: 20px; display: flex; gap: 10px;">
                        <button onclick="editDocument('${docId}', event)" style="background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 14px;">✏️ Edit Document</button>
                        <button onclick="exportSingle('${docId}')" style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 14px;">💾 Export JSON</button>
                        <button onclick="this.closest('div[style*=fixed]').remove()" style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 14px;">Cancel</button>
                    </div>
                `;

                modal.appendChild(content);
                document.body.appendChild(modal);
            } catch (e) {
                alert('Error parsing document: ' + e.message);
            }
        }

        function editDocument(docId, event) {
            if (event) event.preventDefault();

            // Find the document data
            const row = document.querySelector(`tr[data-doc-id="${docId}"]`) || document.querySelector(`.document-card[data-doc-id="${docId}"]`);
            if (!row) {
                alert('Document not found');
                return;
            }

            const docJson = row.dataset.json;
            if (!docJson) {
                alert('Document data not available');
                return;
            }

            try {
                const doc = JSON.parse(docJson);
                const formatted = JSON.stringify(doc, null, 2);
                
                // Get CSRF token from page
                const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
                const collectionName = new URLSearchParams(window.location.search).get('collection') || '';

                // Create modal
                const modal = document.createElement('div');
                modal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10000; display: flex; align-items: center; justify-content: center; padding: 20px;';

                const content = document.createElement('div');
                content.style.cssText = 'background: white; border-radius: 12px; padding: 30px; max-width: 900px; max-height: 85vh; overflow: auto; box-shadow: 0 10px 40px rgba(0,0,0,0.3); width: 100%;';
                content.innerHTML = `
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="margin: 0; color: #333;">✏️ Edit Document</h2>
                        <button onclick="this.closest('div[style*=fixed]').remove()" style="background: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 14px;">✖️ Close</button>
                    </div>
                    <div style="background: #fff3cd; padding: 12px; border-radius: 6px; margin-bottom: 15px; border-left: 4px solid #ffc107;">
                        <strong style="color: #856404;">⚠️ Warning:</strong> <span style="color: #856404;">Edit the JSON carefully. Invalid JSON will not be saved.</span>
                    </div>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                        <strong style="color: #495057;">Document ID:</strong>
                        <code style="background: white; padding: 4px 8px; border-radius: 4px; margin-left: 8px;">${docId}</code>
                    </div>
                    <form method="POST" action="${window.location.pathname}" onsubmit="return validateEditForm(this)">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="csrf_token" value="${csrfToken}">
                        <input type="hidden" name="doc_id" value="${docId}">
                        <input type="hidden" name="collection" value="${collectionName}">
                        <textarea name="json_data" id="editDocData" required style="width: 100%; min-height: 400px; padding: 15px; border: 2px solid #dee2e6; border-radius: 8px; font-family: 'Courier New', monospace; font-size: 13px; line-height: 1.5; background: #1e1e1e; color: #d4d4d4;">${escapeHtml(formatted)}</textarea>
                        <div style="margin-top: 20px; display: flex; gap: 10px;">
                            <button type="submit" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600;">💾 Save Changes</button>
                            <button type="button" onclick="validateJSON()" style="background: #17a2b8; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 14px;">✓ Validate JSON</button>
                            <button type="button" onclick="this.closest('div[style*=fixed]').remove()" style="background: #6c757d; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 14px;">Cancel</button>
                        </div>
                    </form>
                `;

                modal.appendChild(content);
                document.body.appendChild(modal);

                // Close on background click
                modal.onclick = function (e) {
                    if (e.target === modal) {
                        if (confirm('Close without saving changes?')) {
                            modal.remove();
                        }
                    }
                };
            } catch (e) {
                alert('Error parsing document: ' + e.message);
            }
        }

        function validateEditForm(form) {
            const jsonText = form.querySelector('#editDocData').value;
            try {
                JSON.parse(jsonText);
                return true;
            } catch (e) {
                alert('Invalid JSON: ' + e.message);
                return false;
            }
        }

        function validateJSON() {
            const jsonText = document.getElementById('editDocData').value;
            try {
                const parsed = JSON.parse(jsonText);
                alert('✓ Valid JSON! Document has ' + Object.keys(parsed).length + ' fields.');
            } catch (e) {
                alert('✗ Invalid JSON:\n\n' + e.message);
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Ensure inline handlers can access these functions
        window.viewDocument = viewDocument;
        window.editDocument = editDocument;

        // JSON Import Modal
        function openJsonImportModal() {
            const modal = document.createElement('div');
            modal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10000; display: flex; align-items: center; justify-content: center; padding: 20px;';

            const content = document.createElement('div');
            content.style.cssText = 'background: white; border-radius: 12px; padding: 30px; max-width: 900px; max-height: 85vh; overflow: auto; box-shadow: 0 10px 40px rgba(0,0,0,0.3); width: 100%;';
            
            // Get CSRF token from page
            const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
            
            content.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 style="margin: 0; color: #333;">📥 Import JSON Data</h2>
                    <button onclick="this.closest('div[style*=fixed]').remove()" style="background: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 14px;">✖️ Close</button>
                </div>
                
                <div style="background: #e3f2fd; padding: 15px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #2196f3;">
                    <div style="color: #1565c0; font-size: 14px; margin-bottom: 8px;">
                        <strong>💡 Supported Formats:</strong>
                    </div>
                    <ul style="margin: 0; padding-left: 20px; color: #1976d2; font-size: 13px;">
                        <li>Single document: <code style="background: white; padding: 2px 6px; border-radius: 3px;">{"name": "John", "age": 30}</code></li>
                        <li>Array of documents: <code style="background: white; padding: 2px 6px; border-radius: 3px;">[{...}, {...}]</code></li>
                        <li>MongoDB export format with _id fields</li>
                    </ul>
                </div>
                
                <form method="POST" id="jsonImportForm" onsubmit="return validateImportJson(event)">
                    <input type="hidden" name="action" value="import_json_direct">
                    <input type="hidden" name="csrf_token" value="${csrfToken}">
                    
                    <div style="margin-bottom: 15px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 8px;">Paste JSON Data:</label>
                        <textarea name="json_data" id="importJsonData" required placeholder='Paste your JSON here...
Example:
[
  {"name": "Alice", "email": "alice@example.com"},
  {"name": "Bob", "email": "bob@example.com"}
]' style="width: 100%; min-height: 400px; padding: 15px; border: 2px solid #dee2e6; border-radius: 8px; font-family: 'Courier New', monospace; font-size: 13px; line-height: 1.5; background: #1e1e1e; color: #d4d4d4;"></textarea>
                    </div>
                    
                    <div id="jsonPreview" style="display: none; background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #28a745;">
                        <h4 style="margin: 0 0 10px 0; color: #155724;">✓ Preview:</h4>
                        <div id="jsonPreviewContent" style="font-size: 13px; color: #155724;"></div>
                    </div>
                    
                    <div style="display: flex; gap: 10px;">
                        <button type="button" onclick="previewImportJson()" style="background: #17a2b8; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600;">
                            👁️ Preview & Validate
                        </button>
                        <button type="submit" style="background: linear-gradient(135deg, #4caf50 0%, #66bb6a 100%); color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600; flex: 1;">
                            ⬆️ Import Documents
                        </button>
                        <button type="button" onclick="this.closest('div[style*=fixed]').remove()" style="background: #6c757d; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 14px;">
                            Cancel
                        </button>
                    </div>
                </form>
            `;

            modal.appendChild(content);
            document.body.appendChild(modal);

            // Close on background click
            modal.onclick = function (e) {
                if (e.target === modal) {
                    if (confirm('Close without importing?')) {
                        modal.remove();
                    }
                }
            };
        }

        function previewImportJson() {
            const jsonText = document.getElementById('importJsonData').value;
            const preview = document.getElementById('jsonPreview');
            const previewContent = document.getElementById('jsonPreviewContent');

            try {
                const parsed = JSON.parse(jsonText);
                let docs = Array.isArray(parsed) ? parsed : [parsed];

                // Validate all documents
                let validCount = 0;
                let totalFields = 0;

                for (let doc of docs) {
                    if (typeof doc === 'object' && doc !== null) {
                        validCount++;
                        totalFields += Object.keys(doc).length;
                    }
                }

                if (validCount === 0) {
                    throw new Error('No valid documents found');
                }

                const avgFields = Math.round(totalFields / validCount);

                previewContent.innerHTML = `
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 10px;">
                        <div style="background: white; padding: 10px; border-radius: 6px;">
                            <div style="font-size: 24px; font-weight: bold; color: #4caf50;">${validCount}</div>
                            <div style="font-size: 12px;">Documents</div>
                        </div>
                        <div style="background: white; padding: 10px; border-radius: 6px;">
                            <div style="font-size: 24px; font-weight: bold; color: #2196f3;">${avgFields}</div>
                            <div style="font-size: 12px;">Avg. Fields</div>
                        </div>
                        <div style="background: white; padding: 10px; border-radius: 6px;">
                            <div style="font-size: 24px; font-weight: bold; color: #ff9800;">${totalFields}</div>
                            <div style="font-size: 12px;">Total Fields</div>
                        </div>
                    </div>
                    <div style="font-size: 12px;">
                        <strong>Sample fields:</strong> ${Object.keys(docs[0]).slice(0, 5).join(', ')}${Object.keys(docs[0]).length > 5 ? '...' : ''}
                    </div>
                `;

                preview.style.display = 'block';

            } catch (e) {
                preview.style.display = 'block';
                preview.style.borderLeftColor = '#dc3545';
                preview.style.background = '#f8d7da';
                previewContent.innerHTML = `
                    <h4 style="margin: 0 0 10px 0; color: #721c24;">✗ Invalid JSON:</h4>
                    <div style="color: #721c24; font-family: monospace; font-size: 12px;">${escapeHtml(e.message)}</div>
                `;
            }
        }

        function validateImportJson(event) {
            const jsonText = document.getElementById('importJsonData').value;

            try {
                const parsed = JSON.parse(jsonText);
                let docs = Array.isArray(parsed) ? parsed : [parsed];

                // Validate structure
                for (let doc of docs) {
                    if (typeof doc !== 'object' || doc === null) {
                        throw new Error('Each document must be a valid object');
                    }
                }

                return confirm(`Import ${docs.length} document(s) into the collection?`);

            } catch (e) {
                alert('Invalid JSON:\n\n' + e.message);
                event.preventDefault();
                return false;
            }
        }

        // Tab Switching Function
        function switchTab(tabName, buttonElement) {
            console.log('switchTab called with tabName: ' + tabName);
            
            // Hide all tab content by removing active class
            const allTabs = document.querySelectorAll('.tab-content');
            console.log('Hiding ' + allTabs.length + ' tabs');
            allTabs.forEach(tab => {
                tab.classList.remove('active');
            });

            // Show selected tab by adding active class
            const selectedTab = document.getElementById(tabName);
            console.log('Selected tab element: ', selectedTab);
            if (selectedTab) {
                selectedTab.classList.add('active');
                console.log('Added active class to tab: ' + tabName);
                try {
                    selectedTab.scrollIntoView({ behavior: 'smooth', block: 'start' });
                } catch (e) {
                    console.log('Scroll failed: ' + e);
                }
            } else {
                console.error('Tab not found: ' + tabName);
            }

            // Update button styling
            const allButtons = document.querySelectorAll('.tab-btn');
            allButtons.forEach(btn => {
                btn.classList.remove('active');
            });

            // Style active button
            if (buttonElement) {
                buttonElement.classList.add('active');
                console.log('Added active class to button');
            }

            // Save active tab to localStorage
            try {
                localStorage.setItem('activeTab', tabName);
            } catch (e) {
                console.log('localStorage save failed: ' + e);
            }
        }

        function switchCollection(collectionName) {
            window.location.href = window.location.pathname + '?collection=' + encodeURIComponent(collectionName);
        }

        function toggleTheme() {
            const html = document.documentElement;
            const currentTheme = html.getAttribute('data-theme') || 'light';
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
        }

        function loadTemplate(templateJson) {
            const textarea = document.querySelector('textarea[name="json_data"]');
            if (textarea) {
                try {
                    const template = JSON.parse(templateJson);
                    textarea.value = JSON.stringify(template, null, 2);
                    switchTab('add', document.querySelectorAll('.tab-btn')[3]);
                } catch (e) {
                    alert('Error loading template: ' + e.message);
                }
            }
        }

        // Initialize first tab on page load
        document.addEventListener('DOMContentLoaded', function () {
            console.log('DOMContentLoaded event fired');
            
            // Ensure all tabs start as hidden except the first one
            const allTabs = document.querySelectorAll('.tab-content');
            console.log('Found ' + allTabs.length + ' tab-content elements');
            
            allTabs.forEach((tab, index) => {
                tab.classList.remove('active');
            });

            // Get active tab preference from localStorage
            let activeTabName = 'browse'; // default
            try {
                const savedTab = localStorage.getItem('activeTab');
                if (savedTab && document.getElementById(savedTab)) {
                    activeTabName = savedTab;
                }
            } catch (e) {
                console.log('localStorage not available');
            }

            console.log('Activating tab: ' + activeTabName);

            // Find the corresponding button and activate the tab
            const buttons = document.querySelectorAll('.tab-btn');
            console.log('Found ' + buttons.length + ' tab buttons');
            
            let activeButton = null;
            buttons.forEach(btn => {
                btn.classList.remove('active');
                if (btn.getAttribute('data-tab') === activeTabName) {
                    activeButton = btn;
                }
            });

            // Activate the tab
            switchTab(activeTabName, activeButton);

            // Restore theme preference
            try {
                const savedTheme = localStorage.getItem('theme');
                if (savedTheme) {
                    document.documentElement.setAttribute('data-theme', savedTheme);
                }
            } catch (e) {
                console.log('Theme restore failed');
            }

            console.log('Tab initialization complete');
        });
    </script>
    <?php include 'templates/footer.php'; ?>
