    <div id="add" class="tab-content">
        <h2 style="margin-bottom: 20px;">➕ Add New Document</h2>

        <?php
        // Show available templates
        try {
            $templatesCollection = $database->getCollection('_templates');
            $availableTemplates = $templatesCollection->find(['user_collection' => $collectionName])->toArray();

            if (!empty($availableTemplates)):
                ?>
                <div
                    style="background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%); padding: 20px; border-radius: 12px; margin-bottom: 20px; border-left: 4px solid #667eea;">
                    <h3
                        style="color: #333; margin-bottom: 15px; font-size: 16px; display: flex; align-items: center; gap: 8px;">
                        <span>📚</span> Quick Start with Templates
                    </h3>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <?php foreach ($availableTemplates as $template): ?>
                            <button type="button" class="btn"
                                onclick="loadTemplate('<?php echo htmlspecialchars(json_encode($template->data), ENT_QUOTES); ?>'); return false;"
                                style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 10px 18px;">
                                📄 <?php echo htmlspecialchars($template->name); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <p style="color: #666; font-size: 13px; margin-top: 12px;">
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
            <form method="POST" style="background: white; padding: 20px; border-radius: 8px;">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                <div class="form-group">
                    <label style="display: flex; justify-content: space-between; align-items: center;">
                        <span>JSON Data:</span>
                        <button type="button"
                            onclick="switchTab('advanced', document.querySelectorAll('.tab-btn')[6]); return false;"
                            class="btn" style="background: #6c757d; color: white; padding: 6px 12px; font-size: 12px;">
                            💾 Manage Templates
                        </button>
                    </label>
                    <textarea name="json_data" placeholder="Paste JSON here..." required
                        style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 6px; font-family: 'Courier New', monospace; min-height: 250px;">{"key": "value"}</textarea>
                </div>
                <button type="submit" class="btn"
                    style="background: #28a745; color: white; width: 100%; padding: 12px;">✅ Add Document</button>
            </form>
        </div>
    </div>

    <!-- Bulk Operations Tab -->
