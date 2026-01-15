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

use MongoDB\Client;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

// Load security functions
require_once 'config/security.php';

// Load authentication functions
require_once 'config/auth.php';

// Load database configuration first (needed for authentication)
require_once 'config/database.php';

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
            header('Location: ' . $_SERVER['PHP_SELF']);
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
    }
    // Handle logout
    elseif ($authAction === 'logout') {
        logoutUser();
        $_SESSION['auth_message'] = 'You have been logged out.';
        $_SESSION['auth_success'] = true;
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
    unset($_SESSION['mongo_connection']);
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

        header('Location: ' . $_SERVER['PHP_SELF']);
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
    
    if (!$bypassCsrf) {
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

// Load collection statistics
include 'includes/statistics.php';

// Load backup and audit logging utilities
include 'includes/backup.php';

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
    
    // Limit session history to last 50 queries
    if (count($_SESSION['query_history']) >= 50) {
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
                'created_at' => new MongoDB\BSON\UTCDateTime(time() * 1000)
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
function getQueryHistory($limit = 10) {
    global $database;
    
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
    auditLog('query_history_cleared', []);
}

// Handle Settings and Security tab form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Handle Display Settings
    if ($action === 'save_display_settings') {
        $_SESSION['settings'] = $_SESSION['settings'] ?? [];
        $_SESSION['settings']['items_per_page'] = (int) ($_POST['items_per_page'] ?? 50);
        $_SESSION['settings']['date_format'] = sanitizeInput($_POST['date_format'] ?? 'Y-m-d H:i:s');
        $_SESSION['settings']['theme'] = sanitizeInput($_POST['theme'] ?? 'light');
        $_SESSION['settings']['syntax_highlighting'] = isset($_POST['syntax_highlighting']);
        $_SESSION['settings']['pretty_print'] = isset($_POST['pretty_print']);
        $_SESSION['settings']['show_objectid_as_string'] = isset($_POST['show_objectid_as_string']);
        $_SESSION['settings']['collapsible_json'] = isset($_POST['collapsible_json']);
        $_SESSION['settings']['zebra_stripes'] = isset($_POST['zebra_stripes']);
        $_SESSION['settings']['row_hover'] = isset($_POST['row_hover']);
        $_SESSION['settings']['fixed_header'] = isset($_POST['fixed_header']);
        $_SESSION['settings']['compact_mode'] = isset($_POST['compact_mode']);
        
        $message = '✅ Display settings saved successfully';
        $messageType = 'success';
        auditLog('display_settings_saved', ['items_per_page' => $_SESSION['settings']['items_per_page']]);
    }
    
    // Handle Performance Settings
    elseif ($action === 'save_performance_settings') {
        $_SESSION['settings'] = $_SESSION['settings'] ?? [];
        $_SESSION['settings']['query_timeout'] = max(5, min(300, (int) ($_POST['query_timeout'] ?? 30)));
        $_SESSION['settings']['max_results'] = max(100, min(10000, (int) ($_POST['max_results'] ?? 1000)));
        $_SESSION['settings']['memory_limit'] = max(128, min(2048, (int) ($_POST['memory_limit'] ?? 256)));
        $_SESSION['settings']['cache_ttl'] = max(1, min(1440, (int) ($_POST['cache_ttl'] ?? 15)));
        $_SESSION['settings']['query_cache'] = isset($_POST['query_cache']);
        $_SESSION['settings']['auto_indexes'] = isset($_POST['auto_indexes']);
        $_SESSION['settings']['schema_cache'] = isset($_POST['schema_cache']);
        $_SESSION['settings']['lazy_load'] = isset($_POST['lazy_load']);
        
        $message = '✅ Performance settings saved successfully';
        $messageType = 'success';
        auditLog('performance_settings_saved', ['query_timeout' => $_SESSION['settings']['query_timeout']]);
    }
    
    // Handle Security Settings
    elseif ($action === 'save_security_settings') {
        $_SESSION['settings'] = $_SESSION['settings'] ?? [];
        $_SESSION['settings']['csrf_token_lifetime'] = max(10, min(1440, (int) ($_POST['csrf_token_lifetime'] ?? 60)));
        $_SESSION['settings']['rate_limit_requests'] = max(10, min(1000, (int) ($_POST['rate_limit_requests'] ?? 30)));
        $_SESSION['settings']['rate_limit_lockout'] = max(30, min(3600, (int) ($_POST['rate_limit_lockout'] ?? 60)));
        $_SESSION['settings']['log_all_actions'] = isset($_POST['log_all_actions']);
        $_SESSION['settings']['log_failed_logins'] = isset($_POST['log_failed_logins']);
        $_SESSION['settings']['log_security_events'] = isset($_POST['log_security_events']);
        
        $message = '✅ Security settings saved successfully';
        $messageType = 'success';
        auditLog('security_settings_saved', ['rate_limit_requests' => $_SESSION['settings']['rate_limit_requests']]);
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
        } elseif ($_FILES['settings_file']['type'] !== 'application/json' && pathinfo($_FILES['settings_file']['name'], PATHINFO_EXTENSION) !== 'json') {
            $message = '❌ Only JSON files are allowed';
            $messageType = 'error';
        } else {
            $fileContent = file_get_contents($_FILES['settings_file']['tmp_name']);
            $importedSettings = json_decode($fileContent, true);
            
            if ($importedSettings === null) {
                $message = '❌ Invalid JSON format in settings file';
                $messageType = 'error';
            } else {
                $_SESSION['settings'] = array_merge($_SESSION['settings'] ?? [], $importedSettings);
                $message = '✅ Settings imported successfully';
                $messageType = 'success';
                auditLog('settings_imported', ['settings_count' => count($importedSettings)]);
            }
        }
    }
    
    // Handle Cache Clear
    elseif ($action === 'clear_cache') {
        $_SESSION['cache'] = [];
        $message = '✅ Application cache cleared successfully';
        $messageType = 'success';
        auditLog('cache_cleared', []);
    }
    
    // Handle Settings Reset
    elseif ($action === 'reset_settings') {
        $_SESSION['settings'] = [
            'items_per_page' => 50,
            'date_format' => 'Y-m-d H:i:s',
            'theme' => 'light',
            'syntax_highlighting' => true,
            'pretty_print' => true,
            'query_timeout' => 30,
            'max_results' => 1000,
            'query_cache' => true,
            'auto_indexes' => true
        ];
        $message = '✅ All settings reset to defaults';
        $messageType = 'success';
        auditLog('settings_reset', []);
    }
    
    // Handle Clear Logs
    elseif ($action === 'clear_logs') {
        $logFile = __DIR__ . '/logs/security.log';
        if (file_exists($logFile)) {
            file_put_contents($logFile, '');
        }
        $message = '✅ Security logs cleared successfully';
        $messageType = 'success';
        auditLog('logs_cleared', []);
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

            $sortField = sanitizeInput($_POST['sort'] ?? '_id');
            $sortOrder = ($_POST['sort_order'] ?? 'desc') === 'asc' ? 1 : -1;
            $limit = (int) ($_POST['limit'] ?? 100);
            if ($limit < 1) {
                $limit = 1;
            }
            if ($limit > 5000) {
                $limit = 5000;
            }

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

                $findOptions = ['limit' => $limit, 'sort' => [$sortField => $sortOrder]];
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

                $findOptions = ['limit' => $limit, 'sort' => [$sortField => $sortOrder]];
                if ($projection) {
                    $findOptions['projection'] = $projection;
                }

                $queryResults = $collection->find($mongoQuery, $findOptions)->toArray();
            }

            $baseFile = 'mongo_export_' . $collectionName . '_' . date('Ymd_His');

            if ($action === 'export_query_json') {
                header('Content-Type: application/json; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $baseFile . '.json"');
                echo json_encode($queryResults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                exit;
            }

            // CSV export
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $baseFile . '.csv"');

            $out = fopen('php://output', 'w');
            if ($out === false) {
                throw new Exception('Unable to open output stream');
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

            fputcsv($out, $columns);

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
                fputcsv($out, $row);
            }

            fclose($out);
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

    <?php if ($message): ?>
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
        <button type="button" class="tab-btn" data-tab="dashboard"
            onclick="switchTab('dashboard', this); return false;">🎯 Dashboard</button>
        <button type="button" class="tab-btn" data-tab="browse" onclick="switchTab('browse', this); return false;">📋
            Browse</button>
        <button type="button" class="tab-btn" data-tab="query" onclick="switchTab('query', this); return false;">🔍
            Query Builder</button>
        <button type="button" class="tab-btn" data-tab="add" onclick="switchTab('add', this); return false;">➕ Add
            Document</button>
        <button type="button" class="tab-btn" data-tab="bulk" onclick="switchTab('bulk', this); return false;">📦 Bulk
            Operations</button>
        <button type="button" class="tab-btn" data-tab="tools" onclick="switchTab('tools', this); return false;">🛠️
            Tools</button>
        <button type="button" class="tab-btn" data-tab="advanced"
            onclick="switchTab('advanced', this); return false;">🔬 Advanced</button>
        <button type="button" class="tab-btn" data-tab="performance"
            onclick="switchTab('performance', this); return false;">⚡ Performance</button>
        <button type="button" class="tab-btn" data-tab="stats" onclick="switchTab('stats', this); return false;">📊
            Analytics</button>
        <button type="button" class="tab-btn" data-tab="schema" onclick="switchTab('schema', this); return false;">📐
            Schema</button>
        <button type="button" class="tab-btn" data-tab="security"
            onclick="switchTab('security', this); return false;">🔒 Security</button>
        <button type="button" class="tab-btn" data-tab="settings"
            onclick="switchTab('settings', this); return false;">⚙️ Settings</button>
    </div>

    <!-- Dashboard Tab -->
    <div id="dashboard" class="tab-content">
        <h2 style="margin-bottom: 25px; display: flex; align-items: center; gap: 10px;">
            🎯 Dashboard Overview
            <span style="font-size: 14px; color: #666; font-weight: normal;">Real-time collection insights</span>
        </h2>

        <div
            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div
                style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 12px; box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <p style="opacity: 0.9; font-size: 14px;">Total Documents</p>
                        <p style="font-size: 42px; font-weight: bold; margin: 10px 0;">
                            <?php echo number_format($documentCount); ?>
                        </p>
                        <p style="opacity: 0.8; font-size: 12px;">📈 Active Records</p>
                    </div>
                    <div style="font-size: 48px; opacity: 0.2;">📄</div>
                </div>
            </div>

            <div
                style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 25px; border-radius: 12px; box-shadow: 0 8px 20px rgba(240, 147, 251, 0.3);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <p style="opacity: 0.9; font-size: 14px;">Storage Size</p>
                        <p style="font-size: 42px; font-weight: bold; margin: 10px 0;">
                            <?php echo number_format($totalSize / 1024 / 1024, 1); ?> MB
                        </p>
                        <p style="opacity: 0.8; font-size: 12px;">💾 Total Disk Usage</p>
                    </div>
                    <div style="font-size: 48px; opacity: 0.2;">💽</div>
                </div>
            </div>

            <div
                style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 25px; border-radius: 12px; box-shadow: 0 8px 20px rgba(79, 172, 254, 0.3);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <p style="opacity: 0.9; font-size: 14px;">Avg Document Size</p>
                        <p style="font-size: 42px; font-weight: bold; margin: 10px 0;">
                            <?php echo number_format($avgDocSize / 1024, 1); ?> KB
                        </p>
                        <p style="opacity: 0.8; font-size: 12px;">📊 Per Record</p>
                    </div>
                    <div style="font-size: 48px; opacity: 0.2;">📏</div>
                </div>
            </div>

            <div
                style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; padding: 25px; border-radius: 12px; box-shadow: 0 8px 20px rgba(67, 233, 123, 0.3);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <p style="opacity: 0.9; font-size: 14px;">Collections</p>
                        <p style="font-size: 42px; font-weight: bold; margin: 10px 0;">
                            <?php echo count($collectionNames); ?>
                        </p>
                        <p style="opacity: 0.8; font-size: 12px;">📦 In Database</p>
                    </div>
                    <div style="font-size: 48px; opacity: 0.2;">🗂️</div>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <h3 style="margin-bottom: 20px; color: #333; display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 24px;">📈</span> Quick Actions
                </h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                    <button type="button" class="btn"
                        onclick="switchTab('add', document.querySelectorAll('.tab-btn')[3]); return false;"
                        style="background: #28a745; color: white; padding: 15px; justify-content: center; display: flex; align-items: center; gap: 8px;">
                        ➕ Add New
                    </button>
                    <button type="button" class="btn"
                        onclick="switchTab('query', document.querySelectorAll('.tab-btn')[2]); return false;"
                        style="background: #17a2b8; color: white; padding: 15px; justify-content: center; display: flex; align-items: center; gap: 8px;">
                        🔍 Query
                    </button>
                    <button type="button" class="btn"
                        onclick="switchTab('tools', document.querySelectorAll('.tab-btn')[5]); return false;"
                        style="background: #ffc107; color: #333; padding: 15px; justify-content: center; display: flex; align-items: center; gap: 8px;">
                        🛠️ Tools
                    </button>
                    <button type="button" class="btn"
                        onclick="switchTab('schema', document.querySelectorAll('.tab-btn')[8]); return false;"
                        style="background: #6f42c1; color: white; padding: 15px; justify-content: center; display: flex; align-items: center; gap: 8px;">
                        📐 Schema
                    </button>
                </div>
            </div>

            <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <h3 style="margin-bottom: 20px; color: #333; display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 24px;">⏱️</span> Status
                </h3>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <div
                        style="display: flex; justify-content: space-between; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                        <span style="color: #666;">Connection:</span>
                        <span style="color: #28a745; font-weight: 600;">● Active</span>
                    </div>
                    <div
                        style="display: flex; justify-content: space-between; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                        <span style="color: #666;">Last Updated:</span>
                        <span style="font-weight: 600;"><?php echo date('H:i:s'); ?></span>
                    </div>
                    <div
                        style="display: flex; justify-content: space-between; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                        <span style="color: #666;">Database:</span>
                        <span style="font-weight: 600;"><?php echo htmlspecialchars($db); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="margin-bottom: 20px; color: #333; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">📚</span> Collections Overview
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px;">
                <?php foreach ($collectionNames as $collName): ?>
                    <div onclick="switchCollection('<?php echo htmlspecialchars($collName); ?>')"
                        style="padding: 15px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 8px; cursor: pointer; transition: all 0.3s; border: 2px solid <?php echo $collName === $collectionName ? '#667eea' : 'transparent'; ?>; box-shadow: 0 2px 8px rgba(0,0,0,0.05);"
                        onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'"
                        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.05)'">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <span style="font-size: 20px;">📦</span>
                            <?php if ($collName === $collectionName): ?>
                                <span
                                    style="background: #667eea; color: white; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: 600;">ACTIVE</span>
                            <?php endif; ?>
                        </div>
                        <p style="margin-top: 8px; font-weight: 600; color: #333; font-size: 14px;">
                            <?php echo htmlspecialchars($collName); ?>
                        </p>
                        <p style="color: #666; font-size: 12px; margin-top: 4px;">Click to switch</p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Query Builder Tab -->
    <div id="query" class="tab-content">
        <h2 style="margin-bottom: 20px;">🔍 Advanced Query Builder</h2>
        <div
            style="background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%); padding: 20px; border-radius: 12px; margin-bottom: 20px; border-left: 4px solid #667eea;">
            <p style="color: #666; line-height: 1.8;">
                <strong>💡 Tip:</strong> Build complex MongoDB queries visually or write custom JSON queries.
                Supports filtering, sorting, projection, and aggregation pipelines.
            </p>
        </div>

        <div
            style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 20px;">
            <h3 style="margin-bottom: 20px; color: #333;">🎯 Quick Query</h3>
            <form method="POST" id="quickQueryForm"
                style="display: grid; gap: 15px;">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #555;">Field
                            Name:</label>
                        <input type="text" name="query_field" placeholder="e.g., email, status, name"
                            style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #555;">Field
                            Value:</label>
                        <input type="text" name="query_value" placeholder="Search value"
                            style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px;">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                    <div>
                        <label
                            style="display: block; font-weight: 600; margin-bottom: 8px; color: #555;">Operator:</label>
                        <select name="query_op"
                            style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px;">
                            <option value="equals">Equals (=)</option>
                            <option value="contains">Contains</option>
                            <option value="starts">Starts With</option>
                            <option value="ends">Ends With</option>
                            <option value="gt">Greater Than (&gt;)</option>
                            <option value="lt">Less Than (&lt;)</option>
                            <option value="gte">Greater or Equal (&ge;)</option>
                            <option value="lte">Less or Equal (&le;)</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #555;">Sort
                            By:</label>
                        <select name="sort"
                            style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px;">
                            <option value="_id">_id</option>
                            <option value="created_at">Created Date</option>
                            <option value="updated_at">Updated Date</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #555;">Limit:</label>
                        <input type="number" name="limit" value="50" min="1" max="1000"
                            style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #555;">Value
                            Type:</label>
                        <select name="value_type"
                            style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px;">
                            <option value="string" selected>String</option>
                            <option value="number">Number</option>
                            <option value="bool">Boolean</option>
                            <option value="null">Null</option>
                            <option value="objectid">ObjectId</option>
                            <option value="date">Date</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #555;">Sort
                            Order:</label>
                        <select name="sort_order"
                            style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px;">
                            <option value="desc" selected>Descending</option>
                            <option value="asc">Ascending</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #555;">Projection
                            (fields):</label>
                        <input type="text" name="projection" placeholder="e.g., email,status,name"
                            style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px;">
                    </div>
                </div>
                <button type="button" class="btn" onclick="executeQuickQuery(); return false;"
                    style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px; font-size: 16px; width: 100%;">
                    🔍 Execute Query
                </button>
            </form>
        </div>

        <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="margin-bottom: 20px; color: #333;">📝 Custom JSON Query</h3>
            <p style="color: #666; margin-bottom: 15px; font-size: 14px;">Write a MongoDB query in JSON format (e.g.,
                <code>{"status": "active", "age": {"$gt": 18}}</code>)
            </p>
            <form method="POST" id="customQueryForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div>
                        <label
                            style="display: block; font-weight: 600; margin-bottom: 6px; color: #555; font-size: 13px;">Sort
                            By:</label>
                        <select name="sort"
                            style="width: 100%; padding: 10px 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 13px;">
                            <option value="_id">_id</option>
                            <option value="created_at">Created Date</option>
                            <option value="updated_at">Updated Date</option>
                        </select>
                    </div>
                    <div>
                        <label
                            style="display: block; font-weight: 600; margin-bottom: 6px; color: #555; font-size: 13px;">Sort
                            Order:</label>
                        <select name="sort_order"
                            style="width: 100%; padding: 10px 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 13px;">
                            <option value="desc" selected>Descending</option>
                            <option value="asc">Ascending</option>
                        </select>
                    </div>
                    <div>
                        <label
                            style="display: block; font-weight: 600; margin-bottom: 6px; color: #555; font-size: 13px;">Limit:</label>
                        <input type="number" name="limit" value="100" min="1" max="5000"
                            style="width: 100%; padding: 10px 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 13px;">
                    </div>
                    <div>
                        <label
                            style="display: block; font-weight: 600; margin-bottom: 6px; color: #555; font-size: 13px;">Projection:</label>
                        <input type="text" name="projection" placeholder="email,status,name"
                            style="width: 100%; padding: 10px 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 13px;">
                    </div>
                </div>
                <textarea name="custom_query" placeholder='{"field": "value"}'
                    style="width: 100%; padding: 15px; border: 2px solid #ddd; border-radius: 8px; font-family: 'Courier New', monospace; min-height: 150px; font-size: 13px; background: #f8f9fa;"></textarea>
                <button type="button" class="btn" onclick="executeCustomQuery(); return false;"
                    style="background: #17a2b8; color: white; padding: 12px 24px; margin-top: 15px;">
                    ⚡ Run Custom Query
                </button>
            </form>
        </div>

        <?php if (isset($_POST['action']) && ($_POST['action'] === 'execute_query' || $_POST['action'] === 'execute_custom_query')): ?>
            <div id="query_results" class="query-result"
                style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-top: 20px;">
                <h3 style="margin-bottom: 20px; color: #333;">📊 Query Results</h3>

                <?php
                try {
                    $queryResults = [];

                    if ($_POST['action'] === 'execute_query') {
                        // Quick Query execution
                        $field = sanitizeInput($_POST['query_field'] ?? '');
                        $rawValue = (string) ($_POST['query_value'] ?? '');
                        $operator = $_POST['query_op'] ?? 'equals';
                        $valueType = $_POST['value_type'] ?? 'string';
                        $sortField = sanitizeInput($_POST['sort'] ?? '_id');
                        $sortOrder = ($_POST['sort_order'] ?? 'desc') === 'asc' ? 1 : -1;
                        $limit = (int) ($_POST['limit'] ?? 50);

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

                        // Build MongoDB query
                        $mongoQuery = [];
                        switch ($operator) {
                            case 'equals':
                                $mongoQuery[$field] = $value;
                                break;
                            case 'contains':
                                $mongoQuery[$field] = ['$regex' => $rawValue, '$options' => 'i'];
                                break;
                            case 'starts':
                                $mongoQuery[$field] = ['$regex' => '^' . $rawValue, '$options' => 'i'];
                                break;
                            case 'ends':
                                $mongoQuery[$field] = ['$regex' => $rawValue . '$', '$options' => 'i'];
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
                        }

                        $findOptions = [
                            'sort' => [$sortField => $sortOrder],
                            'limit' => $limit
                        ];
                        if ($projection) {
                            $findOptions['projection'] = $projection;
                        }

                        $queryResults = $collection->find($mongoQuery, $findOptions)->toArray();

                        echo '<p style="color: #666; margin-bottom: 15px;"><strong>Query:</strong> Field: ' . htmlspecialchars($field) . ' | Operator: ' . htmlspecialchars($operator) . ' | Value: ' . htmlspecialchars($rawValue) . ' | Type: ' . htmlspecialchars($valueType) . ' | Sort: ' . htmlspecialchars($sortField) . ' ' . ($sortOrder === 1 ? 'ASC' : 'DESC') . ' | Limit: ' . htmlspecialchars((string) $limit) . '</p>';
                        if ($projectionRaw !== '') {
                            echo '<p style="color: #666; margin-bottom: 15px;"><strong>Projection:</strong> ' . htmlspecialchars($projectionRaw) . '</p>';
                        }
                    } else {
                        // Custom JSON Query execution
                        $customQuery = $_POST['custom_query'] ?? '{}';

                        if (!validateJSON($customQuery)) {
                            throw new Exception('Invalid JSON or dangerous patterns detected');
                        }

                        $query = json_decode($customQuery, true);
                        $sanitizedQuery = sanitizeMongoQuery($query);

                        $sortField = sanitizeInput($_POST['sort'] ?? '_id');
                        $sortOrder = ($_POST['sort_order'] ?? 'desc') === 'asc' ? 1 : -1;
                        $limit = (int) ($_POST['limit'] ?? 100);
                        if ($limit < 1) {
                            $limit = 1;
                        }
                        if ($limit > 5000) {
                            $limit = 5000;
                        }

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

                        $findOptions = ['limit' => $limit, 'sort' => [$sortField => $sortOrder]];
                        if ($projection) {
                            $findOptions['projection'] = $projection;
                        }

                        $queryResults = $collection->find($sanitizedQuery, $findOptions)->toArray();

                        echo '<p style="color: #666; margin-bottom: 15px;"><strong>Custom Query:</strong> Sort: ' . htmlspecialchars($sortField) . ' ' . ($sortOrder === 1 ? 'ASC' : 'DESC') . ' | Limit: ' . htmlspecialchars((string) $limit) . '</p>';
                        if ($projectionRaw !== '') {
                            echo '<p style="color: #666; margin-bottom: 15px;"><strong>Projection:</strong> ' . htmlspecialchars($projectionRaw) . '</p>';
                        }
                        echo '<pre style="background: #f8f9fa; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: 12px; color: #333;">' . htmlspecialchars($customQuery) . '</pre>';
                    }

                    echo '<p style="color: #28a745; font-weight: 600; margin: 15px 0;"> Found ' . count($queryResults) . ' document(s)</p>';

                    // Add to query history
                    $historyEntry = [
                        'type' => $_POST['action'] === 'execute_query' ? 'visual' : 'custom',
                        'query' => $_POST['action'] === 'execute_query' 
                            ? ['field' => $_POST['query_field'] ?? '', 'op' => $_POST['query_op'] ?? '', 'value' => $_POST['query_value'] ?? '']
                            : ['custom' => $_POST['custom_query'] ?? ''],
                        'results_count' => count($queryResults),
                        'status' => 'success'
                    ];
                    addToQueryHistory($historyEntry);

                    // Export buttons
                    if (!empty($queryResults)) {
                        echo '<div style="display:flex; gap:10px; flex-wrap:wrap; margin: 10px 0 0 0;">';
                        echo '<form method="POST" style="display:inline;">';
                        echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCSRFToken()) . '">';
                        echo '<input type="hidden" name="collection" value="' . htmlspecialchars($collectionName) . '">';
                        echo '<input type="hidden" name="action" value="export_query_json">';
                        foreach (['query_field', 'query_value', 'query_op', 'value_type', 'custom_query', 'sort', 'sort_order', 'limit', 'projection'] as $k) {
                            if (isset($_POST[$k])) {
                                echo '<input type="hidden" name="' . htmlspecialchars($k) . '" value="' . htmlspecialchars((string) $_POST[$k]) . '">';
                            }
                        }
                        echo '<button type="submit" class="btn" style="background:#343a40;color:#fff; padding:8px 12px; font-size:12px;">⬇️ Export JSON</button>';
                        echo '</form>';

                        echo '<form method="POST" style="display:inline;">';
                        echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCSRFToken()) . '">';
                        echo '<input type="hidden" name="collection" value="' . htmlspecialchars($collectionName) . '">';
                        echo '<input type="hidden" name="action" value="export_query_csv">';
                        foreach (['query_field', 'query_value', 'query_op', 'value_type', 'custom_query', 'sort', 'sort_order', 'limit', 'projection'] as $k) {
                            if (isset($_POST[$k])) {
                                echo '<input type="hidden" name="' . htmlspecialchars($k) . '" value="' . htmlspecialchars((string) $_POST[$k]) . '">';
                            }
                        }
                        echo '<button type="submit" class="btn" style="background:#198754;color:#fff; padding:8px 12px; font-size:12px;">⬇️ Export CSV</button>';
                        echo '</form>';
                        echo '</div>';
                    }

                    if (!empty($queryResults)) {
                        echo '<table class="data-table" style="margin-top: 20px;">';
                        echo '<thead><tr><th>Document ID</th><th>Data</th><th>Actions</th></tr></thead>';
                        echo '<tbody>';

                        foreach ($queryResults as $doc) {
                            $docId = (string) $doc['_id'];
                            $docJson = json_encode($doc, JSON_PRETTY_PRINT);
                            echo '<tr data-json="' . htmlspecialchars($docJson) . '">';
                            echo '<td style="font-family: monospace; font-size: 12px;">' . htmlspecialchars($docId) . '</td>';
                            echo '<td><pre style="background: #f8f9fa; padding: 10px; border-radius: 6px; max-height: 200px; overflow-y: auto; font-size: 11px;">' . htmlspecialchars(substr($docJson, 0, 500)) . '...</pre></td>';
                            echo '<td>';
                            echo '<button type="button" class="btn" style="background-color: #6c757d; color: white; font-size: 11px; padding: 4px 8px;" onclick="viewDocument(\'' . htmlspecialchars($docId) . '\', event); return false;">View</button> ';
                            echo '<button type="button" class="btn btn-edit" style="font-size: 11px; padding: 4px 8px;" onclick="editDocument(\'' . htmlspecialchars($docId) . '\', event); return false;">Edit</button>';
                            echo '</td>';
                            echo '</tr>';
                        }

                        echo '</tbody></table>';
                    }
                } catch (Exception $e) {
                    echo '<div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 6px; border: 1px solid #f5c6cb;">';
                    echo '❌ Error: ' . htmlspecialchars($e->getMessage());
                    echo '</div>';
                }
                ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Browse Tab -->
    <div id="browse" class="tab-content">
        <!-- Success/Error Messages -->
        <?php if (!empty($message)): ?>
            <div style="background: <?php echo $messageType === 'success' ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo $messageType === 'success' ? '#155724' : '#721c24'; ?>; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid <?php echo $messageType === 'success' ? '#28a745' : '#dc3545'; ?>; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 20px;"><?php echo $messageType === 'success' ? '✅' : '❌'; ?></span>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <div
            style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 8px 24px rgba(102,126,234,0.3);">
            <h2 style="color: white; margin: 0; font-size: 28px; display: flex; align-items: center; gap: 12px;">
                <span style="font-size: 32px;">📋</span> Browse Documents
                <span
                    style="background: rgba(255,255,255,0.2); padding: 6px 14px; border-radius: 20px; font-size: 14px; font-weight: normal;">
                    <?php echo number_format($documentCount); ?> documents
                </span>
            </h2>
            <p style="color: rgba(255,255,255,0.9); margin: 10px 0 0 0; font-size: 14px;">
                View, search, filter, and manage your collection documents
            </p>
        </div>

        <!-- Advanced Search & Filters -->
        <div
            style="background: white; padding: 25px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0; color: #333; display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 20px;">🔍</span> Search & Filters
                </h3>
                <button type="button" class="btn"
                    style="background: #f8f9fa; color: #495057; padding: 8px 16px; font-size: 13px;"
                    onclick="resetFilters()">
                    🔄 Reset All
                </button>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <label
                        style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 13px; color: #495057;">
                        🔎 Text Search
                    </label>
                    <input type="text" id="searchInput" value="<?php echo htmlspecialchars($searchQuery); ?>"
                        placeholder="Search across all fields..."
                        style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; transition: border-color 0.3s;"
                        onfocus="this.style.borderColor='#667eea'" onblur="this.style.borderColor='#e0e0e0'">
                </div>
                <div>
                    <label
                        style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 13px; color: #495057;">
                        📊 Sort Field
                    </label>
                    <select id="sortField"
                        style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; background: white;">
                        <?php
                        $fieldIcons = [
                            '_id' => '📌',
                            'id' => '📌',
                            'created_at' => '📅',
                            'createdAt' => '📅',
                            'created' => '📅',
                            'date' => '📅',
                            'updated_at' => '🔄',
                            'updatedAt' => '🔄',
                            'updated' => '🔄',
                            'modified' => '🔄',
                            'name' => '📝',
                            'title' => '📝',
                            'email' => '📧',
                            'status' => '🏷️',
                            'type' => '📂',
                            'category' => '📂',
                            'price' => '💰',
                            'amount' => '💰',
                            'count' => '🔢',
                            'quantity' => '🔢',
                            'age' => '🎂',
                            'phone' => '📞',
                            'address' => '🏠',
                            'username' => '👤',
                            'user' => '👤'
                        ];

                        foreach ($detectedFields as $field):
                            $icon = '📊';
                            foreach ($fieldIcons as $pattern => $fieldIcon) {
                                if (stripos($field, $pattern) !== false || $field === $pattern) {
                                    $icon = $fieldIcon;
                                    break;
                                }
                            }
                            $selected = ($sortField === $field) ? 'selected' : '';
                            $displayName = str_replace('_', ' ', ucfirst($field));
                            ?>
                            <option value="<?php echo htmlspecialchars($field); ?>" <?php echo $selected; ?>>
                                <?php echo $icon . ' ' . htmlspecialchars($displayName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <div>
                    <label
                        style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 13px; color: #495057;">
                        ⬆️⬇️ Order
                    </label>
                    <select id="sortOrder"
                        style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; background: white;">
                        <option value="-1" <?php echo $sortOrder === '-1' ? 'selected' : ''; ?>>⬇️ Descending</option>
                        <option value="1" <?php echo $sortOrder === '1' ? 'selected' : ''; ?>>⬆️ Ascending</option>
                    </select>
                </div>
            </div>

            <!-- JSON Filter -->
            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 13px; color: #495057;">
                    🎯 Advanced JSON Filter
                </label>
                <div style="position: relative;">
                    <textarea id="jsonFilter" placeholder='{"status": "active"} or {"age": {"$gte": 18}}'
                        style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-family: 'Courier New', monospace; font-size: 13px; min-height: 60px; resize: vertical;"
                        onfocus="this.style.borderColor='#667eea'" onblur="this.style.borderColor='#e0e0e0'"></textarea>
                    <small style="color: #6c757d; font-size: 12px;">MongoDB query syntax supported</small>
                </div>
            </div>

            <!-- Quick Filters -->
            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 13px; color: #495057;">
                    ⚡ Quick Filters
                </label>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <?php
                    // Dynamic quick filters based on detected fields
                    $quickFilters = [];

                    // Date-based filters if date fields exist
                    $dateFields = array_filter($detectedFields, function ($field) {
                        return stripos($field, 'date') !== false ||
                            stripos($field, 'created') !== false ||
                            stripos($field, 'time') !== false ||
                            in_array($field, ['created_at', 'updated_at', 'timestamp']);
                    });

                    if (!empty($dateFields)) {
                        $dateField = reset($dateFields);
                        $quickFilters[] = [
                            'label' => '📅 Today',
                            'style' => 'background: #e3f2fd; color: #1976d2; border: 2px solid #1976d2;',
                            'action' => "applyQuickFilter('today', '" . htmlspecialchars($dateField) . "')"
                        ];
                        $quickFilters[] = [
                            'label' => '📆 Last 7 Days',
                            'style' => 'background: #f3e5f5; color: #7b1fa2; border: 2px solid #7b1fa2;',
                            'action' => "applyQuickFilter('week', '" . htmlspecialchars($dateField) . "')"
                        ];
                        $quickFilters[] = [
                            'label' => '📊 Last 30 Days',
                            'style' => 'background: #e8f5e9; color: #388e3c; border: 2px solid #388e3c;',
                            'action' => "applyQuickFilter('month', '" . htmlspecialchars($dateField) . "')"
                        ];
                    }

                    // Status/type filters if they exist
                    if (in_array('status', $detectedFields)) {
                        $quickFilters[] = [
                            'label' => '✅ Active',
                            'style' => 'background: #e8f5e9; color: #2e7d32; border: 2px solid #4caf50;',
                            'action' => "applyQuickFilter('status_value', 'status', 'active')"
                        ];
                        $quickFilters[] = [
                            'label' => '⏸️ Inactive',
                            'style' => 'background: #fbe9e7; color: #d84315; border: 2px solid #ff5722;',
                            'action' => "applyQuickFilter('status_value', 'status', 'inactive')"
                        ];
                    }

                    // Email field filters
                    if (in_array('email', $detectedFields)) {
                        $quickFilters[] = [
                            'label' => '📧 Has Email',
                            'style' => 'background: #fff3e0; color: #f57c00; border: 2px solid #ff9800;',
                            'action' => "applyQuickFilter('has_field', 'email')"
                        ];
                        $quickFilters[] = [
                            'label' => '❌ No Email',
                            'style' => 'background: #ffebee; color: #c62828; border: 2px solid #f44336;',
                            'action' => "applyQuickFilter('empty_field', 'email')"
                        ];
                    }

                    // Name field filters
                    if (in_array('name', $detectedFields) || in_array('username', $detectedFields)) {
                        $nameField = in_array('name', $detectedFields) ? 'name' : 'username';
                        $quickFilters[] = [
                            'label' => '✓ Has Name',
                            'style' => 'background: #e1f5fe; color: #01579b; border: 2px solid #03a9f4;',
                            'action' => "applyQuickFilter('has_field', '" . $nameField . "')"
                        ];
                    }

                    // Add "All Documents" filter
                    $quickFilters[] = [
                        'label' => '🌐 All Documents',
                        'style' => 'background: #f5f5f5; color: #616161; border: 2px solid #9e9e9e;',
                        'action' => "applyQuickFilter('all')"
                    ];

                    // Render filters
                    foreach ($quickFilters as $filter):
                        ?>
                        <button type="button" class="btn"
                            style="<?php echo $filter['style']; ?> padding: 8px 16px; font-size: 13px;"
                            onclick="<?php echo $filter['action']; ?>">
                            <?php echo $filter['label']; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="button" class="btn"
                    style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 24px; font-size: 14px; font-weight: 600; box-shadow: 0 4px 12px rgba(102,126,234,0.4);"
                    onclick="performSearch()">
                    🔍 Apply Filters
                </button>
                <button type="button" class="btn"
                    style="background: #28a745; color: white; padding: 12px 24px; font-size: 14px;"
                    onclick="window.location.reload()">
                    🔄 Refresh Data
                </button>
                <button type="button" class="btn" id="autoRefreshBtn"
                    style="background: #6c757d; color: white; padding: 12px 24px; font-size: 14px;"
                    onclick="toggleAutoRefresh()">
                    ⏸️ Auto-Refresh
                </button>
                <button type="button" class="btn"
                    style="background: #17a2b8; color: white; padding: 12px 24px; font-size: 14px;"
                    onclick="toggleBulkSelection()">
                    ☑️ Bulk Select
                </button>
                <button type="button" class="btn"
                    style="background: #ffc107; color: #333; padding: 12px 24px; font-size: 14px;"
                    onclick="exportVisible()">
                    💾 Export Visible
                </button>
            </div>
        </div>

        <div id="autoRefreshStatus"
            style="display: none; background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%); color: #0c5460; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; border-left: 4px solid #17a2b8; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 18px;">🔄</span>
            <span>Auto-refresh enabled - Updates every <strong><span id="refreshInterval">30</span>
                    seconds</strong></span>
        </div>

        <!-- Bulk Actions Bar -->
        <div id="bulkActionsBar"
            style="display: none; background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #ffc107;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <span style="font-weight: 600; color: #856404;">
                        <span id="selectedCount">0</span> documents selected
                    </span>
                    <button type="button" class="btn"
                        style="background: #dc3545; color: white; padding: 8px 16px; font-size: 13px;"
                        onclick="bulkDelete()">
                        🗑️ Delete Selected
                    </button>
                    <button type="button" class="btn"
                        style="background: #007bff; color: white; padding: 8px 16px; font-size: 13px;"
                        onclick="bulkExport()">
                        💾 Export Selected
                    </button>
                    <button type="button" class="btn"
                        style="background: #28a745; color: white; padding: 8px 16px; font-size: 13px;"
                        onclick="bulkUpdate()">
                        ✏️ Update Selected
                    </button>
                </div>
                <button type="button" class="btn"
                    style="background: #6c757d; color: white; padding: 8px 16px; font-size: 13px;"
                    onclick="clearSelection()">
                    ✖️ Clear Selection
                </button>
            </div>
        </div>

        <!-- Documents Grid/Table View Toggle -->
        <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <div>
                    <h3 style="margin: 0; color: #333;">
                        Showing <?php echo count($documentsList); ?> of <?php echo number_format($documentCount); ?>
                        documents
                    </h3>
                    <p style="margin: 5px 0 0 0; font-size: 13px; color: #6c757d;">
                        Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                    </p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn" id="viewToggleBtn"
                        style="background: #f8f9fa; color: #495057; padding: 8px 16px; border: 2px solid #dee2e6;"
                        onclick="toggleView()">
                        <span id="viewIcon">📊</span> <span id="viewText">Grid View</span>
                    </button>
                    <select id="perPageSelect"
                        style="padding: 8px 12px; border: 2px solid #dee2e6; border-radius: 6px; background: white;"
                        onchange="changePerPage(this.value)">
                        <option value="10" <?php echo $perPage == 10 ? 'selected' : ''; ?>>10 per page</option>
                        <option value="25" <?php echo $perPage == 25 ? 'selected' : ''; ?>>25 per page</option>
                        <option value="50" <?php echo $perPage == 50 ? 'selected' : ''; ?>>50 per page</option>
                        <option value="100" <?php echo $perPage == 100 ? 'selected' : ''; ?>>100 per page</option>
                    </select>
                </div>
            </div>

            <!-- Table View (Default) -->
            <div id="tableView">
                <table class="data-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);">
                            <th
                                style="padding: 15px; text-align: left; font-weight: 600; color: #333; border-bottom: 2px solid #667eea; width: 40px;">
                                <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)"
                                    style="cursor: pointer; width: 18px; height: 18px; display: none;">
                            </th>
                            <th
                                style="padding: 15px; text-align: left; font-weight: 600; color: #333; border-bottom: 2px solid #667eea;">
                                📌 Document ID
                            </th>
                            <th
                                style="padding: 15px; text-align: left; font-weight: 600; color: #333; border-bottom: 2px solid #667eea;">
                                📄 Document Data
                            </th>
                            <th
                                style="padding: 15px; text-align: center; font-weight: 600; color: #333; border-bottom: 2px solid #667eea; width: 280px;">
                                ⚙️ Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documentsList as $index => $doc): ?>
                            <?php
                            $docArray = json_decode(json_encode($doc), true);
                            $docId = (string) ($doc['_id'] ?? '');
                            $docJson = json_encode($docArray);
                            ?>
                            <tr data-json="<?php echo htmlspecialchars($docJson); ?>"
                                data-doc-id="<?php echo htmlspecialchars((string)$docId); ?>"
                                style="border-bottom: 1px solid #e9ecef; transition: background-color 0.2s;"
                                onmouseover="this.style.backgroundColor='#f8f9fa'"
                                onmouseout="this.style.backgroundColor='white'">
                                <td style="padding: 12px;">
                                    <input type="checkbox" class="doc-checkbox"
                                        value="<?php echo htmlspecialchars($docId); ?>"
                                        style="cursor: pointer; width: 18px; height: 18px; display: none;" onchange="updateBulkBar()">
                                </td>
                                <td style="padding: 12px;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <code
                                            style="background: #e9ecef; padding: 6px 10px; border-radius: 6px; font-size: 12px; color: #495057; font-weight: 600;">
                                                                                <?php echo htmlspecialchars(substr((string) $docId, -8)); ?>
                                                                            </code>
                                        <button type="button" class="btn"
                                            style="background: none; border: none; color: #6c757d; padding: 4px; cursor: pointer; font-size: 16px;"
                                            onclick="copyToClipboard('<?php echo htmlspecialchars($docId); ?>')"
                                            title="Copy full ID">
                                            📋
                                        </button>
                                    </div>
                                </td>
                                <td style="padding: 12px;">
                                    <div style="max-width: 500px;">
                                        <?php
                                        // Show key fields in a nice format
                                        $keyFields = ['name', 'title', 'email', 'status', 'type', 'category'];
                                        $displayFields = [];
                                        foreach ($keyFields as $field) {
                                            if (isset($docArray[$field]) && $docArray[$field] !== '') {
                                                $displayFields[] = '<span style="background: #e3f2fd; color: #1976d2; padding: 4px 10px; border-radius: 12px; font-size: 12px; display: inline-block; margin: 2px;"><strong>' . htmlspecialchars($field) . ':</strong> ' . htmlspecialchars(substr((string) $docArray[$field], 0, 30)) . '</span>';
                                            }
                                        }
                                        if (!empty($displayFields)) {
                                            echo implode(' ', array_slice($displayFields, 0, 3));
                                        } else {
                                            $preview = json_encode($docArray, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                                            echo '<span style="color: #6c757d; font-size: 12px; font-family: monospace;">' . htmlspecialchars(substr($preview, 0, 80)) . '...</span>';
                                        }
                                        ?>
                                        <details style="margin-top: 8px;">
                                            <summary
                                                style="cursor: pointer; color: #667eea; font-size: 12px; font-weight: 600;">
                                                📖 Show Full Document</summary>
                                            <pre
                                                style="background: #f8f9fa; padding: 12px; border-radius: 6px; margin-top: 8px; font-size: 11px; overflow-x: auto; max-height: 200px; overflow-y: auto; border: 1px solid #dee2e6;"><code><?php echo htmlspecialchars(json_encode($docArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?></code></pre>
                                        </details>
                                    </div>
                                </td>
                                <td style="padding: 12px;">
                                    <div style="display: flex; gap: 6px; justify-content: center; flex-wrap: wrap;">
                                        <button type="button" class="btn"
                                            style="background: #6c757d; color: white; padding: 6px 12px; font-size: 12px; border-radius: 6px;"
                                            onclick="viewDocument('<?php echo htmlspecialchars((string)$docId); ?>', event)" title="View Document">
                                            👁️
                                        </button>
                                        <button type="button" class="btn"
                                            style="background: #007bff; color: white; padding: 6px 12px; font-size: 12px; border-radius: 6px;"
                                            onclick="editDocument('<?php echo htmlspecialchars((string)$docId); ?>', event)" title="Edit Document">
                                            ✏️
                                        </button>
                                        <button type="button" class="btn"
                                            style="background: #17a2b8; color: white; padding: 6px 12px; font-size: 12px; border-radius: 6px;"
                                            onclick="duplicateDoc('<?php echo htmlspecialchars((string)$docId); ?>')" title="Duplicate">
                                            📋
                                        </button>
                                        <button type="button" class="btn"
                                            style="background: #28a745; color: white; padding: 6px 12px; font-size: 12px; border-radius: 6px;"
                                            onclick="exportSingle('<?php echo htmlspecialchars((string)$docId); ?>')" title="Export JSON">
                                            💾
                                        </button>
                                        <button type="button" class="btn"
                                            style="background: #dc3545; color: white; padding: 6px 12px; font-size: 12px; border-radius: 6px;"
                                            onclick="deleteDoc('<?php echo htmlspecialchars((string)$docId); ?>')" title="Delete">
                                            🗑️
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Grid View -->
            <div id="gridView" style="display: none;">
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">
                    <?php foreach ($documentsList as $doc): ?>
                        <?php
                        $docArray = json_decode(json_encode($doc), true);
                        $docId = (string) ($doc['_id'] ?? '');
                        $docJson = json_encode($docArray);
                        ?>
                        <div class="document-card" data-doc-id="<?php echo htmlspecialchars((string)$docId); ?>"
                            data-json="<?php echo htmlspecialchars($docJson); ?>"
                            style="background: white; border: 2px solid #e9ecef; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: all 0.3s; position: relative;"
                            onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 8px 24px rgba(0,0,0,0.15)'"
                            onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.08)'">
                            <div style="position: absolute; top: 15px; right: 15px;">
                                <input type="checkbox" class="doc-checkbox" value="<?php echo htmlspecialchars($docId); ?>"
                                    style="cursor: pointer; width: 18px; height: 18px; display: none;" onchange="updateBulkBar()">
                            </div>

                            <div style="margin-bottom: 15px;">
                                <div
                                    style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 8px 12px; border-radius: 8px; display: inline-block; font-size: 11px; font-weight: 600; margin-bottom: 10px;">
                                    📄 DOCUMENT
                                </div>
                                <div style="font-size: 12px; color: #6c757d; font-family: monospace;">
                                    ID: <?php echo htmlspecialchars(substr((string) $docId, -12)); ?>
                                </div>
                            </div>

                            <div style="margin-bottom: 15px;">
                                <?php
                                $keyFields = ['name', 'title', 'email', 'status', 'type'];
                                foreach ($keyFields as $field) {
                                    if (isset($docArray[$field]) && $docArray[$field] !== '') {
                                        $icon = ['name' => '👤', 'title' => '📝', 'email' => '📧', 'status' => '🏷️', 'type' => '📌'][$field] ?? '•';
                                        echo '<div style="margin-bottom: 8px;">';
                                        echo '<span style="color: #6c757d; font-size: 12px; font-weight: 600;">' . $icon . ' ' . ucfirst($field) . ':</span> ';
                                        echo '<span style="color: #333; font-size: 13px;">' . htmlspecialchars(substr((string) $docArray[$field], 0, 40)) . '</span>';
                                        echo '</div>';
                                        break;
                                    }
                                }
                                ?>
                            </div>

                            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                <button type="button" class="btn"
                                    style="background: #6c757d; color: white; padding: 6px 12px; font-size: 11px; flex: 1;"
                                    onclick="viewDocument('<?php echo htmlspecialchars((string)$docId); ?>', event)">
                                    👁️ View
                                </button>
                                <button type="button" class="btn"
                                    style="background: #007bff; color: white; padding: 6px 12px; font-size: 11px; flex: 1;"
                                    onclick="editDocument('<?php echo htmlspecialchars((string)$docId); ?>', event)">
                                    ✏️ Edit
                                </button>
                                <button type="button" class="btn"
                                    style="background: #17a2b8; color: white; padding: 6px 12px; font-size: 11px;"
                                    onclick="duplicateDoc('<?php echo htmlspecialchars((string)$docId); ?>')">
                                    📋
                                </button>
                                <button type="button" class="btn"
                                    style="background: #28a745; color: white; padding: 6px 12px; font-size: 11px;"
                                    onclick="exportSingle('<?php echo htmlspecialchars((string)$docId); ?>')">
                                    💾
                                </button>
                                <button type="button" class="btn"
                                    style="background: #dc3545; color: white; padding: 6px 12px; font-size: 11px;"
                                    onclick="deleteDoc('<?php echo htmlspecialchars((string)$docId); ?>')">
                                    🗑️
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

        <!-- Enhanced Pagination -->
        <?php if ($totalPages > 1): ?>
            <div
                style="background: white; padding: 20px; border-radius: 12px; margin-top: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <div
                    style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <button type="button" class="btn"
                            style="background: #667eea; color: white; padding: 10px 16px; border-radius: 8px; <?php echo $page <= 1 ? 'opacity: 0.5; cursor: not-allowed;' : ''; ?>"
                            onclick="jumpToPage(1)" <?php echo $page <= 1 ? 'disabled' : ''; ?>>
                            ⏮️ First
                        </button>
                        <button type="button" class="btn"
                            style="background: #667eea; color: white; padding: 10px 16px; border-radius: 8px; <?php echo $page <= 1 ? 'opacity: 0.5; cursor: not-allowed;' : ''; ?>"
                            onclick="jumpToPage(<?php echo $page - 1; ?>)" <?php echo $page <= 1 ? 'disabled' : ''; ?>>
                            ◀️ Prev
                        </button>
                    </div>

                    <div style="display: flex; gap: 6px; flex-wrap: wrap; justify-content: center;">
                        <?php
                        $startPage = max(1, $page - 4);
                        $endPage = min($totalPages, $page + 4);

                        if ($startPage > 1): ?>
                            <button type="button" class="btn"
                                style="padding: 10px 14px; background: #f8f9fa; color: #333; border-radius: 8px;"
                                onclick="jumpToPage(1)">1</button>
                            <?php if ($startPage > 2): ?>
                                <span style="padding: 10px; color: #6c757d;">...</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <button type="button" class="btn"
                                style="padding: 10px 14px; background: <?php echo $i === $page ? 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' : '#f8f9fa'; ?>; color: <?php echo $i === $page ? 'white' : '#333'; ?>; border-radius: 8px; font-weight: <?php echo $i === $page ? '700' : '400'; ?>; min-width: 44px; <?php echo $i === $page ? 'box-shadow: 0 4px 12px rgba(102,126,234,0.4);' : ''; ?>"
                                onclick="jumpToPage(<?php echo $i; ?>)">
                                <?php echo $i; ?>
                            </button>
                        <?php endfor; ?>

                        <?php if ($endPage < $totalPages): ?>
                            <?php if ($endPage < $totalPages - 1): ?>
                                <span style="padding: 10px; color: #6c757d;">...</span>
                            <?php endif; ?>
                            <button type="button" class="btn"
                                style="padding: 10px 14px; background: #f8f9fa; color: #333; border-radius: 8px;"
                                onclick="jumpToPage(<?php echo $totalPages; ?>)">
                                <?php echo $totalPages; ?>
                            </button>
                        <?php endif; ?>
                    </div>

                    <div style="display: flex; gap: 8px; align-items: center;">
                        <button type="button" class="btn"
                            style="background: #667eea; color: white; padding: 10px 16px; border-radius: 8px; <?php echo $page >= $totalPages ? 'opacity: 0.5; cursor: not-allowed;' : ''; ?>"
                            onclick="jumpToPage(<?php echo $page + 1; ?>)" <?php echo $page >= $totalPages ? 'disabled' : ''; ?>>
                            Next ▶️
                        </button>
                        <button type="button" class="btn"
                            style="background: #667eea; color: white; padding: 10px 16px; border-radius: 8px; <?php echo $page >= $totalPages ? 'opacity: 0.5; cursor: not-allowed;' : ''; ?>"
                            onclick="jumpToPage(<?php echo $totalPages; ?>)" <?php echo $page >= $totalPages ? 'disabled' : ''; ?>>
                            Last ⏭️
                        </button>
                    </div>
                </div>

                <div style="text-align: center; margin-top: 15px; padding-top: 15px; border-top: 1px solid #e9ecef;">
                    <span style="color: #6c757d; font-size: 13px;">Jump to page:</span>
                    <input type="number" id="jumpPageInput" min="1" max="<?php echo $totalPages; ?>"
                        placeholder="<?php echo $page; ?>"
                        style="width: 80px; padding: 6px; border: 2px solid #dee2e6; border-radius: 6px; margin: 0 8px; text-align: center;">
                    <button type="button" class="btn" style="background: #28a745; color: white; padding: 6px 16px;"
                        onclick="jumpToPage(document.getElementById('jumpPageInput').value)">
                        Go
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div><!-- End of container -->

    <!-- Query History Section -->
    <div id="query_history_section" style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin: 20px 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="color: #333; margin: 0; font-size: 18px;">📜 Query History (Last 10)</h3>
            <a href="?action=clear_query_history" class="btn" style="background: #dc3545; color: white; padding: 8px 16px; text-decoration: none; font-size: 12px;" 
                onclick="return confirm('Clear all query history?');">
                🗑️ Clear History
            </a>
        </div>

        <?php
        $history = getQueryHistory(10);
        if (!empty($history)):
        ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                        <th style="padding: 12px; text-align: left; color: #333; font-weight: 600;">Timestamp</th>
                        <th style="padding: 12px; text-align: left; color: #333; font-weight: 600;">Type</th>
                        <th style="padding: 12px; text-align: left; color: #333; font-weight: 600;">Query</th>
                        <th style="padding: 12px; text-align: center; color: #333; font-weight: 600;">Results</th>
                        <th style="padding: 12px; text-align: center; color: #333; font-weight: 600;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $entry): ?>
                    <tr style="border-bottom: 1px solid #dee2e6; transition: background 0.2s;">
                        <td style="padding: 12px; color: #666;"><?php echo htmlspecialchars($entry['timestamp']); ?></td>
                        <td style="padding: 12px; color: #666;">
                            <span style="background: <?php echo $entry['type'] === 'visual' ? '#17a2b8' : '#6f42c1'; ?>; color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px;">
                                <?php echo ucfirst($entry['type']); ?>
                            </span>
                        </td>
                        <td style="padding: 12px; color: #666; max-width: 400px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <code style="background: #f8f9fa; padding: 4px 8px; border-radius: 4px; font-size: 11px;">
                                <?php 
                                if ($entry['type'] === 'visual' && isset($entry['query']['field'])) {
                                    echo htmlspecialchars($entry['query']['field'] . ' ' . $entry['query']['op'] . ' ' . substr($entry['query']['value'], 0, 20));
                                } else {
                                    $customQuery = isset($entry['query']['custom']) ? substr($entry['query']['custom'], 0, 50) : '';
                                    echo htmlspecialchars($customQuery);
                                }
                                ?>
                            </code>
                        </td>
                        <td style="padding: 12px; text-align: center; color: #28a745; font-weight: 600;">
                            <?php echo htmlspecialchars((string)$entry['results_count']); ?>
                        </td>
                        <td style="padding: 12px; text-align: center;">
                            <span style="background: #28a745; color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px;">
                                ✓ <?php echo ucfirst($entry['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p style="color: #999; text-align: center; padding: 20px;">No queries executed yet. Execute your first query to see it in history!</p>
        <?php endif; ?>
    </div>

    <!-- Add Document Tab -->
    <div id="add" class="tab-content">
        <h2 style="margin-bottom: 20px;">➕ Add New Document</h2>

        <?php
        // Show available templates
        try {
            $templatesCollection = $database->getCollection('_templates');
            $availableTemplates = $templatesCollection->find(['user_collection' => $collectionName])->toArray();

            if (!empty($availableTemplates)):
                ?>
                <div
                    style="background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%); padding: 20px; border-radius: 12px; margin-bottom: 20px; border-left: 4px solid #667eea;">
                    <h3
                        style="color: #333; margin-bottom: 15px; font-size: 16px; display: flex; align-items: center; gap: 8px;">
                        <span>📚</span> Quick Start with Templates
                    </h3>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <?php foreach ($availableTemplates as $template): ?>
                            <button type="button" class="btn"
                                onclick="loadTemplate('<?php echo htmlspecialchars(json_encode($template->data), ENT_QUOTES); ?>'); return false;"
                                style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 10px 18px;">
                                📄 <?php echo htmlspecialchars($template->name); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <p style="color: #666; font-size: 13px; margin-top: 12px;">
                        💡 Click a template to load it into the editor below
                    </p>
                </div>
                <?php
            endif;
        } catch (Exception $e) {
            // Silently fail if templates collection doesn't exist
        }
        ?>

        <div style="max-width: 800px;">
            <form method="POST" style="background: white; padding: 20px; border-radius: 8px;">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                <div class="form-group">
                    <label style="display: flex; justify-content: space-between; align-items: center;">
                        <span>JSON Data:</span>
                        <button type="button"
                            onclick="switchTab('advanced', document.querySelectorAll('.tab-btn')[6]); return false;"
                            class="btn" style="background: #6c757d; color: white; padding: 6px 12px; font-size: 12px;">
                            💾 Manage Templates
                        </button>
                    </label>
                    <textarea name="json_data" placeholder="Paste JSON here..." required
                        style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 6px; font-family: 'Courier New', monospace; min-height: 250px;">{"key": "value"}</textarea>
                </div>
                <button type="submit" class="btn"
                    style="background: #28a745; color: white; width: 100%; padding: 12px;">✅ Add Document</button>
            </form>
        </div>
    </div>

    <!-- Bulk Operations Tab -->
    <div id="bulk" class="tab-content">
        <h2>📦 Bulk Operations</h2>

        <!-- Field Operations Section -->
        <div
            style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); padding: 20px; border-radius: 12px; margin-bottom: 25px; color: white;">
            <h3 style="color: white; margin-bottom: 20px;">🔧 Field Operations</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px;">
                <!-- Add Field -->
                <div style="background: rgba(255,255,255,0.95); padding: 18px; border-radius: 8px; color: #333;">
                    <h4 style="color: #28a745; margin-bottom: 12px;">➕ Add Field</h4>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_field">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                        <input type="text" name="field_name" placeholder="Field name" required
                            style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px; margin-bottom: 8px;">
                        <input type="text" name="default_value" placeholder="Default value (or JSON)"
                            style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px; margin-bottom: 8px;">
                        <button type="submit" class="btn"
                            style="background: #28a745; color: white; width: 100%; padding: 10px; font-size: 14px;">➕
                            Add to All</button>
                    </form>
                </div>

                <!-- Remove Field -->
                <div style="background: rgba(255,255,255,0.95); padding: 18px; border-radius: 8px; color: #333;">
                    <h4 style="color: #dc3545; margin-bottom: 12px;">❌ Remove Field</h4>
                    <form method="POST" onsubmit="return confirm('Remove this field from ALL documents?')">
                        <input type="hidden" name="action" value="remove_field">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                        <input type="text" name="field_name" placeholder="Field name" required
                            style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px; margin-bottom: 8px;">
                        <button type="submit" class="btn"
                            style="background: #dc3545; color: white; width: 100%; padding: 10px; font-size: 14px;">❌
                            Remove from All</button>
                    </form>
                </div>

                <!-- Rename Field -->
                <div style="background: rgba(255,255,255,0.95); padding: 18px; border-radius: 8px; color: #333;">
                    <h4 style="color: #ffc107; margin-bottom: 12px;">✏️ Rename Field</h4>
                    <form method="POST">
                        <input type="hidden" name="action" value="rename_field">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                        <input type="text" name="old_field_name" placeholder="Old field name" required
                            style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px; margin-bottom: 8px;">
                        <input type="text" name="new_field_name" placeholder="New field name" required
                            style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px; margin-bottom: 8px;">
                        <button type="submit" class="btn"
                            style="background: #ffc107; color: #333; width: 100%; padding: 10px; font-size: 14px;">✏️
                            Rename</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Data Operations -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px;">
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <h3>🔄 Bulk Update</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="bulkupdate">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                    <div class="form-group">
                        <label>Match Field:</label>
                        <input type="text" name="match_field" placeholder="e.g., email" required
                            style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                    </div>
                    <div class="form-group">
                        <label>Match Value (regex):</label>
                        <input type="text" name="match_value" placeholder="value to search" required
                            style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                    </div>
                    <div class="form-group">
                        <label>Update Field:</label>
                        <input type="text" name="update_field" placeholder="field name" required
                            style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                    </div>
                    <div class="form-group">
                        <label>New Value:</label>
                        <input type="text" name="update_value" placeholder="new value" required
                            style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                    </div>
                    <button type="submit" class="btn"
                        style="background: #ffc107; color: #333; width: 100%; padding: 12px;"
                        onclick="return confirm('Update all matching documents?')">🔄 Update All</button>
                </form>
            </div>

            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <h3>🔍 Find & Replace</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="findreplace">
                    <div class="form-group">
                        <label>Field Name:</label>
                        <input type="text" name="field_name" placeholder="e.g., description" required
                            style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                    </div>
                    <div class="form-group">
                        <label>Find (regex):</label>
                        <input type="text" name="find_value" placeholder="text to find" required
                            style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                    </div>
                    <div class="form-group">
                        <label>Replace With:</label>
                        <input type="text" name="replace_value" placeholder="replacement text" required
                            style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                    </div>
                    <button type="submit" class="btn"
                        style="background: #17a2b8; color: white; width: 100%; padding: 12px;"
                        onclick="return confirm('Replace all matches?')">✨ Replace All</button>
                </form>
            </div>
        </div>

        <!-- Advanced Bulk Operations -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
            <!-- Deduplication -->
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <h3 style="color: #17a2b8;">🧹 Remove Duplicates</h3>
                <form method="POST" onsubmit="return confirm('This will remove duplicate documents. Continue?')">
                    <input type="hidden" name="action" value="deduplicate">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                    <div class="form-group">
                        <label>Field to Check:</label>
                        <input type="text" name="dedup_field" placeholder="e.g., email" required
                            style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                        <small style="color: #666;">Keeps first occurrence, removes rest</small>
                    </div>
                    <button type="submit" class="btn"
                        style="background: #17a2b8; color: white; width: 100%; padding: 12px;">🧹 Deduplicate</button>
                </form>
            </div>

            <!-- Bulk Delete by Field -->
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <h3 style="color: #dc3545;">🗑️ Bulk Delete by Field</h3>
                <form method="POST" onsubmit="return confirm('This will permanently delete documents. Are you sure?')">
                    <input type="hidden" name="action" value="bulk_delete_by_field">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                    <div class="form-group">
                        <label>Field Name:</label>
                        <input type="text" name="delete_field" placeholder="e.g., status" required
                            style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                    </div>
                    <div class="form-group">
                        <label>Operator:</label>
                        <select name="delete_operator"
                            style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                            <option value="equals">Equals</option>
                            <option value="contains">Contains</option>
                            <option value="empty">Is Empty</option>
                            <option value="not_empty">Is Not Empty</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Value:</label>
                        <input type="text" name="delete_value" placeholder="value"
                            style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                    </div>
                    <button type="submit" class="btn"
                        style="background: #dc3545; color: white; width: 100%; padding: 12px;">🗑️ Delete
                        Matching</button>
                </form>
            </div>

            <!-- Data Generator -->
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <h3 style="color: #28a745;">🎲 Generate Test Data</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="generate_data">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                    <div class="form-group">
                        <label>Template (JSON):</label>
                        <textarea name="data_template"
                            placeholder='{"name": "User {{index}}", "code": "{{random}}", "created": "{{date}}"}'
                            required
                            style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; min-height: 100px; font-family: monospace; font-size: 12px;"></textarea>
                        <small style="color: #666;">Placeholders: {{index}}, {{random}}, {{date}}, {{timestamp}}</small>
                    </div>
                    <div class="form-group">
                        <label>Count (max 1000):</label>
                        <input type="number" name="data_count" value="10" min="1" max="1000" required
                            style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                    </div>
                    <button type="submit" class="btn"
                        style="background: #28a745; color: white; width: 100%; padding: 12px;">🎲 Generate &
                        Insert</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Tools Tab -->
    <div id="tools" class="tab-content">
        <h2>🛠️ Tools & Utilities</h2>

        <!-- Collection Management Section -->
        <div
            style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 12px; margin-bottom: 25px; color: white;">
            <h3 style="color: white; margin-bottom: 20px;">📏 Collection Management</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px;">
                <!-- Create Collection -->
                <div style="background: rgba(255,255,255,0.95); padding: 18px; border-radius: 8px; color: #333;">
                    <h4 style="color: #667eea; margin-bottom: 12px;">➕ Create Collection</h4>
                    <form method="POST">
                        <input type="hidden" name="action" value="create_collection">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="text" name="collection_name" placeholder="New collection name" required
                            pattern="[a-zA-Z0-9_-]+"
                            style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px; margin-bottom: 8px;">
                        <button type="submit" class="btn"
                            style="background: #667eea; color: white; width: 100%; padding: 10px; font-size: 14px;">➕
                            Create</button>
                    </form>
                </div>

                <!-- Drop Collection -->
                <div style="background: rgba(255,255,255,0.95); padding: 18px; border-radius: 8px; color: #333;">
                    <h4 style="color: #dc3545; margin-bottom: 12px;">🗑️ Drop Collection</h4>
                    <form method="POST" onsubmit="return confirm('Are you ABSOLUTELY sure? This cannot be undone!')">
                        <input type="hidden" name="action" value="drop_collection">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <select name="collection_to_drop" required
                            style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px; margin-bottom: 8px;">
                            <option value="">Select collection...</option>
                            <?php foreach ($allCollectionNames as $cname): ?>
                                <option value="<?php echo htmlspecialchars($cname); ?>">
                                    <?php echo htmlspecialchars($cname); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="confirm_collection_name" placeholder="Type name to confirm" required
                            style="width: 100%; padding: 8px; border: 2px solid #dc3545; border-radius: 4px; margin-bottom: 8px;">
                        <button type="submit" class="btn"
                            style="background: #dc3545; color: white; width: 100%; padding: 10px; font-size: 14px;">🗑️
                            Drop</button>
                    </form>
                </div>

                <!-- Rename Collection -->
                <div style="background: rgba(255,255,255,0.95); padding: 18px; border-radius: 8px; color: #333;">
                    <h4 style="color: #ffc107; margin-bottom: 12px;">✏️ Rename Collection</h4>
                    <form method="POST">
                        <input type="hidden" name="action" value="rename_collection">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <select name="old_collection_name" required
                            style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px; margin-bottom: 8px;">
                            <option value="">Select collection...</option>
                            <?php foreach ($allCollectionNames as $cname): ?>
                                <option value="<?php echo htmlspecialchars($cname); ?>">
                                    <?php echo htmlspecialchars($cname); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="new_collection_name" placeholder="New name" required
                            pattern="[a-zA-Z0-9_-]+"
                            style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px; margin-bottom: 8px;">
                        <button type="submit" class="btn"
                            style="background: #ffc107; color: #333; width: 100%; padding: 10px; font-size: 14px;">✏️
                            Rename</button>
                    </form>
                </div>

                <!-- Clone Collection -->
                <div style="background: rgba(255,255,255,0.95); padding: 18px; border-radius: 8px; color: #333;">
                    <h4 style="color: #17a2b8; margin-bottom: 12px;">📋 Clone Collection</h4>
                    <form method="POST">
                        <input type="hidden" name="action" value="clone_collection">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <select name="source_collection" required
                            style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px; margin-bottom: 8px;">
                            <option value="">Source collection...</option>
                            <?php foreach ($allCollectionNames as $cname): ?>
                                <option value="<?php echo htmlspecialchars($cname); ?>">
                                    <?php echo htmlspecialchars($cname); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="target_collection" placeholder="Target name" required
                            pattern="[a-zA-Z0-9_-]+"
                            style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px; margin-bottom: 8px;">
                        <button type="submit" class="btn"
                            style="background: #17a2b8; color: white; width: 100%; padding: 10px; font-size: 14px;">📋
                            Clone</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Index Management Section -->
        <div
            style="background: white; padding: 20px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="color: #333; margin-bottom: 20px;">📊 Index Management</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <!-- Create Index -->
                <div style="background: #f8f9fa; padding: 18px; border-radius: 8px; border-left: 4px solid #28a745;">
                    <h4 style="color: #28a745; margin-bottom: 12px;">➕ Create Index</h4>
                    <form method="POST">
                        <input type="hidden" name="action" value="create_index">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                </div>
                <div style="margin-bottom: 12px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">Field
                        Name:</label>
                    <input type="text" name="index_field" placeholder="e.g., email" required
                        style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px;">
                </div>
                <div style="margin-bottom: 12px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">Order:</label>
                    <select name="index_order"
                        style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px;">
                        <option value="1">Ascending (1)</option>
                        <option value="-1">Descending (-1)</option>
                    </select>
                </div>
                <div style="margin-bottom: 12px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="index_unique" value="1" style="width: 18px; height: 18px;">
                        <span style="font-size: 14px;">Unique Index</span>
                    </label>
                </div>
                <button type="submit" class="btn"
                    style="background: #28a745; color: white; width: 100%; padding: 10px;">➕
                    Create Index</button>
                </form>
            </div>

            <!-- Drop Index -->
            <div style="background: #f8f9fa; padding: 18px; border-radius: 8px; border-left: 4px solid #dc3545;">
                <h4 style="color: #dc3545; margin-bottom: 12px;">🗑️ Drop Index</h4>
                <form method="POST" onsubmit="return confirm('Drop this index?')">
                    <input type="hidden" name="action" value="drop_index">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">Select
                            Index:</label>
                        <select name="index_name" required
                            style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px;">
                            <option value="">Select index...</option>
                            <?php
                            try {
                                foreach ($collection->listIndexes() as $index) {
                                    $indexName = $index['name'];
                                    if ($indexName !== '_id_') {
                                        $keys = json_encode($index['key']);
                                        echo "<option value=\"" . htmlspecialchars($indexName) . "\">" . htmlspecialchars($indexName) . " (" . htmlspecialchars($keys) . ")</option>";
                                    }
                                }
                            } catch (Exception $e) {
                                echo "<option value=\"\">No indexes found</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <button type="submit" class="btn"
                        style="background: #dc3545; color: white; width: 100%; padding: 10px;">🗑️ Drop Index</button>
                </form>
            </div>
        </div>

        <!-- Current Indexes Display -->
        <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; border-left: 4px solid #2196f3;">
            <h4 style="color: #1976d2; margin-bottom: 12px;">📊 Current Indexes on
                '<?php echo htmlspecialchars($collectionName); ?>'</h4>
            <div style="max-height: 200px; overflow-y: auto;">
                <?php
                try {
                    $indexes = $collection->listIndexes();
                    echo '<table style="width: 100%; font-size: 13px; border-collapse: collapse;">';
                    echo '<tr style="background: #1976d2; color: white;"><th style="padding: 8px; text-align: left;">Name</th><th style="padding: 8px; text-align: left;">Keys</th><th style="padding: 8px; text-align: left;">Unique</th></tr>';
                    foreach ($indexes as $index) {
                        $unique = isset($index['unique']) && $index['unique'] ? '✅ Yes' : '❌ No';
                        $keys = json_encode($index['key'], JSON_UNESCAPED_SLASHES);
                        echo '<tr style="background: white; border-bottom: 1px solid #ddd;">';
                        echo '<td style="padding: 8px;">' . htmlspecialchars($index['name']) . '</td>';
                        echo '<td style="padding: 8px; font-family: monospace;">' . htmlspecialchars($keys) . '</td>';
                        echo '<td style="padding: 8px;">' . $unique . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                } catch (Exception $e) {
                    echo '<p style="color: #666;">No indexes found or error loading indexes.</p>';
                }
                ?>
            </div>
        </div>

    <!-- Data Import/Export Section -->
    <div
        style="background: white; padding: 20px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <h3 style="color: #333; margin-bottom: 20px;">💾 Backup & Data Management</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
            <!-- Backup Collection -->
            <div style="background: #e3f2fd; padding: 18px; border-radius: 8px; border-left: 4px solid #2196f3;">
                <h4 style="color: #1976d2; margin-bottom: 12px;">💾 Backup Collection</h4>
                <form method="POST">
                    <input type="hidden" name="action" value="backup_collection">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                    <div class="form-group">
                        <label style="font-size: 13px; font-weight: 600;">Backup Name (optional):</label>
                        <input type="text" name="backup_name"
                            placeholder="<?php echo htmlspecialchars($collectionName); ?>_backup"
                            pattern="[a-zA-Z0-9_-]+"
                            style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px; margin-bottom: 8px;">
                        <small style="color: #666;">Leave empty for auto-generated name</small>
                    </div>
                    <button type="submit" class="btn"
                        style="background: #2196f3; color: white; width: 100%; padding: 10px;">💾 Create Backup</button>
                </form>
            </div>

            <!-- Export Data -->
            <div style="background: #f3e5f5; padding: 18px; border-radius: 8px; border-left: 4px solid #9c27b0;">
                <h4 style="color: #7b1fa2; margin-bottom: 12px;">📤 Export Data</h4>
                <form method="POST" style="margin-bottom: 10px;">
                    <input type="hidden" name="action" value="export">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                    <p style="color: #666; font-size: 12px; margin-bottom: 8px;">Download all (or filtered) documents
                    </p>
                    <button type="submit" class="btn"
                        style="background: #9c27b0; color: white; width: 100%; padding: 10px; margin-bottom: 8px;">📥
                        Export
                        JSON</button>
                </form>
                <form method="POST">
                    <input type="hidden" name="action" value="exportcsv">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                    <button type="submit" class="btn"
                        style="background: #7b1fa2; color: white; width: 100%; padding: 10px;">📊 Export CSV</button>
                </form>
            </div>

            <!-- Import Data -->
            <div style="background: #e8f5e9; padding: 18px; border-radius: 8px; border-left: 4px solid #4caf50;">
                <h4 style="color: #388e3c; margin-bottom: 12px;">📥 Import JSON Data</h4>

                <!-- File Upload Method -->
                <form method="POST" enctype="multipart/form-data" id="importFileForm">
                    <input type="hidden" name="action" value="import">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="font-size: 13px; font-weight: 600; display: block; margin-bottom: 6px;">📁 Upload
                            JSON
                            File:</label>
                        <input type="file" name="json_file" id="jsonFileInput" accept=".json"
                            style="width: 100%; padding: 8px; border: 2px solid #81c784; border-radius: 6px; margin-bottom: 8px; font-size: 13px; background: white;">
                        <small style="color: #558b2f; font-size: 11px;">Supports single document or array of
                            documents</small>
                    </div>
                    <button type="submit" class="btn"
                        style="background: #4caf50; color: white; width: 100%; padding: 10px; font-weight: 600;">⬆️
                        Import
                        from File</button>
                </form>

                <div style="text-align: center; margin: 15px 0; color: #66bb6a; font-weight: 600;">— OR —</div>

                <!-- JSON Paste Method -->
                <button type="button" class="btn" onclick="openJsonImportModal()"
                    style="background: #66bb6a; color: white; width: 100%; padding: 10px; font-weight: 600;">
                    📋 Paste JSON Directly
                </button>
            </div>
        </div>
    </div>

    <!-- Collection Migration -->
    <div
        style="background: white; padding: 20px; border-radius: 12px; margin-top: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <h3 style="color: #333; margin-bottom: 20px;">🔄 Collection Migration</h3>
        <form method="POST">
            <input type="hidden" name="action" value="migrate_collection">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">Source Collection:</label>
                    <select name="source_collection" required
                        style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                        <option value="">Select source...</option>
                        <?php foreach ($collectionNames as $collName): ?>
                            <option value="<?php echo htmlspecialchars($collName); ?>">
                                <?php echo htmlspecialchars($collName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">Target Collection:</label>
                    <input type="text" name="target_collection" placeholder="New or existing collection" required
                        pattern="[a-zA-Z0-9_-]+"
                        style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                </div>
            </div>
            <div style="margin-top: 15px;">
                <label style="font-weight: 600; margin-bottom: 8px; display: block;">Filter (Optional JSON):</label>
                <input type="text" name="migrate_filter" placeholder='{"status": "active"}'
                    style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-family: monospace;">
                <small style="color: #666;">Leave empty to migrate all documents</small>
            </div>
            <div style="margin-top: 15px; display: flex; gap: 10px; align-items: center;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="migrate_copy" value="1" checked>
                    <span style="font-size: 14px;">Copy mode (keep source documents)</span>
                </label>
            </div>
            <button type="submit" class="btn"
                style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; width: 100%; padding: 14px; margin-top: 20px;"
                onclick="return confirm('Migrate documents to target collection?')">🔄 Start Migration</button>
        </form>
    </div>

    <!-- Index Management -->
    <div
        style="background: white; padding: 25px; border-radius: 12px; margin-top: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <h3 style="color: #333; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">🔍</span> Index Management
        </h3>

        <!-- List Current Indexes -->
        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <h4 style="margin-bottom: 12px; color: #495057;">📋 Current Indexes:</h4>
            <?php
            try {
                $indexes = iterator_to_array($collection->listIndexes());
                if (count($indexes) > 0): ?>
                    <div style="display: grid; gap: 10px;">
                        <?php foreach ($indexes as $index): ?>
                            <div
                                style="background: white; padding: 12px; border-radius: 6px; display: flex; justify-content: space-between; align-items: center; border-left: 3px solid <?php echo $index['name'] === '_id_' ? '#007bff' : '#28a745'; ?>;">
                                <div>
                                    <strong style="color: #333;"><?php echo htmlspecialchars($index['name']); ?></strong>
                                    <code
                                        style="background: #e9ecef; padding: 4px 8px; border-radius: 4px; margin-left: 10px; font-size: 12px;">
                                                                                                                                                <?php echo htmlspecialchars(json_encode($index['key'])); ?>
                                                                                                                                            </code>
                                    <?php if (isset($index['unique']) && $index['unique']): ?>
                                        <span
                                            style="background: #007bff; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; margin-left: 8px;">UNIQUE</span>
                                    <?php endif; ?>
                                    <?php if (isset($index['sparse']) && $index['sparse']): ?>
                                        <span
                                            style="background: #6c757d; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; margin-left: 8px;">SPARSE</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($index['name'] !== '_id_'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="drop_index">
                                        <input type="hidden" name="drop_index_name"
                                            value="<?php echo htmlspecialchars($index['name']); ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <button type="submit" class="btn"
                                            onclick="return confirm('Drop index <?php echo htmlspecialchars($index['name']); ?>?')"
                                            style="background: #dc3545; color: white; padding: 6px 12px; font-size: 12px;">🗑️
                                            Drop</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color: #6c757d; font-style: italic;">No indexes found</p>
                <?php endif;
            } catch (Exception $e) {
                echo '<p style="color: #dc3545;">Error loading indexes: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
            ?>
        </div>

        <!-- Create New Index -->
        <form method="POST"
            style="background: #e3f2fd; padding: 20px; border-radius: 8px; border-left: 4px solid #2196f3;">
            <input type="hidden" name="action" value="create_index">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
            <h4 style="margin-bottom: 15px; color: #1565c0;">➕ Create New Index</h4>
            <div style="display: grid; grid-template-columns: 2fr 1fr 2fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <label style="font-weight: 600; margin-bottom: 6px; display: block;">Field Name:</label>
                    <input type="text" name="index_field" placeholder="e.g., email, user_id" required
                        style="width: 100%; padding: 10px; border: 2px solid #90caf9; border-radius: 6px;">
                </div>
                <div>
                    <label style="font-weight: 600; margin-bottom: 6px; display: block;">Type:</label>
                    <select name="index_type" required
                        style="width: 100%; padding: 10px; border: 2px solid #90caf9; border-radius: 6px;">
                        <option value="1">Ascending (1)</option>
                        <option value="-1">Descending (-1)</option>
                    </select>
                </div>
                <div>
                    <label style="font-weight: 600; margin-bottom: 6px; display: block;">Index Name (optional):</label>
                    <input type="text" name="index_name" placeholder="Auto-generated if empty"
                        style="width: 100%; padding: 10px; border: 2px solid #90caf9; border-radius: 6px;">
                </div>
            </div>
            <div style="display: flex; gap: 20px; margin-bottom: 15px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="index_unique" value="1">
                    <span style="font-size: 14px; font-weight: 600;">🔒 Unique Index</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="index_sparse" value="1">
                    <span style="font-size: 14px; font-weight: 600;">📊 Sparse Index</span>
                </label>
            </div>
            <button type="submit" class="btn" style="background: #2196f3; color: white; width: 100%; padding: 12px;">➕
                Create Index</button>
        </form>
    </div>

    <!-- Clone Collection -->
    <div
        style="background: white; padding: 25px; border-radius: 12px; margin-top: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <h3 style="color: #333; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">📋</span> Clone Collection
        </h3>
        <form method="POST">
            <input type="hidden" name="action" value="clone_collection">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px;">
                <div>
                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">Source Collection:</label>
                    <select name="clone_source" required
                        style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                        <option value="">Select source...</option>
                        <?php foreach ($collectionNames as $collName): ?>
                            <option value="<?php echo htmlspecialchars($collName); ?>" <?php echo $collName === $collectionName ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($collName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">Target Collection Name:</label>
                    <input type="text" name="clone_target" placeholder="e.g., users_backup" required
                        pattern="[a-zA-Z0-9_-]+"
                        style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                </div>
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="clone_indexes" value="1" checked>
                    <span style="font-size: 14px; font-weight: 600;">📇 Copy Indexes</span>
                </label>
            </div>
            <button type="submit" class="btn"
                style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; width: 100%; padding: 14px;"
                onclick="return confirm('Clone this collection?')">📋 Clone Collection</button>
        </form>
    </div>

    <!-- Duplicate Finder -->
    <div
        style="background: white; padding: 25px; border-radius: 12px; margin-top: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <h3 style="color: #333; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">🔍</span> Find Duplicate Values
        </h3>
        <form method="POST">
            <input type="hidden" name="action" value="find_duplicates">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
            <div style="margin-bottom: 15px;">
                <label style="font-weight: 600; margin-bottom: 8px; display: block;">Field to Check:</label>
                <input type="text" name="dup_field" placeholder="e.g., email, username, product_id" required
                    style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                <small style="color: #666;">Find documents with duplicate values in this field</small>
            </div>
            <button type="submit" class="btn"
                style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; width: 100%; padding: 12px;">🔍
                Find Duplicates</button>
        </form>

        <?php if (isset($_SESSION['duplicate_results'])):
            $dupResults = $_SESSION['duplicate_results'];
            ?>
            <div
                style="background: #fff3cd; padding: 20px; border-radius: 8px; margin-top: 20px; border-left: 4px solid #ffc107;">
                <h4 style="color: #856404; margin-bottom: 15px;">
                    📊 Duplicate Analysis for "<?php echo htmlspecialchars($dupResults['field']); ?>"
                </h4>
                <p style="color: #856404; margin-bottom: 15px;">
                    Found <strong><?php echo $dupResults['total']; ?></strong> unique values with duplicates
                </p>
                <?php if (count($dupResults['results']) > 0): ?>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <?php foreach (array_slice($dupResults['results'], 0, 20) as $dup):
                            $dupData = json_decode(json_encode($dup), true);
                            ?>
                            <div
                                style="background: white; padding: 12px; border-radius: 6px; margin-bottom: 10px; border-left: 3px solid #ffc107;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong style="color: #333;">Value:
                                            <?php echo htmlspecialchars(json_encode($dupData['_id'])); ?></strong>
                                        <span
                                            style="background: #dc3545; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; margin-left: 10px;">
                                            <?php echo $dupData['count']; ?> occurrences
                                        </span>
                                    </div>
                                </div>
                                <details style="margin-top: 8px;">
                                    <summary style="cursor: pointer; color: #007bff; font-size: 12px;">Show Document IDs</summary>
                                    <div
                                        style="background: #f8f9fa; padding: 8px; border-radius: 4px; margin-top: 6px; font-family: monospace; font-size: 11px;">
                                        <?php foreach ($dupData['ids'] as $id): ?>
                                            <div><?php echo htmlspecialchars((string) $id); ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                </details>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <button onclick="<?php unset($_SESSION['duplicate_results']); ?> window.location.reload();" class="btn"
                    style="background: #6c757d; color: white; padding: 8px 16px; margin-top: 10px;">Clear Results</button>
            </div>
            <?php unset($_SESSION['duplicate_results']); endif; ?>
    </div>

    <!-- Bulk Update by Query -->
    <div
        style="background: white; padding: 25px; border-radius: 12px; margin-top: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <h3 style="color: #333; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">⚡</span> Bulk Update by Query
        </h3>
        <form method="POST">
            <input type="hidden" name="action" value="bulk_update_query">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
            <div style="margin-bottom: 15px;">
                <label style="font-weight: 600; margin-bottom: 8px; display: block;">Filter (Match documents):</label>
                <textarea name="bulk_filter" placeholder='{"status": "pending"}' required
                    style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-family: monospace; min-height: 80px;"></textarea>
            </div>
            <div style="margin-bottom: 15px;">
                <label style="font-weight: 600; margin-bottom: 8px; display: block;">Update (Set new values):</label>
                <textarea name="bulk_update" placeholder='{"status": "completed", "updated_at": "2026-01-14"}' required
                    style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-family: monospace; min-height: 80px;"></textarea>
                <small style="color: #666;">Auto-wraps with $set if no operators provided</small>
            </div>
            <button type="submit" class="btn"
                style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; width: 100%; padding: 14px;"
                onclick="return confirm('Update all matching documents?')">⚡ Execute Bulk Update</button>
        </form>
    </div>

    <!-- Data Validation Schema -->
    <div
        style="background: white; padding: 25px; border-radius: 12px; margin-top: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <h3 style="color: #333; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">✅</span> Data Validation Rules
        </h3>
        <form method="POST">
            <input type="hidden" name="action" value="add_validation">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
            <div style="margin-bottom: 15px;">
                <label style="font-weight: 600; margin-bottom: 8px; display: block;">JSON Schema:</label>
                <textarea name="validation_schema"
                    placeholder='{"bsonType": "object", "required": ["name", "email"], "properties": {"name": {"bsonType": "string"}, "email": {"bsonType": "string", "pattern": "^.+@.+$"}}}'
                    required
                    style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-family: monospace; min-height: 120px;"></textarea>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">Validation Level:</label>
                    <select name="validation_level"
                        style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                        <option value="strict">Strict (all inserts/updates)</option>
                        <option value="moderate">Moderate (inserts only)</option>
                        <option value="off">Off</option>
                    </select>
                </div>
                <div>
                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">Validation Action:</label>
                    <select name="validation_action"
                        style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                        <option value="error">Error (reject invalid)</option>
                        <option value="warn">Warn (log only)</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn"
                style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; width: 100%; padding: 14px;">✅
                Apply Validation Schema</button>
        </form>
    </div>

    <!-- Compare Collections -->
    <div
        style="background: white; padding: 25px; border-radius: 12px; margin-top: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <h3 style="color: #333; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">⚖️</span> Compare Collections
        </h3>
        <form method="POST">
            <input type="hidden" name="action" value="compare_collections">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">Collection 1:</label>
                    <select name="compare_coll1" required
                        style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                        <option value="">Select...</option>
                        <?php foreach ($collectionNames as $collName): ?>
                            <option value="<?php echo htmlspecialchars($collName); ?>">
                                <?php echo htmlspecialchars($collName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">Collection 2:</label>
                    <select name="compare_coll2" required
                        style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                        <option value="">Select...</option>
                        <?php foreach ($collectionNames as $collName): ?>
                            <option value="<?php echo htmlspecialchars($collName); ?>">
                                <?php echo htmlspecialchars($collName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">Compare Field:</label>
                    <input type="text" name="compare_field" placeholder="e.g., _id, email" required
                        style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                </div>
            </div>
            <button type="submit" class="btn"
                style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; width: 100%; padding: 12px;">⚖️
                Compare Collections</button>
        </form>

        <?php if (isset($_SESSION['compare_results'])):
            $compResults = $_SESSION['compare_results'];
            ?>
            <div
                style="background: #e3f2fd; padding: 20px; border-radius: 8px; margin-top: 20px; border-left: 4px solid #2196f3;">
                <h4 style="color: #1565c0; margin-bottom: 15px;">📊 Comparison Results</h4>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px;">
                    <div style="background: white; padding: 15px; border-radius: 6px; text-align: center;">
                        <p style="font-size: 28px; font-weight: bold; color: #28a745;">
                            <?php echo $compResults['stats']['common']; ?>
                        </p>
                        <p style="font-size: 13px; color: #666;">Common Values</p>
                    </div>
                    <div style="background: white; padding: 15px; border-radius: 6px; text-align: center;">
                        <p style="font-size: 28px; font-weight: bold; color: #007bff;">
                            <?php echo $compResults['stats']['unique_1']; ?>
                        </p>
                        <p style="font-size: 13px; color: #666;">Only in
                            <?php echo htmlspecialchars($compResults['coll1']); ?>
                        </p>
                    </div>
                    <div style="background: white; padding: 15px; border-radius: 6px; text-align: center;">
                        <p style="font-size: 28px; font-weight: bold; color: #ffc107;">
                            <?php echo $compResults['stats']['unique_2']; ?>
                        </p>
                        <p style="font-size: 13px; color: #666;">Only in
                            <?php echo htmlspecialchars($compResults['coll2']); ?>
                        </p>
                    </div>
                </div>
                <button onclick="<?php unset($_SESSION['compare_results']); ?> window.location.reload();" class="btn"
                    style="background: #6c757d; color: white; padding: 8px 16px;">Clear Results</button>
            </div>
            <?php unset($_SESSION['compare_results']); endif; ?>
    </div>

    <!-- Export Collection Data -->
    <div
        style="background: white; padding: 25px; border-radius: 12px; margin-top: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <h3 style="color: #333; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">💾</span> Export Collection Data
        </h3>
        <form method="POST">
            <input type="hidden" name="action" value="export_collection_data">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">Export Format:</label>
                    <select name="export_format" required
                        style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                        <option value="json">JSON</option>
                        <option value="csv">CSV</option>
                    </select>
                </div>
                <div>
                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">Filter (optional):</label>
                    <input type="text" name="export_filter" placeholder='{"status": "active"}'
                        style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-family: monospace;">
                </div>
            </div>
            <button type="submit" class="btn"
                style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; width: 100%; padding: 12px;">💾
                Export Data</button>
        </form>
    </div>
    </div>

<!-- Advanced Tab -->
<div id="advanced" class="tab-content">
    <h2 style="color: var(--text-primary); margin-bottom: 20px;">🔬 Advanced Features</h2>

    <!-- Dangerous Operations Section -->
    <div
        style="background: white; padding: 25px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border-left: 4px solid #dc3545;">
        <h3 style="color: #dc3545; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">🗑️</span> Delete All Documents
        </h3>
        <div
            style="background: #fff3cd; padding: 12px; border-radius: 6px; margin-bottom: 15px; border-left: 4px solid #ffc107;">
            <strong style="color: #856404;">⚠️ Warning:</strong> <span style="color: #856404;">This will permanently
                delete ALL documents from this collection. This action cannot be undone!</span>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="delete_all">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
            <button type="submit" class="btn"
                style="background: #dc3545; color: white; width: 100%; padding: 14px; font-weight: 600;"
                onclick="return confirm('⚠️ FINAL WARNING: This will delete ALL <?php echo $documentCount; ?> documents from <?php echo htmlspecialchars($collectionName); ?>. This cannot be undone! Type YES to confirm.') && prompt('Type DELETE to confirm:') === 'DELETE'">⚠️
                Delete All Documents</button>
        </form>
    </div>

    <!-- Query History -->
    <div
        style="background: white; padding: 25px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <h3 style="color: #333; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">📚</span> Query History
        </h3>

        <!-- Save Current Query -->
        <form method="POST" style="background: #e8f5e9; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <input type="hidden" name="action" value="save_query">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
            <h4 style="color: #2e7d32; margin-bottom: 12px;">💾 Save New Query</h4>
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div>
                    <label style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 13px;">Query
                        Name:</label>
                    <input type="text" name="query_name" placeholder="e.g., Active Users" required
                        style="width: 100%; padding: 8px; border: 2px solid #81c784; border-radius: 6px;">
                </div>
                <div>
                    <label
                        style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 13px;">Collection:</label>
                    <input type="text" name="query_collection" value="<?php echo htmlspecialchars($collectionName); ?>"
                        required style="width: 100%; padding: 8px; border: 2px solid #81c784; border-radius: 6px;">
                </div>
            </div>
            <div style="margin-bottom: 12px;">
                <label style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 13px;">Query
                    JSON:</label>
                <textarea name="query_filter" placeholder='{"status": "active"}' required
                    style="width: 100%; padding: 8px; border: 2px solid #81c784; border-radius: 6px; font-family: monospace; min-height: 60px;"></textarea>
            </div>
            <button type="submit" class="btn" style="background: #4caf50; color: white; width: 100%; padding: 10px;">💾
                Save
                Query</button>
        </form>

        <!-- Saved Queries List -->
        <?php if (isset($_SESSION['saved_queries']) && count($_SESSION['saved_queries']) > 0): ?>
            <h4 style="margin-bottom: 15px; color: #495057;">📋 Saved Queries
                (<?php echo count($_SESSION['saved_queries']); ?>)
            </h4>
            <div style="max-height: 400px; overflow-y: auto;">
                <?php foreach ($_SESSION['saved_queries'] as $query): ?>
                    <div
                        style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 12px; border-left: 3px solid #007bff;">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                            <div>
                                <strong
                                    style="color: #333; font-size: 15px;"><?php echo htmlspecialchars($query['name']); ?></strong>
                                <div style="font-size: 12px; color: #6c757d; margin-top: 4px;">
                                    📦 <?php echo htmlspecialchars($query['collection']); ?> |
                                    📅 <?php echo htmlspecialchars($query['created']); ?>
                                </div>
                            </div>
                            <div style="display: flex; gap: 6px;">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="load_query">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="query_id" value="<?php echo htmlspecialchars($query['id']); ?>">
                                    <button type="submit" class="btn"
                                        style="background: #28a745; color: white; padding: 6px 12px; font-size: 12px;">▶️
                                        Load</button>
                                </form>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="delete_query">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="query_id" value="<?php echo htmlspecialchars($query['id']); ?>">
                                    <button type="submit" class="btn" onclick="return confirm('Delete this query?')"
                                        style="background: #dc3545; color: white; padding: 6px 12px; font-size: 12px;">🗑️</button>
                                </form>
                            </div>
                        </div>
                        <code
                            style="display: block; background: white; padding: 10px; border-radius: 4px; font-size: 12px; overflow-x: auto; color: #495057;">
                                                                                                    <?php echo htmlspecialchars($query['filter']); ?>
                                                                                                </code>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="color: #6c757d; font-style: italic; text-align: center; padding: 20px;">No saved queries yet. Save
                your
                frequently used queries above!</p>
        <?php endif; ?>
    </div>

    <!-- Template & Stats Section -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
        <div
            style="background: var(--card-bg); padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px var(--shadow-color);">
            <h3 style="color: var(--text-primary); margin-bottom: 15px;">💾 Document Templates</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                <input type="hidden" name="action" value="savetemplate">
                <div class="form-group">
                    <label style="color: var(--text-secondary);">Template Name:</label>
                    <input type="text" name="template_name" placeholder="e.g., user_template" required
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px;">
                </div>
                <div class="form-group">
                    <label style="color: var(--text-secondary);">Template JSON:</label>
                    <textarea name="template_data" placeholder='{"name": "", "email": "", "age": 0}' required
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px; min-height: 100px; font-family: 'Courier New', monospace;"></textarea>
                </div>
                <button type="submit" class="btn"
                    style="background: #28a745; color: white; width: 100%; padding: 10px;">💾 Save Template</button>
            </form>

            <?php
            // Load saved templates
            try {
                $templatesCollection = $database->getCollection('_templates');
                $savedTemplates = $templatesCollection->find(['user_collection' => $collectionName])->toArray();

                if (!empty($savedTemplates)):
                    ?>
                    <div style="margin-top: 20px; padding-top: 20px; border-top: 2px solid var(--table-border);">
                        <h4 style="color: var(--text-primary); margin-bottom: 15px; font-size: 14px;">📚 Saved Templates
                        </h4>
                        <?php foreach ($savedTemplates as $template): ?>
                            <div
                                style="background: var(--table-header-bg); padding: 12px; border-radius: 6px; margin-bottom: 10px; border-left: 3px solid #667eea;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <strong style="color: var(--text-primary); font-size: 13px;">
                                        📄 <?php echo htmlspecialchars($template->name); ?>
                                    </strong>
                                    <div style="display: flex; gap: 6px;">
                                        <button type="button" class="btn"
                                            onclick="loadTemplate('<?php echo htmlspecialchars(json_encode($template->data), ENT_QUOTES); ?>'); return false;"
                                            style="background: #17a2b8; color: white; padding: 4px 10px; font-size: 11px;">
                                            📋 Use
                                        </button>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="action" value="deletetemplate">
                                            <input type="hidden" name="template_name"
                                                value="<?php echo htmlspecialchars($template->name); ?>">
                                            <button type="submit" class="btn" onclick="return confirm('Delete this template?')"
                                                style="background: #dc3545; color: white; padding: 4px 10px; font-size: 11px;">
                                                🗑️
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <pre
                                    style="background: var(--code-bg); padding: 8px; border-radius: 4px; margin-top: 8px; font-size: 11px; overflow-x: auto; color: var(--text-secondary); max-height: 80px; overflow-y: auto;"><code><?php echo htmlspecialchars(json_encode($template->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></code></pre>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php
                endif;
            } catch (Exception $e) {
                // Silently fail if templates collection doesn't exist
            }
            ?>
        </div>

        <div
            style="background: var(--card-bg); padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px var(--shadow-color);">
            <h3 style="color: var(--text-primary); margin-bottom: 15px;">📊 Field Statistics</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                <input type="hidden" name="action" value="fieldstats">
                <div class="form-group">
                    <label style="color: var(--text-secondary);">Field Name:</label>
                    <input type="text" name="field_name" placeholder="e.g., status" required
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px;">
                </div>
                <button type="submit" class="btn"
                    style="background: #667eea; color: white; width: 100%; padding: 10px;">📈 Analyze</button>
            </form>

            <?php if (isset($_SESSION['field_stats'])): ?>
                <div
                    style="margin-top: 20px; padding: 15px; background: var(--table-header-bg); border-radius: 6px; border: 1px solid var(--table-border);">
                    <p style="font-weight: 600; margin-bottom: 10px; color: var(--text-primary);">Field:
                        <?php echo htmlspecialchars($_SESSION['field_stats']['field']); ?>
                    </p>
                    <?php foreach ($_SESSION['field_stats']['data'] as $stat): ?>
                        <div
                            style="display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid var(--table-border);">
                            <span
                                style="color: var(--text-primary);"><?php echo htmlspecialchars($stat['_id'] ?? 'null'); ?></span>
                            <span
                                style="background: #667eea; color: white; padding: 2px 8px; border-radius: 4px; font-size: 12px;"><?php echo $stat['count'] ?? 0; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Additional Features Section -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
        <div
            style="background: var(--card-bg); padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px var(--shadow-color);">
            <h3 style="color: var(--text-primary); margin-bottom: 15px;">🔢 Data Aggregation</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                <input type="hidden" name="action" value="aggregate">
                <div class="form-group">
                    <label style="color: var(--text-secondary);">Pipeline (JSON Array):</label>
                    <textarea name="pipeline" placeholder='[{"$group": {"_id": "$status", "count": {"$sum": 1}}}]'
                        required
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px; min-height: 120px; font-family: 'Courier New', monospace;"></textarea>
                </div>
                <button type="submit" class="btn"
                    style="background: #9c27b0; color: white; width: 100%; padding: 10px;">🔢 Run
                    Aggregation</button>
            </form>
            <?php if (isset($_SESSION['aggregation_result'])): ?>
                <div
                    style="margin-top: 15px; padding: 12px; background: var(--success-bg); border-left: 4px solid var(--success-border); border-radius: 4px;">
                    <p style="color: var(--success-text); font-weight: 600; margin-bottom: 8px;">✓ Results:</p>
                    <pre
                        style="background: var(--code-bg); padding: 10px; border-radius: 4px; font-size: 11px; max-height: 200px; overflow-y: auto; color: var(--text-primary);"><code><?php echo htmlspecialchars(json_encode($_SESSION['aggregation_result'], JSON_PRETTY_PRINT)); ?></code></pre>
                </div>
                <?php unset($_SESSION['aggregation_result']); ?>
            <?php endif; ?>
        </div>

        <div
            style="background: var(--card-bg); padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px var(--shadow-color);">
            <h3 style="color: var(--text-primary); margin-bottom: 15px;">🎯 Index Management</h3>
            <form method="POST" onsubmit="return confirm('Create this index?');">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                <input type="hidden" name="action" value="createindex">
                <div class="form-group">
                    <label style="color: var(--text-secondary);">Index Fields (JSON):</label>
                    <textarea name="index_fields" placeholder='{"email": 1, "status": -1}' required
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px; min-height: 60px; font-family: 'Courier New', monospace;"></textarea>
                </div>
                <div class="form-group">
                    <label style="color: var(--text-secondary);">Index Name:</label>
                    <input type="text" name="index_name" placeholder="e.g., email_status_idx" required
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px;">
                </div>
                <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 15px;">
                    <input type="checkbox" name="unique_index" id="unique_index" style="width: 18px; height: 18px;">
                    <label for="unique_index" style="color: var(--text-secondary); font-size: 13px;">Make this index
                        unique</label>
                </div>
                <button type="submit" class="btn"
                    style="background: #ff5722; color: white; width: 100%; padding: 10px;">🎯 Create Index</button>
            </form>
        </div>
    </div>

    <!-- Export & Transform Section -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
        <div
            style="background: var(--card-bg); padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px var(--shadow-color);">
            <h3 style="color: var(--text-primary); margin-bottom: 15px;">📤 Advanced Export</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                <input type="hidden" name="action" value="advancedexport">
                <div class="form-group">
                    <label style="color: var(--text-secondary);">Export Filter (JSON):</label>
                    <textarea name="export_filter" placeholder='{"status": "active"}'
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px; min-height: 60px; font-family: 'Courier New', monospace;"></textarea>
                </div>
                <div class="form-group">
                    <label style="color: var(--text-secondary);">Fields to Export (comma-separated):</label>
                    <input type="text" name="export_fields" placeholder="name,email,status"
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px;">
                </div>
                <div class="form-group">
                    <label style="color: var(--text-secondary);">Limit:</label>
                    <input type="number" name="export_limit" value="1000" min="1" max="10000"
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px;">
                </div>
                <button type="submit" class="btn"
                    style="background: #00bcd4; color: white; width: 100%; padding: 10px;">📤 Export as
                    JSON</button>
            </form>
        </div>

        <div
            style="background: var(--card-bg); padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px var(--shadow-color);">
            <h3 style="color: var(--text-primary); margin-bottom: 15px;">🔄 Field Transformation</h3>
            <form method="POST" onsubmit="return confirm('Transform field across all documents?');">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                <input type="hidden" name="action" value="transformfield">
                <div class="form-group">
                    <label style="color: var(--text-secondary);">Field to Transform:</label>
                    <input type="text" name="transform_field" placeholder="e.g., createdAt" required
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px;">
                </div>
                <div class="form-group">
                    <label style="color: var(--text-secondary);">Operation:</label>
                    <select name="transform_operation" required
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px;">
                        <option value="">Select operation...</option>
                        <option value="lowercase">Convert to Lowercase</option>
                        <option value="uppercase">Convert to Uppercase</option>
                        <option value="trim">Trim Whitespace</option>
                        <option value="todate">Convert to Date</option>
                        <option value="tonumber">Convert to Number</option>
                        <option value="tostring">Convert to String</option>
                    </select>
                </div>
                <div
                    style="margin-top: 15px; padding: 10px; background: var(--warning-bg); border-left: 4px solid var(--warning-border); border-radius: 4px;">
                    <p style="color: var(--warning-text); font-size: 12px; line-height: 1.6;">
                        ⚠️ <strong>Warning:</strong> This will modify all documents. Test on a backup first!
                    </p>
                </div>
                <button type="submit" class="btn"
                    style="background: #f44336; color: white; width: 100%; padding: 10px; margin-top: 10px;">🔄
                    Transform Field</button>
            </form>
        </div>
    </div>

    <!-- Collection Analysis Section -->
    <div
        style="background: var(--card-bg); padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px var(--shadow-color); margin-bottom: 20px;">
        <h3 style="color: var(--text-primary); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">📊</span> Collection Analysis Tools
        </h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                <input type="hidden" name="action" value="findduplicates">
                <input type="hidden" name="duplicate_field" id="dup_field">
                <button type="button"
                    onclick="var field = prompt('Enter field name to check for duplicates:', 'email'); if(field) { document.getElementById('dup_field').value = field; this.form.submit(); }"
                    class="btn"
                    style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; width: 100%; padding: 15px; font-size: 14px; font-weight: 600;">
                    🔍 Find Duplicates
                </button>
            </form>
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                <input type="hidden" name="action" value="orphanedfields">
                <button type="submit" class="btn"
                    style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; width: 100%; padding: 15px; font-size: 14px; font-weight: 600;">
                    🗑️ Find Orphaned Fields
                </button>
            </form>
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                <input type="hidden" name="action" value="dataintegrity">
                <button type="submit" class="btn"
                    style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; width: 100%; padding: 15px; font-size: 14px; font-weight: 600;">
                    ✓ Check Data Integrity
                </button>
            </form>
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                <input type="hidden" name="action" value="sizestats">
                <button type="submit" class="btn"
                    style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; width: 100%; padding: 15px; font-size: 14px; font-weight: 600;">
                    📏 Collection Size Stats
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Performance Tab -->
<div id="performance" class="tab-content">
    <h2 style="margin-bottom: 25px;">⚡ Performance & Monitoring</h2>

    <!-- Query Profiler -->
    <div
        style="background: white; padding: 25px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <h3 style="color: #333; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">🔬</span> Query Profiler
        </h3>
        <form method="POST">
            <input type="hidden" name="action" value="profile_query">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <div class="form-group">
                <label style="font-weight: 600;">MongoDB Query (JSON):</label>
                <textarea name="profile_query" placeholder='{"field": "value"}' required
                    style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 6px; min-height: 120px; font-family: monospace; font-size: 13px;"></textarea>
                <small style="color: #666;">Test query performance and execution time</small>
            </div>
            <button type="submit" class="btn"
                style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; width: 100%; padding: 14px;">🔬
                Profile Query</button>
        </form>

        <?php if (isset($_SESSION['profile_result'])): ?>
            <div
                style="margin-top: 20px; padding: 20px; background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border-radius: 8px; border-left: 4px solid #2196f3;">
                <h4 style="color: #1976d2; margin-bottom: 15px;">📊 Profile Results:</h4>
                <div style="display: grid; gap: 10px;">
                    <div style="background: white; padding: 12px; border-radius: 6px;">
                        <strong>Execution Time:</strong> <span
                            style="color: #2196f3; font-size: 18px; font-weight: bold;"><?php echo $_SESSION['profile_result']['execution_time']; ?>ms</span>
                    </div>
                    <div style="background: white; padding: 12px; border-radius: 6px;">
                        <strong>Results Found:</strong> <span
                            style="color: #28a745; font-size: 18px; font-weight: bold;"><?php echo $_SESSION['profile_result']['result_count']; ?></span>
                    </div>
                    <div style="background: white; padding: 12px; border-radius: 6px;">
                        <strong>Query:</strong> <code
                            style="background: #f8f9fa; padding: 4px 8px; border-radius: 4px; font-size: 12px;"><?php echo htmlspecialchars($_SESSION['profile_result']['query']); ?></code>
                    </div>
                </div>
            </div>
            <?php unset($_SESSION['profile_result']); ?>
        <?php endif; ?>
    </div>

    <!-- Collection Operations -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px;">
        <!-- Compact Collection -->
        <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="color: #333; margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 20px;">🗜️</span> Compact Collection
            </h3>
            <form method="POST">
                <input type="hidden" name="action" value="compact_collection">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <p style="color: #666; font-size: 14px; margin-bottom: 15px;">
                    Defragments the collection storage and reclaims disk space. Reduces file size and improves
                    performance.
                </p>
                <p
                    style="background: #fff3cd; color: #856404; padding: 10px; border-radius: 6px; font-size: 13px; margin-bottom: 15px;">
                    ⚠️ This operation may take time and block writes temporarily
                </p>
                <button type="submit" class="btn" style="background: #ffc107; color: #333; width: 100%; padding: 12px;"
                    onclick="return confirm('Compact collection? This may take a while.')">🗜️ Compact Now</button>
            </form>
        </div>

        <!-- Validate Collection -->
        <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="color: #333; margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 20px;">✅</span> Validate Collection
            </h3>
            <form method="POST">
                <input type="hidden" name="action" value="validate_collection">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <p style="color: #666; font-size: 14px; margin-bottom: 15px;">
                    Scans the collection's data and indexes for correctness. Checks for errors and corruption.
                </p>
                <p
                    style="background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 6px; font-size: 13px; margin-bottom: 15px;">
                    ℹ️ Full validation checks both data and index structures
                </p>
                <button type="submit" class="btn"
                    style="background: #17a2b8; color: white; width: 100%; padding: 12px;">✅ Validate Now</button>
            </form>
        </div>
    </div>

    <?php if (isset($_SESSION['validate_result'])): ?>
        <div
            style="background: white; padding: 25px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="color: #333; margin-bottom: 15px;">📋 Validation Results:</h3>
            <pre
                style="background: #f8f9fa; padding: 20px; border-radius: 8px; overflow-x: auto; font-size: 12px; border: 1px solid #dee2e6;"><code><?php echo htmlspecialchars($_SESSION['validate_result']); ?></code></pre>
        </div>
        <?php unset($_SESSION['validate_result']); ?>
    <?php endif; ?>

    <!-- Connection & Server Stats -->
    <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <h3 style="color: #333; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">📊</span> Server Statistics
        </h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
            <?php
            try {
                $serverStatus = $database->command(['serverStatus' => 1])->toArray()[0];
                $connections = $serverStatus->connections ?? null;
                $network = $serverStatus->network ?? null;
                $opcounters = $serverStatus->opcounters ?? null;
                ?>

                <?php if ($connections): ?>
                    <div
                        style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px;">
                        <p style="font-size: 13px; opacity: 0.9;">Active Connections</p>
                        <p style="font-size: 32px; font-weight: bold; margin: 8px 0;">
                            <?php echo $connections->current ?? 0; ?>
                        </p>
                        <p style="font-size: 11px; opacity: 0.8;">Available: <?php echo $connections->available ?? 0; ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($network): ?>
                    <div
                        style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 20px; border-radius: 10px;">
                        <p style="font-size: 13px; opacity: 0.9;">Network Traffic</p>
                        <p style="font-size: 32px; font-weight: bold; margin: 8px 0;">
                            <?php echo round(($network->bytesIn ?? 0) / 1024 / 1024, 1); ?> MB
                        </p>
                        <p style="font-size: 11px; opacity: 0.8;">In | Out:
                            <?php echo round(($network->bytesOut ?? 0) / 1024 / 1024, 1); ?> MB
                        </p>
                    </div>
                <?php endif; ?>

                <?php if ($opcounters): ?>
                    <div
                        style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 20px; border-radius: 10px;">
                        <p style="font-size: 13px; opacity: 0.9;">Query Operations</p>
                        <p style="font-size: 32px; font-weight: bold; margin: 8px 0;">
                            <?php echo number_format($opcounters->query ?? 0); ?>
                        </p>
                        <p style="font-size: 11px; opacity: 0.8;">Inserts:
                            <?php echo number_format($opcounters->insert ?? 0); ?>
                        </p>
                    </div>

                    <div
                        style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; padding: 20px; border-radius: 10px;">
                        <p style="font-size: 13px; opacity: 0.9;">Update Operations</p>
                        <p style="font-size: 32px; font-weight: bold; margin: 8px 0;">
                            <?php echo number_format($opcounters->update ?? 0); ?>
                        </p>
                        <p style="font-size: 11px; opacity: 0.8;">Deletes:
                            <?php echo number_format($opcounters->delete ?? 0); ?>
                        </p>
                    </div>
                <?php endif; ?>
                <?php
            } catch (Exception $e) {
                echo '<div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px;">Unable to fetch server statistics</div>';
            }
            ?>
        </div>
    </div>
</div>

<!-- Analytics Tab -->
<div id="stats" class="tab-content">
    <h2 style="color: var(--text-primary); margin-bottom: 20px;">📊 Analytics & Statistics</h2>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
        <div
            style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px;">
            <p style="opacity: 0.9;">Total Documents</p>
            <p style="font-size: 32px; font-weight: bold; margin: 10px 0;">
                <?php echo number_format($documentCount); ?>
            </p>
        </div>
        <div
            style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 20px; border-radius: 8px;">
            <p style="opacity: 0.9;">Total Storage</p>
            <p style="font-size: 32px; font-weight: bold; margin: 10px 0;">
                <?php echo number_format($totalSize / 1024 / 1024, 2); ?> MB
            </p>
        </div>
        <div
            style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 20px; border-radius: 8px;">
            <p style="opacity: 0.9;">Avg Doc Size</p>
            <p style="font-size: 32px; font-weight: bold; margin: 10px 0;">
                <?php echo number_format($avgDocSize / 1024, 2); ?> KB
            </p>
        </div>
        <div
            style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; padding: 20px; border-radius: 8px;">
            <p style="opacity: 0.9;">Connected Collections</p>
            <p style="font-size: 32px; font-weight: bold; margin: 10px 0;"><?php echo count($collectionNames); ?>
            </p>
        </div>
    </div>

    <div
        style="background: var(--card-bg); padding: 20px; border-radius: 8px; margin-top: 20px; box-shadow: 0 2px 8px var(--shadow-color);">
        <h3 style="color: var(--text-primary);">Collections in Database</h3>
        <div style="display: flex; flex-wrap: wrap; gap: 10px;">
            <?php foreach ($collectionNames as $collName): ?>
                <span
                    style="background: var(--table-header-bg); color: var(--text-primary); padding: 8px 12px; border-radius: 20px; border: 1px solid var(--table-border);">
                    📦 <?php echo htmlspecialchars($collName); ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Data Visualization -->
    <div
        style="background: var(--card-bg); padding: 25px; border-radius: 12px; margin-top: 20px; box-shadow: 0 4px 15px var(--shadow-color);">
        <h3 style="color: var(--text-primary); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">📊</span> Data Visualization
        </h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
            <input type="hidden" name="action" value="visualize_data">
            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 12px; margin-bottom: 20px;">
                <div>
                    <label style="color: var(--text-secondary); font-weight: 600; font-size: 13px;">Group By
                        Field:</label>
                    <input type="text" name="viz_field" placeholder="e.g., status, category, type" required
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px; font-size: 14px;">
                </div>
                <div>
                    <label style="color: var(--text-secondary); font-weight: 600; font-size: 13px;">Chart
                        Type:</label>
                    <select name="viz_type"
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px; font-size: 14px;">
                        <option value="bar">📊 Bar Chart</option>
                        <option value="pie">🥧 Pie Chart</option>
                        <option value="list">📋 List View</option>
                    </select>
                </div>
                <div>
                    <label style="color: var(--text-secondary); font-weight: 600; font-size: 13px;">Max
                        Items:</label>
                    <input type="number" name="viz_limit" value="10" min="5" max="50"
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px; font-size: 14px;">
                </div>
                <div style="display: flex; align-items: end;">
                    <button type="submit" class="btn"
                        style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 10px 20px; font-weight: 600;">📈
                        Visualize</button>
                </div>
            </div>
        </form>

        <?php if (isset($_SESSION['viz_data'])): ?>
            <?php $vizData = $_SESSION['viz_data']; ?>
            <div
                style="padding: 25px; background: var(--table-header-bg); border-radius: 10px; margin-top: 20px; border: 1px solid var(--table-border);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h4 style="color: var(--text-primary);">Results for
                        "<?php echo htmlspecialchars($vizData['field']); ?>"
                    </h4>
                    <span
                        style="background: #667eea; color: white; padding: 6px 16px; border-radius: 20px; font-size: 14px; font-weight: 600;">Total:
                        <?php echo $vizData['total']; ?></span>
                </div>
                <div style="display: grid; gap: 12px;">
                    <?php foreach ($vizData['results'] as $item): ?>
                        <?php $percentage = $vizData['total'] > 0 ? ($item['count'] / $vizData['total'] * 100) : 0; ?>
                        <div style="background: var(--card-bg); padding: 18px; border-radius: 10px; box-shadow: 0 2px 8px var(--shadow-color); transition: all 0.3s;"
                            onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.15)'; this.style.transform='translateY(-2px)'"
                            onmouseout="this.style.boxShadow='0 2px 8px var(--shadow-color)'; this.style.transform='translateY(0)'">
                            <div
                                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <span
                                    style="font-weight: 600; color: var(--text-primary); font-size: 15px;"><?php echo htmlspecialchars((string) $item['_id']); ?></span>
                                <span
                                    style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 6px 14px; border-radius: 16px; font-size: 13px; font-weight: bold; box-shadow: 0 2px 6px rgba(102,126,234,0.3);"><?php echo number_format($item['count']); ?></span>
                            </div>
                            <div
                                style="background: var(--table-border); height: 12px; border-radius: 6px; overflow: hidden; box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);">
                                <div
                                    style="background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); height: 100%; width: <?php echo $percentage; ?>%; transition: width 0.5s ease-out; box-shadow: 0 0 10px rgba(102,126,234,0.5);">
                                </div>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-top: 8px;">
                                <span
                                    style="font-size: 12px; color: var(--text-secondary);"><?php echo number_format($percentage, 1); ?>%
                                    of total</span>
                                <span
                                    style="font-size: 12px; color: var(--text-secondary); font-weight: 600;"><?php echo number_format($item['count']); ?>
                                    documents</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php unset($_SESSION['viz_data']); ?>
        <?php endif; ?>
    </div>

    <!-- Time Series Analysis -->
    <div
        style="background: var(--card-bg); padding: 25px; border-radius: 12px; margin-top: 20px; box-shadow: 0 4px 15px var(--shadow-color);">
        <h3 style="color: var(--text-primary); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">📅</span> Time Series Analysis
        </h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
            <input type="hidden" name="action" value="timeseries">
            <div style="display: grid; grid-template-columns: 2fr 1fr auto; gap: 12px; margin-bottom: 20px;">
                <div>
                    <label style="color: var(--text-secondary); font-weight: 600; font-size: 13px;">Date
                        Field:</label>
                    <input type="text" name="date_field" placeholder="e.g., createdAt, timestamp" required
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px; font-size: 14px;">
                </div>
                <div>
                    <label style="color: var(--text-secondary); font-weight: 600; font-size: 13px;">Group
                        By:</label>
                    <select name="time_group"
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px; font-size: 14px;">
                        <option value="day">📆 Day</option>
                        <option value="week">📅 Week</option>
                        <option value="month" selected>📊 Month</option>
                        <option value="year">📈 Year</option>
                    </select>
                </div>
                <div style="display: flex; align-items: end;">
                    <button type="submit" class="btn"
                        style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 10px 20px; font-weight: 600;">📅
                        Analyze</button>
                </div>
            </div>
        </form>

        <?php if (isset($_SESSION['timeseries_data'])): ?>
            <?php $tsData = $_SESSION['timeseries_data']; ?>
            <div style="padding: 25px; background: var(--table-header-bg); border-radius: 10px; margin-top: 20px; border: 1px solid var(--table-border);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h4 style="color: var(--text-primary);">Time Series: "<?php echo htmlspecialchars($tsData['field']); ?>" (Grouped by <?php echo htmlspecialchars($tsData['grouping']); ?>)</h4>
                </div>
                <div style="display: grid; gap: 12px;">
                    <?php foreach ($tsData['results'] as $item): ?>
                        <div style="background: var(--card-bg); padding: 15px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-weight: 600; color: var(--text-primary);"><?php echo htmlspecialchars($item['_id']); ?></span>
                            <span style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 6px 14px; border-radius: 16px; font-weight: bold;"><?php echo number_format($item['count']); ?> docs</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php unset($_SESSION['timeseries_data']); ?>
        <?php endif; ?>
    </div>

    <!-- Field Correlation Analysis -->
    <div
        style="background: var(--card-bg); padding: 25px; border-radius: 12px; margin-top: 20px; box-shadow: 0 4px 15px var(--shadow-color);">
        <h3 style="color: var(--text-primary); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">🔗</span> Field Correlation Analysis
        </h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
            <input type="hidden" name="action" value="correlation">
            <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 12px;">
                <div>
                    <label style="color: var(--text-secondary); font-weight: 600; font-size: 13px;">Field 1:</label>
                    <input type="text" name="field1" placeholder="e.g., status" required
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px;">
                </div>
                <div>
                    <label style="color: var(--text-secondary); font-weight: 600; font-size: 13px;">Field 2:</label>
                    <input type="text" name="field2" placeholder="e.g., priority" required
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px;">
                </div>
                <div style="display: flex; align-items: end;">
                    <button type="submit" class="btn"
                        style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 10px 20px; font-weight: 600;">🔗
                        Correlate</button>
                </div>
            </div>
        </form>

        <?php if (isset($_SESSION['correlation_data'])): ?>
            <?php $corrData = $_SESSION['correlation_data']; ?>
            <div style="padding: 25px; background: var(--table-header-bg); border-radius: 10px; margin-top: 20px; border: 1px solid var(--table-border);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h4 style="color: var(--text-primary);">Correlation: "<?php echo htmlspecialchars($corrData['field1']); ?>" × "<?php echo htmlspecialchars($corrData['field2']); ?>"</h4>
                    <span style="background: #4facfe; color: white; padding: 6px 16px; border-radius: 20px; font-size: 14px; font-weight: 600;"><?php echo count($corrData['results']); ?> combinations</span>
                </div>
                <div style="display: grid; gap: 10px;">
                    <?php foreach ($corrData['results'] as $item): ?>
                        <div style="background: var(--card-bg); padding: 15px; border-radius: 8px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <span style="font-weight: 600; color: #667eea;"><?php echo htmlspecialchars(json_encode($item['_id']['field1'])); ?></span>
                                    <span style="color: var(--text-secondary); margin: 0 8px;">×</span>
                                    <span style="font-weight: 600; color: #f5576c;"><?php echo htmlspecialchars(json_encode($item['_id']['field2'])); ?></span>
                                </div>
                                <span style="background: #17a2b8; color: white; padding: 6px 12px; border-radius: 12px; font-size: 13px; font-weight: bold;"><?php echo number_format($item['count']); ?> occurrences</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php unset($_SESSION['correlation_data']); ?>
        <?php endif; ?>
    </div>

    <!-- Data Quality Metrics -->
    <div
        style="background: var(--card-bg); padding: 25px; border-radius: 12px; margin-top: 20px; box-shadow: 0 4px 15px var(--shadow-color);">
        <h3 style="color: var(--text-primary); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">✓</span> Data Quality Metrics
        </h3>
        <?php
        // Calculate data quality metrics
        $qualityMetrics = [
            'total_docs' => $documentCount,
            'empty_docs' => 0,
            'null_fields' => 0,
            'missing_fields' => []
        ];

        // Sample documents for quality check
        $qualitySample = $collection->find([], ['limit' => min(100, $documentCount)])->toArray();
        foreach ($qualitySample as $doc) {
            $docArray = json_decode(json_encode($doc), true);
            if (count($docArray) <= 1) { // Only _id
                $qualityMetrics['empty_docs']++;
            }
            foreach ($docArray as $key => $value) {
                if ($value === null || $value === '') {
                    $qualityMetrics['null_fields']++;
                }
            }
        }

        $completeness = $qualityMetrics['total_docs'] > 0
            ? (($qualityMetrics['total_docs'] - $qualityMetrics['empty_docs']) / $qualityMetrics['total_docs'] * 100)
            : 100;
        ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <div
                style="background: linear-gradient(135deg, #43e97b15 0%, #38f9d715 100%); padding: 20px; border-radius: 10px; border-left: 4px solid #43e97b;">
                <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 8px;">Data Completeness</p>
                <p style="font-size: 32px; font-weight: bold; color: #43e97b; margin-bottom: 8px;">
                    <?php echo number_format($completeness, 1); ?>%
                </p>
                <div style="background: var(--table-border); height: 8px; border-radius: 4px; overflow: hidden;">
                    <div
                        style="background: linear-gradient(90deg, #43e97b 0%, #38f9d7 100%); height: 100%; width: <?php echo $completeness; ?>%; transition: width 0.5s;">
                    </div>
                </div>
            </div>
            <div
                style="background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%); padding: 20px; border-radius: 10px; border-left: 4px solid #667eea;">
                <p style="color: var(--text-secondary); font-size: 13px;">Empty Documents</p>
                <p style="font-size: 32px; font-weight: bold; color: #667eea;">
                    <?php echo number_format($qualityMetrics['empty_docs']); ?>
                </p>
                <p style="color: var(--text-muted); font-size: 12px;">Out of
                    <?php echo number_format(count($qualitySample)); ?> sampled
                </p>
            </div>
            <div
                style="background: linear-gradient(135deg, #f093fb15 0%, #f5576c15 100%); padding: 20px; border-radius: 10px; border-left: 4px solid #f5576c;">
                <p style="color: var(--text-secondary); font-size: 13px;">Null/Empty Fields</p>
                <p style="font-size: 32px; font-weight: bold; color: #f5576c;">
                    <?php echo number_format($qualityMetrics['null_fields']); ?>
                </p>
                <p style="color: var(--text-muted); font-size: 12px;">Fields with null/empty values</p>
            </div>
            <div
                style="background: linear-gradient(135deg, #fa709a15 0%, #fee14015 100%); padding: 20px; border-radius: 10px; border-left: 4px solid #fa709a;">
                <p style="color: var(--text-secondary); font-size: 13px;">Avg Fields per Doc</p>
                <?php
                $avgFields = count($qualitySample) > 0 ? array_sum(array_map(function ($d) {
                    return count(json_decode(json_encode($d), true));
                }, $qualitySample)) / count($qualitySample) : 0;
                ?>
                <p style="font-size: 32px; font-weight: bold; color: #fa709a;">
                    <?php echo number_format($avgFields, 1); ?>
                </p>
                <p style="color: var(--text-muted); font-size: 12px;">Average field count</p>
            </div>
        </div>
    </div>

    <!-- Top Values Analysis -->
    <div
        style="background: var(--card-bg); padding: 25px; border-radius: 12px; margin-top: 20px; box-shadow: 0 4px 15px var(--shadow-color);">
        <h3 style="color: var(--text-primary); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">🔝</span> Top Values Analysis
        </h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
            <input type="hidden" name="action" value="topvalues">
            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 12px;">
                <div>
                    <label style="color: var(--text-secondary); font-weight: 600; font-size: 13px;">Field
                        Name:</label>
                    <input type="text" name="top_field" placeholder="e.g., country, category" required
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px;">
                </div>
                <div>
                    <label style="color: var(--text-secondary); font-weight: 600; font-size: 13px;">Show
                        Top:</label>
                    <select name="top_count"
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px;">
                        <option value="5">Top 5</option>
                        <option value="10" selected>Top 10</option>
                        <option value="20">Top 20</option>
                        <option value="50">Top 50</option>
                    </select>
                </div>
                <div>
                    <label style="color: var(--text-secondary); font-weight: 600; font-size: 13px;">Sort By:</label>
                    <select name="sort_by"
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px;">
                        <option value="count">Count</option>
                        <option value="value">Value</option>
                    </select>
                </div>
                <div style="display: flex; align-items: end;">
                    <button type="submit" class="btn"
                        style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; padding: 10px 20px; font-weight: 600;">🔝
                        Analyze</button>
                </div>
            </div>
        </form>

        <?php if (isset($_SESSION['top_values_data'])): ?>
            <?php $topData = $_SESSION['top_values_data']; ?>
            <div style="padding: 25px; background: var(--table-header-bg); border-radius: 10px; margin-top: 20px; border: 1px solid var(--table-border);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h4 style="color: var(--text-primary);">Top Values: "<?php echo htmlspecialchars($topData['field']); ?>"</h4>
                    <span style="background: #fa709a; color: white; padding: 6px 16px; border-radius: 20px; font-size: 14px; font-weight: 600;"><?php echo count($topData['results']); ?> values</span>
                </div>
                <div style="display: grid; gap: 12px;">
                    <?php foreach ($topData['results'] as $idx => $item): ?>
                        <div style="background: var(--card-bg); padding: 15px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <span style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; padding: 6px 12px; border-radius: 50%; font-weight: bold; font-size: 14px; min-width: 36px; text-align: center;"><?php echo $idx + 1; ?></span>
                                <span style="font-weight: 600; color: var(--text-primary); font-size: 15px;"><?php echo htmlspecialchars(json_encode($item['_id'])); ?></span>
                            </div>
                            <span style="background: #28a745; color: white; padding: 8px 16px; border-radius: 16px; font-weight: bold;"><?php echo number_format($item['count']); ?> docs</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php unset($_SESSION['top_values_data']); ?>
        <?php endif; ?>
    </div>

    <!-- Comparison Analytics -->
    <div
        style="background: var(--card-bg); padding: 25px; border-radius: 12px; margin-top: 20px; box-shadow: 0 4px 15px var(--shadow-color);">
        <h3 style="color: var(--text-primary); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">⚖️</span> Compare Collections
        </h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
            <input type="hidden" name="action" value="comparecollections">
            <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 12px;">
                <div>
                    <label style="color: var(--text-secondary); font-weight: 600; font-size: 13px;">Collection
                        1:</label>
                    <select name="compare_coll1" required
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px;">
                        <option value="">Select collection...</option>
                        <?php foreach ($collectionNames as $cName): ?>
                            <option value="<?php echo htmlspecialchars($cName); ?>" <?php echo $cName === $collectionName ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="color: var(--text-secondary); font-weight: 600; font-size: 13px;">Collection
                        2:</label>
                    <select name="compare_coll2" required
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px;">
                        <option value="">Select collection...</option>
                        <?php foreach ($collectionNames as $cName): ?>
                            <option value="<?php echo htmlspecialchars($cName); ?>">
                                <?php echo htmlspecialchars($cName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display: flex; align-items: end;">
                    <button type="submit" class="btn"
                        style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 10px 20px; font-weight: 600;">⚖️
                        Compare</button>
                </div>
            </div>
        </form>

        <?php if (isset($_SESSION['comparison_data'])): ?>
            <?php $compData = $_SESSION['comparison_data']; ?>
            <div style="padding: 25px; background: var(--table-header-bg); border-radius: 10px; margin-top: 20px; border: 1px solid var(--table-border);">
                <h4 style="color: var(--text-primary); margin-bottom: 20px;">⚖️ Collection Comparison Results</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div style="background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%); padding: 20px; border-radius: 10px; border-left: 4px solid #667eea;">
                        <h5 style="color: #667eea; margin-bottom: 15px; font-size: 18px;">📦 <?php echo htmlspecialchars($compData['collection1']['name']); ?></h5>
                        <div style="display: grid; gap: 10px;">
                            <div style="display: flex; justify-content: space-between; padding: 8px; background: white; border-radius: 6px;">
                                <span style="color: var(--text-secondary);">Documents:</span>
                                <span style="font-weight: bold; color: var(--text-primary);"><?php echo number_format($compData['collection1']['count']); ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 8px; background: white; border-radius: 6px;">
                                <span style="color: var(--text-secondary);">Size:</span>
                                <span style="font-weight: bold; color: var(--text-primary);"><?php echo number_format($compData['collection1']['size'] / 1024, 2); ?> KB</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 8px; background: white; border-radius: 6px;">
                                <span style="color: var(--text-secondary);">Avg Doc Size:</span>
                                <span style="font-weight: bold; color: var(--text-primary);"><?php echo number_format($compData['collection1']['avgSize'], 2); ?> bytes</span>
                            </div>
                        </div>
                    </div>
                    <div style="background: linear-gradient(135deg, #f093fb15 0%, #f5576c15 100%); padding: 20px; border-radius: 10px; border-left: 4px solid #f5576c;">
                        <h5 style="color: #f5576c; margin-bottom: 15px; font-size: 18px;">📦 <?php echo htmlspecialchars($compData['collection2']['name']); ?></h5>
                        <div style="display: grid; gap: 10px;">
                            <div style="display: flex; justify-content: space-between; padding: 8px; background: white; border-radius: 6px;">
                                <span style="color: var(--text-secondary);">Documents:</span>
                                <span style="font-weight: bold; color: var(--text-primary);"><?php echo number_format($compData['collection2']['count']); ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 8px; background: white; border-radius: 6px;">
                                <span style="color: var(--text-secondary);">Size:</span>
                                <span style="font-weight: bold; color: var(--text-primary);"><?php echo number_format($compData['collection2']['size'] / 1024, 2); ?> KB</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 8px; background: white; border-radius: 6px;">
                                <span style="color: var(--text-secondary);">Avg Doc Size:</span>
                                <span style="font-weight: bold; color: var(--text-primary);"><?php echo number_format($compData['collection2']['avgSize'], 2); ?> bytes</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php unset($_SESSION['comparison_data']); ?>
        <?php endif; ?>
    </div>

    <!-- Export Analytics Report -->
    <div
        style="background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%); padding: 20px; border-radius: 12px; margin-top: 20px; border-left: 4px solid #667eea;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h4 style="color: var(--text-primary); margin-bottom: 8px;">📄 Generate Analytics Report</h4>
                <p style="color: var(--text-secondary); font-size: 13px; line-height: 1.6;">
                    Export comprehensive analytics including all metrics, visualizations, and quality assessments
                </p>
            </div>
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                <input type="hidden" name="action" value="exportanalytics">
                <button type="submit" class="btn"
                    style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; padding: 12px 24px; font-weight: 600; white-space: nowrap;">
                    📥 Export Report
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Schema Explorer Tab -->
<div id="schema" class="tab-content">
    <h2 style="margin-bottom: 20px;">📐 Schema Explorer</h2>

    <div
        style="background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%); padding: 20px; border-radius: 12px; margin-bottom: 20px; border-left: 4px solid #667eea;">
        <p style="color: #666; line-height: 1.8;">
            <strong>💡 Info:</strong> Automatically detect and analyze the structure of your documents.
            Shows field types, frequencies, and nested structures.
        </p>
    </div>

    <?php
    // Analyze schema from sample documents
    $sampleSize = min(100, $documentCount);
    $sampleDocs = $collection->find([], ['limit' => $sampleSize])->toArray();

    $schemaAnalysis = [];
    foreach ($sampleDocs as $doc) {
        $docArray = json_decode(json_encode($doc), true);
        foreach ($docArray as $field => $value) {
            if (!isset($schemaAnalysis[$field])) {
                $schemaAnalysis[$field] = [
                    'count' => 0,
                    'types' => [],
                    'samples' => []
                ];
            }
            $schemaAnalysis[$field]['count']++;

            $type = gettype($value);
            if ($type === 'object' || $type === 'array') {
                $type = is_array($value) ? 'array' : 'object';
            }

            if (!in_array($type, $schemaAnalysis[$field]['types'])) {
                $schemaAnalysis[$field]['types'][] = $type;
            }

            if (count($schemaAnalysis[$field]['samples']) < 3) {
                $sampleValue = $value;
                if (is_array($sampleValue) || is_object($sampleValue)) {
                    $sampleValue = json_encode($sampleValue);
                    if (strlen($sampleValue) > 50) {
                        $sampleValue = substr($sampleValue, 0, 50) . '...';
                    }
                } else {
                    $sampleValue = (string) $sampleValue;
                    if (strlen($sampleValue) > 50) {
                        $sampleValue = substr($sampleValue, 0, 50) . '...';
                    }
                }
                $schemaAnalysis[$field]['samples'][] = $sampleValue;
            }
        }
    }

    // Sort by frequency
    uasort($schemaAnalysis, function ($a, $b) {
        return $b['count'] - $a['count'];
    });
    ?>

    <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
            <h3 style="color: #333; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">🔍</span> Detected Fields
            </h3>
            <span
                style="background: #667eea; color: white; padding: 8px 16px; border-radius: 20px; font-size: 14px; font-weight: 600;">
                <?php echo count($schemaAnalysis); ?> fields found
            </span>
        </div>

        <div style="display: grid; gap: 15px;">
            <?php foreach ($schemaAnalysis as $fieldName => $fieldInfo): ?>
                <div style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 20px; border-radius: 10px; border-left: 4px solid #667eea; transition: all 0.3s;"
                    onmouseover="this.style.transform='translateX(5px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'"
                    onmouseout="this.style.transform='translateX(0)'; this.style.boxShadow='none'">
                    <div style="display: grid; grid-template-columns: 200px 1fr 150px; gap: 20px; align-items: start;">
                        <div>
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                <span style="font-size: 20px;">📌</span>
                                <strong
                                    style="color: #333; font-size: 15px;"><?php echo htmlspecialchars($fieldName); ?></strong>
                            </div>
                            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                <?php foreach ($fieldInfo['types'] as $type): ?>
                                    <span
                                        style="background: #667eea; color: white; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                                        <?php echo htmlspecialchars($type); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div>
                            <p style="color: #666; font-size: 13px; margin-bottom: 8px;"><strong>Sample Values:</strong>
                            </p>
                            <div style="display: flex; flex-direction: column; gap: 6px;">
                                <?php foreach ($fieldInfo['samples'] as $sample): ?>
                                    <code
                                        style="background: white; padding: 6px 10px; border-radius: 4px; font-size: 12px; border: 1px solid #dee2e6; display: block; overflow-x: auto;">
                                                                                                                <?php echo htmlspecialchars($sample); ?>
                                                                                                            </code>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div style="text-align: center;">
                            <p style="color: #666; font-size: 12px; margin-bottom: 6px;">Frequency</p>
                            <div
                                style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 10px; border-radius: 8px;">
                                <div style="font-size: 24px; font-weight: bold;">
                                    <?php echo round(($fieldInfo['count'] / $sampleSize) * 100); ?>%
                                </div>
                                <div style="font-size: 11px; opacity: 0.9;">
                                    <?php echo $fieldInfo['count']; ?> / <?php echo $sampleSize; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($schemaAnalysis)): ?>
            <div style="text-align: center; padding: 60px 20px; color: #999;">
                <div style="font-size: 64px; margin-bottom: 20px;">📭</div>
                <p style="font-size: 18px; color: #666;">No documents found to analyze</p>
                <p style="font-size: 14px; color: #999; margin-top: 10px;">Add some documents to see the schema
                    structure
                </p>
            </div>
        <?php endif; ?>
    </div>

    <div
        style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-top: 20px;">
        <h3 style="margin-bottom: 20px; color: #333; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">📊</span> Schema Statistics
        </h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <div
                style="background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%); padding: 20px; border-radius: 8px; border: 2px solid #667eea;">
                <p style="color: #666; font-size: 13px;">Total Fields</p>
                <p style="font-size: 32px; font-weight: bold; color: #667eea; margin-top: 8px;">
                    <?php echo count($schemaAnalysis); ?>
                </p>
            </div>
            <div
                style="background: linear-gradient(135deg, #f093fb15 0%, #f5576c15 100%); padding: 20px; border-radius: 8px; border: 2px solid #f5576c;">
                <p style="color: #666; font-size: 13px;">Analyzed Docs</p>
                <p style="font-size: 32px; font-weight: bold; color: #f5576c; margin-top: 8px;">
                    <?php echo $sampleSize; ?>
                </p>
            </div>
            <div
                style="background: linear-gradient(135deg, #43e97b15 0%, #38f9d715 100%); padding: 20px; border-radius: 8px; border: 2px solid #43e97b;">
                <p style="color: #666; font-size: 13px;">Collection</p>
                <p style="font-size: 20px; font-weight: bold; color: #43e97b; margin-top: 8px;">
                    <?php echo htmlspecialchars($collectionName); ?>
                </p>
            </div>
        </div>
    </div>

    <div
        style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-top: 20px;">
        <h3 style="margin-bottom: 20px; color: #333; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">📋</span> Collection Indexes
        </h3>
        <?php
        try {
            if (method_exists($collection, 'listIndexes')) {
                $indexes = $collection->listIndexes();
                $hasIndexes = false;
                foreach ($indexes as $index) {
                    $hasIndexes = true;
                    $indexName = isset($index['name']) ? $index['name'] : 'Unknown';
                    $indexKeys = isset($index['key']) ? $index['key'] : [];
                    echo '<div style="background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%); padding: 15px; border-radius: 8px; margin-bottom: 12px; border-left: 4px solid #667eea;">';
                    echo '<p style="font-weight: 600; margin-bottom: 8px; color: #333; font-size: 15px;">📌 ' . htmlspecialchars($indexName) . '</p>';
                    if (!empty($indexKeys)) {
                        echo '<div style="background: white; padding: 10px; border-radius: 4px; margin-top: 8px;">';
                        echo '<code style="font-size: 13px; color: #666;">';
                        $keyStr = [];
                        foreach ($indexKeys as $field => $direction) {
                            $keyStr[] = htmlspecialchars($field) . ': ' . ($direction == 1 ? '↑' : '↓');
                        }
                        echo implode(', ', $keyStr);
                        echo '</code>';
                        echo '</div>';
                    }
                    echo '</div>';
                }
                if (!$hasIndexes) {
                    echo '<p style="color: #666; text-align: center; padding: 20px;">No indexes found</p>';
                }
            } else {
                echo '<p style="color: #666; text-align: center; padding: 20px;">Index management not available</p>';
            }
        } catch (Exception $e) {
            echo '<p style="color: #666; text-align: center; padding: 20px;">Unable to load indexes</p>';
        }
        ?>
    </div>
</div>

<!-- Security & Backup Tab -->
<div id="security" class="tab-content">
    <h2 style="margin-bottom: 20px;">🔒 Security & Backup</h2>

    <div
        style="background: linear-gradient(135deg, #dc354515 0%, #ff000015 100%); padding: 20px; border-radius: 12px; margin-bottom: 20px; border-left: 4px solid #dc3545;">
        <p style="color: #721c24; line-height: 1.8;">
            <strong>⚠️ Important:</strong> This panel includes critical database operations.
            Always create backups before making bulk changes. CSRF protection and rate limiting are active.
        </p>
    </div>

    <?php
    // Handle backup action
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'create_backup') {
            $backupResult = createDatabaseBackup($database);
            if ($backupResult['success']) {
                $message = 'Backup created successfully: ' . $backupResult['file'] . ' (' . round($backupResult['size'] / 1024, 2) . ' KB)';
                $messageType = 'success';
                auditLog('backup_created', $backupResult);
            } else {
                $message = 'Backup failed: ' . $backupResult['error'];
                $messageType = 'error';
            }
        }
    }

    $backups = listBackups();
    ?>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
        <!-- Backup Section -->
        <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="margin-bottom: 20px; color: #333; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">💾</span> Database Backup
            </h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_backup">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <p style="color: #666; margin-bottom: 15px; font-size: 14px;">
                    Create a complete backup of all collections in this database. Backups are compressed and stored
                    locally.
                </p>
                <button type="submit" class="btn"
                    style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; width: 100%; padding: 15px; font-size: 16px;">
                    💾 Create Backup Now
                </button>
            </form>

            <?php if (!empty($backups)): ?>
                <div style="margin-top: 25px; padding-top: 20px; border-top: 2px solid #e9ecef;">
                    <h4 style="color: #333; margin-bottom: 15px; font-size: 14px;">📂 Available Backups</h4>
                    <?php foreach ($backups as $backup): ?>
                        <div
                            style="background: #f8f9fa; padding: 12px; border-radius: 6px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong
                                    style="color: #333; font-size: 13px;"><?php echo htmlspecialchars($backup['name']); ?></strong>
                                <p style="color: #666; font-size: 11px; margin-top: 4px;">
                                    <?php echo $backup['date']; ?> • <?php echo round($backup['size'] / 1024, 2); ?> KB
                                </p>
                            </div>
                            <a href="backups/<?php echo htmlspecialchars($backup['name']); ?>" download class="btn"
                                style="background: #17a2b8; color: white; padding: 6px 12px; font-size: 11px; text-decoration: none;">
                                📥 Download
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Security Settings -->
        <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="margin-bottom: 20px; color: #333; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">🛡️</span> Security Settings
            </h3>

            <div style="display: flex; flex-direction: column; gap: 15px;">
                <div
                    style="padding: 15px; background: linear-gradient(135deg, #28a74515 0%, #20c99715 100%); border-radius: 8px; border-left: 3px solid #28a745;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="color: #333;">CSRF Protection</strong>
                            <p style="color: #666; font-size: 12px; margin-top: 4px;">Prevents cross-site request
                                forgery</p>
                        </div>
                        <span
                            style="background: #28a745; color: white; padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: 600;">ACTIVE</span>
                    </div>
                </div>

                <div
                    style="padding: 15px; background: linear-gradient(135deg, #28a74515 0%, #20c99715 100%); border-radius: 8px; border-left: 3px solid #28a745;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="color: #333;">Rate Limiting</strong>
                            <p style="color: #666; font-size: 12px; margin-top: 4px;">30 requests per minute</p>
                        </div>
                        <span
                            style="background: #28a745; color: white; padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: 600;">ACTIVE</span>
                    </div>
                </div>

                <div
                    style="padding: 15px; background: linear-gradient(135deg, #28a74515 0%, #20c99715 100%); border-radius: 8px; border-left: 3px solid #28a745;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="color: #333;">Input Sanitization</strong>
                            <p style="color: #666; font-size: 12px; margin-top: 4px;">XSS and injection prevention
                            </p>
                        </div>
                        <span
                            style="background: #28a745; color: white; padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: 600;">ACTIVE</span>
                    </div>
                </div>

                <div
                    style="padding: 15px; background: linear-gradient(135deg, #28a74515 0%, #20c99715 100%); border-radius: 8px; border-left: 3px solid #28a745;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="color: #333;">Query Validation</strong>
                            <p style="color: #666; font-size: 12px; margin-top: 4px;">Operator whitelisting enabled
                            </p>
                        </div>
                        <span
                            style="background: #28a745; color: white; padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: 600;">ACTIVE</span>
                    </div>
                </div>
            </div>

            <div
                style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 8px; border: 1px solid #ffc107;">
                <p style="color: #856404; font-size: 13px; line-height: 1.6;">
                    <strong>💡 Security Tips:</strong><br>
                    • Change default credentials<br>
                    • Use firewall rules<br>
                    • Enable SSL/TLS connections<br>
                    • Regular security audits
                </p>
            </div>
        </div>
    </div>

    <!-- Audit Log -->
    <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <h3 style="margin-bottom: 20px; color: #333; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">📋</span> Recent Activity (Audit Log)
        </h3>

        <?php
        try {
            $auditCollection = $database->selectCollection('_audit_log');
            $recentLogs = $auditCollection->find([], [
                'sort' => ['timestamp' => -1],
                'limit' => 10
            ])->toArray();

            if (!empty($recentLogs)):
                ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                                <th style="padding: 12px; text-align: left; font-size: 13px; color: #666;">Timestamp</th>
                                <th style="padding: 12px; text-align: left; font-size: 13px; color: #666;">Action</th>
                                <th style="padding: 12px; text-align: left; font-size: 13px; color: #666;">User</th>
                                <th style="padding: 12px; text-align: left; font-size: 13px; color: #666;">IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentLogs as $log): ?>
                                <tr style="border-bottom: 1px solid #e9ecef;">
                                    <td style="padding: 12px; font-size: 12px;">
                                        <?php echo date('Y-m-d H:i:s', $log->timestamp->toDateTime()->getTimestamp()); ?>
                                    </td>
                                    <td style="padding: 12px; font-size: 12px;">
                                        <span
                                            style="background: #667eea; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px;">
                                            <?php echo htmlspecialchars($log->action); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 12px; font-size: 12px;"><?php echo htmlspecialchars($log->user); ?></td>
                                    <td style="padding: 12px; font-size: 12px; font-family: monospace;">
                                        <?php echo htmlspecialchars($log->ip); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #999;">
                    <p>No audit logs available</p>
                </div>
            <?php endif; ?>
        <?php } catch (Exception $e) {
            echo '<p style="color: #999;">Audit log not available</p>';
        } ?>
    </div>

    <!-- Security Logs Viewer -->
    <div
        style="background: white; padding: 25px; border-radius: 12px; margin-top: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="color: #333; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">🔍</span> Security Logs
            </h3>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="clear_logs">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <button type="submit" class="btn"
                    style="background: #dc3545; color: white; padding: 8px 16px; font-size: 13px;"
                    onclick="return confirm('Clear all security logs?')">🗑️ Clear Logs</button>
            </form>
        </div>

        <?php
        $logFile = __DIR__ . '/logs/security.log';
        if (file_exists($logFile)) {
            $logContent = file_get_contents($logFile);
            $logLines = array_filter(explode(PHP_EOL, $logContent));
            $logLines = array_slice(array_reverse($logLines), 0, 20); // Last 20 entries
        
            if (!empty($logLines)):
                ?>
                <div
                    style="max-height: 400px; overflow-y: auto; background: #f8f9fa; padding: 15px; border-radius: 8px; font-family: monospace; font-size: 12px;">
                    <?php foreach ($logLines as $line):
                        $logEntry = json_decode($line, true);
                        if ($logEntry):
                            $severity = 'info';
                            if (strpos($logEntry['event'], 'failed') !== false || strpos($logEntry['event'], 'violation') !== false) {
                                $severity = 'danger';
                            } elseif (strpos($logEntry['event'], 'warning') !== false) {
                                $severity = 'warning';
                            }
                            $bgColor = $severity === 'danger' ? '#f8d7da' : ($severity === 'warning' ? '#fff3cd' : '#d1ecf1');
                            $textColor = $severity === 'danger' ? '#721c24' : ($severity === 'warning' ? '#856404' : '#0c5460');
                            ?>
                            <div
                                style="background: <?php echo $bgColor; ?>; color: <?php echo $textColor; ?>; padding: 10px; margin-bottom: 8px; border-radius: 6px; border-left: 3px solid currentColor;">
                                <div style="display: flex; justify-content: space-between; align-items: start;">
                                    <div style="flex: 1;">
                                        <strong><?php echo htmlspecialchars($logEntry['event']); ?></strong>
                                        <div style="margin-top: 5px; opacity: 0.8; font-size: 11px;">
                                            IP: <?php echo htmlspecialchars($logEntry['ip']); ?> |
                                            Session: <?php echo substr($logEntry['session'], 0, 10); ?>... |
                                            <?php echo htmlspecialchars($logEntry['timestamp']); ?>
                                        </div>
                                        <?php if (!empty($logEntry['details'])): ?>
                                            <div style="margin-top: 5px; opacity: 0.7; font-size: 11px;">
                                                Details: <?php echo htmlspecialchars(json_encode($logEntry['details'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php
                        endif;
                    endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <div style="font-size: 48px; margin-bottom: 15px;">📭</div>
                    <p>No security logs found</p>
                </div>
            <?php endif; ?>
        <?php } else { ?>
            <div style="text-align: center; padding: 40px; color: #666;">
                <div style="font-size: 48px; margin-bottom: 15px;">📝</div>
                <p>Log file not created yet</p>
            </div>
        <?php } ?>
    </div>
</div>

    <!-- Settings Tab -->
    <div id="settings" class="tab-content">
        <h2 style="margin-bottom: 25px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 32px;">⚙️</span> Application Settings
        </h2>

        <!-- Connection Settings -->
        <div
            style="background: white; padding: 25px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="color: #333; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">🔌</span> Connection Settings
            </h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #667eea;">
                    <h4 style="color: #667eea; margin-bottom: 15px;">Current Connection</h4>
                    <div style="display: grid; gap: 10px; font-size: 14px;">
                        <div><strong>Host:</strong> <span
                                style="font-family: monospace; color: #666;"><?php echo htmlspecialchars($_SESSION['hostname'] ?? 'localhost'); ?></span>
                        </div>
                        <div><strong>Port:</strong> <span
                                style="font-family: monospace; color: #666;"><?php echo htmlspecialchars($_SESSION['port'] ?? '27017'); ?></span>
                        </div>
                        <div><strong>Database:</strong> <span
                                style="font-family: monospace; color: #28a745;"><?php echo htmlspecialchars($_SESSION['database'] ?? 'N/A'); ?></span>
                        </div>
                        <div><strong>Collection:</strong> <span
                                style="font-family: monospace; color: #17a2b8;"><?php echo htmlspecialchars($_SESSION['collection'] ?? 'N/A'); ?></span>
                        </div>
                        <div><strong>Username:</strong> <span
                                style="font-family: monospace; color: #666;"><?php echo !empty($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : '<i>None</i>'; ?></span>
                        </div>
                    </div>
                </div>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #28a745;">
                    <h4 style="color: #28a745; margin-bottom: 15px;">Connection Options</h4>
                    <div style="display: grid; gap: 8px; font-size: 13px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" checked disabled>
                            <span>Persistent Connections</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" checked disabled>
                            <span>Auto-reconnect on Timeout</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" checked disabled>
                            <span>Connection Pooling</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" checked disabled>
                            <span>SSL/TLS Encryption</span>
                        </label>
                    </div>
                    <a href="templates/connection.php" class="btn"
                        style="display: inline-block; margin-top: 15px; background: #28a745; color: white; padding: 8px 16px; text-decoration: none; border-radius: 6px; font-size: 13px;">🔄
                        Change Connection</a>
                </div>
            </div>
        </div>

        <!-- Display Preferences -->
        <div
            style="background: white; padding: 25px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="color: #333; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">🎨</span> Display Preferences
            </h3>
            <form method="POST">
                <input type="hidden" name="action" value="save_display_settings">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                    <div>
                        <label style="font-weight: 600; margin-bottom: 8px; display: block;">Items per Page:</label>
                        <select name="items_per_page"
                            style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                            <option value="25" <?php echo ($_SESSION['settings']['items_per_page'] ?? 50) == 25 ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo ($_SESSION['settings']['items_per_page'] ?? 50) == 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo ($_SESSION['settings']['items_per_page'] ?? 50) == 100 ? 'selected' : ''; ?>>100</option>
                            <option value="200" <?php echo ($_SESSION['settings']['items_per_page'] ?? 50) == 200 ? 'selected' : ''; ?>>200</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-weight: 600; margin-bottom: 8px; display: block;">Date Format:</label>
                        <select name="date_format"
                            style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                            <option value="Y-m-d H:i:s" <?php echo ($_SESSION['settings']['date_format'] ?? 'Y-m-d H:i:s') == 'Y-m-d H:i:s' ? 'selected' : ''; ?>>YYYY-MM-DD HH:MM:SS</option>
                            <option value="d/m/Y H:i" <?php echo ($_SESSION['settings']['date_format'] ?? '') == 'd/m/Y H:i' ? 'selected' : ''; ?>>DD/MM/YYYY HH:MM</option>
                            <option value="m/d/Y h:i A" <?php echo ($_SESSION['settings']['date_format'] ?? '') == 'm/d/Y h:i A' ? 'selected' : ''; ?>>MM/DD/YYYY HH:MM AM/PM</option>
                            <option value="relative" <?php echo ($_SESSION['settings']['date_format'] ?? '') == 'relative' ? 'selected' : ''; ?>>Relative (2 hours ago)</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-weight: 600; margin-bottom: 8px; display: block;">Theme:</label>
                        <select name="theme"
                            style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                            <option value="light" <?php echo ($_SESSION['settings']['theme'] ?? 'light') == 'light' ? 'selected' : ''; ?>>Light</option>
                            <option value="dark" <?php echo ($_SESSION['settings']['theme'] ?? 'light') == 'dark' ? 'selected' : ''; ?>>Dark</option>
                            <option value="auto" <?php echo ($_SESSION['settings']['theme'] ?? 'light') == 'auto' ? 'selected' : ''; ?>>Auto (System)</option>
                        </select>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                    <div>
                        <label style="font-weight: 600; margin-bottom: 8px; display: block;">JSON Display:</label>
                        <div style="display: grid; gap: 8px;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="syntax_highlighting" value="1" <?php echo ($_SESSION['settings']['syntax_highlighting'] ?? true) ? 'checked' : ''; ?>>
                                <span>Syntax Highlighting</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="pretty_print" value="1" <?php echo ($_SESSION['settings']['pretty_print'] ?? true) ? 'checked' : ''; ?>>
                                <span>Pretty Print (Formatted)</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="show_objectid_as_string" value="1" <?php echo ($_SESSION['settings']['show_objectid_as_string'] ?? false) ? 'checked' : ''; ?>>
                                <span>Show ObjectId as String</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="collapsible_json" value="1" <?php echo ($_SESSION['settings']['collapsible_json'] ?? false) ? 'checked' : ''; ?>>
                                <span>Collapsible JSON Trees</span>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label style="font-weight: 600; margin-bottom: 8px; display: block;">Table Display:</label>
                        <div style="display: grid; gap: 8px;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="zebra_stripes" value="1" <?php echo ($_SESSION['settings']['zebra_stripes'] ?? true) ? 'checked' : ''; ?>>
                                <span>Zebra Stripes</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="row_hover" value="1" <?php echo ($_SESSION['settings']['row_hover'] ?? true) ? 'checked' : ''; ?>>
                                <span>Row Hover Effect</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="fixed_header" value="1" <?php echo ($_SESSION['settings']['fixed_header'] ?? false) ? 'checked' : ''; ?>>
                                <span>Fixed Header</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="compact_mode" value="1" <?php echo ($_SESSION['settings']['compact_mode'] ?? false) ? 'checked' : ''; ?>>
                                <span>Compact Mode</span>
                            </label>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn"
                    style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 30px; margin-top: 20px;">💾
                    Save Display Settings</button>
            </form>
        </div>

        <!-- Performance Settings -->
        <div
            style="background: white; padding: 25px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="color: #333; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">⚡</span> Performance Settings
            </h3>
            <form method="POST">
                <input type="hidden" name="action" value="save_performance_settings">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                        <h4 style="color: #333; margin-bottom: 15px;">Query Optimization</h4>
                        <div style="display: grid; gap: 12px;">
                            <div>
                                <label
                                    style="font-weight: 600; margin-bottom: 8px; display: block; font-size: 14px;">Query
                                    Timeout (seconds):</label>
                                <input type="number" name="query_timeout"
                                    value="<?php echo $_SESSION['settings']['query_timeout'] ?? 30; ?>" min="5"
                                    max="300"
                                    style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 6px;">
                            </div>
                            <div>
                                <label
                                    style="font-weight: 600; margin-bottom: 8px; display: block; font-size: 14px;">Max
                                    Results Limit:</label>
                                <input type="number" name="max_results"
                                    value="<?php echo $_SESSION['settings']['max_results'] ?? 1000; ?>" min="100"
                                    max="10000"
                                    style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 6px;">
                            </div>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="query_cache" value="1" <?php echo ($_SESSION['settings']['query_cache'] ?? true) ? 'checked' : ''; ?>>
                                <span style="font-size: 14px;">Enable Query Caching</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="auto_indexes" value="1" <?php echo ($_SESSION['settings']['auto_indexes'] ?? true) ? 'checked' : ''; ?>>
                                <span style="font-size: 14px;">Use Indexes Automatically</span>
                            </label>
                        </div>
                    </div>
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                        <h4 style="color: #333; margin-bottom: 15px;">Memory & Cache</h4>
                        <div style="display: grid; gap: 12px;">
                            <div>
                                <label
                                    style="font-weight: 600; margin-bottom: 8px; display: block; font-size: 14px;">Memory
                                    Limit (MB):</label>
                                <input type="number" name="memory_limit"
                                    value="<?php echo $_SESSION['settings']['memory_limit'] ?? 256; ?>" min="128"
                                    max="2048"
                                    style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 6px;">
                            </div>
                            <div>
                                <label
                                    style="font-weight: 600; margin-bottom: 8px; display: block; font-size: 14px;">Cache
                                    TTL (minutes):</label>
                                <input type="number" name="cache_ttl"
                                    value="<?php echo $_SESSION['settings']['cache_ttl'] ?? 15; ?>" min="1" max="1440"
                                    style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 6px;">
                            </div>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="schema_cache" value="1" <?php echo ($_SESSION['settings']['schema_cache'] ?? false) ? 'checked' : ''; ?>>
                                <span style="font-size: 14px;">Enable Schema Caching</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="lazy_load" value="1" <?php echo ($_SESSION['settings']['lazy_load'] ?? false) ? 'checked' : ''; ?>>
                                <span style="font-size: 14px;">Lazy Load Large Documents</span>
                            </label>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn"
                    style="background: #ffc107; color: #333; padding: 12px 30px; margin-top: 20px;">⚡ Save
                    Performance
                    Settings</button>
            </form>
        </div>

        <!-- Security Settings -->
        <div
            style="background: white; padding: 25px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="color: #333; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">🔒</span> Security Settings
            </h3>
            <form method="POST">
                <input type="hidden" name="action" value="save_security_settings">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div
                        style="background: #fff3cd; padding: 20px; border-radius: 8px; border-left: 4px solid #ffc107;">
                        <h4 style="color: #856404; margin-bottom: 15px;">CSRF Protection</h4>
                        <div style="display: grid; gap: 10px;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" checked disabled>
                                <span style="font-size: 14px;">✅ CSRF Tokens Enabled</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" checked disabled>
                                <span style="font-size: 14px;">✅ Session Validation</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" checked disabled>
                                <span style="font-size: 14px;">✅ IP Address Tracking</span>
                            </label>
                            <div style="margin-top: 10px; padding: 10px; background: white; border-radius: 6px;">
                                <label
                                    style="font-weight: 600; margin-bottom: 8px; display: block; font-size: 13px;">Token
                                    Lifetime (minutes):</label>
                                <input type="number" name="csrf_token_lifetime"
                                    value="<?php echo $_SESSION['settings']['csrf_token_lifetime'] ?? 60; ?>" min="10"
                                    max="1440"
                                    style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 6px;">
                            </div>
                        </div>
                    </div>
                    <div
                        style="background: #f8d7da; padding: 20px; border-radius: 8px; border-left: 4px solid #dc3545;">
                        <h4 style="color: #721c24; margin-bottom: 15px;">Rate Limiting</h4>
                        <div style="display: grid; gap: 10px;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" checked disabled>
                                <span style="font-size: 14px;">✅ Rate Limiting Active</span>
                            </label>
                            <div style="background: white; padding: 10px; border-radius: 6px; margin-top: 5px;">
                                <label
                                    style="font-weight: 600; margin-bottom: 8px; display: block; font-size: 13px;">Max
                                    Requests/Minute:</label>
                                <input type="number" name="rate_limit_requests"
                                    value="<?php echo $_SESSION['settings']['rate_limit_requests'] ?? 30; ?>" min="10"
                                    max="1000"
                                    style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 6px;">
                            </div>
                            <div style="background: white; padding: 10px; border-radius: 6px;">
                                <label
                                    style="font-weight: 600; margin-bottom: 8px; display: block; font-size: 13px;">Lockout
                                    Duration (seconds):</label>
                                <input type="number" name="rate_limit_lockout"
                                    value="<?php echo $_SESSION['settings']['rate_limit_lockout'] ?? 60; ?>" min="30"
                                    max="3600"
                                    style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 6px;">
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    style="margin-top: 20px; padding: 15px; background: #d1ecf1; border-radius: 8px; border-left: 4px solid #17a2b8;">
                    <h4 style="color: #0c5460; margin-bottom: 10px;">Audit Logging</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="log_all_actions" value="1" <?php echo ($_SESSION['settings']['log_all_actions'] ?? true) ? 'checked' : ''; ?>>
                            <span style="font-size: 14px;">Log All Actions</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="log_failed_logins" value="1" <?php echo ($_SESSION['settings']['log_failed_logins'] ?? true) ? 'checked' : ''; ?>>
                            <span style="font-size: 14px;">Log Failed Logins</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="log_security_events" value="1" <?php echo ($_SESSION['settings']['log_security_events'] ?? true) ? 'checked' : ''; ?>>
                            <span style="font-size: 14px;">Log Security Events</span>
                        </label>
                    </div>
                </div>
                <button type="submit" class="btn"
                    style="background: #dc3545; color: white; padding: 12px 30px; margin-top: 20px;">🔒 Save
                    Security
                    Settings</button>
            </form>
        </div>

        <!-- System Information -->
        <div
            style="background: white; padding: 25px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="color: #333; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">💻</span> System Information
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                <div
                    style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px;">
                    <p style="font-size: 13px; opacity: 0.9; margin-bottom: 5px;">PHP Version</p>
                    <p style="font-size: 24px; font-weight: bold;"><?php echo phpversion(); ?></p>
                </div>
                <div
                    style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 20px; border-radius: 10px;">
                    <p style="font-size: 13px; opacity: 0.9; margin-bottom: 5px;">MongoDB Extension</p>
                    <p style="font-size: 24px; font-weight: bold;"><?php echo phpversion('mongodb') ?: 'N/A'; ?></p>
                </div>
                <div
                    style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 20px; border-radius: 10px;">
                    <p style="font-size: 13px; opacity: 0.9; margin-bottom: 5px;">Server Software</p>
                    <p style="font-size: 16px; font-weight: bold;">
                        <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?>
                    </p>
                </div>
                <div
                    style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; padding: 20px; border-radius: 10px;">
                    <p style="font-size: 13px; opacity: 0.9; margin-bottom: 5px;">Memory Limit</p>
                    <p style="font-size: 24px; font-weight: bold;"><?php echo ini_get('memory_limit'); ?></p>
                </div>
            </div>

            <div style="margin-top: 20px; background: #f8f9fa; padding: 20px; border-radius: 8px;">
                <h4 style="color: #333; margin-bottom: 15px;">Loaded Extensions</h4>
                <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                    <?php
                    $extensions = ['mongodb', 'json', 'mbstring', 'openssl', 'curl', 'session', 'fileinfo'];
                    foreach ($extensions as $ext) {
                        $loaded = extension_loaded($ext);
                        $bgColor = $loaded ? '#d4edda' : '#f8d7da';
                        $textColor = $loaded ? '#155724' : '#721c24';
                        $icon = $loaded ? '✅' : '❌';
                        echo '<span style="background: ' . $bgColor . '; color: ' . $textColor . '; padding: 6px 12px; border-radius: 16px; font-size: 12px; font-weight: 600;">' . $icon . ' ' . $ext . '</span>';
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Export/Import Settings -->
        <div
            style="background: white; padding: 25px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="color: #333; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">📦</span> Settings Management
            </h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div style="background: #e8f5e9; padding: 20px; border-radius: 8px; border-left: 4px solid #4caf50;">
                    <h4 style="color: #2e7d32; margin-bottom: 15px;">📤 Export Settings</h4>
                    <p style="color: #666; font-size: 14px; margin-bottom: 15px;">Download all your application
                        settings
                        as
                        a JSON file for backup or migration.</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="export_settings">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <button type="submit" class="btn"
                            style="background: #4caf50; color: white; width: 100%; padding: 12px;">📥 Export
                            Settings
                            JSON</button>
                    </form>
                </div>
                <div style="background: #e3f2fd; padding: 20px; border-radius: 8px; border-left: 4px solid #2196f3;">
                    <h4 style="color: #1565c0; margin-bottom: 15px;">📥 Import Settings</h4>
                    <p style="color: #666; font-size: 14px; margin-bottom: 15px;">Upload a settings JSON file to
                        restore
                        your configuration.</p>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="import_settings">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="file" name="settings_file" accept=".json" required
                            style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 6px; margin-bottom: 10px;">
                        <button type="submit" class="btn"
                            style="background: #2196f3; color: white; width: 100%; padding: 12px;">⬆️ Import
                            Settings</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Danger Zone -->
        <div
            style="background: white; padding: 25px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: 2px solid #dc3545;">
            <h3 style="color: #dc3545; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">⚠️</span> Danger Zone
            </h3>
            <div style="background: #f8d7da; padding: 20px; border-radius: 8px; border-left: 4px solid #dc3545;">
                <div style="display: grid; gap: 15px;">
                    <div>
                        <h4 style="color: #721c24; margin-bottom: 10px;">🗑️ Clear Application Cache</h4>
                        <p style="color: #666; font-size: 14px; margin-bottom: 10px;">Remove all cached data
                            including
                            query
                            results and schema information.</p>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Clear all cache?');">
                            <input type="hidden" name="action" value="clear_cache">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <button type="submit" class="btn"
                                style="background: #ffc107; color: #333; padding: 10px 20px;">🗑️ Clear
                                Cache</button>
                        </form>
                    </div>
                    <div style="border-top: 1px solid #f5c6cb; padding-top: 15px;">
                        <h4 style="color: #721c24; margin-bottom: 10px;">🔄 Reset All Settings</h4>
                        <p style="color: #666; font-size: 14px; margin-bottom: 10px;">Reset all application settings
                            to
                            default values. This cannot be undone!</p>
                        <form method="POST" style="display: inline;"
                            onsubmit="return confirm('Reset ALL settings to defaults? This cannot be undone!');">
                            <input type="hidden" name="action" value="reset_settings">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <button type="submit" class="btn"
                                style="background: #dc3545; color: white; padding: 10px 20px;">⚠️ Reset to
                                Defaults</button>
                        </form>
                    </div>
                    <div style="border-top: 1px solid #f5c6cb; padding-top: 15px;">
                        <h4 style="color: #721c24; margin-bottom: 10px;">🧹 Clear Session Data</h4>
                        <p style="color: #666; font-size: 14px; margin-bottom: 10px;">End current session and clear
                            all
                            stored session data.</p>
                        <a href="templates/connection.php" class="btn"
                            style="background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; display: inline-block;">🚪
                            Logout & Clear Session</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
        if (alertMessage) {
            setTimeout(function () {
                alertMessage.style.animation = 'fadeOut 0.5s ease-out';
                setTimeout(function () {
                    alertMessage.remove();
                }, 500);
            }, 5000);
        }

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

        function toggleView() {
            const tableView = document.getElementById('tableView');
            const gridView = document.getElementById('gridView');
            const viewIcon = document.getElementById('viewIcon');
            const viewText = document.getElementById('viewText');

            if (tableView.style.display === 'none') {
                tableView.style.display = 'block';
                gridView.style.display = 'none';
                viewIcon.textContent = '📊';
                viewText.textContent = 'Grid View';
            } else {
                tableView.style.display = 'none';
                gridView.style.display = 'block';
                viewIcon.textContent = '📋';
                viewText.textContent = 'Table View';
            }
        }

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

            if (confirm(`Delete ${selected.length} selected documents? This cannot be undone!`)) {
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
            if (confirm('Delete this document? This cannot be undone!')) {
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
                const interval = parseInt(document.getElementById('refreshInterval')?.textContent || '30');
                autoRefreshInterval = setInterval(() => {
                    window.location.reload();
                }, interval * 1000);
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
                        <textarea name="doc_data" id="editDocData" required style="width: 100%; min-height: 400px; padding: 15px; border: 2px solid #dee2e6; border-radius: 8px; font-family: 'Courier New', monospace; font-size: 13px; line-height: 1.5; background: #1e1e1e; color: #d4d4d4;">${escapeHtml(formatted)}</textarea>
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