<?php
/**
 * Collection Statistics and Data Retrieval
 * 
 * Retrieves collection statistics, field analysis, schema detection,
 * and data metrics for display in the admin panel.
 * 
 * @package MongoDB Admin Panel
 * @subpackage Includes
 * @version 1.0.0
 * @author Development Team
 * @link https://github.com/frame-dev/MongoDBAdminPanel
 * @license MIT
 */

// Get collection statistics
require_once 'config/security.php';
$queryTimeoutMs = (int) getSetting('query_timeout', 30) * 1000;
$queryTimeoutMs = max(5000, min(300000, $queryTimeoutMs));
$sampleSizeSetting = (int) getSetting('schema_sample_size', 100);
$sampleSizeSetting = max(10, min(500, $sampleSizeSetting));
try {
    if ($database && $collectionName) {
        $stats = $database->command(['collStats' => $collectionName])->toArray()[0];
        $documentCount = $collection->countDocuments([], ['maxTimeMS' => $queryTimeoutMs]);
        $totalSize = $stats->totalSize ?? 0;
        $collectionSize = $stats->size ?? 0;
        $avgDocSize = round($totalSize / max($documentCount, 1));
    } else {
        throw new Exception('No active database connection');
    }
} catch (Exception $e) {
    $documentCount = 0;
    $avgDocSize = 0;
    $totalSize = 0;
    $collectionSize = 0;
}

$collectionNames = $allCollectionNames;

// Detect all fields in the collection for sorting
$detectedFields = ['_id']; // Always include _id
try {
    // Sample multiple documents to get a comprehensive field list
    if ($collection) {
        $sampleDocs = $collection->find([], [
            'limit' => min($sampleSizeSetting, 1000),
            'maxTimeMS' => $queryTimeoutMs
        ])->toArray();
        $fieldSet = [];
        
        foreach ($sampleDocs as $doc) {
            $docArray = json_decode(json_encode($doc), true);
            foreach ($docArray as $key => $value) {
                if (!isset($fieldSet[$key])) {
                    $fieldSet[$key] = true;
                }
            }
        }
        $detectedFields = array_keys($fieldSet);
        sort($detectedFields);
    }
} catch (Exception $e) {
    // If field detection fails, use defaults
    $detectedFields = ['_id', 'created_at', 'updated_at', 'name', 'email', 'status', 'type'];
}

// Count total filtered documents
$totalDocs = $collection->countDocuments($filter, ['maxTimeMS' => $queryTimeoutMs]);
$totalPages = ceil($totalDocs / $pageSize);

// Find documents with pagination
$sortOptions = [$sortField => (int)$sortOrder];
$documents = $collection->find($filter, [
    'sort' => $sortOptions,
    'limit' => $pageSize,
    'skip' => ($page - 1) * $pageSize,
    'maxTimeMS' => $queryTimeoutMs
]);
$documentsList = iterator_to_array($documents);
