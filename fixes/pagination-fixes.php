<?php
/**
 * Pagination HTML Generator
 * Generates pagination controls
 */

function generatePaginationControls($page, $totalPages) {
    $html = '';
    $startPage = max(1, $page - 4);
    $endPage = min($totalPages, $page + 4);
    
    // First and Previous buttons
    if ($page > 1) {
        $html .= '<button type="button" class="btn" onclick="jumpToPage(1)" style="background: var(--accent-primary); color: var(--text-on-accent); padding: 10px 16px;">⏮️ First</button>';
        $html .= '<button type="button" class="btn" onclick="jumpToPage(' . ($page - 1) . ')" style="background: var(--accent-primary); color: var(--text-on-accent); padding: 10px 16px;">⬅️ Previous</button>';
    }
    
    // Ellipsis if needed
    if ($startPage > 1) {
        $html .= '<span style="padding: 10px; color: var(--text-secondary);">...</span>';
    }
    
    // Page numbers
    for ($i = $startPage; $i <= $endPage; $i++) {
        $isActive = ($i === $page);
        $btnStyle = $isActive 
            ? 'background: linear-gradient(135deg, var(--accent-primary) 0%, var(--accent-secondary) 100%); color: var(--text-on-accent); padding: 10px 16px; font-weight: 600; border: none; border-radius: 6px;'
            : 'background: var(--card-bg); color: var(--accent-primary); padding: 10px 16px; border: 2px solid var(--accent-primary); border-radius: 6px; cursor: pointer;';
        
        $html .= '<button type="button" class="btn" onclick="jumpToPage(' . $i . ')" style="' . $btnStyle . '">' . $i . '</button>';
    }
    
    // Ellipsis if needed
    if ($endPage < $totalPages) {
        $html .= '<span style="padding: 10px; color: var(--text-secondary);">...</span>';
    }
    
    // Next and Last buttons
    if ($page < $totalPages) {
        $html .= '<button type="button" class="btn" onclick="jumpToPage(' . ($page + 1) . ')" style="background: var(--accent-primary); color: var(--text-on-accent); padding: 10px 16px;">➡️ Next</button>';
        $html .= '<button type="button" class="btn" onclick="jumpToPage(' . $totalPages . ')" style="background: var(--accent-primary); color: var(--text-on-accent); padding: 10px 16px;">⏭️ Last</button>';
    }
    
    return $html;
}
?>
