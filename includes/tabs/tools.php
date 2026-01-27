    <div id="tools" class="tab-content">
        <h2>🛠️ Tools & Utilities</h2>

        <!-- Collection Management Section -->
        <div
            style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 12px; margin-bottom: 25px; color: white;">
            <h3 style="color: white; margin-bottom: 20px;">📏 Collection Management</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px;">
                <!-- Create Collection -->
                <div style="background: rgba(255,255,255,0.95); padding: 18px; border-radius: 8px; color: #333;">
                    <h4 style="color: #667eea; margin-bottom: 12px;">➕ Create Collection</h4>
                    <form method="POST">
                        <input type="hidden" name="action" value="create_collection">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="text" name="collection_name" placeholder="New collection name" required
                            pattern="[a-zA-Z0-9_-]+"
                            style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px; margin-bottom: 8px;">
                        <button type="submit" class="btn"
                            style="background: #667eea; color: white; width: 100%; padding: 10px; font-size: 14px;">➕
                            Create</button>
                    </form>
                </div>

                <!-- Drop Collection -->
                <div style="background: rgba(255,255,255,0.95); padding: 18px; border-radius: 8px; color: #333;">
                    <h4 style="color: #dc3545; margin-bottom: 12px;">🗑️ Drop Collection</h4>
                    <form method="POST" onsubmit="return confirm('Are you ABSOLUTELY sure? This cannot be undone!')">
                        <input type="hidden" name="action" value="drop_collection">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <select name="collection_to_drop" required
                        style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px; margin-bottom: 8px;">
                        <?php echo generateCollectionOptions($allCollectionNames); ?>
                    </select>
                        <input type="text" name="confirm_collection_name" placeholder="Type name to confirm" required
                            style="width: 100%; padding: 8px; border: 2px solid #dc3545; border-radius: 4px; margin-bottom: 8px;">
                        <button type="submit" class="btn"
                            style="background: #dc3545; color: white; width: 100%; padding: 10px; font-size: 14px;">🗑️
                            Drop</button>
                    </form>
                </div>

                <!-- Rename Collection -->
                <div style="background: rgba(255,255,255,0.95); padding: 18px; border-radius: 8px; color: #333;">
                    <h4 style="color: #ffc107; margin-bottom: 12px;">✏️ Rename Collection</h4>
                    <form method="POST">
                        <input type="hidden" name="action" value="rename_collection">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <select name="old_collection_name" required
                            style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px; margin-bottom: 8px;">
                            <option value="">Select collection...</option>
                            <?php foreach ($allCollectionNames as $cname): ?>
                                <option value="<?php echo htmlspecialchars($cname); ?>">
                                    <?php echo htmlspecialchars($cname); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="new_collection_name" placeholder="New name" required
                            pattern="[a-zA-Z0-9_-]+"
                            style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px; margin-bottom: 8px;">
                        <button type="submit" class="btn"
                            style="background: #ffc107; color: #333; width: 100%; padding: 10px; font-size: 14px;">✏️
                            Rename</button>
                    </form>
                </div>

                <!-- Clone Collection -->
                <div style="background: rgba(255,255,255,0.95); padding: 18px; border-radius: 8px; color: #333;">
                    <h4 style="color: #17a2b8; margin-bottom: 12px;">📋 Clone Collection</h4>
                    <form method="POST">
                        <input type="hidden" name="action" value="clone_collection">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <select name="source_collection" required
                            style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px; margin-bottom: 8px;">
                            <option value="">Source collection...</option>
                            <?php foreach ($allCollectionNames as $cname): ?>
                                <option value="<?php echo htmlspecialchars($cname); ?>">
                                    <?php echo htmlspecialchars($cname); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="target_collection" placeholder="Target name" required
                            pattern="[a-zA-Z0-9_-]+"
                            style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px; margin-bottom: 8px;">
                        <button type="submit" class="btn"
                            style="background: #17a2b8; color: white; width: 100%; padding: 10px; font-size: 14px;">📋
                            Clone</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Index Management Section -->
        <div
            style="background: white; padding: 20px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="color: #333; margin-bottom: 20px;">📊 Index Management</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <!-- Create Index -->
                <div style="background: #f8f9fa; padding: 18px; border-radius: 8px; border-left: 4px solid #28a745;">
                    <h4 style="color: #28a745; margin-bottom: 12px;">➕ Create Index</h4>
                    <form method="POST">
                        <input type="hidden" name="action" value="create_index">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                </div>
                <div style="margin-bottom: 12px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">Field
                        Name:</label>
                    <input type="text" name="index_field" placeholder="e.g., email" required
                        style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px;">
                </div>
                <div style="margin-bottom: 12px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">Order:</label>
                    <select name="index_order"
                        style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px;">
                        <option value="1">Ascending (1)</option>
                        <option value="-1">Descending (-1)</option>
                    </select>
                </div>
                <div style="margin-bottom: 12px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="index_unique" value="1" style="width: 18px; height: 18px;">
                        <span style="font-size: 14px;">Unique Index</span>
                    </label>
                </div>
                <button type="submit" class="btn"
                    style="background: #28a745; color: white; width: 100%; padding: 10px;">➕
                    Create Index</button>
                </form>
            </div>

            <!-- Drop Index -->
            <div style="background: #f8f9fa; padding: 18px; border-radius: 8px; border-left: 4px solid #dc3545;">
                <h4 style="color: #dc3545; margin-bottom: 12px;">🗑️ Drop Index</h4>
                <form method="POST" onsubmit="return confirm('Drop this index?')">
                    <input type="hidden" name="action" value="drop_index">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">Select
                            Index:</label>
                        <select name="index_name" required
                            style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px;">
                            <option value="">Select index...</option>
                            <?php
                            try {
                                foreach ($collection->listIndexes() as $index) {
                                    $indexName = $index['name'];
                                    if ($indexName !== '_id_') {
                                        $keys = json_encode($index['key']);
                                        echo "<option value=\"" . htmlspecialchars($indexName) . "\">" . htmlspecialchars($indexName) . " (" . htmlspecialchars($keys) . ")</option>";
                                    }
                                }
                            } catch (Exception $e) {
                                echo "<option value=\"\">No indexes found</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <button type="submit" class="btn"
                        style="background: #dc3545; color: white; width: 100%; padding: 10px;">🗑️ Drop Index</button>
                </form>
            </div>
        </div>

        <!-- Current Indexes Display -->
        <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; border-left: 4px solid #2196f3;">
            <h4 style="color: #1976d2; margin-bottom: 12px;">📊 Current Indexes on
                '<?php echo htmlspecialchars($collectionName); ?>'</h4>
            <div style="max-height: 200px; overflow-y: auto;">
                <?php
                try {
                    $indexes = $collection->listIndexes();
                    echo '<table style="width: 100%; font-size: 13px; border-collapse: collapse;">';
                    echo '<tr style="background: #1976d2; color: white;"><th style="padding: 8px; text-align: left;">Name</th><th style="padding: 8px; text-align: left;">Keys</th><th style="padding: 8px; text-align: left;">Unique</th></tr>';
                    foreach ($indexes as $index) {
                        $unique = isset($index['unique']) && $index['unique'] ? '✅ Yes' : '❌ No';
                        $keys = json_encode($index['key'], JSON_UNESCAPED_SLASHES);
                        echo '<tr style="background: white; border-bottom: 1px solid #ddd;">';
                        echo '<td style="padding: 8px;">' . htmlspecialchars($index['name']) . '</td>';
                        echo '<td style="padding: 8px; font-family: monospace;">' . htmlspecialchars($keys) . '</td>';
                        echo '<td style="padding: 8px;">' . $unique . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                } catch (Exception $e) {
                    echo '<p style="color: #666;">No indexes found or error loading indexes.</p>';
                }
                ?>
            </div>
        </div>

    <!-- Data Import/Export Section -->
    <div
        style="background: white; padding: 20px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <h3 style="color: #333; margin-bottom: 20px;">💾 Backup & Data Management</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
            <!-- Backup Collection -->
            <div style="background: #e3f2fd; padding: 18px; border-radius: 8px; border-left: 4px solid #2196f3;">
                <h4 style="color: #1976d2; margin-bottom: 12px;">💾 Backup Collection</h4>
                <form method="POST">
                    <input type="hidden" name="action" value="backup_collection">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                    <div class="form-group">
                        <label style="font-size: 13px; font-weight: 600;">Backup Name (optional):</label>
                        <input type="text" name="backup_name"
                            placeholder="<?php echo htmlspecialchars($collectionName); ?>_backup"
                            pattern="[a-zA-Z0-9_-]+"
                            style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px; margin-bottom: 8px;">
                        <small style="color: #666;">Leave empty for auto-generated name</small>
                    </div>
                    <button type="submit" class="btn"
                        style="background: #2196f3; color: white; width: 100%; padding: 10px;">💾 Create Backup</button>
                </form>
            </div>

            <!-- Export Data -->
            <div style="background: #f3e5f5; padding: 18px; border-radius: 8px; border-left: 4px solid #9c27b0;">
                <h4 style="color: #7b1fa2; margin-bottom: 12px;">📤 Export Data</h4>
                <form method="POST" style="margin-bottom: 10px;">
                    <input type="hidden" name="action" value="export">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                    <p style="color: #666; font-size: 12px; margin-bottom: 8px;">Download all (or filtered) documents
                    </p>
                    <button type="submit" class="btn"
                        style="background: #9c27b0; color: white; width: 100%; padding: 10px; margin-bottom: 8px;">📥
                        Export
                        JSON</button>
                </form>
                <form method="POST">
                    <input type="hidden" name="action" value="exportcsv">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                    <button type="submit" class="btn"
                        style="background: #7b1fa2; color: white; width: 100%; padding: 10px;">📊 Export CSV</button>
                </form>
            </div>

            <!-- Import Data -->
            <div style="background: #e8f5e9; padding: 18px; border-radius: 8px; border-left: 4px solid #4caf50;">
                <h4 style="color: #388e3c; margin-bottom: 12px;">📥 Import JSON Data</h4>

                <!-- File Upload Method -->
                <form method="POST" enctype="multipart/form-data" id="importFileForm">
                    <input type="hidden" name="action" value="import">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="font-size: 13px; font-weight: 600; display: block; margin-bottom: 6px;">📁 Upload
                            JSON
                            File:</label>
                        <input type="file" name="json_file" id="jsonFileInput" accept=".json"
                            style="width: 100%; padding: 8px; border: 2px solid #81c784; border-radius: 6px; margin-bottom: 8px; font-size: 13px; background: white;">
                        <small style="color: #558b2f; font-size: 11px;">Supports single document or array of
                            documents</small>
                    </div>
                    <button type="submit" class="btn"
                        style="background: #4caf50; color: white; width: 100%; padding: 10px; font-weight: 600;">⬆️
                        Import
                        from File</button>
                </form>

                <div style="text-align: center; margin: 15px 0; color: #66bb6a; font-weight: 600;">— OR —</div>

                <!-- JSON Paste Method -->
                <button type="button" class="btn" onclick="openJsonImportModal()"
                    style="background: #66bb6a; color: white; width: 100%; padding: 10px; font-weight: 600;">
                    📋 Paste JSON Directly
                </button>
            </div>
        </div>
    </div>

    <!-- Collection Migration -->
    <div
        style="background: white; padding: 20px; border-radius: 12px; margin-top: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <h3 style="color: #333; margin-bottom: 20px;">🔄 Collection Migration</h3>
        <form method="POST">
            <input type="hidden" name="action" value="migrate_collection">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">Source Collection:</label>
                    <select name="source_collection" required
                        style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                        <option value="">Select source...</option>
                        <?php foreach ($collectionNames as $collName): ?>
                            <option value="<?php echo htmlspecialchars($collName); ?>">
                                <?php echo htmlspecialchars($collName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">Target Collection:</label>
                    <input type="text" name="target_collection" placeholder="New or existing collection" required
                        pattern="[a-zA-Z0-9_-]+"
                        style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                </div>
            </div>
            <div style="margin-top: 15px;">
                <label style="font-weight: 600; margin-bottom: 8px; display: block;">Filter (Optional JSON):</label>
                <input type="text" name="migrate_filter" placeholder='{"status": "active"}'
                    style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-family: monospace;">
                <small style="color: #666;">Leave empty to migrate all documents</small>
            </div>
            <div style="margin-top: 15px; display: flex; gap: 10px; align-items: center;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="migrate_copy" value="1" checked>
                    <span style="font-size: 14px;">Copy mode (keep source documents)</span>
                </label>
            </div>
            <button type="submit" class="btn"
                style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; width: 100%; padding: 14px; margin-top: 20px;"
                onclick="return confirm('Migrate documents to target collection?')">🔄 Start Migration</button>
        </form>
    </div>

    <!-- Index Management -->
    <div
        style="background: white; padding: 25px; border-radius: 12px; margin-top: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <h3 style="color: #333; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">🔍</span> Index Management
        </h3>

        <!-- List Current Indexes -->
        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <h4 style="margin-bottom: 12px; color: #495057;">📋 Current Indexes:</h4>
            <?php
            try {
                $indexes = iterator_to_array($collection->listIndexes());
                if (count($indexes) > 0): ?>
                    <div style="display: grid; gap: 10px;">
                        <?php foreach ($indexes as $index): ?>
                            <div
                                style="background: white; padding: 12px; border-radius: 6px; display: flex; justify-content: space-between; align-items: center; border-left: 3px solid <?php echo $index['name'] === '_id_' ? '#007bff' : '#28a745'; ?>;">
                                <div>
                                    <strong style="color: #333;"><?php echo htmlspecialchars($index['name']); ?></strong>
                                    <code
                                        style="background: #e9ecef; padding: 4px 8px; border-radius: 4px; margin-left: 10px; font-size: 12px;">
                                                                                                                                                <?php echo htmlspecialchars(json_encode($index['key'])); ?>
                                                                                                                                            </code>
                                    <?php if (isset($index['unique']) && $index['unique']): ?>
                                        <span
                                            style="background: #007bff; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; margin-left: 8px;">UNIQUE</span>
                                    <?php endif; ?>
                                    <?php if (isset($index['sparse']) && $index['sparse']): ?>
                                        <span
                                            style="background: #6c757d; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; margin-left: 8px;">SPARSE</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($index['name'] !== '_id_'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="drop_index">
                                        <input type="hidden" name="drop_index_name"
                                            value="<?php echo htmlspecialchars($index['name']); ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <button type="submit" class="btn"
                                            onclick="return confirm('Drop index <?php echo htmlspecialchars($index['name']); ?>?')"
                                            style="background: #dc3545; color: white; padding: 6px 12px; font-size: 12px;">🗑️
                                            Drop</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color: #6c757d; font-style: italic;">No indexes found</p>
                <?php endif;
            } catch (Exception $e) {
                echo '<p style="color: #dc3545;">Error loading indexes: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
            ?>
        </div>

        <!-- Create New Index -->
        <form method="POST"
            style="background: #e3f2fd; padding: 20px; border-radius: 8px; border-left: 4px solid #2196f3;">
            <input type="hidden" name="action" value="create_index">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
            <h4 style="margin-bottom: 15px; color: #1565c0;">➕ Create New Index</h4>
            <div style="display: grid; grid-template-columns: 2fr 1fr 2fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <label style="font-weight: 600; margin-bottom: 6px; display: block;">Field Name:</label>
                    <input type="text" name="index_field" placeholder="e.g., email, user_id" required
                        style="width: 100%; padding: 10px; border: 2px solid #90caf9; border-radius: 6px;">
                </div>
                <div>
                    <label style="font-weight: 600; margin-bottom: 6px; display: block;">Type:</label>
                    <select name="index_type" required
                        style="width: 100%; padding: 10px; border: 2px solid #90caf9; border-radius: 6px;">
                        <option value="1">Ascending (1)</option>
                        <option value="-1">Descending (-1)</option>
                    </select>
                </div>
                <div>
                    <label style="font-weight: 600; margin-bottom: 6px; display: block;">Index Name (optional):</label>
                    <input type="text" name="index_name" placeholder="Auto-generated if empty"
                        style="width: 100%; padding: 10px; border: 2px solid #90caf9; border-radius: 6px;">
                </div>
            </div>
            <div style="display: flex; gap: 20px; margin-bottom: 15px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="index_unique" value="1">
                    <span style="font-size: 14px; font-weight: 600;">🔒 Unique Index</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="index_sparse" value="1">
                    <span style="font-size: 14px; font-weight: 600;">📊 Sparse Index</span>
                </label>
            </div>
            <button type="submit" class="btn" style="background: #2196f3; color: white; width: 100%; padding: 12px;">➕
                Create Index</button>
        </form>
    </div>

    <!-- Clone Collection -->
    <div
        style="background: white; padding: 25px; border-radius: 12px; margin-top: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <h3 style="color: #333; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">📋</span> Clone Collection
        </h3>
        <form method="POST">
            <input type="hidden" name="action" value="clone_collection">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px;">
                <div>
                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">Source Collection:</label>
                    <select name="clone_source" required
                        style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                        <option value="">Select source...</option>
                        <?php foreach ($collectionNames as $collName): ?>
                            <option value="<?php echo htmlspecialchars($collName); ?>" <?php echo $collName === $collectionName ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($collName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">Target Collection Name:</label>
                    <input type="text" name="clone_target" placeholder="e.g., users_backup" required
                        pattern="[a-zA-Z0-9_-]+"
                        style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                </div>
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="clone_indexes" value="1" checked>
                    <span style="font-size: 14px; font-weight: 600;">📇 Copy Indexes</span>
                </label>
            </div>
            <button type="submit" class="btn"
                style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; width: 100%; padding: 14px;"
                onclick="return confirm('Clone this collection?')">📋 Clone Collection</button>
        </form>
    </div>

    <!-- Duplicate Finder -->
    <div
        style="background: white; padding: 25px; border-radius: 12px; margin-top: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <h3 style="color: #333; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">🔍</span> Find Duplicate Values
        </h3>
        <form method="POST">
            <input type="hidden" name="action" value="find_duplicates">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
            <div style="margin-bottom: 15px;">
                <label style="font-weight: 600; margin-bottom: 8px; display: block;">Field to Check:</label>
                <input type="text" name="dup_field" placeholder="e.g., email, username, product_id" required
                    style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                <small style="color: #666;">Find documents with duplicate values in this field</small>
            </div>
            <button type="submit" class="btn"
                style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; width: 100%; padding: 12px;">🔍
                Find Duplicates</button>
        </form>

        <?php if (isset($_SESSION['duplicate_results'])):
            $dupResults = $_SESSION['duplicate_results'];
            ?>
            <div
                style="background: #fff3cd; padding: 20px; border-radius: 8px; margin-top: 20px; border-left: 4px solid #ffc107;">
                <h4 style="color: #856404; margin-bottom: 15px;">
                    📊 Duplicate Analysis for "<?php echo htmlspecialchars($dupResults['field']); ?>"
                </h4>
                <p style="color: #856404; margin-bottom: 15px;">
                    Found <strong><?php echo $dupResults['total']; ?></strong> unique values with duplicates
                </p>
                <?php if (count($dupResults['results']) > 0): ?>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <?php foreach (array_slice($dupResults['results'], 0, 20) as $dup):
                            $dupData = json_decode(json_encode($dup), true);
                            ?>
                            <div
                                style="background: white; padding: 12px; border-radius: 6px; margin-bottom: 10px; border-left: 3px solid #ffc107;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong style="color: #333;">Value:
                                            <?php echo htmlspecialchars(json_encode($dupData['_id'])); ?></strong>
                                        <span
                                            style="background: #dc3545; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; margin-left: 10px;">
                                            <?php echo $dupData['count']; ?> occurrences
                                        </span>
                                    </div>
                                </div>
                                <details style="margin-top: 8px;">
                                    <summary style="cursor: pointer; color: #007bff; font-size: 12px;">Show Document IDs</summary>
                                    <div
                                        style="background: #f8f9fa; padding: 8px; border-radius: 4px; margin-top: 6px; font-family: monospace; font-size: 11px;">
                                        <?php foreach ($dupData['ids'] as $id): ?>
                                            <div><?php echo htmlspecialchars((string) $id); ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                </details>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <button onclick="<?php unset($_SESSION['duplicate_results']); ?> window.location.reload();" class="btn"
                    style="background: #6c757d; color: white; padding: 8px 16px; margin-top: 10px;">Clear Results</button>
            </div>
            <?php unset($_SESSION['duplicate_results']); endif; ?>
    </div>

    <!-- Bulk Update by Query -->
    <div
        style="background: white; padding: 25px; border-radius: 12px; margin-top: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <h3 style="color: #333; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">⚡</span> Bulk Update by Query
        </h3>
        <form method="POST">
            <input type="hidden" name="action" value="bulk_update_query">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
            <div style="margin-bottom: 15px;">
                <label style="font-weight: 600; margin-bottom: 8px; display: block;">Filter (Match documents):</label>
                <textarea name="bulk_filter" placeholder='{"status": "pending"}' required
                    style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-family: monospace; min-height: 80px;"></textarea>
            </div>
            <div style="margin-bottom: 15px;">
                <label style="font-weight: 600; margin-bottom: 8px; display: block;">Update (Set new values):</label>
                <textarea name="bulk_update" placeholder='{"status": "completed", "updated_at": "2026-01-14"}' required
                    style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-family: monospace; min-height: 80px;"></textarea>
                <small style="color: #666;">Auto-wraps with $set if no operators provided</small>
            </div>
            <button type="submit" class="btn"
                style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; width: 100%; padding: 14px;"
                onclick="return confirm('Update all matching documents?')">⚡ Execute Bulk Update</button>
        </form>
    </div>

    <!-- Data Validation Schema -->
    <div
        style="background: white; padding: 25px; border-radius: 12px; margin-top: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <h3 style="color: #333; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">✅</span> Data Validation Rules
        </h3>
        <form method="POST">
            <input type="hidden" name="action" value="add_validation">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
            <div style="margin-bottom: 15px;">
                <label style="font-weight: 600; margin-bottom: 8px; display: block;">JSON Schema:</label>
                <textarea name="validation_schema"
                    placeholder='{"bsonType": "object", "required": ["name", "email"], "properties": {"name": {"bsonType": "string"}, "email": {"bsonType": "string", "pattern": "^.+@.+$"}}}'
                    required
                    style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-family: monospace; min-height: 120px;"></textarea>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">Validation Level:</label>
                    <select name="validation_level"
                        style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                        <option value="strict">Strict (all inserts/updates)</option>
                        <option value="moderate">Moderate (inserts only)</option>
                        <option value="off">Off</option>
                    </select>
                </div>
                <div>
                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">Validation Action:</label>
                    <select name="validation_action"
                        style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                        <option value="error">Error (reject invalid)</option>
                        <option value="warn">Warn (log only)</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn"
                style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; width: 100%; padding: 14px;">✅
                Apply Validation Schema</button>
        </form>
    </div>

    <!-- Compare Collections -->
    <div
        style="background: white; padding: 25px; border-radius: 12px; margin-top: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <h3 style="color: #333; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">⚖️</span> Compare Collections
        </h3>
        <form method="POST">
            <input type="hidden" name="action" value="compare_collections">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">Collection 1:</label>
                    <select name="compare_coll1" required
                        style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                        <option value="">Select...</option>
                        <?php foreach ($collectionNames as $collName): ?>
                            <option value="<?php echo htmlspecialchars($collName); ?>">
                                <?php echo htmlspecialchars($collName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">Collection 2:</label>
                    <select name="compare_coll2" required
                        style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                        <option value="">Select...</option>
                        <?php foreach ($collectionNames as $collName): ?>
                            <option value="<?php echo htmlspecialchars($collName); ?>">
                                <?php echo htmlspecialchars($collName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">Compare Field:</label>
                    <input type="text" name="compare_field" placeholder="e.g., _id, email" required
                        style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                </div>
            </div>
            <button type="submit" class="btn"
                style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; width: 100%; padding: 12px;">⚖️
                Compare Collections</button>
        </form>

        <?php if (isset($_SESSION['compare_results'])):
            $compResults = $_SESSION['compare_results'];
            ?>
            <div
                style="background: #e3f2fd; padding: 20px; border-radius: 8px; margin-top: 20px; border-left: 4px solid #2196f3;">
                <h4 style="color: #1565c0; margin-bottom: 15px;">📊 Comparison Results</h4>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px;">
                    <div style="background: white; padding: 15px; border-radius: 6px; text-align: center;">
                        <p style="font-size: 28px; font-weight: bold; color: #28a745;">
                            <?php echo $compResults['stats']['common']; ?>
                        </p>
                        <p style="font-size: 13px; color: #666;">Common Values</p>
                    </div>
                    <div style="background: white; padding: 15px; border-radius: 6px; text-align: center;">
                        <p style="font-size: 28px; font-weight: bold; color: #007bff;">
                            <?php echo $compResults['stats']['unique_1']; ?>
                        </p>
                        <p style="font-size: 13px; color: #666;">Only in
                            <?php echo htmlspecialchars($compResults['coll1']); ?>
                        </p>
                    </div>
                    <div style="background: white; padding: 15px; border-radius: 6px; text-align: center;">
                        <p style="font-size: 28px; font-weight: bold; color: #ffc107;">
                            <?php echo $compResults['stats']['unique_2']; ?>
                        </p>
                        <p style="font-size: 13px; color: #666;">Only in
                            <?php echo htmlspecialchars($compResults['coll2']); ?>
                        </p>
                    </div>
                </div>
                <button onclick="<?php unset($_SESSION['compare_results']); ?> window.location.reload();" class="btn"
                    style="background: #6c757d; color: white; padding: 8px 16px;">Clear Results</button>
            </div>
            <?php unset($_SESSION['compare_results']); endif; ?>
    </div>

    <!-- Export Collection Data -->
    <div
        style="background: white; padding: 25px; border-radius: 12px; margin-top: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <h3 style="color: #333; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">💾</span> Export Collection Data
        </h3>
        <form method="POST">
            <input type="hidden" name="action" value="export_collection_data">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">Export Format:</label>
                    <select name="export_format" required
                        style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                        <option value="json">JSON</option>
                        <option value="csv">CSV</option>
                    </select>
                </div>
                <div>
                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">Filter (optional):</label>
                    <input type="text" name="export_filter" placeholder='{"status": "active"}'
                        style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-family: monospace;">
                </div>
            </div>
            <button type="submit" class="btn"
                style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; width: 100%; padding: 12px;">💾
                Export Data</button>
        </form>
    </div>
    </div>

<!-- Advanced Tab -->
