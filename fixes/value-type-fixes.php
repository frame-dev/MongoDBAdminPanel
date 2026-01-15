<?php
/**
 * Value Type Coercion Fixes
 * Include this file where value type conversion is needed
 */

function coerceValueByType($rawValue, $valueType) {
    $value = $rawValue;
    
    switch ($valueType) {
        case 'number':
            if (!is_numeric($rawValue)) {
                throw new Exception('Value is not numeric');
            }
            $value = (strpos($rawValue, '.') !== false) ? (float) $rawValue : (int) $rawValue;
            break;
            
        case 'bool':
            $v = strtolower(trim($rawValue));
            $value = in_array($v, ['1', 'true', 'yes', 'y', 'on'], true);
            break;
            
        case 'null':
            $value = null;
            break;
            
        case 'objectid':
            $value = new MongoDB\BSON\ObjectId($rawValue);
            break;
            
        case 'date':
            $ts = strtotime($rawValue);
            if ($ts === false) {
                throw new Exception('Invalid date format');
            }
            $value = new MongoDB\BSON\UTCDateTime($ts * 1000);
            break;
            
        case 'string':
        default:
            $value = $rawValue;
            break;
    }
    
    return $value;
}
?>
