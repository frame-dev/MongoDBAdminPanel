<?php
/**
 * Quick Filters Generator
 * Generates dynamic quick filter buttons based on detected fields
 */

function generateQuickFilters($detectedFields) {
    $quickFilters = [];
    
    // Date-based filters
    $dateFields = array_filter($detectedFields, function ($field) {
        return stripos($field, 'date') !== false || 
               in_array($field, ['created_at', 'updated_at', 'timestamp']);
    });
    
    if (!empty($dateFields)) {
        $dateField = reset($dateFields);
        $quickFilters[] = [
            'label' => '📅 Today',
            'style' => 'background: var(--accent-primary); color: var(--text-on-accent);',
            'action' => "applyQuickFilter('today', '" . htmlspecialchars($dateField) . "')"
        ];
        $quickFilters[] = [
            'label' => '📆 This Week',
            'style' => 'background: var(--accent-primary); color: var(--text-on-accent);',
            'action' => "applyQuickFilter('week', '" . htmlspecialchars($dateField) . "')"
        ];
        $quickFilters[] = [
            'label' => '📅 This Month',
            'style' => 'background: var(--accent-primary); color: var(--text-on-accent);',
            'action' => "applyQuickFilter('month', '" . htmlspecialchars($dateField) . "')"
        ];
    }
    
    // Status filters
    if (in_array('status', $detectedFields)) {
        $quickFilters[] = [
            'label' => '🟢 Active',
            'style' => 'background: var(--accent-success); color: var(--text-on-accent);',
            'action' => "applyQuickFilter('status_value', 'status', 'active')"
        ];
        $quickFilters[] = [
            'label' => '🔴 Inactive',
            'style' => 'background: var(--accent-danger); color: var(--text-on-accent);',
            'action' => "applyQuickFilter('status_value', 'status', 'inactive')"
        ];
    }
    
    // Email filters
    if (in_array('email', $detectedFields)) {
        $quickFilters[] = [
            'label' => '📧 Has Email',
            'style' => 'background: var(--accent-info); color: var(--text-on-accent);',
            'action' => "applyQuickFilter('has_field', 'email')"
        ];
        $quickFilters[] = [
            'label' => '❌ No Email',
            'style' => 'background: var(--text-muted); color: var(--text-on-accent);',
            'action' => "applyQuickFilter('empty_field', 'email')"
        ];
    }
    
    // All documents filter
    $quickFilters[] = [
        'label' => '🌐 All Documents',
        'style' => 'background: var(--surface-muted); color: var(--text-secondary); border: 2px solid var(--border-color);',
        'action' => "applyQuickFilter('all')"
    ];
    
    return $quickFilters;
}

function renderQuickFilters($quickFilters) {
    $html = '';
    foreach ($quickFilters as $filter) {
        $html .= '<button type="button" class="btn" style="' . $filter['style'] . ' padding: 10px 16px; font-size: 13px; border: none; border-radius: 6px; cursor: pointer;" onclick="' . $filter['action'] . '; return false;">';
        $html .= $filter['label'];
        $html .= '</button>';
    }
    return $html;
}
?>
