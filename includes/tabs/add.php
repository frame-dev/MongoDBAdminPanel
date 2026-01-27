<div id="add" class="tab-content">
    <h2 style="margin-bottom: 20px;">➕ Add New Document</h2>

    <?php if (!userHasPermission('create_data')): ?>
        <div class="alert alert-warning">
            <span class="alert-icon">⚠️</span>
            <span class="alert-text">You don't have permission to add documents. Contact an administrator.</span>
        </div>
    <?php else:
    try {
        $templatesCollection = $database->getCollection('_templates');
        $availableTemplates = $templatesCollection->find(['user_collection' => $collectionName])->toArray();

        if (!empty($availableTemplates)):
            ?>
            <div
                style="
                    background: rgba(102,126,234,0.10);
                    padding: 20px;
                    border-radius: 12px;
                    margin-bottom: 20px;
                    border-left: 4px solid var(--input-focus-border);
                    color: var(--text-primary);
                ">
                <h3 style="margin-bottom: 15px; font-size: 16px; display: flex; align-items: center; gap: 8px; color: var(--text-primary);">
                    <span>📚</span> Quick Start with Templates
                </h3>

                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <?php foreach ($availableTemplates as $template): ?>
                        <button type="button" class="btn"
                            onclick="loadTemplate('<?php echo htmlspecialchars(json_encode($template->data), ENT_QUOTES); ?>'); return false;"
                            style="background: linear-gradient(135deg, var(--bg-gradient-start) 0%, var(--bg-gradient-end) 100%); color: var(--text-on-accent); padding: 10px 18px;">
                            📄 <?php echo htmlspecialchars($template->name); ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <p style="font-size: 13px; margin-top: 12px; color: var(--text-secondary);">
                    💡 Click a template to load it into the editor below
                </p>
            </div>
            <?php
        endif;
    } catch (Exception $e) {
        // Silently fail if templates collection doesn't exist
    }
    ?>

    <div style="max-width: 800px;">
        <form method="POST"
              style="background: var(--card-bg); border: 1px solid var(--card-border); padding: 20px; border-radius: 8px;">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">

            <div class="form-group">
                <label style="display: flex; justify-content: space-between; align-items: center;">
                    <span>JSON Data:</span>
                    <button type="button"
                        onclick="switchTab('advanced', document.querySelectorAll('.tab-btn')[6]); return false;"
                        class="btn"
                        style="background: var(--table-header-bg); color: var(--text-primary); padding: 6px 12px; font-size: 12px; border: 1px solid var(--table-border);">
                        💾 Manage Templates
                    </button>
                </label>

                <textarea name="json_data" placeholder="Paste JSON here..." required
                    style="
                        width: 100%;
                        padding: 12px;
                        border: 2px solid var(--input-border);
                        border-radius: 6px;
                        font-family: 'Courier New', monospace;
                        min-height: 250px;
                        background: var(--input-bg);
                        color: var(--text-primary);
                    ">{"key": "value"}</textarea>
            </div>

            <button type="submit" class="btn"
                style="background: var(--accent-success); color: var(--text-on-accent); width: 100%; padding: 12px;">
                ✅ Add Document
            </button>
        </form>
    </div>
</div>
<?php endif; ?>