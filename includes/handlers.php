<?php
/**
 * Search, Filter, and Form Handlers
 * 
 * Handles form submissions, document operations, bulk operations,
 * queries, imports, exports, and all user-initiated actions.
 * 
 * @package MongoDB Admin Panel
 * @subpackage Includes
 * @version 1.0.0
 * @author Development Team
 * @link https://github.com/frame-dev/MongoDBAdminPanel
 * @license MIT
 */

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

require_once 'config/security.php';

// Initialize message variables
$message = $_SESSION['message'] ?? '';
$messageType = $_SESSION['messageType'] ?? 'info';

// Clear session messages after retrieving them
if (isset($_SESSION['message'])) {
    unset($_SESSION['message']);
    unset($_SESSION['messageType']);
}

// Handle search and filters
$searchQuery = $_GET['search'] ?? '';
$sortField = $_GET['sort'] ?? '_id';
$sortOrder = $_GET['order'] ?? '-1';
$perPage = (int)($_GET['per_page'] ?? 50);
$perPage = max(10, min(100, $perPage)); // Limit between 10 and 100
$pageSize = $perPage;
$page = max(1, (int) ($_GET['page'] ?? 1));

$filter = [];
if ($searchQuery) {
    // Get a sample document to determine available fields
    $sampleDoc = $collection->findOne();

    if ($sampleDoc) {
        // Get all keys from the sample document (excluding _id for search)
        $docArray = json_decode(json_encode($sampleDoc), true);
        $searchableFields = array_keys($docArray);

        // Remove _id from searchable fields as it's typically an ObjectId
        $searchableFields = array_filter($searchableFields, function ($field) {
            return $field !== '_id';
        });

        // Build dynamic $or filter for all fields
        if (!empty($searchableFields)) {
            $orConditions = [];
            foreach ($searchableFields as $field) {
                $orConditions[] = [$field => ['$regex' => $searchQuery, '$options' => 'i']];
            }
            $filter = ['$or' => $orConditions];
        }
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Include additional handlers now that $action is defined
    if (file_exists(__DIR__ . '/settings_handlers.php')) {
        include __DIR__ . '/settings_handlers.php';
    }
    if (file_exists(__DIR__ . '/new_handlers.php')) {
        include __DIR__ . '/new_handlers.php';
    }

    // CSRF Protection for dangerous operations
    $dangerousActions = ['delete', 'delete_all', 'update', 'bulkupdate', 'import', 'import_json_direct', 'duplicate', 'create_collection', 
        'drop_collection', 'rename_collection', 'clone_collection', 'create_index', 'drop_index',
        'backup_collection', 'restore_collection', 'drop_database', 'add_field', 'remove_field', 
        'rename_field', 'deduplicate', 'bulk_delete_by_field', 'generate_data', 'sanitize_field',
        'compact_collection', 'validate_collection', 'profile_query', 'clear_logs', 'migrate_collection',
        'save_display_settings', 'save_performance_settings', 'save_security_settings', 'import_settings',
        'clear_cache', 'reset_settings', 'bulk_update_query', 'add_validation', 'export_collection_data',
        'bulk_delete_selected', 'bulk_update_selected'];
    if (in_array($action, $dangerousActions)) {
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!verifyCSRFToken($csrfToken)) {
            $message = 'Security error: Invalid CSRF token';
            $messageType = 'error';
            logSecurityEvent('csrf_violation', ['action' => $action]);
            $action = ''; // Prevent action execution
        }
    }

    // Rate limiting
    if (!checkRateLimit('post_action', 30, 60)) {
        $message = 'Too many requests. Please wait a moment.';
        $messageType = 'error';
        logSecurityEvent('rate_limit_exceeded', ['action' => $action]);
        $action = '';
    }

    if ($action === 'savetemplate') {
        try {
            $templateName = sanitizeInput($_POST['template_name'] ?? '');
            $templateData = $_POST['template_data'] ?? '{}';

            if (!$templateName || strlen($templateName) > 100) {
                throw new Exception('Template name is required and must be under 100 characters');
            }

            if (!validateJSON($templateData)) {
                throw new Exception('Invalid or potentially dangerous JSON in template');
            }

            json_decode($templateData);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON in template');
            }

            $templatesCollection = $database->getCollection('_templates');
            $templatesCollection->updateOne(
                ['name' => $templateName, 'user_collection' => $collectionName],
                [
                    '$set' => [
                        'name' => $templateName,
                        'data' => json_decode($templateData),
                        'user_collection' => $collectionName,
                        'created_at' => new UTCDateTime(),
                    ]
                ],
                ['upsert' => true]
            );

            $message = 'Template saved successfully!';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'deletetemplate') {
        try {
            $templateName = $_POST['template_name'] ?? '';
            $templatesCollection = $database->getCollection('_templates');
            $templatesCollection->deleteOne(['name' => $templateName, 'user_collection' => $collectionName]);
            $message = 'Template deleted successfully!';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'fieldstats') {
        try {
            $fieldName = $_POST['field_name'] ?? '';
            if (!$fieldName) {
                throw new Exception('Field name is required');
            }

            $stats = $collection->aggregate([
                [
                    '$group' => [
                        '_id' => '$' . $fieldName,
                        'count' => ['$sum' => 1]
                    ]
                ],
                ['$sort' => ['count' => -1]],
                ['$limit' => 20]
            ])->toArray();

            $statsArray = [];
            foreach ($stats as $stat) {
                $statsArray[] = [
                    '_id' => $stat->_id ?? null,
                    'count' => $stat->count ?? 0
                ];
            }

            $_SESSION['field_stats'] = [
                'field' => $fieldName,
                'data' => $statsArray
            ];

            $message = 'Field statistics retrieved!';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'bulkupdate') {
        try {
            $updateField = $_POST['update_field'] ?? '';
            $updateValue = $_POST['update_value'] ?? '';
            $matchField = $_POST['match_field'] ?? '';
            $matchValue = $_POST['match_value'] ?? '';

            if (!$updateField || !$matchField) {
                throw new Exception('Match field and update field are required');
            }

            $matchFilter = [$matchField => ['$regex' => $matchValue, '$options' => 'i']];
            $result = $collection->updateMany($matchFilter, ['$set' => [$updateField => $updateValue]]);

            $message = 'Updated ' . $result->getModifiedCount() . ' document(s)!';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'findreplace') {
        try {
            $findValue = $_POST['find_value'] ?? '';
            $replaceValue = $_POST['replace_value'] ?? '';
            $fieldName = $_POST['field_name'] ?? '';

            if (!$fieldName || !$findValue) {
                throw new Exception('Field name and find value are required');
            }

            $result = $collection->updateMany(
                [$fieldName => ['$regex' => $findValue, '$options' => 'i']],
                ['$set' => [$fieldName => $replaceValue]]
            );

            $message = 'Replaced in ' . $result->getModifiedCount() . ' document(s)!';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'add') {
        try {
            $data = json_decode($_POST['json_data'] ?? '{}', true);
            if (!is_array($data))
                throw new Exception('Invalid JSON format');
            $collection->insertOne($data);
            $message = 'Document added successfully!';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'delete') {
        try {
            $id = new ObjectId($_POST['doc_id']);
            $collection->deleteOne(['_id' => $id]);
            $_SESSION['message'] = 'Document deleted successfully!';
            $_SESSION['messageType'] = 'success';
            header('Location: ' . $_SERVER['PHP_SELF'] . '?collection=' . urlencode($collectionName) . '#browse');
            exit;
        } catch (Exception $e) {
            $_SESSION['message'] = 'Error: ' . $e->getMessage();
            $_SESSION['messageType'] = 'error';
            header('Location: ' . $_SERVER['PHP_SELF'] . '?collection=' . urlencode($collectionName) . '#browse');
            exit;
        }
    }

    if ($action === 'delete_all') {
        try {
            $collection->deleteMany($filter ?? []);
            $_SESSION['message'] = 'All matching documents deleted!';
            $_SESSION['messageType'] = 'success';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'duplicate') {
        try {
            $id = new ObjectId($_POST['doc_id']);
            $doc = $collection->findOne(['_id' => $id]);
            if ($doc) {
                unset($doc['_id']);
                $collection->insertOne($doc);
                $_SESSION['message'] = 'Document duplicated successfully!';
                $_SESSION['messageType'] = 'success';
                header('Location: ' . $_SERVER['PHP_SELF'] . '?collection=' . urlencode($collectionName) . '#browse');
                exit;
            } else {
                $_SESSION['message'] = 'Document not found';
                $_SESSION['messageType'] = 'error';
                header('Location: ' . $_SERVER['PHP_SELF'] . '?collection=' . urlencode($collectionName) . '#browse');
                exit;
            }
        } catch (Exception $e) {
            $_SESSION['message'] = 'Error: ' . $e->getMessage();
            $_SESSION['messageType'] = 'error';
            header('Location: ' . $_SERVER['PHP_SELF'] . '?collection=' . urlencode($collectionName) . '#browse');
            exit;
        }
    }

    if ($action === 'import') {
        try {
            if (!isset($_FILES['json_file']) || $_FILES['json_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('No file uploaded or upload error');
            }
            $fileContent = file_get_contents($_FILES['json_file']['tmp_name']);
            $data = json_decode($fileContent, true);

            if (!is_array($data)) {
                throw new Exception('Invalid JSON format');
            }

            if (isset($data['_id']) || !isset($data[0])) {
                $data = [$data];
            }

            $insertCount = 0;
            foreach ($data as $doc) {
                if (is_array($doc)) {
                    $collection->insertOne($doc);
                    $insertCount++;
                }
            }
            $message = "Successfully imported $insertCount document(s)!";
            $messageType = 'success';
            logSecurityEvent('json_import_file', ['count' => $insertCount, 'collection' => $collectionName]);
        } catch (Exception $e) {
            $message = 'Import Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
    
    if ($action === 'import_json_direct') {
        try {
            $jsonData = $_POST['json_data'] ?? '';
            
            if (empty($jsonData)) {
                throw new Exception('No JSON data provided');
            }
            
            $data = json_decode($jsonData, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON: ' . json_last_error_msg());
            }
            
            if (!is_array($data)) {
                throw new Exception('JSON must be an object or array');
            }
            
            // Check if it's a single document or array of documents
            if (isset($data['_id']) || !isset($data[0])) {
                $data = [$data];
            }
            
            $insertCount = 0;
            $errors = [];
            
            foreach ($data as $index => $doc) {
                if (is_array($doc) && !empty($doc)) {
                    try {
                        // Remove _id if it's empty or let MongoDB generate it
                        if (isset($doc['_id']) && empty($doc['_id'])) {
                            unset($doc['_id']);
                        }
                        $collection->insertOne($doc);
                        $insertCount++;
                    } catch (Exception $e) {
                        $errors[] = "Document #" . ($index + 1) . ": " . $e->getMessage();
                    }
                }
            }
            
            if ($insertCount > 0) {
                $message = "Successfully imported $insertCount document(s)!";
                if (!empty($errors)) {
                    $message .= " " . count($errors) . " document(s) failed.";
                }
                $messageType = 'success';
                logSecurityEvent('json_import_direct', ['count' => $insertCount, 'collection' => $collectionName, 'errors' => count($errors)]);
            } else {
                throw new Exception('No documents were imported. ' . implode(' ', $errors));
            }
            
        } catch (Exception $e) {
            $message = 'Import Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'update') {
        try {
            $id = new ObjectId($_POST['doc_id']);
            // Support both json_data and doc_data field names
            $jsonData = $_POST['doc_data'] ?? $_POST['json_data'] ?? '{}';
            $data = json_decode($jsonData, true);
            if (!is_array($data))
                throw new Exception('Invalid JSON format');
            
            // Remove _id from update data if present (can't modify _id)
            unset($data['_id']);
            
            $collection->updateOne(['_id' => $id], ['$set' => $data]);
            $_SESSION['message'] = 'Document updated successfully!';
            $_SESSION['messageType'] = 'success';
        } catch (Exception $e) {
            $_SESSION['message'] = 'Error: ' . $e->getMessage();
            $_SESSION['messageType'] = 'error';
        }
        
        // Redirect back to Browse tab
        header('Location: ' . $_SERVER['PHP_SELF'] . '?collection=' . urlencode($collectionName) . '#browse');
        exit;
    }

    if ($action === 'export') {
        try {
            $sortOptions = [$sortField => (int) $sortOrder];
            $exportDocuments = $collection->find($filter, ['sort' => $sortOptions]);
            $exportList = iterator_to_array($exportDocuments);

            $data = array_map(fn($doc) => json_decode(json_encode($doc), true), $exportList);

            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="export_' . date('Y-m-d_H-i-s') . '.json"');
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit;
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'create_collection') {
        try {
            $newCollectionName = sanitizeInput($_POST['collection_name'] ?? '');
            
            if (!$newCollectionName) {
                throw new Exception('Collection name is required');
            }
            
            if (!validateCollectionName($newCollectionName)) {
                throw new Exception('Invalid collection name. Avoid special characters like $ and system. prefix');
            }
            
            // Check if collection already exists
            $existingCollections = [];
            foreach ($database->listCollections() as $collectionInfo) {
                $existingCollections[] = $collectionInfo->getName();
            }
            
            if (in_array($newCollectionName, $existingCollections)) {
                throw new Exception('Collection already exists');
            }
            
            // Create the collection
            $database->createCollection($newCollectionName);
            
            // Switch to the new collection
            $_SESSION['mongo_connection']['collection'] = $newCollectionName;
            $_SESSION['message'] = "Collection '{$newCollectionName}' created successfully!";
            $_SESSION['messageType'] = 'success';
            
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'drop_collection') {
        try {
            $collectionToDrop = sanitizeInput($_POST['collection_to_drop'] ?? '');
            $confirmName = sanitizeInput($_POST['confirm_collection_name'] ?? '');
            
            if ($collectionToDrop !== $confirmName) {
                throw new Exception('Collection name confirmation does not match');
            }
            
            if (!validateCollectionName($collectionToDrop)) {
                throw new Exception('Invalid collection name');
            }
            
            // Drop the collection
            $database->dropCollection($collectionToDrop);
            
            // If we dropped the current collection, switch to another one
            if ($collectionToDrop === $collectionName) {
                $collections = [];
                foreach ($database->listCollections() as $col) {
                    $collections[] = $col->getName();
                }
                
                if (!empty($collections)) {
                    $_SESSION['mongo_connection']['collection'] = $collections[0];
                } else {
                    // Create a default collection if none exist
                    $database->createCollection('default');
                    $_SESSION['mongo_connection']['collection'] = 'default';
                }
            }
            
            $_SESSION['message'] = "Collection '{$collectionToDrop}' dropped successfully!";
            $_SESSION['messageType'] = 'success';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'rename_collection') {
        try {
            $oldName = sanitizeInput($_POST['old_collection_name'] ?? '');
            $newName = sanitizeInput($_POST['new_collection_name'] ?? '');
            
            if (!$oldName || !$newName) {
                throw new Exception('Both old and new collection names are required');
            }
            
            if (!validateCollectionName($newName)) {
                throw new Exception('Invalid new collection name');
            }
            
            // Rename the collection
            $database->command([
                'renameCollection' => $db . '.' . $oldName,
                'to' => $db . '.' . $newName
            ]);
            
            // Update session if we renamed the current collection
            if ($oldName === $collectionName) {
                $_SESSION['mongo_connection']['collection'] = $newName;
            }
            
            $_SESSION['message'] = "Collection renamed from '{$oldName}' to '{$newName}'!";
            $_SESSION['messageType'] = 'success';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'clone_collection') {
        try {
            $sourceCollection = sanitizeInput($_POST['source_collection'] ?? '');
            $targetCollection = sanitizeInput($_POST['target_collection'] ?? '');
            
            if (!$sourceCollection || !$targetCollection) {
                throw new Exception('Both source and target collection names are required');
            }
            
            if (!validateCollectionName($targetCollection)) {
                throw new Exception('Invalid target collection name');
            }
            
            // Check if target already exists
            $existingCollections = [];
            foreach ($database->listCollections() as $collectionInfo) {
                $existingCollections[] = $collectionInfo->getName();
            }
            
            if (in_array($targetCollection, $existingCollections)) {
                throw new Exception('Target collection already exists');
            }
            
            // Clone all documents
            $sourceCol = $database->selectCollection($sourceCollection);
            $targetCol = $database->selectCollection($targetCollection);
            
            $documents = $sourceCol->find()->toArray();
            
            if (!empty($documents)) {
                // Remove _id to let MongoDB generate new ones
                $docsToInsert = [];
                foreach ($documents as $doc) {
                    $docArray = json_decode(json_encode($doc), true);
                    $docsToInsert[] = $docArray;
                }
                $targetCol->insertMany($docsToInsert);
            }
            
            $_SESSION['message'] = "Collection '{$sourceCollection}' cloned to '{$targetCollection}' with " . count($documents) . " documents!";
            $_SESSION['messageType'] = 'success';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'create_index') {
        try {
            $indexField = sanitizeInput($_POST['index_field'] ?? '');
            $indexOrder = (int)($_POST['index_order'] ?? 1);
            $indexUnique = isset($_POST['index_unique']) && $_POST['index_unique'] === '1';
            
            if (!$indexField) {
                throw new Exception('Index field name is required');
            }
            
            if (!validateFieldName($indexField)) {
                throw new Exception('Invalid field name');
            }
            
            $options = [];
            if ($indexUnique) {
                $options['unique'] = true;
            }
            
            $collection->createIndex([$indexField => $indexOrder], $options);
            
            $_SESSION['message'] = "Index created on field '{$indexField}'!";
            $_SESSION['messageType'] = 'success';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'drop_index') {
        try {
            $indexName = sanitizeInput($_POST['index_name'] ?? '');
            
            if (!$indexName) {
                throw new Exception('Index name is required');
            }
            
            if ($indexName === '_id_') {
                throw new Exception('Cannot drop the _id index');
            }
            
            $collection->dropIndex($indexName);
            
            $_SESSION['message'] = "Index '{$indexName}' dropped successfully!";
            $_SESSION['messageType'] = 'success';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'backup_collection') {
        try {
            $backupName = sanitizeInput($_POST['backup_name'] ?? '');
            if (!$backupName) {
                $backupName = $collectionName . '_backup_' . date('Ymd_His');
            }
            
            if (!validateCollectionName($backupName)) {
                throw new Exception('Invalid backup name');
            }
            
            $backupCollection = $database->selectCollection($backupName);
            $documents = $collection->find()->toArray();
            
            if (!empty($documents)) {
                $docsToInsert = [];
                foreach ($documents as $doc) {
                    $docsToInsert[] = json_decode(json_encode($doc), true);
                }
                $backupCollection->insertMany($docsToInsert);
            }
            
            $_SESSION['message'] = "Backup created: '{$backupName}' with " . count($documents) . " documents!";
            $_SESSION['messageType'] = 'success';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'add_field') {
        try {
            $fieldName = sanitizeInput($_POST['field_name'] ?? '');
            $defaultValue = $_POST['default_value'] ?? '';
            
            if (!$fieldName || !validateFieldName($fieldName)) {
                throw new Exception('Invalid field name');
            }
            
            // Try to parse as JSON, otherwise treat as string
            $parsedValue = json_decode($defaultValue, true);
            $value = ($parsedValue !== null) ? $parsedValue : $defaultValue;
            
            $result = $collection->updateMany(
                [$fieldName => ['$exists' => false]],
                ['$set' => [$fieldName => $value]]
            );
            
            $_SESSION['message'] = "Field '{$fieldName}' added to {$result->getModifiedCount()} documents!";
            $_SESSION['messageType'] = 'success';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'remove_field') {
        try {
            $fieldName = sanitizeInput($_POST['field_name'] ?? '');
            
            if (!$fieldName) {
                throw new Exception('Field name is required');
            }
            
            $result = $collection->updateMany(
                [],
                ['$unset' => [$fieldName => '']]
            );
            
            $_SESSION['message'] = "Field '{$fieldName}' removed from {$result->getModifiedCount()} documents!";
            $_SESSION['messageType'] = 'success';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'rename_field') {
        try {
            $oldFieldName = sanitizeInput($_POST['old_field_name'] ?? '');
            $newFieldName = sanitizeInput($_POST['new_field_name'] ?? '');
            
            if (!$oldFieldName || !$newFieldName) {
                throw new Exception('Both old and new field names are required');
            }
            
            if (!validateFieldName($newFieldName)) {
                throw new Exception('Invalid new field name');
            }
            
            $result = $collection->updateMany(
                [],
                ['$rename' => [$oldFieldName => $newFieldName]]
            );
            
            $_SESSION['message'] = "Field renamed from '{$oldFieldName}' to '{$newFieldName}' in {$result->getModifiedCount()} documents!";
            $_SESSION['messageType'] = 'success';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'deduplicate') {
        try {
            $fieldName = sanitizeInput($_POST['dedup_field'] ?? '');
            
            if (!$fieldName) {
                throw new Exception('Field name is required');
            }
            
            // Find duplicates using aggregation
            $pipeline = [
                ['$group' => [
                    '_id' => '$' . $fieldName,
                    'ids' => ['$push' => '$_id'],
                    'count' => ['$sum' => 1]
                ]],
                ['$match' => ['count' => ['$gt' => 1]]]
            ];
            
            $duplicates = $collection->aggregate($pipeline)->toArray();
            $deletedCount = 0;
            
            foreach ($duplicates as $dup) {
                $ids = $dup->ids;
                // Keep first, delete rest
                array_shift($ids);
                foreach ($ids as $id) {
                    $collection->deleteOne(['_id' => $id]);
                    $deletedCount++;
                }
            }
            
            $_SESSION['message'] = "Removed {$deletedCount} duplicate documents based on field '{$fieldName}'!";
            $_SESSION['messageType'] = 'success';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'bulk_delete_by_field') {
        try {
            $fieldName = sanitizeInput($_POST['delete_field'] ?? '');
            $fieldValue = $_POST['delete_value'] ?? '';
            $operator = sanitizeInput($_POST['delete_operator'] ?? 'equals');
            
            if (!$fieldName) {
                throw new Exception('Field name is required');
            }
            
            $query = [];
            switch ($operator) {
                case 'equals':
                    $query = [$fieldName => $fieldValue];
                    break;
                case 'contains':
                    $query = [$fieldName => ['$regex' => $fieldValue, '$options' => 'i']];
                    break;
                case 'empty':
                    $query = ['$or' => [[$fieldName => ''], [$fieldName => ['$exists' => false]]]];
                    break;
                case 'not_empty':
                    $query = [$fieldName => ['$ne' => '', '$exists' => true]];
                    break;
            }
            
            $result = $collection->deleteMany($query);
            
            $_SESSION['message'] = "Deleted {$result->getDeletedCount()} documents!";
            $_SESSION['messageType'] = 'success';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'generate_data') {
        try {
            $template = $_POST['data_template'] ?? '';
            $count = min((int)($_POST['data_count'] ?? 1), 1000); // Max 1000
            
            if (!$template) {
                throw new Exception('Data template is required');
            }
            
            $templateData = json_decode($template, true);
            if (!$templateData) {
                throw new Exception('Invalid JSON template');
            }
            
            $documents = [];
            for ($i = 0; $i < $count; $i++) {
                $doc = $templateData;
                // Replace placeholders
                array_walk_recursive($doc, function(&$value) use ($i) {
                    if (is_string($value)) {
                        $value = str_replace('{{index}}', $i + 1, $value);
                        $value = str_replace('{{random}}', rand(1000, 9999), $value);
                        $value = str_replace('{{timestamp}}', time(), $value);
                        $value = str_replace('{{date}}', date('Y-m-d H:i:s'), $value);
                    }
                });
                $documents[] = $doc;
            }
            
            $collection->insertMany($documents);
            
            $_SESSION['message'] = "Generated and inserted {$count} documents!";
            $_SESSION['messageType'] = 'success';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'exportcsv') {
        try {
            $sortOptions = [$sortField => (int) $sortOrder];
            $csvDocuments = $collection->find($filter, ['sort' => $sortOptions]);
            $csvList = iterator_to_array($csvDocuments);

            if (empty($csvList)) {
                throw new Exception('No documents to export');
            }

            // Alle Keys sammeln
            $allKeys = [];
            foreach ($csvList as $doc) {
                $docArray = json_decode(json_encode($doc), true);
                $allKeys = array_values(array_unique(array_merge($allKeys, array_keys($docArray))));
            }

            // WICHTIG: Kein Output vor den Headern
            if (ob_get_length()) {
                ob_clean();
            }

            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="export_' . date('Y-m-d_H-i-s') . '.csv"');
            header('Pragma: no-cache');
            header('Expires: 0');

            $delimiter = ';'; // am kompatibelsten (Excel DE)
            $enclosure = '"';
            $escape = '\\';

            $output = fopen('php://output', 'w');

            // UTF-8 BOM für Excel
            fwrite($output, "\xEF\xBB\xBF");

            // Header-Zeile
            fputcsv($output, $allKeys, $delimiter, $enclosure, $escape);

            foreach ($csvList as $doc) {
                $docArray = json_decode(json_encode($doc), true);
                $row = [];

                foreach ($allKeys as $key) {
                    $value = $docArray[$key] ?? '';

                    // Mongo ObjectId hübsch machen, falls es als {"$oid":"..."} kommt
                    if ($key === '_id' && is_array($value) && isset($value['$oid'])) {
                        $value = $value['$oid'];
                    }

                    // Arrays/Objekte als JSON in einer Zelle
                    if (is_array($value) || is_object($value)) {
                        $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    }

                    $row[] = $value;
                }

                fputcsv($output, $row, $delimiter, $enclosure, $escape);
            }

            fclose($output);
            exit;

        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'compact_collection') {
        try {
            $result = $database->command(['compact' => $collectionName]);
            
            $_SESSION['message'] = "Collection '{$collectionName}' compacted successfully!";
            $_SESSION['messageType'] = 'success';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'validate_collection') {
        try {
            $result = $database->command(['validate' => $collectionName, 'full' => true]);
            
            $_SESSION['validate_result'] = json_encode($result, JSON_PRETTY_PRINT);
            $_SESSION['message'] = 'Collection validation completed!';
            $_SESSION['messageType'] = 'success';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'profile_query') {
        try {
            $queryJson = $_POST['profile_query'] ?? '{}';
            $query = json_decode($queryJson, true);
            
            if (!$query) {
                throw new Exception('Invalid query JSON');
            }
            
            // Enable profiling
            $database->command(['profile' => 2]);
            
            // Execute query
            $startTime = microtime(true);
            $results = $collection->find($query)->toArray();
            $endTime = microtime(true);
            
            // Disable profiling
            $database->command(['profile' => 0]);
            
            $executionTime = round(($endTime - $startTime) * 1000, 2);
            
            $_SESSION['profile_result'] = [
                'execution_time' => $executionTime,
                'result_count' => count($results),
                'query' => $queryJson
            ];
            $_SESSION['message'] = "Query profiled: {$executionTime}ms, " . count($results) . " results";
            $_SESSION['messageType'] = 'success';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'clear_logs') {
        try {
            $logFile = __DIR__ . '/../logs/security.log';
            if (file_exists($logFile)) {
                file_put_contents($logFile, '');
            }
            
            $_SESSION['message'] = 'Security logs cleared!';
            $_SESSION['messageType'] = 'success';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    // Analytics Tab Actions
    if ($action === 'visualize_data') {
        try {
            $vizField = sanitizeInput($_POST['viz_field'] ?? '');
            $vizLimit = (int)($_POST['viz_limit'] ?? 10);
            $vizLimit = max(5, min(50, $vizLimit));

            if (!$vizField || !validateFieldName($vizField)) {
                throw new Exception('Invalid field name');
            }

            $pipeline = [
                ['$group' => ['_id' => '$' . $vizField, 'count' => ['$sum' => 1]]],
                ['$sort' => ['count' => -1]],
                ['$limit' => $vizLimit]
            ];

            $results = $collection->aggregate($pipeline)->toArray();
            $totalCount = array_sum(array_map(function($item) { return $item->count; }, $results));

            $_SESSION['viz_data'] = [
                'field' => $vizField,
                'results' => json_decode(json_encode($results), true),
                'total' => $totalCount
            ];

            $_SESSION['message'] = 'Visualization generated successfully!';
            $_SESSION['messageType'] = 'success';
            header('Location: ' . $_SERVER['PHP_SELF'] . '#stats');
            exit;
        } catch (Exception $e) {
            $message = 'Visualization error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'timeseries') {
        try {
            $dateField = sanitizeInput($_POST['date_field'] ?? '');
            $timeGroup = $_POST['time_group'] ?? 'month';

            if (!$dateField || !validateFieldName($dateField)) {
                throw new Exception('Invalid date field name');
            }

            if (!in_array($timeGroup, ['day', 'week', 'month', 'year'])) {
                throw new Exception('Invalid time grouping');
            }

            // MongoDB date aggregation based on grouping
            $dateFormat = [
                'day' => '%Y-%m-%d',
                'week' => '%Y-W%V',
                'month' => '%Y-%m',
                'year' => '%Y'
            ][$timeGroup];

            $pipeline = [
                ['$match' => [$dateField => ['$exists' => true, '$ne' => null]]],
                ['$group' => [
                    '_id' => ['$dateToString' => ['format' => $dateFormat, 'date' => '$' . $dateField]],
                    'count' => ['$sum' => 1]
                ]],
                ['$sort' => ['_id' => 1]],
                ['$limit' => 50]
            ];

            $results = $collection->aggregate($pipeline)->toArray();

            $_SESSION['timeseries_data'] = [
                'field' => $dateField,
                'grouping' => $timeGroup,
                'results' => json_decode(json_encode($results), true)
            ];

            $_SESSION['message'] = 'Time series analysis completed!';
            $_SESSION['messageType'] = 'success';
            header('Location: ' . $_SERVER['PHP_SELF'] . '#stats');
            exit;
        } catch (Exception $e) {
            $message = 'Time series error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'correlation') {
        try {
            $field1 = sanitizeInput($_POST['field1'] ?? '');
            $field2 = sanitizeInput($_POST['field2'] ?? '');

            if (!$field1 || !$field2 || !validateFieldName($field1) || !validateFieldName($field2)) {
                throw new Exception('Invalid field names');
            }

            $pipeline = [
                ['$match' => [
                    $field1 => ['$exists' => true, '$ne' => null],
                    $field2 => ['$exists' => true, '$ne' => null]
                ]],
                ['$group' => [
                    '_id' => ['field1' => '$' . $field1, 'field2' => '$' . $field2],
                    'count' => ['$sum' => 1]
                ]],
                ['$sort' => ['count' => -1]],
                ['$limit' => 20]
            ];

            $results = $collection->aggregate($pipeline)->toArray();

            $_SESSION['correlation_data'] = [
                'field1' => $field1,
                'field2' => $field2,
                'results' => json_decode(json_encode($results), true)
            ];

            $_SESSION['message'] = 'Correlation analysis completed!';
            $_SESSION['messageType'] = 'success';
            header('Location: ' . $_SERVER['PHP_SELF'] . '#stats');
            exit;
        } catch (Exception $e) {
            $message = 'Correlation error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'topvalues') {
        try {
            $topField = sanitizeInput($_POST['top_field'] ?? '');
            $topCount = (int)($_POST['top_count'] ?? 10);
            $sortBy = $_POST['sort_by'] ?? 'count';

            if (!$topField || !validateFieldName($topField)) {
                throw new Exception('Invalid field name');
            }

            $topCount = max(5, min(50, $topCount));

            $pipeline = [
                ['$match' => [$topField => ['$exists' => true, '$ne' => null]]],
                ['$group' => ['_id' => '$' . $topField, 'count' => ['$sum' => 1]]],
                ['$sort' => [$sortBy === 'value' ? '_id' : 'count' => -1]],
                ['$limit' => $topCount]
            ];

            $results = $collection->aggregate($pipeline)->toArray();

            $_SESSION['top_values_data'] = [
                'field' => $topField,
                'results' => json_decode(json_encode($results), true),
                'sort_by' => $sortBy
            ];

            $_SESSION['message'] = 'Top values analysis completed!';
            $_SESSION['messageType'] = 'success';
            header('Location: ' . $_SERVER['PHP_SELF'] . '#stats');
            exit;
        } catch (Exception $e) {
            $message = 'Top values error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'comparecollections') {
        try {
            $coll1Name = sanitizeInput($_POST['compare_coll1'] ?? '');
            $coll2Name = sanitizeInput($_POST['compare_coll2'] ?? '');

            if (!$coll1Name || !$coll2Name) {
                throw new Exception('Both collections must be selected');
            }

            if ($coll1Name === $coll2Name) {
                throw new Exception('Please select different collections');
            }

            $coll1 = $database->selectCollection($coll1Name);
            $coll2 = $database->selectCollection($coll2Name);

            $stats1 = [
                'name' => $coll1Name,
                'count' => $coll1->countDocuments(),
                'size' => 0,
                'avgSize' => 0
            ];

            $stats2 = [
                'name' => $coll2Name,
                'count' => $coll2->countDocuments(),
                'size' => 0,
                'avgSize' => 0
            ];

            try {
                $collStats1 = $database->command(['collStats' => $coll1Name])->toArray()[0];
                $stats1['size'] = $collStats1->size ?? 0;
                $stats1['avgSize'] = $stats1['count'] > 0 ? $stats1['size'] / $stats1['count'] : 0;
            } catch (Exception $e) {
                // Stats not available
            }

            try {
                $collStats2 = $database->command(['collStats' => $coll2Name])->toArray()[0];
                $stats2['size'] = $collStats2->size ?? 0;
                $stats2['avgSize'] = $stats2['count'] > 0 ? $stats2['size'] / $stats2['count'] : 0;
            } catch (Exception $e) {
                // Stats not available
            }

            $_SESSION['comparison_data'] = [
                'collection1' => $stats1,
                'collection2' => $stats2
            ];

            $_SESSION['message'] = 'Collection comparison completed!';
            $_SESSION['messageType'] = 'success';
            header('Location: ' . $_SERVER['PHP_SELF'] . '#stats');
            exit;
        } catch (Exception $e) {
            $message = 'Comparison error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'exportanalytics') {
        try {
            // Gather all analytics data
            $analyticsReport = [
                'generated_at' => date('Y-m-d H:i:s'),
                'collection' => $collectionName,
                'database' => $db,
                'total_documents' => $documentCount,
                'total_size_mb' => round($totalSize / 1024 / 1024, 2),
                'avg_document_size_kb' => round($avgDocSize / 1024, 2),
                'collections_in_db' => count($collectionNames)
            ];

            // Export as JSON
            $filename = 'analytics_' . $collectionName . '_' . date('Ymd_His') . '.json';
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo json_encode($analyticsReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        } catch (Exception $e) {
            $message = 'Export error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    // Bulk Delete Selected Documents (from Browse tab)
    if ($action === 'bulk_delete_selected') {
        $docIds = sanitizeInput($_POST['doc_ids'] ?? '');
        
        if (!empty($docIds)) {
            try {
                $ids = array_filter(array_map('trim', explode(',', $docIds)));
                $objectIds = [];
                
                foreach ($ids as $id) {
                    try {
                        $objectIds[] = new ObjectId($id);
                    } catch (Exception $e) {
                        // Skip invalid IDs
                    }
                }
                
                if (!empty($objectIds)) {
                    $result = $collection->deleteMany(['_id' => ['$in' => $objectIds]]);
                    $_SESSION['message'] = $result->getDeletedCount() . ' document(s) deleted successfully!';
                    $_SESSION['messageType'] = 'success';
                } else {
                    $_SESSION['message'] = 'No valid document IDs provided';
                    $_SESSION['messageType'] = 'error';
                }
            } catch (Exception $e) {
                $_SESSION['message'] = 'Bulk delete error: ' . $e->getMessage();
                $_SESSION['messageType'] = 'error';
            }
        }
        
        header('Location: ' . $_SERVER['PHP_SELF'] . '?collection=' . urlencode($collectionName) . '#browse');
        exit;
    }

    // Bulk Update Selected Documents (from Browse tab)
    if ($action === 'bulk_update_selected') {
        $docIds = sanitizeInput($_POST['doc_ids'] ?? '');
        $updateData = $_POST['update_data'] ?? '';
        
        if (!empty($docIds) && !empty($updateData)) {
            try {
                // Validate JSON
                if (!validateJSON($updateData)) {
                    throw new Exception('Invalid JSON format');
                }
                
                $updateArray = json_decode($updateData, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('JSON decode error: ' . json_last_error_msg());
                }
                
                $sanitizedUpdate = sanitizeMongoQuery($updateArray);
                
                $ids = array_filter(array_map('trim', explode(',', $docIds)));
                $objectIds = [];
                
                foreach ($ids as $id) {
                    try {
                        $objectIds[] = new ObjectId($id);
                    } catch (Exception $e) {
                        // Skip invalid IDs
                    }
                }
                
                if (!empty($objectIds)) {
                    $result = $collection->updateMany(
                        ['_id' => ['$in' => $objectIds]],
                        ['$set' => $sanitizedUpdate]
                    );
                    $_SESSION['message'] = $result->getModifiedCount() . ' document(s) updated successfully!';
                    $_SESSION['messageType'] = 'success';
                } else {
                    $_SESSION['message'] = 'No valid document IDs provided';
                    $_SESSION['messageType'] = 'error';
                }
            } catch (Exception $e) {
                $_SESSION['message'] = 'Bulk update error: ' . $e->getMessage();
                $_SESSION['messageType'] = 'error';
            }
        }
        
        header('Location: ' . $_SERVER['PHP_SELF'] . '?collection=' . urlencode($collectionName) . '#browse');
        exit;
    }
}
