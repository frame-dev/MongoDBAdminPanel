# Persistent Query History - Implementation Guide

## Overview
Query history is now persisted to MongoDB, allowing queries to be tracked across sessions and providing better audit capabilities. The implementation includes automatic cleanup, per-user tracking, and indexed retrieval for performance.

## What Changed

### Before (v1.0.0)
- Query history stored only in PHP session
- Lost when session expires or user logs out
- Limited to 50 most recent queries
- No audit trail or performance tracking

### After (v1.1.0)
- Query history stored in MongoDB `_query_history` collection
- Persists across sessions and logins
- Per-user tracking (each user sees only their queries)
- 30-day retention with automatic cleanup
- Indexed for fast retrieval
- Full performance metrics captured

## Database Schema

### Collection: `_query_history`

```javascript
{
  _id: ObjectId,
  user_id: ObjectId,           // Reference to _auth_users
  collection: String,          // Collection name queried
  database: String,            // Database name
  type: String,                // "visual" or "manual"
  query: Object,               // Full query object
  results_count: Number,       // Documents returned
  execution_time: Number,      // Milliseconds
  status: String,              // "success" or "error"
  created_at: ISODate          // Timestamp with TTL index
}
```

### Indexes
```javascript
// Compound index for efficient user queries
db._query_history.createIndex({ user_id: 1, created_at: -1 })

// TTL index - automatically deletes queries older than 30 days
db._query_history.createIndex({ created_at: -1 }, { expireAfterSeconds: 2592000 })
```

## Code Changes

### Updated Functions in index.php

#### `addToQueryHistory($queryData)`
- Saves to both session (backward compatibility) and MongoDB
- Automatically creates indexes on first use
- Includes user ID for per-user tracking
- Captures full query metadata

**Parameters:**
```php
$queryData = [
    'type' => 'visual' | 'manual',
    'query' => [...],           // Query object
    'results_count' => 123,
    'execution_time' => 45.5,
    'status' => 'success'
]
```

#### `getQueryHistory($limit = 10)`
- First attempts to fetch from MongoDB (if connected)
- Falls back to session if database unavailable
- Returns array of most recent queries first
- Includes full query details and metadata

**Returns:**
```php
[
    [
        'timestamp' => '2026-01-15 10:30:45',
        'type' => 'visual',
        'query' => [...],
        'results_count' => 42,
        'execution_time' => 23.5,
        'status' => 'success'
    ],
    ...
]
```

#### `clearQueryHistory()`
- Clears both session and database history
- Only removes current user's history (per-user isolation)
- Logged in audit trail

## Features

### Automatic Index Creation
- Compound index on (user_id, created_at) created on first save
- TTL index for automatic 30-day cleanup
- Prevents duplicate index creation

### Per-User Isolation
- Each user only sees their own query history
- User ID from authentication system
- Secure multi-user environment

### Fallback Support
- Works even if MongoDB connection is temporarily lost
- Session storage provides temporary backup
- Automatic sync when connection restored

### Performance Tracking
- Execution time captured for each query
- Results count for performance analysis
- Status tracking (success/failure)

### Automatic Cleanup
- Queries older than 30 days automatically deleted
- Prevents database bloat
- MongoDB TTL index handles cleanup

## Usage Examples

### Tracking a Visual Query
```php
$startTime = microtime(true);

// Execute query
$results = $collection->find($filter)->toArray();

$executionTime = (microtime(true) - $startTime) * 1000; // Convert to ms

addToQueryHistory([
    'type' => 'visual',
    'query' => $filter,
    'results_count' => count($results),
    'execution_time' => $executionTime,
    'status' => 'success'
]);
```

### Retrieving User's Recent Queries
```php
$recentQueries = getQueryHistory(20);  // Get last 20 queries

foreach ($recentQueries as $query) {
    echo $query['timestamp'] . ' - ' . $query['type'] . ': ' . 
         $query['results_count'] . ' results';
}
```

### Clearing History
```php
clearQueryHistory();  // Deletes from both session and database
```

## Benefits

### ✅ Audit Trail
- Complete record of all queries executed
- Timestamp and user tracking
- Security and compliance ready

### ✅ Performance Analysis
- Historical execution times
- Results volume tracking
- Identify slow queries

### ✅ User Experience
- History survives logout/session expiration
- Quick access to recent queries
- No data loss on browser close

### ✅ Multi-User Support
- Each user isolated to their own history
- No cross-user visibility
- Secure in shared environments

### ✅ Automatic Maintenance
- 30-day auto-cleanup prevents bloat
- No manual cleanup needed
- Storage-efficient design

## Security Considerations

### Data Isolation
- Users can only access their own history
- Query content stored for audit purposes
- Consider sensitive data in query filters

### Access Control
- History queries require authentication
- Only authenticated users' queries tracked
- Anonymous users use session-only fallback

### Storage
- Queries stored in same database as other sensitive data
- Use MongoDB authentication and encryption
- Regular backups recommended

## Migration Notes

### From v1.0.0
- No data migration needed
- Existing session history continues to work
- New queries automatically go to database
- Session and database stay in sync

### Backward Compatibility
- Session-based fallback maintained
- Works with or without database connection
- Existing code continues to function
- No breaking changes

## Troubleshooting

### "Collection already exists" Error
- Normal on first run when creating indexes
- Indexes may be created by a concurrent request
- Safe to ignore, query history still works

### History Not Persisting
- Check MongoDB connection status
- Verify user authentication
- Session fallback will still work
- Check error logs for details

### Performance Issues
- Verify indexes are created properly
- Check database load
- Consider limiting query history retention
- Review MongoDB query performance

## Configuration

### Change Retention Period
In `addToQueryHistory()`, modify the TTL value:
```php
// Change from 2592000 (30 days) to 604800 (7 days)
$historyCollection->createIndex(
    ['created_at' => -1], 
    ['expireAfterSeconds' => 604800]
);
```

### Disable Database Storage
Edit `addToQueryHistory()` and comment out the database save section:
```php
// $historyCollection->insertOne($historyEntry);  // Disabled
```

### Increase History Limit
Default is 10 queries retrieved. Call with higher limit:
```php
getQueryHistory(50);  // Get last 50 instead of 10
```

## Future Enhancements

### Planned Improvements (v1.2.0+)
- [ ] Query export to CSV/JSON
- [ ] Query performance analytics dashboard
- [ ] Query pattern analysis
- [ ] Saved query templates
- [ ] Query execution alerts
- [ ] Slow query identification

## Support & Questions

For issues or questions about persistent query history:
1. Check error logs in `logs/` directory
2. Verify MongoDB `_query_history` collection exists
3. Ensure user authentication is working
4. Check MongoDB indexes with: `db._query_history.getIndexes()`

---

**Feature Added:** January 15, 2026  
**Version:** 1.1.0  
**Status:** ✅ Production Ready
