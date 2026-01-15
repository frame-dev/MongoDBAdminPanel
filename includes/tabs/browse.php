    <div id="browse" class="tab-content">
        <!-- Success/Error Messages -->
        <?php if (!empty($message)): ?>
            <div style="background: <?php echo $messageType === 'success' ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo $messageType === 'success' ? '#155724' : '#721c24'; ?>; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid <?php echo $messageType === 'success' ? '#28a745' : '#dc3545'; ?>; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 20px;"><?php echo $messageType === 'success' ? '✅' : '❌'; ?></span>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <div
            style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 8px 24px rgba(102,126,234,0.3);">
            <h2 style="color: white; margin: 0; font-size: 28px; display: flex; align-items: center; gap: 12px;">
                <span style="font-size: 32px;">📋</span> Browse Documents
                <span
                    style="background: rgba(255,255,255,0.2); padding: 6px 14px; border-radius: 20px; font-size: 14px; font-weight: normal;">
                    <?php echo number_format($documentCount); ?> documents
                </span>
            </h2>
            <p style="color: rgba(255,255,255,0.9); margin: 10px 0 0 0; font-size: 14px;">
                View, search, filter, and manage your collection documents
            </p>
        </div>

        <!-- Advanced Search & Filters -->
        <div
            style="background: white; padding: 25px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0; color: #333; display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 20px;">🔍</span> Search & Filters
                </h3>
                <button type="button" class="btn"
                    style="background: #f8f9fa; color: #495057; padding: 8px 16px; font-size: 13px;"
                    onclick="resetFilters()">
                    🔄 Reset All
                </button>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <label
                        style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 13px; color: #495057;">
                        🔎 Text Search
                    </label>
                    <input type="text" id="searchInput" value="<?php echo htmlspecialchars($searchQuery); ?>"
                        placeholder="Search across all fields..."
                        style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; transition: border-color 0.3s;"
                        onfocus="this.style.borderColor='#667eea'" onblur="this.style.borderColor='#e0e0e0'">
                </div>
                <div>
                    <label
                        style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 13px; color: #495057;">
                        📊 Sort Field
                    </label>
                    <select id="sortField"
                        style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; background: white;">
                        <?php
                        $fieldIcons = [
                            '_id' => '📌',
                            'id' => '📌',
                            'created_at' => '📅',
                            'createdAt' => '📅',
                            'created' => '📅',
                            'date' => '📅',
                            'updated_at' => '🔄',
                            'updatedAt' => '🔄',
                            'updated' => '🔄',
                            'modified' => '🔄',
                            'name' => '📝',
                            'title' => '📝',
                            'email' => '📧',
                            'status' => '🏷️',
                            'type' => '📂',
                            'category' => '📂',
                            'price' => '💰',
                            'amount' => '💰',
                            'count' => '🔢',
                            'quantity' => '🔢',
                            'age' => '🎂',
                            'phone' => '📞',
                            'address' => '🏠',
                            'username' => '👤',
                            'user' => '👤'
                        ];

                        foreach ($detectedFields as $field):
                            $icon = '📊';
                            foreach ($fieldIcons as $pattern => $fieldIcon) {
                                if (stripos($field, $pattern) !== false || $field === $pattern) {
                                    $icon = $fieldIcon;
                                    break;
                                }
                            }
                            $selected = ($sortField === $field) ? 'selected' : '';
                            $displayName = str_replace('_', ' ', ucfirst($field));
                            ?>
                            <option value="<?php echo htmlspecialchars($field); ?>" <?php echo $selected; ?>>
                                <?php echo $icon . ' ' . htmlspecialchars($displayName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <div>
                    <label
                        style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 13px; color: #495057;">
                        ⬆️⬇️ Order
                    </label>
                    <select id="sortOrder"
                        style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; background: white;">
                        <option value="-1" <?php echo $sortOrder === '-1' ? 'selected' : ''; ?>>⬇️ Descending</option>
                        <option value="1" <?php echo $sortOrder === '1' ? 'selected' : ''; ?>>⬆️ Ascending</option>
                    </select>
                </div>
            </div>

            <!-- JSON Filter -->
            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 13px; color: #495057;">
                    🎯 Advanced JSON Filter
                </label>
                <div style="position: relative;">
                    <textarea id="jsonFilter" placeholder='{"status": "active"} or {"age": {"$gte": 18}}'
                        style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-family: 'Courier New', monospace; font-size: 13px; min-height: 60px; resize: vertical;"
                        onfocus="this.style.borderColor='#667eea'" onblur="this.style.borderColor='#e0e0e0'"></textarea>
                    <small style="color: #6c757d; font-size: 12px;">MongoDB query syntax supported</small>
                </div>
            </div>

            <!-- Quick Filters -->
            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 13px; color: #495057;">
                    ⚡ Quick Filters
                </label>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <?php
                    // Dynamic quick filters based on detected fields
                    $quickFilters = [];

                    // Date-based filters if date fields exist
                    $dateFields = array_filter($detectedFields, function ($field) {
                        return stripos($field, 'date') !== false ||
                            stripos($field, 'created') !== false ||
                            stripos($field, 'time') !== false ||
                            in_array($field, ['created_at', 'updated_at', 'timestamp']);
                    });

                    if (!empty($dateFields)) {
                        $dateField = reset($dateFields);
                        $quickFilters[] = [
                            'label' => '📅 Today',
                            'style' => 'background: #e3f2fd; color: #1976d2; border: 2px solid #1976d2;',
                            'action' => "applyQuickFilter('today', '" . htmlspecialchars($dateField) . "')"
                        ];
                        $quickFilters[] = [
                            'label' => '📆 Last 7 Days',
                            'style' => 'background: #f3e5f5; color: #7b1fa2; border: 2px solid #7b1fa2;',
                            'action' => "applyQuickFilter('week', '" . htmlspecialchars($dateField) . "')"
                        ];
                        $quickFilters[] = [
                            'label' => '📊 Last 30 Days',
                            'style' => 'background: #e8f5e9; color: #388e3c; border: 2px solid #388e3c;',
                            'action' => "applyQuickFilter('month', '" . htmlspecialchars($dateField) . "')"
                        ];
                    }

                    // Status/type filters if they exist
                    if (in_array('status', $detectedFields)) {
                        $quickFilters[] = [
                            'label' => '✅ Active',
                            'style' => 'background: #e8f5e9; color: #2e7d32; border: 2px solid #4caf50;',
                            'action' => "applyQuickFilter('status_value', 'status', 'active')"
                        ];
                        $quickFilters[] = [
                            'label' => '⏸️ Inactive',
                            'style' => 'background: #fbe9e7; color: #d84315; border: 2px solid #ff5722;',
                            'action' => "applyQuickFilter('status_value', 'status', 'inactive')"
                        ];
                    }

                    // Email field filters
                    if (in_array('email', $detectedFields)) {
                        $quickFilters[] = [
                            'label' => '📧 Has Email',
                            'style' => 'background: #fff3e0; color: #f57c00; border: 2px solid #ff9800;',
                            'action' => "applyQuickFilter('has_field', 'email')"
                        ];
                        $quickFilters[] = [
                            'label' => '❌ No Email',
                            'style' => 'background: #ffebee; color: #c62828; border: 2px solid #f44336;',
                            'action' => "applyQuickFilter('empty_field', 'email')"
                        ];
                    }

                    // Name field filters
                    if (in_array('name', $detectedFields) || in_array('username', $detectedFields)) {
                        $nameField = in_array('name', $detectedFields) ? 'name' : 'username';
                        $quickFilters[] = [
                            'label' => '✓ Has Name',
                            'style' => 'background: #e1f5fe; color: #01579b; border: 2px solid #03a9f4;',
                            'action' => "applyQuickFilter('has_field', '" . $nameField . "')"
                        ];
                    }

                    // Add "All Documents" filter
                    $quickFilters[] = [
                        'label' => '🌐 All Documents',
                        'style' => 'background: #f5f5f5; color: #616161; border: 2px solid #9e9e9e;',
                        'action' => "applyQuickFilter('all')"
                    ];

                    // Render filters
                    foreach ($quickFilters as $filter):
                        ?>
                        <button type="button" class="btn"
                            style="<?php echo $filter['style']; ?> padding: 8px 16px; font-size: 13px;"
                            onclick="<?php echo $filter['action']; ?>">
                            <?php echo $filter['label']; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="button" class="btn"
                    style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 24px; font-size: 14px; font-weight: 600; box-shadow: 0 4px 12px rgba(102,126,234,0.4);"
                    onclick="performSearch()">
                    🔍 Apply Filters
                </button>
                <button type="button" class="btn"
                    style="background: #28a745; color: white; padding: 12px 24px; font-size: 14px;"
                    onclick="window.location.reload()">
                    🔄 Refresh Data
                </button>
                <button type="button" class="btn" id="autoRefreshBtn"
                    style="background: #6c757d; color: white; padding: 12px 24px; font-size: 14px;"
                    onclick="toggleAutoRefresh()">
                    ⏸️ Auto-Refresh
                </button>
                <button type="button" class="btn"
                    style="background: #17a2b8; color: white; padding: 12px 24px; font-size: 14px;"
                    onclick="toggleBulkSelection()">
                    ☑️ Bulk Select
                </button>
                <button type="button" class="btn"
                    style="background: #ffc107; color: #333; padding: 12px 24px; font-size: 14px;"
                    onclick="exportVisible()">
                    💾 Export Visible
                </button>
            </div>
        </div>

        <div id="autoRefreshStatus"
            style="display: none; background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%); color: #0c5460; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; border-left: 4px solid #17a2b8; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 18px;">🔄</span>
            <span>Auto-refresh enabled - Updates every <strong><span id="refreshInterval">30</span>
                    seconds</strong></span>
        </div>

        <!-- Bulk Actions Bar -->
        <div id="bulkActionsBar"
            style="display: none; background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #ffc107;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <span style="font-weight: 600; color: #856404;">
                        <span id="selectedCount">0</span> documents selected
                    </span>
                    <button type="button" class="btn"
                        style="background: #dc3545; color: white; padding: 8px 16px; font-size: 13px;"
                        onclick="bulkDelete()">
                        🗑️ Delete Selected
                    </button>
                    <button type="button" class="btn"
                        style="background: #007bff; color: white; padding: 8px 16px; font-size: 13px;"
                        onclick="bulkExport()">
                        💾 Export Selected
                    </button>
                    <button type="button" class="btn"
                        style="background: #28a745; color: white; padding: 8px 16px; font-size: 13px;"
                        onclick="bulkUpdate()">
                        ✏️ Update Selected
                    </button>
                </div>
                <button type="button" class="btn"
                    style="background: #6c757d; color: white; padding: 8px 16px; font-size: 13px;"
                    onclick="clearSelection()">
                    ✖️ Clear Selection
                </button>
            </div>
        </div>

        <!-- Documents Grid/Table View Toggle -->
        <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <div>
                    <h3 style="margin: 0; color: #333;">
                        Showing <?php echo count($documentsList); ?> of <?php echo number_format($documentCount); ?>
                        documents
                    </h3>
                    <p style="margin: 5px 0 0 0; font-size: 13px; color: #6c757d;">
                        Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                    </p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn" id="viewToggleBtn"
                        style="background: #f8f9fa; color: #495057; padding: 8px 16px; border: 2px solid #dee2e6;"
                        onclick="toggleView()">
                        <span id="viewIcon">📊</span> <span id="viewText">Grid View</span>
                    </button>
                    <select id="perPageSelect"
                        style="padding: 8px 12px; border: 2px solid #dee2e6; border-radius: 6px; background: white;"
                        onchange="changePerPage(this.value)">
                        <option value="10" <?php echo $perPage == 10 ? 'selected' : ''; ?>>10 per page</option>
                        <option value="25" <?php echo $perPage == 25 ? 'selected' : ''; ?>>25 per page</option>
                        <option value="50" <?php echo $perPage == 50 ? 'selected' : ''; ?>>50 per page</option>
                        <option value="100" <?php echo $perPage == 100 ? 'selected' : ''; ?>>100 per page</option>
                    </select>
                </div>
            </div>

            <!-- Table View (Default) -->
            <div id="tableView">
                <table class="data-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);">
                            <th
                                style="padding: 15px; text-align: left; font-weight: 600; color: #333; border-bottom: 2px solid #667eea; width: 40px;">
                                <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)"
                                    style="cursor: pointer; width: 18px; height: 18px; display: none;">
                            </th>
                            <th
                                style="padding: 15px; text-align: left; font-weight: 600; color: #333; border-bottom: 2px solid #667eea;">
                                📌 Document ID
                            </th>
                            <th
                                style="padding: 15px; text-align: left; font-weight: 600; color: #333; border-bottom: 2px solid #667eea;">
                                📄 Document Data
                            </th>
                            <th
                                style="padding: 15px; text-align: center; font-weight: 600; color: #333; border-bottom: 2px solid #667eea; width: 280px;">
                                ⚙️ Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documentsList as $index => $doc): ?>
                            <?php
                            $docArray = json_decode(json_encode($doc), true);
                            $docId = (string) ($doc['_id'] ?? '');
                            $docJson = json_encode($docArray);
                            ?>
                            <tr data-json="<?php echo htmlspecialchars($docJson); ?>"
                                data-doc-id="<?php echo htmlspecialchars((string)$docId); ?>"
                                style="border-bottom: 1px solid #e9ecef; transition: background-color 0.2s;"
                                onmouseover="this.style.backgroundColor='#f8f9fa'"
                                onmouseout="this.style.backgroundColor='white'">
                                <td style="padding: 12px;">
                                    <input type="checkbox" class="doc-checkbox"
                                        value="<?php echo htmlspecialchars($docId); ?>"
                                        style="cursor: pointer; width: 18px; height: 18px; display: none;" onchange="updateBulkBar()">
                                </td>
                                <td style="padding: 12px;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <code
                                            style="background: #e9ecef; padding: 6px 10px; border-radius: 6px; font-size: 12px; color: #495057; font-weight: 600;">
                                                                                <?php echo htmlspecialchars(substr((string) $docId, -8)); ?>
                                                                            </code>
                                        <button type="button" class="btn"
                                            style="background: none; border: none; color: #6c757d; padding: 4px; cursor: pointer; font-size: 16px;"
                                            onclick="copyToClipboard('<?php echo htmlspecialchars($docId); ?>')"
                                            title="Copy full ID">
                                            📋
                                        </button>
                                    </div>
                                </td>
                                <td style="padding: 12px;">
                                    <div style="max-width: 500px;">
                                        <?php
                                        // Show key fields in a nice format
                                        $keyFields = ['name', 'title', 'email', 'status', 'type', 'category'];
                                        $displayFields = [];
                                        foreach ($keyFields as $field) {
                                            if (isset($docArray[$field]) && $docArray[$field] !== '') {
                                                $displayFields[] = '<span style="background: #e3f2fd; color: #1976d2; padding: 4px 10px; border-radius: 12px; font-size: 12px; display: inline-block; margin: 2px;"><strong>' . htmlspecialchars($field) . ':</strong> ' . htmlspecialchars(substr((string) $docArray[$field], 0, 30)) . '</span>';
                                            }
                                        }
                                        if (!empty($displayFields)) {
                                            echo implode(' ', array_slice($displayFields, 0, 3));
                                        } else {
                                            $preview = json_encode($docArray, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                                            echo '<span style="color: #6c757d; font-size: 12px; font-family: monospace;">' . htmlspecialchars(substr($preview, 0, 80)) . '...</span>';
                                        }
                                        ?>
                                        <details style="margin-top: 8px;">
                                            <summary
                                                style="cursor: pointer; color: #667eea; font-size: 12px; font-weight: 600;">
                                                📖 Show Full Document</summary>
                                            <pre
                                                style="background: #f8f9fa; padding: 12px; border-radius: 6px; margin-top: 8px; font-size: 11px; overflow-x: auto; max-height: 200px; overflow-y: auto; border: 1px solid #dee2e6;"><code><?php echo htmlspecialchars(json_encode($docArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?></code></pre>
                                        </details>
                                    </div>
                                </td>
                                <td style="padding: 12px;">
                                    <div style="display: flex; gap: 6px; justify-content: center; flex-wrap: wrap;">
                                        <button type="button" class="btn"
                                            style="background: #6c757d; color: white; padding: 6px 12px; font-size: 12px; border-radius: 6px;"
                                            onclick="viewDocument('<?php echo htmlspecialchars((string)$docId); ?>', event)" title="View Document">
                                            👁️
                                        </button>
                                        <button type="button" class="btn"
                                            style="background: #007bff; color: white; padding: 6px 12px; font-size: 12px; border-radius: 6px;"
                                            onclick="editDocument('<?php echo htmlspecialchars((string)$docId); ?>', event)" title="Edit Document">
                                            ✏️
                                        </button>
                                        <button type="button" class="btn"
                                            style="background: #17a2b8; color: white; padding: 6px 12px; font-size: 12px; border-radius: 6px;"
                                            onclick="duplicateDoc('<?php echo htmlspecialchars((string)$docId); ?>')" title="Duplicate">
                                            📋
                                        </button>
                                        <button type="button" class="btn"
                                            style="background: #28a745; color: white; padding: 6px 12px; font-size: 12px; border-radius: 6px;"
                                            onclick="exportSingle('<?php echo htmlspecialchars((string)$docId); ?>')" title="Export JSON">
                                            💾
                                        </button>
                                        <button type="button" class="btn"
                                            style="background: #dc3545; color: white; padding: 6px 12px; font-size: 12px; border-radius: 6px;"
                                            onclick="deleteDoc('<?php echo htmlspecialchars((string)$docId); ?>')" title="Delete">
                                            🗑️
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Grid View -->
            <div id="gridView" style="display: none;">
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">
                    <?php foreach ($documentsList as $doc): ?>
                        <?php
                        $docArray = json_decode(json_encode($doc), true);
                        $docId = (string) ($doc['_id'] ?? '');
                        $docJson = json_encode($docArray);
                        ?>
                        <div class="document-card" data-doc-id="<?php echo htmlspecialchars((string)$docId); ?>"
                            data-json="<?php echo htmlspecialchars($docJson); ?>"
                            style="background: white; border: 2px solid #e9ecef; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: all 0.3s; position: relative;"
                            onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 8px 24px rgba(0,0,0,0.15)'"
                            onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.08)'">
                            <div style="position: absolute; top: 15px; right: 15px;">
                                <input type="checkbox" class="doc-checkbox" value="<?php echo htmlspecialchars($docId); ?>"
                                    style="cursor: pointer; width: 18px; height: 18px; display: none;" onchange="updateBulkBar()">
                            </div>

                            <div style="margin-bottom: 15px;">
                                <div
                                    style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 8px 12px; border-radius: 8px; display: inline-block; font-size: 11px; font-weight: 600; margin-bottom: 10px;">
                                    📄 DOCUMENT
                                </div>
                                <div style="font-size: 12px; color: #6c757d; font-family: monospace;">
                                    ID: <?php echo htmlspecialchars(substr((string) $docId, -12)); ?>
                                </div>
                            </div>

                            <div style="margin-bottom: 15px;">
                                <?php
                                $keyFields = ['name', 'title', 'email', 'status', 'type'];
                                foreach ($keyFields as $field) {
                                    if (isset($docArray[$field]) && $docArray[$field] !== '') {
                                        $icon = ['name' => '👤', 'title' => '📝', 'email' => '📧', 'status' => '🏷️', 'type' => '📌'][$field] ?? '•';
                                        echo '<div style="margin-bottom: 8px;">';
                                        echo '<span style="color: #6c757d; font-size: 12px; font-weight: 600;">' . $icon . ' ' . ucfirst($field) . ':</span> ';
                                        echo '<span style="color: #333; font-size: 13px;">' . htmlspecialchars(substr((string) $docArray[$field], 0, 40)) . '</span>';
                                        echo '</div>';
                                        break;
                                    }
                                }
                                ?>
                            </div>

                            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                <button type="button" class="btn"
                                    style="background: #6c757d; color: white; padding: 6px 12px; font-size: 11px; flex: 1;"
                                    onclick="viewDocument('<?php echo htmlspecialchars((string)$docId); ?>', event)">
                                    👁️ View
                                </button>
                                <button type="button" class="btn"
                                    style="background: #007bff; color: white; padding: 6px 12px; font-size: 11px; flex: 1;"
                                    onclick="editDocument('<?php echo htmlspecialchars((string)$docId); ?>', event)">
                                    ✏️ Edit
                                </button>
                                <button type="button" class="btn"
                                    style="background: #17a2b8; color: white; padding: 6px 12px; font-size: 11px;"
                                    onclick="duplicateDoc('<?php echo htmlspecialchars((string)$docId); ?>')">
                                    📋
                                </button>
                                <button type="button" class="btn"
                                    style="background: #28a745; color: white; padding: 6px 12px; font-size: 11px;"
                                    onclick="exportSingle('<?php echo htmlspecialchars((string)$docId); ?>')">
                                    💾
                                </button>
                                <button type="button" class="btn"
                                    style="background: #dc3545; color: white; padding: 6px 12px; font-size: 11px;"
                                    onclick="deleteDoc('<?php echo htmlspecialchars((string)$docId); ?>')">
                                    🗑️
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

        <!-- Enhanced Pagination -->
        <?php if ($totalPages > 1): ?>
            <div
                style="background: white; padding: 20px; border-radius: 12px; margin-top: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <div
                    style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <button type="button" class="btn"
                            style="background: #667eea; color: white; padding: 10px 16px; border-radius: 8px; <?php echo $page <= 1 ? 'opacity: 0.5; cursor: not-allowed;' : ''; ?>"
                            onclick="jumpToPage(1)" <?php echo $page <= 1 ? 'disabled' : ''; ?>>
                            ⏮️ First
                        </button>
                        <button type="button" class="btn"
                            style="background: #667eea; color: white; padding: 10px 16px; border-radius: 8px; <?php echo $page <= 1 ? 'opacity: 0.5; cursor: not-allowed;' : ''; ?>"
                            onclick="jumpToPage(<?php echo $page - 1; ?>)" <?php echo $page <= 1 ? 'disabled' : ''; ?>>
                            ◀️ Prev
                        </button>
                    </div>

                    <div style="display: flex; gap: 6px; flex-wrap: wrap; justify-content: center;">
                        <?php
                        $startPage = max(1, $page - 4);
                        $endPage = min($totalPages, $page + 4);

                        if ($startPage > 1): ?>
                            <button type="button" class="btn"
                                style="padding: 10px 14px; background: #f8f9fa; color: #333; border-radius: 8px;"
                                onclick="jumpToPage(1)">1</button>
                            <?php if ($startPage > 2): ?>
                                <span style="padding: 10px; color: #6c757d;">...</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <button type="button" class="btn"
                                style="padding: 10px 14px; background: <?php echo $i === $page ? 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' : '#f8f9fa'; ?>; color: <?php echo $i === $page ? 'white' : '#333'; ?>; border-radius: 8px; font-weight: <?php echo $i === $page ? '700' : '400'; ?>; min-width: 44px; <?php echo $i === $page ? 'box-shadow: 0 4px 12px rgba(102,126,234,0.4);' : ''; ?>"
                                onclick="jumpToPage(<?php echo $i; ?>)">
                                <?php echo $i; ?>
                            </button>
                        <?php endfor; ?>

                        <?php if ($endPage < $totalPages): ?>
                            <?php if ($endPage < $totalPages - 1): ?>
                                <span style="padding: 10px; color: #6c757d;">...</span>
                            <?php endif; ?>
                            <button type="button" class="btn"
                                style="padding: 10px 14px; background: #f8f9fa; color: #333; border-radius: 8px;"
                                onclick="jumpToPage(<?php echo $totalPages; ?>)">
                                <?php echo $totalPages; ?>
                            </button>
                        <?php endif; ?>
                    </div>

                    <div style="display: flex; gap: 8px; align-items: center;">
                        <button type="button" class="btn"
                            style="background: #667eea; color: white; padding: 10px 16px; border-radius: 8px; <?php echo $page >= $totalPages ? 'opacity: 0.5; cursor: not-allowed;' : ''; ?>"
                            onclick="jumpToPage(<?php echo $page + 1; ?>)" <?php echo $page >= $totalPages ? 'disabled' : ''; ?>>
                            Next ▶️
                        </button>
                        <button type="button" class="btn"
                            style="background: #667eea; color: white; padding: 10px 16px; border-radius: 8px; <?php echo $page >= $totalPages ? 'opacity: 0.5; cursor: not-allowed;' : ''; ?>"
                            onclick="jumpToPage(<?php echo $totalPages; ?>)" <?php echo $page >= $totalPages ? 'disabled' : ''; ?>>
                            Last ⏭️
                        </button>
                    </div>
                </div>

                <div style="text-align: center; margin-top: 15px; padding-top: 15px; border-top: 1px solid #e9ecef;">
                    <span style="color: #6c757d; font-size: 13px;">Jump to page:</span>
                    <input type="number" id="jumpPageInput" min="1" max="<?php echo $totalPages; ?>"
                        placeholder="<?php echo $page; ?>"
                        style="width: 80px; padding: 6px; border: 2px solid #dee2e6; border-radius: 6px; margin: 0 8px; text-align: center;">
                    <button type="button" class="btn" style="background: #28a745; color: white; padding: 6px 16px;"
                        onclick="jumpToPage(document.getElementById('jumpPageInput').value)">
                        Go
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div><!-- End of container -->

    <!-- Query History Section -->
    <div id="query_history_section" style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin: 20px 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="color: #333; margin: 0; font-size: 18px;">📜 Query History (Last 10)</h3>
            <a href="?action=clear_query_history" class="btn" style="background: #dc3545; color: white; padding: 8px 16px; text-decoration: none; font-size: 12px;" 
                onclick="return confirm('Clear all query history?');">
                🗑️ Clear History
            </a>
        </div>

        <?php
        $history = getQueryHistory(10);
        if (!empty($history)):
        ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                        <th style="padding: 12px; text-align: left; color: #333; font-weight: 600;">Timestamp</th>
                        <th style="padding: 12px; text-align: left; color: #333; font-weight: 600;">Type</th>
                        <th style="padding: 12px; text-align: left; color: #333; font-weight: 600;">Query</th>
                        <th style="padding: 12px; text-align: center; color: #333; font-weight: 600;">Results</th>
                        <th style="padding: 12px; text-align: center; color: #333; font-weight: 600;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $entry): ?>
                    <tr style="border-bottom: 1px solid #dee2e6; transition: background 0.2s;">
                        <td style="padding: 12px; color: #666;"><?php echo htmlspecialchars($entry['timestamp']); ?></td>
                        <td style="padding: 12px; color: #666;">
                            <span style="background: <?php echo $entry['type'] === 'visual' ? '#17a2b8' : '#6f42c1'; ?>; color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px;">
                                <?php echo ucfirst($entry['type']); ?>
                            </span>
                        </td>
                        <td style="padding: 12px; color: #666; max-width: 400px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <code style="background: #f8f9fa; padding: 4px 8px; border-radius: 4px; font-size: 11px;">
                                <?php 
                                if ($entry['type'] === 'visual' && isset($entry['query']['field'])) {
                                    echo htmlspecialchars($entry['query']['field'] . ' ' . $entry['query']['op'] . ' ' . substr($entry['query']['value'], 0, 20));
                                } else {
                                    $customQuery = isset($entry['query']['custom']) ? substr($entry['query']['custom'], 0, 50) : '';
                                    echo htmlspecialchars($customQuery);
                                }
                                ?>
                            </code>
                        </td>
                        <td style="padding: 12px; text-align: center; color: #28a745; font-weight: 600;">
                            <?php echo htmlspecialchars((string)$entry['results_count']); ?>
                        </td>
                        <td style="padding: 12px; text-align: center;">
                            <span style="background: #28a745; color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px;">
                                ✓ <?php echo ucfirst($entry['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p style="color: #999; text-align: center; padding: 20px;">No queries executed yet. Execute your first query to see it in history!</p>
        <?php endif; ?>
    </div>

    <!-- Add Document Tab -->
