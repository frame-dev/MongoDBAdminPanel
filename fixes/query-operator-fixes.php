<?php
/**
 * Query Operator Fixes
 * Handles MongoDB query operator conversion
 */

function buildMongoQueryByOperator($field, $operator, $value, $rawValue) {
    $mongoQuery = [];
    
    switch ($operator) {
        case 'equals':
            $mongoQuery[$field] = $value;
            break;
            
        case 'contains':
            $mongoQuery[$field] = ['$regex' => (string) $rawValue, '$options' => 'i'];
            break;
            
        case 'starts':
            $mongoQuery[$field] = ['$regex' => '^' . (string) $rawValue, '$options' => 'i'];
            break;
            
        case 'ends':
            $mongoQuery[$field] = ['$regex' => (string) $rawValue . '$', '$options' => 'i'];
            break;
            
        case 'gt':
            $mongoQuery[$field] = ['$gt' => $value];
            break;
            
        case 'lt':
            $mongoQuery[$field] = ['$lt' => $value];
            break;
            
        case 'gte':
            $mongoQuery[$field] = ['$gte' => $value];
            break;
            
        case 'lte':
            $mongoQuery[$field] = ['$lte' => $value];
            break;
            
        default:
            $mongoQuery[$field] = $value;
            break;
    }
    
    return $mongoQuery;
}
?>
