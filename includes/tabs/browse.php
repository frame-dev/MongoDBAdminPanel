    <div id="browse" class="tab-content">
        <!-- Success/Error Messages -->
        <?php
        $showMessage = true;
        if ($messageType === 'success' && !getSetting('show_success_messages', true)) {
            $showMessage = false;
        }
        if ($messageType === 'error' && !getSetting('show_error_messages', true)) {
            $showMessage = false;
        }
        if ($messageType === 'warning' && !getSetting('show_warning_messages', true)) {
            $showMessage = false;
        }
        ?>
        <?php if (!empty($message) && $showMessage): ?>
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
                        onfocus="this.style.borderColor='#667eea'" onblur="this.style.borderColor='#e0e0e0'"><?php echo htmlspecialchars((string) ($_GET['filter'] ?? '')); ?></textarea>
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
                    $quickFilters = generateQuickFilters($detectedFields);
                    echo renderQuickFilters($quickFilters);
                    ?>
                </div>
            </div>
            
            <!-- Saved Filters -->
            <div style="margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <label style="font-weight: 600; font-size: 13px; color: #495057;">⭐ Saved Filters</label>
                    <button type="button" class="btn"
                        style="background: #f8f9fa; color: #495057; padding: 6px 12px; font-size: 12px;"
                        onclick="saveCurrentFilter()">
                        ➕ Save Current
                    </button>
                </div>
                <?php
                $savedFilters = $_SESSION['saved_filters'][$collectionName] ?? [];
                ?>
                <?php if (!empty($savedFilters)): ?>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <?php foreach ($savedFilters as $savedFilter): ?>
                            <?php
                            $params = $savedFilter['params'] ?? [];
                            $queryParams = [
                                'collection' => $collectionName,
                                'search' => $params['search'] ?? '',
                                'sort' => $params['sort'] ?? '',
                                'order' => $params['order'] ?? '',
                                'filter' => $params['filter'] ?? ''
                            ];
                            $queryParams = array_filter($queryParams, fn($value) => $value !== '' && $value !== null);
                            $filterUrl = '?' . http_build_query($queryParams);
                            ?>
                            <div style="background: #f1f3f5; border-radius: 20px; padding: 6px 10px; display: flex; align-items: center; gap: 8px;">
                                <a href="<?php echo htmlspecialchars($filterUrl); ?>"
                                    style="text-decoration: none; color: #343a40; font-size: 12px; font-weight: 600;">
                                    <?php echo htmlspecialchars($savedFilter['name']); ?>
                                </a>
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="action" value="delete_filter">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                                    <input type="hidden" name="filter_id" value="<?php echo htmlspecialchars($savedFilter['id']); ?>">
                                    <button type="submit" class="btn"
                                        style="background: none; border: none; color: #dc3545; padding: 0 4px; font-size: 12px;"
                                        title="Remove filter">✖</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="color: #6c757d; font-size: 12px; background: #f8f9fa; border-radius: 8px; padding: 10px;">
                        No saved filters yet. Save the current search to reuse it later.
                    </div>
                <?php endif; ?>
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
                        <option value="200" <?php echo $perPage == 200 ? 'selected' : ''; ?>>200 per page</option>
                    </select>
                </div>
            </div>

            <!-- Table View (Default) -->
            <div id="tableView">
                <?php
                $rowHoverEnabled = getSetting('row_hover', true);
                $zebraStripes = getSetting('zebra_stripes', true);
                $compactMode = getSetting('compact_mode', false);
                $fixedHeader = getSetting('fixed_header', false);
                $headerPadding = $compactMode ? '10px' : '15px';
                $cellPadding = $compactMode ? '8px' : '12px';
                ?>
                <table class="data-table" style="width: 100%; border-collapse: collapse;">
                    <thead <?php echo $fixedHeader ? 'style="position: sticky; top: 0; z-index: 2;"' : ''; ?>>
                        <tr style="background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);">
                            <th
                                style="padding: <?php echo $headerPadding; ?>; text-align: left; font-weight: 600; color: #333; border-bottom: 2px solid #667eea; width: 40px;">
                                <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)"
                                    style="cursor: pointer; width: 18px; height: 18px; display: none;">
                            </th>
                            <th
                                style="padding: <?php echo $headerPadding; ?>; text-align: left; font-weight: 600; color: #333; border-bottom: 2px solid #667eea;">
                                📌 Document ID
                            </th>
                            <th
                                style="padding: <?php echo $headerPadding; ?>; text-align: left; font-weight: 600; color: #333; border-bottom: 2px solid #667eea;">
                                📄 Document Data
                            </th>
                            <th
                                style="padding: <?php echo $headerPadding; ?>; text-align: center; font-weight: 600; color: #333; border-bottom: 2px solid #667eea; width: 280px;">
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
                            $docIdShort = formatObjectIdDisplay($docId, 8);
                            $docIdLong = formatObjectIdDisplay($docId, 12);
                            $useCollapsibleJson = getSetting('collapsible_json', false);
                            $fullJson = formatJsonForDisplay($docArray);
                            $rowBackground = ($zebraStripes && ($index % 2 === 1)) ? '#f8f9fa' : 'white';
                            ?>
                            <tr data-json="<?php echo htmlspecialchars($docJson); ?>"
                                data-doc-id="<?php echo htmlspecialchars((string)$docId); ?>"
                                style="border-bottom: 1px solid #e9ecef; transition: background-color 0.2s; background-color: <?php echo $rowBackground; ?>;"
                                <?php if ($rowHoverEnabled): ?>
                                    onmouseover="this.style.backgroundColor='#f8f9fa'"
                                    onmouseout="this.style.backgroundColor='<?php echo $rowBackground; ?>'"
                                <?php endif; ?>>
                                <td style="padding: <?php echo $cellPadding; ?>;">
                                    <input type="checkbox" class="doc-checkbox"
                                        value="<?php echo htmlspecialchars($docId); ?>"
                                        style="cursor: pointer; width: 18px; height: 18px; display: none;" onchange="updateBulkBar()">
                                </td>
                                <td style="padding: <?php echo $cellPadding; ?>;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <code
                                            style="background: #e9ecef; padding: 6px 10px; border-radius: 6px; font-size: 12px; color: #495057; font-weight: 600;">
                                                                                <?php echo htmlspecialchars($docIdShort); ?>
                                                                            </code>
                                        <button type="button" class="btn"
                                            style="background: none; border: none; color: #6c757d; padding: 4px; cursor: pointer; font-size: 16px;"
                                            onclick="copyToClipboard('<?php echo htmlspecialchars($docId); ?>')"
                                            title="Copy full ID">
                                            📋
                                        </button>
                                    </div>
                                </td>
                                <td style="padding: <?php echo $cellPadding; ?>;">
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
                                        <?php if ($useCollapsibleJson): ?>
                                            <details style="margin-top: 8px;">
                                                <summary
                                                    style="cursor: pointer; color: #667eea; font-size: 12px; font-weight: 600;">
                                                    📖 Show Full Document</summary>
                                                <pre
                                                    style="background: #f8f9fa; padding: 12px; border-radius: 6px; margin-top: 8px; font-size: 11px; overflow-x: auto; max-height: 200px; overflow-y: auto; border: 1px solid #dee2e6;"><code><?php echo htmlspecialchars($fullJson); ?></code></pre>
                                            </details>
                                        <?php else: ?>
                                            <pre
                                                style="background: #f8f9fa; padding: 12px; border-radius: 6px; margin-top: 8px; font-size: 11px; overflow-x: auto; max-height: 200px; overflow-y: auto; border: 1px solid #dee2e6;"><code><?php echo htmlspecialchars($fullJson); ?></code></pre>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="padding: <?php echo $cellPadding; ?>;">
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
                        $docIdLong = formatObjectIdDisplay($docId, 12);
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
                                    ID: <?php echo htmlspecialchars($docIdLong); ?>
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
                    <?php echo generatePaginationControls($page, $totalPages); ?>
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
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 10px; flex-wrap: wrap;">
            <h3 style="color: #333; margin: 0; font-size: 18px;">📜 Query History (Last 10)</h3>
            <div style="display: flex; gap: 8px; align-items: center;">
                <form method="POST" style="margin: 0;">
                    <input type="hidden" name="action" value="export_query_history">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <button type="submit" class="btn" style="background: #17a2b8; color: white; padding: 8px 16px; font-size: 12px;">
                        ⬇️ Export History
                    </button>
                </form>
                <a href="?action=clear_query_history" class="btn" style="background: #dc3545; color: white; padding: 8px 16px; text-decoration: none; font-size: 12px;" 
                    onclick="return confirm('Clear all query history?');">
                    🗑️ Clear History
                </a>
            </div>
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
                        <td style="padding: 12px; color: #666;"><?php echo htmlspecialchars(formatDisplayDate($entry['timestamp'])); ?></td>
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
