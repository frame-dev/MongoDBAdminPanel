<?php
/**
 * Schema Analysis Fixes
 * Handles sample value truncation and display
 */

function truncateSampleValue($value, $maxLength = 50) {
    if (is_array($value) || is_object($value)) {
        $sampleValue = json_encode($value);
        if (strlen($sampleValue) > $maxLength) {
            return substr($sampleValue, 0, $maxLength) . '...';
        }
        return $sampleValue;
    }
    
    $sampleValue = (string) $value;
    if (strlen($sampleValue) > $maxLength) {
        return substr($sampleValue, 0, $maxLength) . '...';
    }
    
    return $sampleValue;
}

function renderSchemaField($fieldName, $fieldInfo) {
    $types = implode(', ', $fieldInfo['types']);
    $frequency = round(($fieldInfo['count'] / 100) * 100, 1);
    $samples = implode(', ', array_map('truncateSampleValue', $fieldInfo['samples']));
    
    $html = '<div style="display: grid; grid-template-columns: 200px 1fr 150px; gap: 20px; align-items: start;">';
    $html .= '<div>';
    $html .= '<p style="font-weight: 600; color: #333; margin-bottom: 4px;">' . htmlspecialchars($fieldName) . '</p>';
    $html .= '<span style="background: #e3f2fd; color: #1976d2; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">' . htmlspecialchars($types) . '</span>';
    $html .= '</div>';
    $html .= '<div>';
    $html .= '<p style="color: #666; font-size: 13px; margin-bottom: 6px;">Sample values:</p>';
    $html .= '<p style="color: #999; font-size: 12px; font-family: monospace; background: #f8f9fa; padding: 8px; border-radius: 4px;">' . htmlspecialchars($samples) . '</p>';
    $html .= '</div>';
    $html .= '<div style="text-align: right;">';
    $html .= '<p style="font-size: 24px; font-weight: bold; color: #667eea; margin-bottom: 4px;">' . $frequency . '%</p>';
    $html .= '<p style="font-size: 11px; color: #999;">In ' . $fieldInfo['count'] . ' docs</p>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}
?>
