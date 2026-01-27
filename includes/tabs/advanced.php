<div id="advanced" class="tab-content">
    <h2 style="color: var(--text-primary); margin-bottom: 20px;">🔬 Advanced Features</h2>

    <!-- Dangerous Operations Section -->
    <div
        style="background: var(--card-bg); padding: 25px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border-left: 4px solid var(--accent-danger);">
        <h3 style="color: var(--accent-danger); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">🗑️</span> Delete All Documents
        </h3>
        <div
            style="background: var(--warning-bg); padding: 12px; border-radius: 6px; margin-bottom: 15px; border-left: 4px solid var(--accent-warning);">
            <strong style="color: var(--warning-text);">⚠️ Warning:</strong> <span style="color: var(--warning-text);">This will permanently
                delete ALL documents from this collection. This action cannot be undone!</span>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="delete_all">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
            <button type="submit" class="btn"
                style="background: var(--accent-danger); color: var(--text-on-accent); width: 100%; padding: 14px; font-weight: 600;"
                onclick="return confirm('⚠️ FINAL WARNING: This will delete ALL <?php echo $documentCount; ?> documents from <?php echo htmlspecialchars($collectionName); ?>. This cannot be undone! Type YES to confirm.') && prompt('Type DELETE to confirm:') === 'DELETE'">⚠️
                Delete All Documents</button>
        </form>
    </div>

    <!-- Query History -->
    <div
        style="background: var(--card-bg); padding: 25px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <h3 style="color: var(--text-primary); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">📚</span> Query History
        </h3>

        <!-- Save Current Query -->
        <form method="POST" style="background: var(--success-bg); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <input type="hidden" name="action" value="save_query">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
            <h4 style="color: var(--success-text); margin-bottom: 12px;">💾 Save New Query</h4>
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div>
                    <label style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 13px;">Query
                        Name:</label>
                    <input type="text" name="query_name" placeholder="e.g., Active Users" required
                        style="width: 100%; padding: 8px; border: 2px solid var(--accent-success-soft); border-radius: 6px;">
                </div>
                <div>
                    <label
                        style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 13px;">Collection:</label>
                    <input type="text" name="query_collection" value="<?php echo htmlspecialchars($collectionName); ?>"
                        required style="width: 100%; padding: 8px; border: 2px solid var(--accent-success-soft); border-radius: 6px;">
                </div>
            </div>
            <div style="margin-bottom: 12px;">
                <label style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 13px;">Query
                    JSON:</label>
                <textarea name="query_filter" placeholder='{"status": "active"}' required
                    style="width: 100%; padding: 8px; border: 2px solid var(--accent-success-soft); border-radius: 6px; font-family: monospace; min-height: 60px;"></textarea>
            </div>
            <button type="submit" class="btn" style="background: var(--accent-success); color: var(--text-on-accent); width: 100%; padding: 10px;">💾
                Save
                Query</button>
        </form>

        <!-- Saved Queries List -->
        <?php if (isset($_SESSION['saved_queries']) && count($_SESSION['saved_queries']) > 0): ?>
            <h4 style="margin-bottom: 15px; color: var(--text-secondary);">📋 Saved Queries
                (<?php echo count($_SESSION['saved_queries']); ?>)
            </h4>
            <div style="max-height: 400px; overflow-y: auto;">
                <?php foreach ($_SESSION['saved_queries'] as $query): ?>
                    <div
                        style="background: var(--surface-muted); padding: 15px; border-radius: 8px; margin-bottom: 12px; border-left: 3px solid var(--accent-blue);">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                            <div>
                                <strong
                                    style="color: var(--text-primary); font-size: 15px;"><?php echo htmlspecialchars($query['name']); ?></strong>
                                <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                                    📦 <?php echo htmlspecialchars($query['collection']); ?> |
                                    📅 <?php echo htmlspecialchars($query['created']); ?>
                                </div>
                            </div>
                            <div style="display: flex; gap: 6px;">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="load_query">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="query_id" value="<?php echo htmlspecialchars($query['id']); ?>">
                                    <button type="submit" class="btn"
                                        style="background: var(--accent-success); color: var(--text-on-accent); padding: 6px 12px; font-size: 12px;">▶️
                                        Load</button>
                                </form>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="delete_query">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="query_id" value="<?php echo htmlspecialchars($query['id']); ?>">
                                    <button type="submit" class="btn" onclick="return confirm('Delete this query?')"
                                        style="background: var(--accent-danger); color: var(--text-on-accent); padding: 6px 12px; font-size: 12px;">🗑️</button>
                                </form>
                            </div>
                        </div>
                        <code
                            style="display: block; background: var(--card-bg); padding: 10px; border-radius: 4px; font-size: 12px; overflow-x: auto; color: var(--text-secondary);">
                                                                                                    <?php echo htmlspecialchars($query['filter']); ?>
                                                                                                </code>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="color: var(--text-muted); font-style: italic; text-align: center; padding: 20px;">No saved queries yet. Save
                your
                frequently used queries above!</p>
        <?php endif; ?>
    </div>

    <!-- Template & Stats Section -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
        <div
            style="background: var(--card-bg); padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px var(--shadow-color);">
            <h3 style="color: var(--text-primary); margin-bottom: 15px;">💾 Document Templates</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                <input type="hidden" name="action" value="savetemplate">
                <div class="form-group">
                    <label style="color: var(--text-secondary);">Template Name:</label>
                    <input type="text" name="template_name" placeholder="e.g., user_template" required
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px;">
                </div>
                <div class="form-group">
                    <label style="color: var(--text-secondary);">Template JSON:</label>
                    <textarea name="template_data" placeholder='{"name": "", "email": "", "age": 0}' required
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px; min-height: 100px; font-family: 'Courier New', monospace;"></textarea>
                </div>
                <button type="submit" class="btn"
                    style="background: var(--accent-success); color: var(--text-on-accent); width: 100%; padding: 10px;">💾 Save Template</button>
            </form>

            <?php
            // Load saved templates
            try {
                $templatesCollection = $database->getCollection('_templates');
                $savedTemplates = $templatesCollection->find(['user_collection' => $collectionName])->toArray();

                if (!empty($savedTemplates)):
                    ?>
                    <div style="margin-top: 20px; padding-top: 20px; border-top: 2px solid var(--table-border);">
                        <h4 style="color: var(--text-primary); margin-bottom: 15px; font-size: 14px;">📚 Saved Templates
                        </h4>
                        <?php foreach ($savedTemplates as $template): ?>
                            <div
                                style="background: var(--table-header-bg); padding: 12px; border-radius: 6px; margin-bottom: 10px; border-left: 3px solid var(--accent-primary);">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <strong style="color: var(--text-primary); font-size: 13px;">
                                        📄 <?php echo htmlspecialchars($template->name); ?>
                                    </strong>
                                    <div style="display: flex; gap: 6px;">
                                        <button type="button" class="btn"
                                            onclick="loadTemplate('<?php echo htmlspecialchars(json_encode($template->data), ENT_QUOTES); ?>'); return false;"
                                            style="background: var(--accent-info); color: var(--text-on-accent); padding: 4px 10px; font-size: 11px;">
                                            📋 Use
                                        </button>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="action" value="deletetemplate">
                                            <input type="hidden" name="template_name"
                                                value="<?php echo htmlspecialchars($template->name); ?>">
                                            <button type="submit" class="btn" onclick="return confirm('Delete this template?')"
                                                style="background: var(--accent-danger); color: var(--text-on-accent); padding: 4px 10px; font-size: 11px;">
                                                🗑️
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <pre
                                    style="background: var(--code-bg); padding: 8px; border-radius: 4px; margin-top: 8px; font-size: 11px; overflow-x: auto; color: var(--text-secondary); max-height: 80px; overflow-y: auto;"><code><?php echo htmlspecialchars(json_encode($template->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></code></pre>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php
                endif;
            } catch (Exception $e) {
                // Silently fail if templates collection doesn't exist
            }
            ?>
        </div>

        <div
            style="background: var(--card-bg); padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px var(--shadow-color);">
            <h3 style="color: var(--text-primary); margin-bottom: 15px;">📊 Field Statistics</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                <input type="hidden" name="action" value="fieldstats">
                <div class="form-group">
                    <label style="color: var(--text-secondary);">Field Name:</label>
                    <input type="text" name="field_name" placeholder="e.g., status" required
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px;">
                </div>
                <button type="submit" class="btn"
                    style="background: var(--accent-primary); color: var(--text-on-accent); width: 100%; padding: 10px;">📈 Analyze</button>
            </form>

            <?php if (isset($_SESSION['field_stats'])): ?>
                <div
                    style="margin-top: 20px; padding: 15px; background: var(--table-header-bg); border-radius: 6px; border: 1px solid var(--table-border);">
                    <p style="font-weight: 600; margin-bottom: 10px; color: var(--text-primary);">Field:
                        <?php echo htmlspecialchars($_SESSION['field_stats']['field']); ?>
                    </p>
                    <?php foreach ($_SESSION['field_stats']['data'] as $stat): ?>
                        <div
                            style="display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid var(--table-border);">
                            <span
                                style="color: var(--text-primary);"><?php echo htmlspecialchars($stat['_id'] ?? 'null'); ?></span>
                            <span
                                style="background: var(--accent-primary); color: var(--text-on-accent); padding: 2px 8px; border-radius: 4px; font-size: 12px;"><?php echo $stat['count'] ?? 0; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Additional Features Section -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
        <div
            style="background: var(--card-bg); padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px var(--shadow-color);">
            <h3 style="color: var(--text-primary); margin-bottom: 15px;">🔢 Data Aggregation</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                <input type="hidden" name="action" value="aggregate">
                <div class="form-group">
                    <label style="color: var(--text-secondary);">Pipeline (JSON Array):</label>
                    <textarea name="pipeline" placeholder='[{"$group": {"_id": "$status", "count": {"$sum": 1}}}]'
                        required
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px; min-height: 120px; font-family: 'Courier New', monospace;"></textarea>
                </div>
                <button type="submit" class="btn"
                    style="background: var(--accent-purple); color: var(--text-on-accent); width: 100%; padding: 10px;">🔢 Run
                    Aggregation</button>
            </form>
            <?php if (isset($_SESSION['aggregation_result'])): ?>
                <div
                    style="margin-top: 15px; padding: 12px; background: var(--success-bg); border-left: 4px solid var(--success-border); border-radius: 4px;">
                    <p style="color: var(--success-text); font-weight: 600; margin-bottom: 8px;">✓ Results:</p>
                    <pre
                        style="background: var(--code-bg); padding: 10px; border-radius: 4px; font-size: 11px; max-height: 200px; overflow-y: auto; color: var(--text-primary);"><code><?php echo htmlspecialchars(json_encode($_SESSION['aggregation_result'], JSON_PRETTY_PRINT)); ?></code></pre>
                </div>
                <?php unset($_SESSION['aggregation_result']); ?>
            <?php endif; ?>
        </div>

        <div
            style="background: var(--card-bg); padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px var(--shadow-color);">
            <h3 style="color: var(--text-primary); margin-bottom: 15px;">🎯 Index Management</h3>
            <form method="POST" onsubmit="return confirm('Create this index?');">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                <input type="hidden" name="action" value="createindex">
                <div class="form-group">
                    <label style="color: var(--text-secondary);">Index Fields (JSON):</label>
                    <textarea name="index_fields" placeholder='{"email": 1, "status": -1}' required
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px; min-height: 60px; font-family: 'Courier New', monospace;"></textarea>
                </div>
                <div class="form-group">
                    <label style="color: var(--text-secondary);">Index Name:</label>
                    <input type="text" name="index_name" placeholder="e.g., email_status_idx" required
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px;">
                </div>
                <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 15px;">
                    <input type="checkbox" name="unique_index" id="unique_index" style="width: 18px; height: 18px;">
                    <label for="unique_index" style="color: var(--text-secondary); font-size: 13px;">Make this index
                        unique</label>
                </div>
                <button type="submit" class="btn"
                    style="background: var(--accent-orange); color: var(--text-on-accent); width: 100%; padding: 10px;">🎯 Create Index</button>
            </form>
        </div>
    </div>

    <!-- Export & Transform Section -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
        <div
            style="background: var(--card-bg); padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px var(--shadow-color);">
            <h3 style="color: var(--text-primary); margin-bottom: 15px;">📤 Advanced Export</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                <input type="hidden" name="action" value="advancedexport">
                <div class="form-group">
                    <label style="color: var(--text-secondary);">Export Filter (JSON):</label>
                    <textarea name="export_filter" placeholder='{"status": "active"}'
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px; min-height: 60px; font-family: 'Courier New', monospace;"></textarea>
                </div>
                <div class="form-group">
                    <label style="color: var(--text-secondary);">Fields to Export (comma-separated):</label>
                    <input type="text" name="export_fields" placeholder="name,email,status"
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px;">
                </div>
                <div class="form-group">
                    <label style="color: var(--text-secondary);">Limit:</label>
                    <input type="number" name="export_limit" value="1000" min="1" max="10000"
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px;">
                </div>
                <button type="submit" class="btn"
                    style="background: var(--accent-cyan); color: var(--text-on-accent); width: 100%; padding: 10px;">📤 Export as
                    JSON</button>
            </form>
        </div>

        <div
            style="background: var(--card-bg); padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px var(--shadow-color);">
            <h3 style="color: var(--text-primary); margin-bottom: 15px;">🔄 Field Transformation</h3>
            <form method="POST" onsubmit="return confirm('Transform field across all documents?');">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                <input type="hidden" name="action" value="transformfield">
                <div class="form-group">
                    <label style="color: var(--text-secondary);">Field to Transform:</label>
                    <input type="text" name="transform_field" placeholder="e.g., createdAt" required
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px;">
                </div>
                <div class="form-group">
                    <label style="color: var(--text-secondary);">Operation:</label>
                    <select name="transform_operation" required
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px;">
                        <option value="">Select operation...</option>
                        <option value="lowercase">Convert to Lowercase</option>
                        <option value="uppercase">Convert to Uppercase</option>
                        <option value="trim">Trim Whitespace</option>
                        <option value="todate">Convert to Date</option>
                        <option value="tonumber">Convert to Number</option>
                        <option value="tostring">Convert to String</option>
                    </select>
                </div>
                <div
                    style="margin-top: 15px; padding: 10px; background: var(--warning-bg); border-left: 4px solid var(--warning-border); border-radius: 4px;">
                    <p style="color: var(--warning-text); font-size: 12px; line-height: 1.6;">
                        ⚠️ <strong>Warning:</strong> This will modify all documents. Test on a backup first!
                    </p>
                </div>
                <button type="submit" class="btn"
                    style="background: var(--accent-danger); color: var(--text-on-accent); width: 100%; padding: 10px; margin-top: 10px;">🔄
                    Transform Field</button>
            </form>
        </div>
    </div>

    <!-- Collection Analysis Section -->
    <div
        style="background: var(--card-bg); padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px var(--shadow-color); margin-bottom: 20px;">
        <h3 style="color: var(--text-primary); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">📊</span> Collection Analysis Tools
        </h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                <input type="hidden" name="action" value="findduplicates">
                <input type="hidden" name="duplicate_field" id="dup_field">
                <button type="button"
                    onclick="var field = prompt('Enter field name to check for duplicates:', 'email'); if(field) { document.getElementById('dup_field').value = field; this.form.submit(); }"
                    class="btn"
                    style="background: linear-gradient(135deg, var(--accent-primary) 0%, var(--accent-secondary) 100%); color: var(--text-on-accent); width: 100%; padding: 15px; font-size: 14px; font-weight: 600;">
                    🔍 Find Duplicates
                </button>
            </form>
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                <input type="hidden" name="action" value="orphanedfields">
                <button type="submit" class="btn"
                    style="background: linear-gradient(135deg, var(--gradient-pink-start) 0%, var(--gradient-pink-end) 100%); color: var(--text-on-accent); width: 100%; padding: 15px; font-size: 14px; font-weight: 600;">
                    🗑️ Find Orphaned Fields
                </button>
            </form>
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                <input type="hidden" name="action" value="dataintegrity">
                <button type="submit" class="btn"
                    style="background: linear-gradient(135deg, var(--gradient-green-start) 0%, var(--gradient-green-end) 100%); color: var(--text-on-accent); width: 100%; padding: 15px; font-size: 14px; font-weight: 600;">
                    ✓ Check Data Integrity
                </button>
            </form>
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                <input type="hidden" name="action" value="sizestats">
                <button type="submit" class="btn"
                    style="background: linear-gradient(135deg, var(--gradient-sunset-start) 0%, var(--gradient-sunset-end) 100%); color: var(--text-on-accent); width: 100%; padding: 15px; font-size: 14px; font-weight: 600;">
                    📏 Collection Size Stats
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Performance Tab -->
