    <div id="bulk" class="tab-content">
        <h2>📦 Bulk Operations</h2>

        <!-- Field Operations Section -->
        <div
            style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); padding: 20px; border-radius: 12px; margin-bottom: 25px; color: white;">
            <h3 style="color: white; margin-bottom: 20px;">🔧 Field Operations</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px;">
                <!-- Add Field -->
                <div style="background: rgba(255,255,255,0.95); padding: 18px; border-radius: 8px; color: #333;">
                    <h4 style="color: #28a745; margin-bottom: 12px;">➕ Add Field</h4>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_field">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                        <input type="text" name="field_name" placeholder="Field name" required
                            style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px; margin-bottom: 8px;">
                        <input type="text" name="default_value" placeholder="Default value (or JSON)"
                            style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px; margin-bottom: 8px;">
                        <button type="submit" class="btn"
                            style="background: #28a745; color: white; width: 100%; padding: 10px; font-size: 14px;">➕
                            Add to All</button>
                    </form>
                </div>

                <!-- Remove Field -->
                <div style="background: rgba(255,255,255,0.95); padding: 18px; border-radius: 8px; color: #333;">
                    <h4 style="color: #dc3545; margin-bottom: 12px;">❌ Remove Field</h4>
                    <form method="POST" onsubmit="return confirm('Remove this field from ALL documents?')">
                        <input type="hidden" name="action" value="remove_field">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                        <input type="text" name="field_name" placeholder="Field name" required
                            style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px; margin-bottom: 8px;">
                        <button type="submit" class="btn"
                            style="background: #dc3545; color: white; width: 100%; padding: 10px; font-size: 14px;">❌
                            Remove from All</button>
                    </form>
                </div>

                <!-- Rename Field -->
                <div style="background: rgba(255,255,255,0.95); padding: 18px; border-radius: 8px; color: #333;">
                    <h4 style="color: #ffc107; margin-bottom: 12px;">✏️ Rename Field</h4>
                    <form method="POST">
                        <input type="hidden" name="action" value="rename_field">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                        <input type="text" name="old_field_name" placeholder="Old field name" required
                            style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px; margin-bottom: 8px;">
                        <input type="text" name="new_field_name" placeholder="New field name" required
                            style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px; margin-bottom: 8px;">
                        <button type="submit" class="btn"
                            style="background: #ffc107; color: #333; width: 100%; padding: 10px; font-size: 14px;">✏️
                            Rename</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Data Operations -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px;">
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <h3>🔄 Bulk Update</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="bulkupdate">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                    <div class="form-group">
                        <label>Match Field:</label>
                        <input type="text" name="match_field" placeholder="e.g., email" required
                            style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                    </div>
                    <div class="form-group">
                        <label>Match Value (regex):</label>
                        <input type="text" name="match_value" placeholder="value to search" required
                            style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                    </div>
                    <div class="form-group">
                        <label>Update Field:</label>
                        <input type="text" name="update_field" placeholder="field name" required
                            style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                    </div>
                    <div class="form-group">
                        <label>New Value:</label>
                        <input type="text" name="update_value" placeholder="new value" required
                            style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                    </div>
                    <button type="submit" class="btn"
                        style="background: #ffc107; color: #333; width: 100%; padding: 12px;"
                        onclick="return confirm('Update all matching documents?')">🔄 Update All</button>
                </form>
            </div>

            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <h3>🔍 Find & Replace</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="findreplace">
                    <div class="form-group">
                        <label>Field Name:</label>
                        <input type="text" name="field_name" placeholder="e.g., description" required
                            style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                    </div>
                    <div class="form-group">
                        <label>Find (regex):</label>
                        <input type="text" name="find_value" placeholder="text to find" required
                            style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                    </div>
                    <div class="form-group">
                        <label>Replace With:</label>
                        <input type="text" name="replace_value" placeholder="replacement text" required
                            style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                    </div>
                    <button type="submit" class="btn"
                        style="background: #17a2b8; color: white; width: 100%; padding: 12px;"
                        onclick="return confirm('Replace all matches?')">✨ Replace All</button>
                </form>
            </div>
        </div>

        <!-- Advanced Bulk Operations -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
            <!-- Deduplication -->
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <h3 style="color: #17a2b8;">🧹 Remove Duplicates</h3>
                <form method="POST" onsubmit="return confirm('This will remove duplicate documents. Continue?')">
                    <input type="hidden" name="action" value="deduplicate">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                    <div class="form-group">
                        <label>Field to Check:</label>
                        <input type="text" name="dedup_field" placeholder="e.g., email" required
                            style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                        <small style="color: #666;">Keeps first occurrence, removes rest</small>
                    </div>
                    <button type="submit" class="btn"
                        style="background: #17a2b8; color: white; width: 100%; padding: 12px;">🧹 Deduplicate</button>
                </form>
            </div>

            <!-- Bulk Delete by Field -->
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <h3 style="color: #dc3545;">🗑️ Bulk Delete by Field</h3>
                <form method="POST" onsubmit="return confirm('This will permanently delete documents. Are you sure?')">
                    <input type="hidden" name="action" value="bulk_delete_by_field">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                    <div class="form-group">
                        <label>Field Name:</label>
                        <input type="text" name="delete_field" placeholder="e.g., status" required
                            style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                    </div>
                    <div class="form-group">
                        <label>Operator:</label>
                        <select name="delete_operator"
                            style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                            <option value="equals">Equals</option>
                            <option value="contains">Contains</option>
                            <option value="empty">Is Empty</option>
                            <option value="not_empty">Is Not Empty</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Value:</label>
                        <input type="text" name="delete_value" placeholder="value"
                            style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                    </div>
                    <button type="submit" class="btn"
                        style="background: #dc3545; color: white; width: 100%; padding: 12px;">🗑️ Delete
                        Matching</button>
                </form>
            </div>

            <!-- Data Generator -->
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <h3 style="color: #28a745;">🎲 Generate Test Data</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="generate_data">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                    <div class="form-group">
                        <label>Template (JSON):</label>
                        <textarea name="data_template"
                            placeholder='{"name": "User {{index}}", "code": "{{random}}", "created": "{{date}}"}'
                            required
                            style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; min-height: 100px; font-family: monospace; font-size: 12px;"></textarea>
                        <small style="color: #666;">Placeholders: {{index}}, {{random}}, {{date}}, {{timestamp}}</small>
                    </div>
                    <div class="form-group">
                        <label>Count (max 1000):</label>
                        <input type="number" name="data_count" value="10" min="1" max="1000" required
                            style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                    </div>
                    <button type="submit" class="btn"
                        style="background: #28a745; color: white; width: 100%; padding: 12px;">🎲 Generate &
                        Insert</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Tools Tab -->
