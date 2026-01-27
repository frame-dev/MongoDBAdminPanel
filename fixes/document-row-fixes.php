<?php
/**
 * Document Table Row Generator
 * Generates HTML for document table rows
 */

function generateDocumentTableRow($doc) {
    $docArray = json_decode(json_encode($doc), true);
    $docId = (string) ($doc['_id'] ?? '');
    $docJson = json_encode($docArray);
    $docPreview = implode(', ', array_slice($docArray, 0, 2));
    $displayId = formatObjectIdDisplay($docId, 12);
    
    if (strlen($docPreview) > 50) {
        $docPreview = substr($docPreview, 0, 50) . '...';
    }
    
    $html = '<tr data-doc-id="' . htmlspecialchars($docId) . '" data-json="' . htmlspecialchars($docJson) . '" style="border-bottom: 1px solid var(--table-border); transition: background 0.2s;">';
    $html .= '<td style="padding: 12px; color: var(--text-secondary); font-family: monospace; font-size: 12px;">';
    $html .= '<input type="checkbox" class="doc-checkbox" value="' . htmlspecialchars($docId) . '" style="display: none; margin-right: 8px;">';
    $html .= htmlspecialchars($displayId);
    $html .= '</td>';
    $html .= '<td style="padding: 12px; color: var(--text-secondary);">';
    $html .= '<div style="font-size: 13px; line-height: 1.5;">' . htmlspecialchars($docPreview) . '</div>';
    $html .= '</td>';
    $html .= '<td style="padding: 12px; text-align: center; display: flex; gap: 6px; justify-content: center; flex-wrap: wrap;">';
    $html .= '<button type="button" class="btn" onclick="viewDocument(\'' . htmlspecialchars($docId) . '\'); return false;" style="background: var(--accent-info); color: var(--text-on-accent); padding: 6px 12px; font-size: 11px; border: none; border-radius: 4px; cursor: pointer;">👁️ View</button>';
    $html .= '<button type="button" class="btn" onclick="editDocument(\'' . htmlspecialchars($docId) . '\'); return false;" style="background: var(--accent-primary); color: var(--text-on-accent); padding: 6px 12px; font-size: 11px; border: none; border-radius: 4px; cursor: pointer;">✏️ Edit</button>';
    $html .= '<button type="button" class="btn" onclick="duplicateDoc(\'' . htmlspecialchars($docId) . '\'); return false;" style="background: var(--accent-success); color: var(--text-on-accent); padding: 6px 12px; font-size: 11px; border: none; border-radius: 4px; cursor: pointer;">📋 Copy</button>';
    $html .= '<button type="button" class="btn" onclick="deleteDoc(\'' . htmlspecialchars($docId) . '\'); return false;" style="background: var(--accent-danger); color: var(--text-on-accent); padding: 6px 12px; font-size: 11px; border: none; border-radius: 4px; cursor: pointer;">🗑️ Delete</button>';
    $html .= '</td>';
    $html .= '</tr>';
    
    return $html;
}
?>
