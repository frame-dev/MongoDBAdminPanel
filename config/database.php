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

// Get connection from session
$hostName = $_SESSION['mongo_connection']['hostname'];
$port = $_SESSION['mongo_connection']['port'];
$db = $_SESSION['mongo_connection']['database'];
$user = $_SESSION['mongo_connection']['username'];
$pass = $_SESSION['mongo_connection']['password'];
$collection = $_SESSION['mongo_connection']['collection'];

// Build connection URI
if ($user && $pass) {
    $uri = "mongodb://$user:$pass@$hostName:$port/$db?authSource=$db";
} else {
    $uri = "mongodb://$hostName:$port/$db";
}

// Connect to MongoDB
$client = new Client($uri);
$database = $client->$db;

// Get list of all collections
$collectionslist = $database->listCollections();
$allCollectionNames = [];
foreach ($collectionslist as $coll) {
    $allCollectionNames[] = $coll->getName();
}

// Get selected collection from GET/POST parameter or use default from config
$selectedCollection = $_POST['collection'] ?? $_GET['collection'] ?? $collection;
if (!in_array($selectedCollection, $allCollectionNames)) {
    $selectedCollection = $collection; // Fall back to default if invalid
}

$collectionName = $selectedCollection;
$collection = $database->getCollection($collectionName);

// Initialize message variables
$message = '';
$messageType = '';
