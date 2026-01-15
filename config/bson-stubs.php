<?php
/**
 * MongoDB BSON Class Stubs
 * 
 * Provides type hints and IDE support for MongoDB BSON classes.
 * These are actually provided by the PHP MongoDB extension at runtime,
 * but need to be declared here for IDE/static analysis support.
 * 
 * @package MongoDB Admin Panel
 * @subpackage Config
 */

namespace MongoDB\BSON {
    /**
     * Represents a BSON ObjectId
     */
    if (!class_exists('MongoDB\BSON\ObjectId', false)) {
        class ObjectId {
            public function __construct($id = null) {}
            public function __toString(): string { return ''; }
            public static function isValid($oid) {}
        }
    }

    /**
     * Represents a BSON UTCDateTime
     */
    if (!class_exists('MongoDB\BSON\UTCDateTime', false)) {
        class UTCDateTime {
            public function __construct($milliseconds = null) {}
            public function toDateTime() {}
            public function __toString(): string { return ''; }
        }
    }

    /**
     * Represents a BSON Binary
     */
    if (!class_exists('MongoDB\BSON\Binary', false)) {
        class Binary {
            public function __construct($data, $type = 0) {}
            public function getData() {}
            public function getType() {}
            public function __toString(): string { return ''; }
        }
    }

    /**
     * Represents a BSON Decimal128
     */
    if (!class_exists('MongoDB\BSON\Decimal128', false)) {
        class Decimal128 {
            public function __construct($decimal) {}
            public function __toString(): string { return ''; }
        }
    }

    /**
     * Represents a BSON MaxKey
     */
    if (!class_exists('MongoDB\BSON\MaxKey', false)) {
        class MaxKey {
            public function __toString(): string { return ''; }
        }
    }

    /**
     * Represents a BSON MinKey
     */
    if (!class_exists('MongoDB\BSON\MinKey', false)) {
        class MinKey {
            public function __toString(): string { return ''; }
        }
    }

    /**
     * Represents a BSON Regex
     */
    if (!class_exists('MongoDB\BSON\Regex', false)) {
        class Regex {
            public function __construct($pattern, $flags = '') {}
            public function getPattern() {}
            public function getFlags() {}
            public function __toString(): string { return ''; }
        }
    }

    /**
     * Represents a BSON Timestamp
     */
    if (!class_exists('MongoDB\BSON\Timestamp', false)) {
        class Timestamp {
            public function __construct($increment, $timestamp) {}
            public function getIncrement() {}
            public function getTimestamp() {}
            public function __toString(): string { return ''; }
        }
    }

    /**
     * Represents a BSON Undefined
     */
    if (!class_exists('MongoDB\BSON\Undefined', false)) {
        class Undefined {
            public function __toString(): string { return ''; }
        }
    }
}
