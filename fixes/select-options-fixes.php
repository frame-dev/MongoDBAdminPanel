<?php
/**
 * Select Options HTML Generators
 * Generates HTML for various select dropdowns
 */

function generateOperatorOptions() {
    return '
        <option value="equals">= Equals</option>
        <option value="contains">📝 Contains</option>
        <option value="starts">📌 Starts With</option>
        <option value="ends">📌 Ends With</option>
        <option value="gt">&gt; Greater Than</option>
        <option value="lt">&lt; Less Than</option>
        <option value="gte">&gt;= Greater or Equal</option>
        <option value="lte">&lt;= Less or Equal</option>
    ';
}

function generateValueTypeOptions() {
    return '
        <option value="string">📝 Text</option>
        <option value="number">🔢 Number</option>
        <option value="bool">✓ Boolean</option>
        <option value="null">∅ Null</option>
        <option value="objectid">🔑 ObjectID</option>
        <option value="date">📅 Date</option>
    ';
}

function generateSortOrderOptions($currentOrder = 'desc') {
    $descSelected = $currentOrder === 'desc' ? 'selected' : '';
    $ascSelected = $currentOrder === 'asc' ? 'selected' : '';
    
    return "
        <option value=\"desc\" $descSelected>📉 Descending</option>
        <option value=\"asc\" $ascSelected>📈 Ascending</option>
    ";
}

function generateFieldOptions($fields, $selectedField = '_id') {
    $html = '<option value="_id"' . ($selectedField === '_id' ? ' selected' : '') . '>🔑 Document ID</option>';
    
    foreach ($fields as $field) {
        $selected = ($field === $selectedField) ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars($field) . '"' . $selected . '>' . htmlspecialchars($field) . '</option>';
    }
    
    return $html;
}

function generateCollectionOptions($collections, $selectedCollection = '') {
    $html = '<option value="">Select collection...</option>';
    
    foreach ($collections as $collName) {
        $selected = ($collName === $selectedCollection) ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars($collName) . '"' . $selected . '>' . htmlspecialchars($collName) . '</option>';
    }
    
    return $html;
}
?>
