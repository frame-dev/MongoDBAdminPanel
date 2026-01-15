<?php
/**
 * Database Configuration and Connection
 * 
 * Manages MongoDB database connection and collection selection.
 * Handles connection URI building with authentication support.
 * 
 * @package MongoDB Admin Panel
 * @subpackage Config
 * @version 1.0.0
 * @author Development Team
 * @link https://github.com/frame-dev/MongoDBAdminPanel
 * @license MIT
 */
require_once 'config/security.php';

use MongoDB\Client;

// Initialize database connection as null
$database = null;
$client = null;

// Only attempt connection if session data exists
if (isset($_SESSION['mongo_connection'])) {
    // Get connection from session
    $hostName = $_SESSION['mongo_connection']['hostname'] ?? null;
    $port = $_SESSION['mongo_connection']['port'] ?? null;
    $db = $_SESSION['mongo_connection']['database'] ?? null;
    $user = $_SESSION['mongo_connection']['username'] ?? null;
    $pass = $_SESSION['mongo_connection']['password'] ?? null;
    $collection = $_SESSION['mongo_connection']['collection'] ?? null;

    // Only build URI if we have required connection details
    if ($hostName && $port && $db) {
        // Build connection URI
        if ($user && $pass) {
            $uri = "mongodb://$user:$pass@$hostName:$port/$db?authSource=$db";
        } else {
            $uri = "mongodb://$hostName:$port/$db";
        }

        // Connect to MongoDB
        try {
            $client = new Client($uri);
            $database = $client->$db;
        } catch (Exception $e) {
            // Connection failed - database will be null
            error_log("MongoDB Connection Error: " . $e->getMessage());
        }
    }
} else {
    // Session not set - this is typically the connection form view
    $collection = null;
}

// Get list of all collections (only if connected)
$allCollectionNames = [];
if ($database !== null) {
    try {
        $collectionslist = $database->listCollections();
        foreach ($collectionslist as $coll) {
            $allCollectionNames[] = $coll->getName();
        }
    } catch (Exception $e) {
        error_log("Error listing collections: " . $e->getMessage());
    }
}

// Get selected collection from GET/POST parameter or use default from config
$selectedCollection = null;
if ($database !== null) {
    $selectedCollection = $_POST['collection'] ?? $_GET['collection'] ?? $collection;
    if ($selectedCollection && !in_array($selectedCollection, $allCollectionNames)) {
        $selectedCollection = $collection; // Fall back to default if invalid
    }
}

$collectionName = $selectedCollection;
$collection = null;

// Only get collection if database is connected and we have a selected collection
if ($database !== null && $collectionName !== null) {
    try {
        $collection = $database->getCollection($collectionName);
    } catch (Exception $e) {
        error_log("Error getting collection: " . $e->getMessage());
    }
}

// Initialize message variables
$message = '';
$messageType = '';
