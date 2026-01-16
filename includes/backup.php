<?php
/**
 * Backup and Restore Utilities
 * 
 * Handles database backup creation, compression, restoration,
 * and backup file management with audit logging.
 * 
 * @package MongoDB Admin Panel
 * @subpackage Includes
 * @version 1.0.0
 * @author Development Team
 * @link https://github.com/frame-dev/MongoDBAdminPanel
 * @license MIT
 */

// Create full database backup
require_once 'config/security.php';
function createDatabaseBackup($database, $backupName = null) {
    try {
        if (!$backupName) {
            $backupName = 'backup_' . date('Y-m-d_H-i-s');
        }
        
        $backupDir = __DIR__ . '/../backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $backupFile = $backupDir . '/' . $backupName . '.json';
        
        $collections = $database->listCollections();
        $backupData = [
            'created_at' => date('Y-m-d H:i:s'),
            'database' => (string)$database->getDatabaseName(),
            'collections' => []
        ];
        
        foreach ($collections as $collectionInfo) {
            $collName = $collectionInfo->getName();
            $collection = $database->selectCollection($collName);
            
            $documents = $collection->find()->toArray();
            $backupData['collections'][$collName] = array_map(function($doc) {
                return json_decode(json_encode($doc), true);
            }, $documents);
        }
        
        $compressed = gzencode(json_encode($backupData, JSON_PRETTY_PRINT), 9);
        file_put_contents($backupFile . '.gz', $compressed);
        
        return [
            'success' => true,
            'file' => $backupName . '.json.gz',
            'size' => filesize($backupFile . '.gz'),
            'collections' => count($backupData['collections'])
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// List available backups
function listBackups() {
    $backupDir = __DIR__ . '/../backups';
    if (!is_dir($backupDir)) {
        return [];
    }
    
    $backups = [];
    $files = glob($backupDir . '/*.gz');
    
    foreach ($files as $file) {
        $backups[] = [
            'name' => basename($file),
            'size' => filesize($file),
            'date' => date('Y-m-d H:i:s', filemtime($file))
        ];
    }
    
    usort($backups, function($a, $b) {
        return strcmp($b['date'], $a['date']);
    });
    
    return $backups;
}

/**
 * Create audit log entry with enhanced tracking
 * 
 * @param string $action Action performed
 * @param array $details Additional details
 * @param string $severity Severity level (info, warning, error, critical)
 * @param string $category Category (auth, data, system, security, user)
 */
function auditLog($action, $details = [], $severity = 'info', $category = 'system') {
    try {
        global $database;
        
        if ($database === null) {
            error_log('Audit log skipped: No database connection');
            return;
        }
        
        $auditCollection = $database->selectCollection('_audit_log');
        
        // Get current user info
        $currentUser = getCurrentUser();
        $username = $currentUser['username'] ?? 'anonymous';
        $userId = isset($currentUser['_id']) ? (string)$currentUser['_id'] : null;
        $userRole = $currentUser['role'] ?? 'unknown';
        
        // Get request information
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
        $requestUri = $_SERVER['REQUEST_URI'] ?? 'N/A';
        $referer = $_SERVER['HTTP_REFERER'] ?? 'direct';
        
        // Get session ID (hashed for privacy)
        $sessionId = session_id() ? hash('sha256', session_id()) : null;
        
        // Create comprehensive audit entry
        $auditEntry = [
            'timestamp' => new MongoDB\BSON\UTCDateTime(),
            'action' => $action,
            'severity' => $severity,
            'category' => $category,
            'user' => [
                'username' => $username,
                'user_id' => $userId,
                'role' => $userRole,
                'session_id' => $sessionId
            ],
            'request' => [
                'method' => $requestMethod,
                'uri' => $requestUri,
                'referer' => $referer,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            ],
            'database' => [
                'name' => $_SESSION['mongo_connection']['database'] ?? 'N/A',
                'collection' => $_SESSION['mongo_connection']['collection'] ?? 'N/A'
            ],
            'details' => $details,
            'server_time' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION,
            'memory_usage' => memory_get_usage(true),
            'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
        ];
        
        $auditCollection->insertOne($auditEntry);
        
        // Create indexes on first use
        static $indexesCreated = false;
        if (!$indexesCreated) {
            try {
                $auditCollection->createIndex(['timestamp' => -1]);
                $auditCollection->createIndex(['action' => 1]);
                $auditCollection->createIndex(['user.username' => 1]);
                $auditCollection->createIndex(['category' => 1]);
                $auditCollection->createIndex(['severity' => 1]);
                $auditCollection->createIndex(['timestamp' => 1], ['expireAfterSeconds' => 7776000]); // 90 days TTL
                $indexesCreated = true;
            } catch (Exception $e) {
                // Indexes may already exist
            }
        }
    } catch (Exception $e) {
        // Silent fail - don't break application if audit fails
        error_log('Audit log failed: ' . $e->getMessage());
    }
}

/**
 * Get audit logs with filtering and pagination
 * 
 * @param array $filters Filter criteria
 * @param int $limit Number of records
 * @param int $skip Skip records
 * @return array Audit log entries
 */
function getAuditLogs($filters = [], $limit = 50, $skip = 0) {
    global $database;
    
    if ($database === null) {
        return [];
    }
    
    try {
        $auditCollection = $database->selectCollection('_audit_log');
        
        // Build query
        $query = [];
        
        if (!empty($filters['action'])) {
            $query['action'] = ['$regex' => $filters['action'], '$options' => 'i'];
        }
        
        if (!empty($filters['user'])) {
            $query['user.username'] = ['$regex' => $filters['user'], '$options' => 'i'];
        }
        
        if (!empty($filters['category'])) {
            $query['category'] = $filters['category'];
        }
        
        if (!empty($filters['severity'])) {
            $query['severity'] = $filters['severity'];
        }
        
        if (!empty($filters['date_from'])) {
            $timestamp = strtotime($filters['date_from']);
            $query['timestamp'] = ['$gte' => new MongoDB\BSON\UTCDateTime($timestamp * 1000)];
        }
        
        if (!empty($filters['date_to'])) {
            $timestamp = strtotime($filters['date_to'] . ' 23:59:59');
            if (!isset($query['timestamp'])) {
                $query['timestamp'] = [];
            }
            $query['timestamp']['$lte'] = new MongoDB\BSON\UTCDateTime($timestamp * 1000);
        }
        
        $options = [
            'sort' => ['timestamp' => -1],
            'limit' => $limit,
            'skip' => $skip
        ];
        
        $logs = $auditCollection->find($query, $options)->toArray();
        
        return $logs;
    } catch (Exception $e) {
        error_log('Failed to retrieve audit logs: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get audit log statistics
 * 
 * @return array Statistics
 */
function getAuditLogStats() {
    global $database;
    
    if ($database === null) {
        return [];
    }
    
    try {
        $auditCollection = $database->selectCollection('_audit_log');
        
        $stats = [
            'total_entries' => $auditCollection->countDocuments(),
            'today' => $auditCollection->countDocuments([
                'timestamp' => ['$gte' => new MongoDB\BSON\UTCDateTime(strtotime('today') * 1000)]
            ]),
            'this_week' => $auditCollection->countDocuments([
                'timestamp' => ['$gte' => new MongoDB\BSON\UTCDateTime(strtotime('-7 days') * 1000)]
            ]),
            'by_severity' => [],
            'by_category' => [],
            'top_actions' => [],
            'top_users' => []
        ];
        
        // Count by severity
        foreach (['info', 'warning', 'error', 'critical'] as $severity) {
            $stats['by_severity'][$severity] = $auditCollection->countDocuments(['severity' => $severity]);
        }
        
        // Count by category
        foreach (['auth', 'data', 'system', 'security', 'user'] as $category) {
            $stats['by_category'][$category] = $auditCollection->countDocuments(['category' => $category]);
        }
        
        // Top 5 actions
        $pipeline = [
            ['$group' => ['_id' => '$action', 'count' => ['$sum' => 1]]],
            ['$sort' => ['count' => -1]],
            ['$limit' => 5]
        ];
        $topActions = $auditCollection->aggregate($pipeline)->toArray();
        foreach ($topActions as $item) {
            $key = $item['_id'] ?? 'unknown';
            $stats['top_actions'][$key] = $item['count'];
        }
        
        // Top 5 users
        $pipeline = [
            ['$group' => ['_id' => '$user.username', 'count' => ['$sum' => 1]]],
            ['$sort' => ['count' => -1]],
            ['$limit' => 5]
        ];
        $topUsers = $auditCollection->aggregate($pipeline)->toArray();
        foreach ($topUsers as $item) {
            $key = $item['_id'] ?? 'unknown';
            $stats['top_users'][$key] = $item['count'];
        }
        
        return $stats;
    } catch (Exception $e) {
        error_log('Failed to get audit log stats: ' . $e->getMessage());
        return [];
    }
}

/**
 * Clear old audit logs
 * 
 * @param int $daysToKeep Number of days to keep
 * @return int Number of deleted entries
 */
function clearOldAuditLogs($daysToKeep = 90) {
    global $database;
    
    if ($database === null) {
        return 0;
    }
    
    try {
        $auditCollection = $database->selectCollection('_audit_log');
        $cutoffDate = new MongoDB\BSON\UTCDateTime(strtotime("-$daysToKeep days") * 1000);
        
        $result = $auditCollection->deleteMany([
            'timestamp' => ['$lt' => $cutoffDate]
        ]);
        
        return $result->getDeletedCount();
    } catch (Exception $e) {
        error_log('Failed to clear old audit logs: ' . $e->getMessage());
        return 0;
    }
}