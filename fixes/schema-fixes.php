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

function renderSchemaField($fieldName, $fieldInfo, $sampleSize = 100) {
    $types = implode(', ', $fieldInfo['types']);
    $sampleSize = max(1, (int) $sampleSize);
    $frequency = round(($fieldInfo['count'] / $sampleSize) * 100, 1);
    $samples = implode(', ', array_map('truncateSampleValue', $fieldInfo['samples']));
    
    $html = '<div style="display: grid; grid-template-columns: 200px 1fr 150px; gap: 20px; align-items: start;">';
    $html .= '<div>';
    $html .= '<p style="font-weight: 600; color: var(--text-primary); margin-bottom: 4px;">' . htmlspecialchars($fieldName) . '</p>';
    $html .= '<span style="background: var(--info-bg); color: var(--info-text); padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">' . htmlspecialchars($types) . '</span>';
    $html .= '</div>';
    $html .= '<div>';
    $html .= '<p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 6px;">Sample values:</p>';
    $html .= '<p style="color: var(--text-muted); font-size: 12px; font-family: monospace; background: var(--surface-muted); padding: 8px; border-radius: 4px;">' . htmlspecialchars($samples) . '</p>';
    $html .= '</div>';
    $html .= '<div style="text-align: right;">';
    $html .= '<p style="font-size: 24px; font-weight: bold; color: var(--accent-primary); margin-bottom: 4px;">' . $frequency . '%</p>';
    $html .= '<p style="font-size: 11px; color: var(--text-muted);">In ' . $fieldInfo['count'] . ' docs</p>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}
?>
