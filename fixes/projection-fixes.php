<?php
/**
 * Projection Handling Fixes
 * Parses and validates projection fields
 */

function parseProjectionFields($projectionRaw) {
    $projection = null;
    
    if ($projectionRaw !== '') {
        $fields = array_filter(array_map('trim', explode(',', $projectionRaw)));
        $proj = [];
        
        foreach ($fields as $f) {
            $f = sanitizeInput($f);
            if ($f !== '' && validateFieldName($f)) {
                $proj[$f] = 1;
            }
        }
        
        if (!empty($proj)) {
            $projection = $proj;
        }
    }
    
    return $projection;
}
?>
