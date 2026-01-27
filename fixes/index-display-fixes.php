<?php
/**
 * Index Display Fixes
 * Renders MongoDB indexes in user-friendly format
 */

function renderIndexKeys($indexKeys) {
    if (empty($indexKeys)) {
        return '<span style="color: var(--text-muted);">No keys</span>';
    }
    
    $html = '<div style="display: flex; flex-wrap: wrap; gap: 6px;">';
    
    foreach ($indexKeys as $field => $order) {
        $orderIcon = $order == 1 ? '⬆️' : '⬇️';
        $orderText = $order == 1 ? 'ASC' : 'DESC';
        $html .= '<span style="background: var(--accent-primary); color: var(--text-on-accent); padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">';
        $html .= htmlspecialchars($field) . ' ' . $orderIcon . ' ' . $orderText;
        $html .= '</span>';
    }
    
    $html .= '</div>';
    return $html;
}
?>
