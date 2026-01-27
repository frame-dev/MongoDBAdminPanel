<?php
/**
 * Security Configuration and Helper Functions
 * 
 * Core security functions for CSRF protection, input sanitization, validation,
 * rate limiting, and audit logging. Implements enterprise-grade security measures.
 * 
 * @package MongoDB Admin Panel
 * @subpackage Config
 * @version 1.0.0
 * @author Development Team
 * @link https://github.com/frame-dev/MongoDBAdminPanel
 * @license MIT
 */

// Settings helper (session-backed)
function getSetting($key, $default = null) {
    if (!isset($_SESSION['settings']) || !is_array($_SESSION['settings'])) {
        return $default;
    }
    return array_key_exists($key, $_SESSION['settings']) ? $_SESSION['settings'][$key] : $default;
}

function markSettingsUpdated(): void {
    $_SESSION['settings_updated_at'] = time();
}

function getDatabaseFromSession() {
    if (isset($GLOBALS['database']) && $GLOBALS['database'] !== null) {
        return $GLOBALS['database'];
    }
    if (!isset($_SESSION['mongo_connection']) || !is_array($_SESSION['mongo_connection'])) {
        return null;
    }
    $conn = $_SESSION['mongo_connection'];
    $hostName = $conn['hostname'] ?? null;
    $port = $conn['port'] ?? null;
    $db = $conn['database'] ?? null;
    if (!$hostName || !$port || !$db) {
        return null;
    }
    $user = $conn['username'] ?? null;
    $pass = $conn['password'] ?? null;
    if ($user && $pass) {
        $uri = "mongodb://$user:$pass@$hostName:$port/$db?authSource=$db";
    } else {
        $uri = "mongodb://$hostName:$port/$db";
    }
    try {
        $client = new \MongoDB\Client($uri);
        return $client->selectDatabase($db);
    } catch (Exception $e) {
        error_log('Error connecting for app settings: ' . $e->getMessage());
    }
    return null;
}

function hasGlobalSettingsDoc(): bool {
    $database = getDatabaseFromSession();
    if ($database === null) {
        return false;
    }
    try {
        $collection = $database->getCollection('_app_settings');
        $doc = $collection->findOne(['_id' => 'global'], ['projection' => ['_id' => 1]]);
        return $doc !== null;
    } catch (Exception $e) {
        error_log('Error checking app settings doc: ' . $e->getMessage());
    }
    return false;
}

// Default settings for the application
function getDefaultSettings(): array {
    return [
        'items_per_page' => 50,
        'date_format' => 'Y-m-d H:i:s',
        'theme' => 'light',
        'default_sort_field' => '_id',
        'default_sort_order' => '-1',
        'default_view_mode' => 'table',
        'syntax_highlighting' => true,
        'pretty_print' => true,
        'show_objectid_as_string' => false,
        'collapsible_json' => false,
        'zebra_stripes' => true,
        'row_hover' => true,
        'fixed_header' => false,
        'compact_mode' => false,
        'preview_length' => 80,
        'key_fields_priority' => 'name,title,email,status,type,category',
        'query_timeout' => 30,
        'max_results' => 1000,
        'query_default_limit' => 50,
        'query_history_limit' => 50,
        'memory_limit' => 256,
        'cache_ttl' => 15,
        'query_cache' => true,
        'auto_indexes' => true,
        'schema_cache' => false,
        'lazy_load' => false,
        'schema_sample_size' => 100,
        'editor_theme' => 'monokai',
        'editor_font_size' => 14,
        'line_numbers' => true,
        'auto_format' => true,
        'validate_on_type' => false,
        'auto_refresh' => false,
        'refresh_interval' => 30,
        'confirm_deletions' => true,
        'show_tooltips' => true,
        'keyboard_shortcuts' => true,
        'save_scroll_position' => false,
        'show_success_messages' => true,
        'show_error_messages' => true,
        'show_warning_messages' => true,
        'auto_dismiss_alerts' => true,
        'alert_duration' => 5,
        'enable_sounds' => false,
        'desktop_notifications' => false,
        'animation_effects' => true,
        'loading_indicators' => true,
        'progress_bars' => true,
        'default_export_format' => 'json',
        'export_filename_prefix' => 'export',
        'csv_delimiter' => ';',
        'csv_include_bom' => true,
        'include_metadata' => true,
        'compress_exports' => false,
        'timestamp_exports' => true,
        'auto_backup' => false,
        'backup_frequency' => 'weekly',
        'backup_retention' => 30,
        'csrf_enabled' => true,
        'csrf_token_lifetime' => 60,
        'session_validation_enabled' => true,
        'ip_tracking_enabled' => true,
        'rate_limit_enabled' => true,
        'rate_limit_requests' => 30,
        'rate_limit_lockout' => 60,
        'enable_idle_timeout' => false,
        'idle_timeout_minutes' => 30,
        'log_all_actions' => true,
        'log_failed_logins' => true,
        'log_security_events' => true
    ];
}

// Load global settings from MongoDB (if connected)
function loadGlobalSettingsFromDb(): bool {
    $database = getDatabaseFromSession();
    if ($database === null) {
        return false;
    }
    try {
        $collection = $database->getCollection('_app_settings');
        $doc = $collection->findOne(['_id' => 'global']);
        if ($doc && isset($doc['settings'])) {
            $dbUpdatedAt = 0;
            if (isset($doc['updated_at']) && $doc['updated_at'] instanceof \MongoDB\BSON\UTCDateTime) {
                $dbUpdatedAt = (int) floor($doc['updated_at']->toDateTime()->getTimestamp());
            }
            $sessionUpdatedAt = (int) ($_SESSION['settings_updated_at'] ?? 0);
            if (is_array($_SESSION['settings']) && !empty($_SESSION['settings']) &&
                $sessionUpdatedAt >= $dbUpdatedAt) {
                return true;
            }
            $defaults = getDefaultSettings();
            $settings = $doc['settings'];
            if ($settings instanceof \MongoDB\Model\BSONDocument) {
                $settings = $settings->getArrayCopy();
            } elseif (is_object($settings)) {
                $settings = json_decode(json_encode($settings), true);
            }
            if (is_array($settings)) {
                $_SESSION['settings'] = array_merge($defaults, $settings);
                if ($dbUpdatedAt > 0) {
                    $_SESSION['settings_updated_at'] = $dbUpdatedAt;
                }
                return true;
            }
        }
    } catch (Exception $e) {
        error_log('Error loading app settings: ' . $e->getMessage());
    }
    return false;
}

// Save global settings to MongoDB (if connected)
function saveGlobalSettingsToDb(array $settings): bool {
    $database = getDatabaseFromSession();
    if ($database === null) {
        return false;
    }
    try {
        $collection = $database->getCollection('_app_settings');
        $collection->updateOne(
            ['_id' => 'global'],
            ['$set' => ['settings' => $settings, 'updated_at' => new MongoDB\BSON\UTCDateTime(time() * 1000)]],
            ['upsert' => true]
        );
        return true;
    } catch (Exception $e) {
        error_log('Error saving app settings: ' . $e->getMessage());
    }
    return false;
}

// Generate CSRF token
function generateCSRFToken() {
    if (!getSetting('csrf_enabled', true)) {
        return $_SESSION['csrf_token'] ?? '';
    }
    $lifetimeMinutes = (int) getSetting('csrf_token_lifetime', 60);
    $lifetimeMinutes = max(10, min(1440, $lifetimeMinutes));
    $expiresIn = $lifetimeMinutes * 60;
    $createdAt = $_SESSION['csrf_token_created'] ?? 0;

    if (!isset($_SESSION['csrf_token']) || !$createdAt || (time() - $createdAt) > $expiresIn) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_created'] = time();
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCSRFToken($token) {
    if (!getSetting('csrf_enabled', true)) {
        return true;
    }
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }
    $lifetimeMinutes = (int) getSetting('csrf_token_lifetime', 60);
    $lifetimeMinutes = max(10, min(1440, $lifetimeMinutes));
    $expiresIn = $lifetimeMinutes * 60;
    $createdAt = $_SESSION['csrf_token_created'] ?? 0;

    if (!$createdAt || (time() - $createdAt) > $expiresIn) {
        unset($_SESSION['csrf_token'], $_SESSION['csrf_token_created']);
        return false;
    }
    return true;
}

// Sanitize input to prevent XSS
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Validate MongoDB collection name
function validateCollectionName($name) {
    // MongoDB collection names must not contain: $ or null character, start with system.
    if (empty($name) || strlen($name) > 255) {
        return false;
    }
    if (strpos($name, '$') !== false || strpos($name, "\0") !== false) {
        return false;
    }
    if (strpos($name, 'system.') === 0) {
        return false;
    }
    return true;
}

// Validate field name for MongoDB queries
function validateFieldName($field) {
    // Field names should not start with $ (reserved for operators)
    if (empty($field) || $field[0] === '$') {
        return false;
    }
    return true;
}

// Rate limiting
function checkRateLimit($action, $limit = 50, $period = 60) {
    $key = 'rate_limit_' . $action . '_' . session_id();
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'start' => time()];
    }
    
    $data = $_SESSION[$key];
    
    // Reset if period has passed
    if (time() - $data['start'] > $period) {
        $_SESSION[$key] = ['count' => 1, 'start' => time()];
        return true;
    }
    
    // Check if limit exceeded
    if ($data['count'] >= $limit) {
        return false;
    }
    
    $_SESSION[$key]['count']++;
    return true;
}

// Validate JSON structure
function validateJSON($json) {
    try {
        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        
        // Check for dangerous patterns
        $jsonStr = json_encode($decoded);
        
        // Allow MongoDB Extended JSON types but block dangerous patterns
        $dangerous = ['$where', 'eval(', 'function(', 'constructor'];
        foreach ($dangerous as $pattern) {
            if (stripos($jsonStr, $pattern) !== false) {
                return false;
            }
        }
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Sanitize MongoDB query operators
function sanitizeMongoQuery($query) {
    if (is_array($query)) {
        foreach ($query as $key => $value) {
            // Only allow whitelisted operators
            if (is_string($key) && $key[0] === '$') {
                $allowedOperators = ['$eq', '$ne', '$gt', '$gte', '$lt', '$lte', '$in', '$nin', '$regex', '$exists', '$or', '$and'];
                if (!in_array($key, $allowedOperators)) {
                    unset($query[$key]);
                    continue;
                }
            }
            
            if (is_array($value)) {
                $query[$key] = sanitizeMongoQuery($value);
            }
        }
    }
    return $query;
}

// Format timestamps using display settings
function formatDisplayDate($value, $format = null) {
    $format = $format ?? (string) getSetting('date_format', 'Y-m-d H:i:s');
    $timestamp = null;

    if ($value instanceof DateTimeInterface) {
        $timestamp = $value->getTimestamp();
    } elseif (is_object($value) && method_exists($value, 'toDateTime')) {
        $timestamp = $value->toDateTime()->getTimestamp();
    } elseif (is_numeric($value)) {
        $timestamp = (int) $value;
    } else {
        $parsed = strtotime((string) $value);
        if ($parsed !== false) {
            $timestamp = $parsed;
        }
    }

    if ($timestamp === null) {
        return (string) $value;
    }

    if ($format === 'relative') {
        $diff = time() - $timestamp;
        if ($diff < 0) {
            return 'in the future';
        }
        if ($diff < 60) {
            return $diff . ' seconds ago';
        }
        if ($diff < 3600) {
            return floor($diff / 60) . ' minutes ago';
        }
        if ($diff < 86400) {
            return floor($diff / 3600) . ' hours ago';
        }
        if ($diff < 604800) {
            return floor($diff / 86400) . ' days ago';
        }
        return date('Y-m-d', $timestamp);
    }

    return date($format, $timestamp);
}

// Format JSON payloads based on display settings
function formatJsonForDisplay($data) {
    $options = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    if (getSetting('pretty_print', true)) {
        $options |= JSON_PRETTY_PRINT;
    }
    return json_encode($data, $options);
}

// Format ObjectId for display based on settings
function formatObjectIdDisplay($id, $shortLength = 8) {
    $id = (string) $id;
    if (getSetting('show_objectid_as_string', false)) {
        return $id;
    }
    if (strlen($id) <= $shortLength) {
        return $id;
    }
    return substr($id, -$shortLength);
}

// Log security events
function logSecurityEvent($event, $details = []) {
    $logFile = __DIR__ . '/../logs/security.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event' => $event,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'session' => session_id(),
        'details' => $details
    ];
    
    file_put_contents($logFile, json_encode($logEntry) . PHP_EOL, FILE_APPEND);
}

// Validate file upload
function validateUpload($file, $allowedTypes = ['application/json'], $maxSize = 5242880) {
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new Exception('Invalid file upload parameters');
    }
    
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new Exception('File size exceeds limit');
        default:
            throw new Exception('Upload error occurred');
    }
    
    if ($file['size'] > $maxSize) {
        throw new Exception('File too large. Max size: ' . ($maxSize / 1024 / 1024) . 'MB');
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes) && !in_array('text/plain', $allowedTypes)) {
        throw new Exception('Invalid file type');
    }
    
    return true;
}

// Generate CSRF token for forms
generateCSRFToken();
