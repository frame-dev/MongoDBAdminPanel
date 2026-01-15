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
        $html .= '<button type="button" class="btn" onclick="jumpToPage(1)" style="background: #667eea; color: white; padding: 10px 16px;">⏮️ First</button>';
        $html .= '<button type="button" class="btn" onclick="jumpToPage(' . ($page - 1) . ')" style="background: #667eea; color: white; padding: 10px 16px;">⬅️ Previous</button>';
    }
    
    // Ellipsis if needed
    if ($startPage > 1) {
        $html .= '<span style="padding: 10px; color: #666;">...</span>';
    }
    
    // Page numbers
    for ($i = $startPage; $i <= $endPage; $i++) {
        $isActive = ($i === $page);
        $btnStyle = $isActive 
            ? 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 10px 16px; font-weight: 600; border: none; border-radius: 6px;'
            : 'background: white; color: #667eea; padding: 10px 16px; border: 2px solid #667eea; border-radius: 6px; cursor: pointer;';
        
        $html .= '<button type="button" class="btn" onclick="jumpToPage(' . $i . ')" style="' . $btnStyle . '">' . $i . '</button>';
    }
    
    // Ellipsis if needed
    if ($endPage < $totalPages) {
        $html .= '<span style="padding: 10px; color: #666;">...</span>';
    }
    
    // Next and Last buttons
    if ($page < $totalPages) {
        $html .= '<button type="button" class="btn" onclick="jumpToPage(' . ($page + 1) . ')" style="background: #667eea; color: white; padding: 10px 16px;">➡️ Next</button>';
        $html .= '<button type="button" class="btn" onclick="jumpToPage(' . $totalPages . ')" style="background: #667eea; color: white; padding: 10px 16px;">⏭️ Last</button>';
    }
    
    return $html;
}
?>
