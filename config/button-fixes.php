<?php
/**
 * Complete Button Handler Fixes for All Missing Form Logic
 * Handles all POST requests for buttons throughout the application
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ===== DOCUMENT OPERATIONS =====
    
    // Add Document
    if ($action === 'add') {
        if (!userHasPermission('create_data')) {
            $message = '❌ Permission denied: You do not have permission to create documents';
            $messageType = 'error';
            auditLog('permission_denied', ['action' => 'add_document'], 'warning', 'security');
        } else {
        try {
            $jsonData = $_POST['json_data'] ?? '{}';
            if (!validateJSON($jsonData)) {
                throw new Exception('Invalid JSON format');
            }
            $doc = json_decode($jsonData, true);
            $collection->insertOne($doc);
            $message = '✅ Document added successfully';
            $messageType = 'success';
            auditLog('document_added', ['fields' => count($doc)], 'info', 'data');
        } catch (Exception $e) {
            $message = '❌ Error adding document: ' . $e->getMessage();
            $messageType = 'error';
        }
        }
    }
    
    // Update Document
    elseif ($action === 'update') {
        if (!userHasPermission('edit_data')) {
            $message = '❌ Permission denied: You do not have permission to edit documents';
            $messageType = 'error';
            auditLog('permission_denied', ['action' => 'update_document'], 'warning', 'security');
        } else {
        try {
            $docId = $_POST['doc_id'] ?? '';
            $jsonData = $_POST['json_data'] ?? '{}';
            
            // First decode to check validity
            $updateData = json_decode($jsonData, true);
            
            if ($updateData === null || json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON format: ' . json_last_error_msg());
            }
            
            if (!is_array($updateData)) {
                throw new Exception('JSON must be an object, not a ' . gettype($updateData));
            }
            
            // Log what fields we received
            $originalFields = array_keys($updateData);
            error_log('Update document - Original fields: ' . implode(', ', $originalFields));
            
            // Now validate with security check
            if (!validateJSON($jsonData)) {
                throw new Exception('JSON contains potentially dangerous content');
            }
            
            // Remove _id from update data (can't update _id field)
            unset($updateData['_id']);
            
            // Log what's left after removing _id
            $remainingFields = array_keys($updateData);
            error_log('Update document - After removing _id: ' . implode(', ', $remainingFields));
            
            // Check if the data is empty after removing _id
            if (empty($updateData)) {
                throw new Exception('No fields to update. The form only sent the _id field. Please check that all document fields are included in the JSON.');
            }
            
            // Check if it's a sequential array (has only numeric sequential keys)
            $keys = array_keys($updateData);
            if (!empty($keys) && $keys === range(0, count($keys) - 1)) {
                throw new Exception('Cannot update document with array. Document must be an object with field names like {"field": "value"}.');
            }
            
            $result = $collection->updateOne(
                ['_id' => new \MongoDB\BSON\ObjectId($docId)],
                ['$set' => $updateData]
            );
            
            if ($result->getModifiedCount() > 0) {
                $message = '✅ Document updated successfully';
                $messageType = 'success';
                auditLog('document_updated', ['doc_id' => $docId, 'fields' => count($updateData)], 'info', 'data');
            } else {
                $message = '⚠️ No changes made to document';
                $messageType = 'warning';
            }
        } catch (Exception $e) {
            $message = '❌ Error updating document: ' . $e->getMessage();
            $messageType = 'error';
            error_log('Update document error: ' . $e->getMessage());
        }
        }
    }
    
    // Delete Document
    elseif ($action === 'delete') {
        if (!userHasPermission('delete_data')) {
            $message = '❌ Permission denied: You do not have permission to delete documents';
            $messageType = 'error';
            auditLog('permission_denied', ['action' => 'delete_document'], 'warning', 'security');
        } else {
        try {
            $docId = $_POST['doc_id'] ?? '';
            $result = $collection->deleteOne(['_id' => new \MongoDB\BSON\ObjectId($docId)]);
            
            if ($result->getDeletedCount() > 0) {
                $message = '✅ Document deleted successfully';
                $messageType = 'success';
                auditLog('document_deleted', ['doc_id' => $docId], 'warning', 'data');
            } else {
                $message = '⚠️ Document not found';
                $messageType = 'warning';
            }
        } catch (Exception $e) {
            $message = '❌ Error deleting document: ' . $e->getMessage();
            $messageType = 'error';
        }
        }
    }
    
    // Duplicate Document
    elseif ($action === 'duplicate') {
        try {
            $docId = $_POST['doc_id'] ?? '';
            $original = $collection->findOne(['_id' => new \MongoDB\BSON\ObjectId($docId)]);
            
            if (!$original) {
                throw new Exception('Document not found');
            }
            
            $duplicate = (array)$original;
            unset($duplicate['_id']);
            
            $collection->insertOne($duplicate);
            $message = '✅ Document duplicated successfully';
            $messageType = 'success';
            auditLog('document_duplicated', ['original_id' => $docId], 'info', 'data');
        } catch (Exception $e) {
            $message = '❌ Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
    
    // ===== BULK OPERATIONS =====
    
    // Bulk Delete Selected
    elseif ($action === 'bulk_delete_selected') {
        try {
            $docIds = explode(',', $_POST['doc_ids'] ?? '');
            $deleted = 0;
            
            foreach ($docIds as $docId) {
                try {
                    $result = $collection->deleteOne(['_id' => new \MongoDB\BSON\ObjectId(trim($docId))]);
                    $deleted += $result->getDeletedCount();
                } catch (Exception $e) {
                    continue;
                }
            }
            
            $message = "✅ Deleted $deleted document(s)";
            $messageType = 'success';
            auditLog('bulk_delete', ['count' => $deleted]);
        } catch (Exception $e) {
            $message = '❌ Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
    
    // Bulk Update Selected
    elseif ($action === 'bulk_update_selected') {
        try {
            $docIds = explode(',', $_POST['doc_ids'] ?? '');
            $updateJson = $_POST['update_data'] ?? '{}';
            
            if (!validateJSON($updateJson)) {
                throw new Exception('Invalid JSON in update data');
            }
            
            $updateData = json_decode($updateJson, true);
            $updated = 0;
            
            foreach ($docIds as $docId) {
                try {
                    $result = $collection->updateOne(
                        ['_id' => new \MongoDB\BSON\ObjectId(trim($docId))],
                        ['$set' => $updateData]
                    );
                    $updated += $result->getModifiedCount();
                } catch (Exception $e) {
                    continue;
                }
            }
            
            $message = "✅ Updated $updated document(s)";
            $messageType = 'success';
            auditLog('bulk_update', ['count' => $updated]);
        } catch (Exception $e) {
            $message = '❌ Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
    
    // ===== FIELD OPERATIONS =====
    
    // Add Field to all documents
    elseif ($action === 'add_field') {
        try {
            $fieldName = sanitizeInput($_POST['field_name'] ?? '');
            $defaultValue = $_POST['default_value'] ?? '';
            
            if (empty($fieldName)) {
                throw new Exception('Field name required');
            }
            
            try {
                $value = json_decode($defaultValue, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $value = $defaultValue;
                }
            } catch (Exception $e) {
                $value = $defaultValue;
            }
            
            $result = $collection->updateMany(
                [],
                ['$set' => [$fieldName => $value]]
            );
            
            $message = "✅ Added field to {$result->getModifiedCount()} documents";
            $messageType = 'success';
            auditLog('field_added', ['field' => $fieldName]);
        } catch (Exception $e) {
            $message = '❌ Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
    
    // Remove Field from all documents
    elseif ($action === 'remove_field') {
        try {
            $fieldName = sanitizeInput($_POST['field_name'] ?? '');
            
            if (empty($fieldName)) {
                throw new Exception('Field name required');
            }
            
            $result = $collection->updateMany(
                [],
                ['$unset' => [$fieldName => '']]
            );
            
            $message = "✅ Removed field from {$result->getModifiedCount()} documents";
            $messageType = 'success';
            auditLog('field_removed', ['field' => $fieldName]);
        } catch (Exception $e) {
            $message = '❌ Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
    
    // Rename Field
    elseif ($action === 'rename_field') {
        try {
            $oldName = sanitizeInput($_POST['old_field_name'] ?? '');
            $newName = sanitizeInput($_POST['new_field_name'] ?? '');
            
            if (empty($oldName) || empty($newName)) {
                throw new Exception('Both field names required');
            }
            
            $result = $collection->updateMany(
                [],
                ['$rename' => [$oldName => $newName]]
            );
            
            $message = "✅ Renamed field in {$result->getModifiedCount()} documents";
            $messageType = 'success';
            auditLog('field_renamed', ['old' => $oldName, 'new' => $newName]);
        } catch (Exception $e) {
            $message = '❌ Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
    
    // ===== COLLECTION OPERATIONS =====
    
    // Create Collection
    elseif ($action === 'create_collection') {
        if (!userHasPermission('manage_collections')) {
            $message = '❌ Permission denied: You do not have permission to manage collections';
            $messageType = 'error';
            auditLog('permission_denied', ['action' => 'create_collection'], 'critical', 'security');
        } else {
        try {
            $collectionName = sanitizeInput($_POST['collection_name'] ?? '');
            
            if (empty($collectionName)) {
                throw new Exception('Collection name required');
            }
            
            if (preg_match('/[^a-zA-Z0-9_-]/', $collectionName)) {
                throw new Exception('Invalid collection name (use alphanumeric, _, -)');
            }
            
            $database->createCollection($collectionName);
            $message = "✅ Collection '$collectionName' created";
            $messageType = 'success';
            auditLog('collection_created', ['name' => $collectionName]);
            
            header('Location: ' . $_SERVER['PHP_SELF'] . '?collection=' . urlencode($collectionName));
            exit;
        } catch (Exception $e) {
            $message = '❌ Error: ' . $e->getMessage();
            $messageType = 'error';
        }
        }
    }
    
    // Drop Collection
    elseif ($action === 'drop_collection') {
        if (!userHasPermission('manage_collections')) {
            $message = '❌ Permission denied: You do not have permission to drop collections';
            $messageType = 'error';
            auditLog('permission_denied', ['action' => 'drop_collection'], 'critical', 'security');
        } else {
        try {
            $collectionName = sanitizeInput($_POST['collection_to_drop'] ?? '');
            $confirmName = sanitizeInput($_POST['confirm_collection_name'] ?? '');
            
            if ($collectionName !== $confirmName) {
                throw new Exception('Collection name does not match');
            }
            
            $database->dropCollection($collectionName);
            $message = "✅ Collection '$collectionName' dropped";
            $messageType = 'success';
            auditLog('collection_dropped', ['name' => $collectionName]);
            
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            $message = '❌ Error: ' . $e->getMessage();
            $messageType = 'error';
        }
        }
    }
    
    // Rename Collection
    elseif ($action === 'rename_collection') {
        try {
            $oldName = sanitizeInput($_POST['old_collection_name'] ?? '');
            $newName = sanitizeInput($_POST['new_collection_name'] ?? '');
            
            if (empty($oldName) || empty($newName)) {
                throw new Exception('Both collection names required');
            }
            
            $databaseName = $database->getDatabaseName();
            $adminDatabase = $client->selectDatabase('admin');
            $adminDatabase->command([
                'renameCollection' => "$databaseName.$oldName",
                'to' => "$databaseName.$newName"
            ]);
            
            $message = "✅ Collection renamed to '$newName'";
            $messageType = 'success';
            auditLog('collection_renamed', ['old' => $oldName, 'new' => $newName]);
            
            header('Location: ' . $_SERVER['PHP_SELF'] . '?collection=' . urlencode($newName));
            exit;
        } catch (Exception $e) {
            $message = '❌ Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
    
    // Clone Collection
    elseif ($action === 'clone_collection') {
        try {
            $sourceCollection = sanitizeInput($_POST['clone_source'] ?? $_POST['source_collection'] ?? '');
            $targetCollection = sanitizeInput($_POST['clone_target'] ?? $_POST['target_collection'] ?? '');
            
            if (empty($sourceCollection) || empty($targetCollection)) {
                throw new Exception('Source and target collection names required');
            }
            
            // Create target collection
            $database->createCollection($targetCollection);
            
            // Copy documents
            $sourceColl = $database->getCollection($sourceCollection);
            $targetColl = $database->getCollection($targetCollection);
            
            $documents = $sourceColl->find()->toArray();
            if (!empty($documents)) {
                $targetColl->insertMany($documents);
            }
            
            // Copy indexes if requested
            if (isset($_POST['clone_indexes'])) {
                try {
                    $sourceIndexes = iterator_to_array($sourceColl->listIndexes());
                    foreach ($sourceIndexes as $index) {
                        if ($index['name'] !== '_id_') {
                            $targetColl->createIndex($index['key']);
                        }
                    }
                } catch (Exception $e) {
                    // Index copy failed but collection is ok
                }
            }
            
            $message = "✅ Collection cloned to '$targetCollection'";
            $messageType = 'success';
            auditLog('collection_cloned', ['source' => $sourceCollection, 'target' => $targetCollection]);
        } catch (Exception $e) {
            $message = '❌ Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
    
    // ===== INDEX OPERATIONS =====
    
    // Create Index
    elseif ($action === 'create_index') {
        try {
            $field = sanitizeInput($_POST['index_field'] ?? '');
            $order = (int)($_POST['index_order'] ?? $_POST['index_type'] ?? 1);
            
            if (empty($field)) {
                throw new Exception('Field name required');
            }
            
            $indexOptions = [];
            if (isset($_POST['index_unique'])) {
                $indexOptions['unique'] = true;
            }
            if (isset($_POST['index_sparse'])) {
                $indexOptions['sparse'] = true;
            }
            if (!empty($_POST['index_name'])) {
                $indexOptions['name'] = sanitizeInput($_POST['index_name']);
            }
            
            $collection->createIndex([$field => $order], $indexOptions);
            $message = "✅ Index created on field: $field";
            $messageType = 'success';
            auditLog('index_created', ['field' => $field]);
        } catch (Exception $e) {
            $message = '❌ Error creating index: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
    
    // Drop Index
    elseif ($action === 'drop_index') {
        try {
            $indexName = sanitizeInput($_POST['index_name'] ?? '');
            
            if (empty($indexName)) {
                throw new Exception('Index name required');
            }
            
            $collection->dropIndex($indexName);
            $message = "✅ Index '$indexName' dropped";
            $messageType = 'success';
            auditLog('index_dropped', ['name' => $indexName]);
        } catch (Exception $e) {
            $message = '❌ Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
    
    // ===== ADVANCED OPERATIONS =====
    
    // Delete All Documents
    elseif ($action === 'delete_all') {
        if (!userHasPermission('delete_data') || !userHasPermission('bulk_operations')) {
            $message = '❌ Permission denied: You do not have permission to delete all documents';
            $messageType = 'error';
            auditLog('permission_denied', ['action' => 'delete_all_documents'], 'critical', 'security');
        } else {
        try {
            $result = $collection->deleteMany([]);
            $message = "✅ Deleted {$result->getDeletedCount()} documents";
            $messageType = 'success';
            auditLog('delete_all_documents', ['count' => $result->getDeletedCount()], 'critical', 'data');
        } catch (Exception $e) {
            $message = '❌ Error: ' . $e->getMessage();
            $messageType = 'error';
        }
        }
    }

    
    // Bulk Update by Query
    elseif ($action === 'bulk_update_query') {
        try {
            $filterJson = $_POST['bulk_filter'] ?? '{}';
            $updateJson = $_POST['bulk_update'] ?? '{}';
            
            if (!validateJSON($filterJson) || !validateJSON($updateJson)) {
                throw new Exception('Invalid JSON format');
            }
            
            $filter = json_decode($filterJson, true);
            $updateData = json_decode($updateJson, true);
            
            // Wrap with $set if not already using operators
            $hasOperators = false;
            foreach ($updateData as $key => $value) {
                if (strpos($key, '$') === 0) {
                    $hasOperators = true;
                    break;
                }
            }
            
            if (!$hasOperators) {
                $updateData = ['$set' => $updateData];
            }
            
            $result = $collection->updateMany($filter, $updateData);
            
            $message = "✅ Updated {$result->getModifiedCount()} document(s)";
            $messageType = 'success';
            auditLog('bulk_update_query', ['count' => $result->getModifiedCount()]);
        } catch (Exception $e) {
            $message = '❌ Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
    // Catch-all: If we processed a POST action but didn't redirect yet, redirect now to prevent form resubmission
    if (isset($message)) {
        $_SESSION['message'] = $message;
        $_SESSION['messageType'] = $messageType ?? 'info';
        if (ob_get_length()) ob_clean();
        if (isset($collectionName) && $collectionName) {
            header('Location: ' . $_SERVER['PHP_SELF'] . '?collection=' . urlencode($collectionName));
        } else {
            header('Location: ' . $_SERVER['PHP_SELF']);
        }
        exit;
    }
}
?>
