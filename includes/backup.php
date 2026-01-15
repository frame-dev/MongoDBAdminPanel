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

// Create audit log entry
function auditLog($action, $details, $user = 'system') {
    try {
        global $database;
        $auditCollection = $database->selectCollection('_audit_log');
        
        $auditCollection->insertOne([
            'timestamp' => new MongoDB\BSON\UTCDateTime(),
            'action' => $action,
            'user' => $user,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'details' => $details
        ]);
    } catch (Exception $e) {
        // Silent fail - don't break application if audit fails
        error_log('Audit log failed: ' . $e->getMessage());
    }
}
