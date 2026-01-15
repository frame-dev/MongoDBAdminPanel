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

// Generate CSRF token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
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
        $dangerous = ['$where', 'eval', 'function', 'constructor'];
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
