<?php
/**
 * Collection Statistics and Data Retrieval
 */

// Get collection statistics
require_once 'config/security.php';
try {
    $stats = $database->command(['collStats' => $collectionName])->toArray()[0];
    $documentCount = $collection->countDocuments();
    $totalSize = $stats->totalSize ?? 0;
    $collectionSize = $stats->size ?? 0;
    $avgDocSize = round($totalSize / max($documentCount, 1));
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
    $sampleDocs = $collection->find([], ['limit' => 100])->toArray();
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
    
    // Move _id to the front
    $detectedFields = array_diff($detectedFields, ['_id']);
    array_unshift($detectedFields, '_id');
} catch (Exception $e) {
    // If field detection fails, use defaults
    $detectedFields = ['_id', 'created_at', 'updated_at', 'name', 'email', 'status', 'type'];
}

// Count total filtered documents
$totalDocs = $collection->countDocuments($filter);
$totalPages = ceil($totalDocs / $pageSize);

// Find documents with pagination
$sortOptions = [$sortField => (int)$sortOrder];
$documents = $collection->find($filter, [
    'sort' => $sortOptions,
    'limit' => $pageSize,
    'skip' => ($page - 1) * $pageSize
]);
$documentsList = iterator_to_array($documents);
