    <?php
    $querySortFields = $detectedFields ?? ['_id', 'created_at', 'updated_at'];
    $querySortFields = array_values(array_filter($querySortFields, function ($field) {
        return $field !== '_id';
    }));
    $currentSortOrder = $_POST['sort_order'] ?? 'desc';
    $maxResultsSetting = (int) getSetting('max_results', 1000);
    $maxResultsSetting = max(100, min(10000, $maxResultsSetting));
    $defaultQueryLimit = (int) getSetting('query_default_limit', 50);
    $defaultQueryLimit = max(1, min($maxResultsSetting, $defaultQueryLimit));
    $queryTimeoutMs = (int) getSetting('query_timeout', 30) * 1000;
    $queryTimeoutMs = max(5000, min(300000, $queryTimeoutMs));
    ?>
    <div id="query" class="tab-content">
        <h2 style="margin-bottom: 20px;">🔍 Advanced Query Builder</h2>
        <div
            style="background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%); padding: 20px; border-radius: 12px; margin-bottom: 20px; border-left: 4px solid #667eea;">
            <p style="color: #666; line-height: 1.8;">
                <strong>💡 Tip:</strong> Build complex MongoDB queries visually or write custom JSON queries.
                Supports filtering, sorting, projection, and aggregation pipelines.
            </p>
        </div>

        <div
            style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 20px;">
            <h3 style="margin-bottom: 20px; color: #333;">🎯 Quick Query</h3>
            <form method="POST" id="quickQueryForm"
                style="display: grid; gap: 15px;">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #555;">Field
                            Name:</label>
                        <input type="text" name="query_field" placeholder="e.g., email, status, name"
                            style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #555;">Field
                            Value:</label>
                        <input type="text" name="query_value" placeholder="Search value"
                            style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px;">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                    <div>
                        <label
                            style="display: block; font-weight: 600; margin-bottom: 8px; color: #555;">Operator:</label>
                        <select name="query_op"
                            style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px;">
                            <?php echo generateOperatorOptions(); ?>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #555;">Sort
                            By:</label>
                        <select name="sort"
                            style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px;">
                            <?php echo generateFieldOptions($querySortFields, '_id'); ?>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #555;">Limit:</label>
                        <input type="number" name="limit" value="<?php echo $defaultQueryLimit; ?>" min="1" max="<?php echo $maxResultsSetting; ?>"
                            style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #555;">Value
                            Type:</label>
                        <select name="value_type"
                            style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px;">
                            <?php echo generateValueTypeOptions(); ?>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #555;">Sort
                            Order:</label>
                        <select name="sort_order"
                            style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px;">
                            <?php echo generateSortOrderOptions($currentSortOrder); ?>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #555;">Projection
                            (fields):</label>
                        <input type="text" name="projection" placeholder="e.g., email,status,name"
                            style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px;">
                    </div>
                </div>
                <button type="button" class="btn" onclick="executeQuickQuery(); return false;"
                    style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px; font-size: 16px; width: 100%;">
                    🔍 Execute Query
                </button>
            </form>
        </div>

        <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="margin-bottom: 20px; color: #333;">📝 Custom JSON Query</h3>
            <p style="color: #666; margin-bottom: 15px; font-size: 14px;">Write a MongoDB query in JSON format (e.g.,
                <code>{"status": "active", "age": {"$gt": 18}}</code>)
            </p>
            <form method="POST" id="customQueryForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div>
                        <label
                            style="display: block; font-weight: 600; margin-bottom: 6px; color: #555; font-size: 13px;">Sort
                            By:</label>
                        <select name="sort"
                            style="width: 100%; padding: 10px 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 13px;">
                            <option value="_id">_id</option>
                            <option value="created_at">Created Date</option>
                            <option value="updated_at">Updated Date</option>
                        </select>
                    </div>
                    <div>
                        <label
                            style="display: block; font-weight: 600; margin-bottom: 6px; color: #555; font-size: 13px;">Sort
                            Order:</label>
                        <select name="sort_order"
                            style="width: 100%; padding: 10px 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 13px;">
                            <?php echo generateSortOrderOptions($currentSortOrder); ?>
                        </select>
                    </div>
                    <div>
                        <label
                            style="display: block; font-weight: 600; margin-bottom: 6px; color: #555; font-size: 13px;">Limit:</label>
                        <input type="number" name="limit" value="<?php echo $defaultQueryLimit; ?>" min="1" max="<?php echo $maxResultsSetting; ?>"
                            style="width: 100%; padding: 10px 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 13px;">
                    </div>
                    <div>
                        <label
                            style="display: block; font-weight: 600; margin-bottom: 6px; color: #555; font-size: 13px;">Projection:</label>
                        <input type="text" name="projection" placeholder="email,status,name"
                            style="width: 100%; padding: 10px 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 13px;">
                    </div>
                </div>
                <textarea name="custom_query" placeholder='{"field": "value"}'
                    style="width: 100%; padding: 15px; border: 2px solid #ddd; border-radius: 8px; font-family: 'Courier New', monospace; min-height: 150px; font-size: 13px; background: #f8f9fa;"></textarea>
                <button type="button" class="btn" onclick="executeCustomQuery(); return false;"
                    style="background: #17a2b8; color: white; padding: 12px 24px; margin-top: 15px;">
                    ⚡ Run Custom Query
                </button>
            </form>
        </div>

        <?php if (isset($_POST['action']) && ($_POST['action'] === 'execute_query' || $_POST['action'] === 'execute_custom_query')): ?>
            <div id="query_results" class="query-result"
                style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-top: 20px;">
                <h3 style="margin-bottom: 20px; color: #333;">📊 Query Results</h3>

                <?php
                try {
                    $queryResults = [];

                    if ($_POST['action'] === 'execute_query') {
                        // Quick Query execution
                        $field = sanitizeInput($_POST['query_field'] ?? '');
                        $rawValue = (string) ($_POST['query_value'] ?? '');
                        $operator = $_POST['query_op'] ?? 'equals';
                        $valueType = $_POST['value_type'] ?? 'string';
                        $sortField = sanitizeInput($_POST['sort'] ?? '_id');
                        $sortOrder = ($_POST['sort_order'] ?? 'desc') === 'asc' ? 1 : -1;
                        $limit = (int) ($_POST['limit'] ?? $defaultQueryLimit);
                        $limit = max(1, min($maxResultsSetting, $limit));

                        // Projection (comma-separated fields)
                        $projectionRaw = trim((string) ($_POST['projection'] ?? ''));
                        $projection = parseProjectionFields($projectionRaw);

                        // Type-coerce value
                        $value = coerceValueByType($rawValue, $valueType);

                        // Build MongoDB query
                        $mongoQuery = buildMongoQueryByOperator($field, $operator, $value, $rawValue);

                        $findOptions = [
                            'sort' => [$sortField => $sortOrder],
                            'limit' => $limit,
                            'maxTimeMS' => $queryTimeoutMs
                        ];
                        if ($projection) {
                            $findOptions['projection'] = $projection;
                        }

                        $queryResults = $collection->find($mongoQuery, $findOptions)->toArray();

                        echo '<p style="color: #666; margin-bottom: 15px;"><strong>Query:</strong> Field: ' . htmlspecialchars($field) . ' | Operator: ' . htmlspecialchars($operator) . ' | Value: ' . htmlspecialchars($rawValue) . ' | Type: ' . htmlspecialchars($valueType) . ' | Sort: ' . htmlspecialchars($sortField) . ' ' . ($sortOrder === 1 ? 'ASC' : 'DESC') . ' | Limit: ' . htmlspecialchars((string) $limit) . '</p>';
                        if ($projectionRaw !== '') {
                            echo '<p style="color: #666; margin-bottom: 15px;"><strong>Projection:</strong> ' . htmlspecialchars($projectionRaw) . '</p>';
                        }
                    } else {
                        // Custom JSON Query execution
                        $customQuery = $_POST['custom_query'] ?? '{}';

                        if (!validateJSON($customQuery)) {
                            throw new Exception('Invalid JSON or dangerous patterns detected');
                        }

                        $query = json_decode($customQuery, true);
                        $sanitizedQuery = sanitizeMongoQuery($query);

                        $sortField = sanitizeInput($_POST['sort'] ?? '_id');
                        $sortOrder = ($_POST['sort_order'] ?? 'desc') === 'asc' ? 1 : -1;
                        $limit = (int) ($_POST['limit'] ?? $defaultQueryLimit);
                        $limit = max(1, min($maxResultsSetting, $limit));

                        // Projection (comma-separated fields)
                        $projectionRaw = trim((string) ($_POST['projection'] ?? ''));
                        $projection = parseProjectionFields($projectionRaw);

                        $findOptions = [
                            'limit' => $limit,
                            'sort' => [$sortField => $sortOrder],
                            'maxTimeMS' => $queryTimeoutMs
                        ];
                        if ($projection) {
                            $findOptions['projection'] = $projection;
                        }

                        $queryResults = $collection->find($sanitizedQuery, $findOptions)->toArray();

                        echo '<p style="color: #666; margin-bottom: 15px;"><strong>Custom Query:</strong> Sort: ' . htmlspecialchars($sortField) . ' ' . ($sortOrder === 1 ? 'ASC' : 'DESC') . ' | Limit: ' . htmlspecialchars((string) $limit) . '</p>';
                        if ($projectionRaw !== '') {
                            echo '<p style="color: #666; margin-bottom: 15px;"><strong>Projection:</strong> ' . htmlspecialchars($projectionRaw) . '</p>';
                        }
                        echo '<pre style="background: #f8f9fa; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: 12px; color: #333;">' . htmlspecialchars($customQuery) . '</pre>';
                    }

                    echo '<p style="color: #28a745; font-weight: 600; margin: 15px 0;"> Found ' . count($queryResults) . ' document(s)</p>';

                    // Add to query history
                    $historyEntry = [
                        'type' => $_POST['action'] === 'execute_query' ? 'visual' : 'custom',
                        'query' => $_POST['action'] === 'execute_query' 
                            ? ['field' => $_POST['query_field'] ?? '', 'op' => $_POST['query_op'] ?? '', 'value' => $_POST['query_value'] ?? '']
                            : ['custom' => $_POST['custom_query'] ?? ''],
                        'results_count' => count($queryResults),
                        'status' => 'success'
                    ];
                    addToQueryHistory($historyEntry);

                    // Export buttons
                    if (!empty($queryResults)) {
                        echo '<div style="display:flex; gap:10px; flex-wrap:wrap; margin: 10px 0 0 0;">';
                        echo '<form method="POST" style="display:inline;">';
                        echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCSRFToken()) . '">';
                        echo '<input type="hidden" name="collection" value="' . htmlspecialchars($collectionName) . '">';
                        echo '<input type="hidden" name="action" value="export_query_json">';
                        foreach (['query_field', 'query_value', 'query_op', 'value_type', 'custom_query', 'sort', 'sort_order', 'limit', 'projection'] as $k) {
                            if (isset($_POST[$k])) {
                                echo '<input type="hidden" name="' . htmlspecialchars($k) . '" value="' . htmlspecialchars((string) $_POST[$k]) . '">';
                            }
                        }
                        echo '<button type="submit" class="btn" style="background:#343a40;color:#fff; padding:8px 12px; font-size:12px;">⬇️ Export JSON</button>';
                        echo '</form>';

                        echo '<form method="POST" style="display:inline;">';
                        echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCSRFToken()) . '">';
                        echo '<input type="hidden" name="collection" value="' . htmlspecialchars($collectionName) . '">';
                        echo '<input type="hidden" name="action" value="export_query_csv">';
                        foreach (['query_field', 'query_value', 'query_op', 'value_type', 'custom_query', 'sort', 'sort_order', 'limit', 'projection'] as $k) {
                            if (isset($_POST[$k])) {
                                echo '<input type="hidden" name="' . htmlspecialchars($k) . '" value="' . htmlspecialchars((string) $_POST[$k]) . '">';
                            }
                        }
                        echo '<button type="submit" class="btn" style="background:#198754;color:#fff; padding:8px 12px; font-size:12px;">⬇️ Export CSV</button>';
                        echo '</form>';
                        echo '</div>';
                    }

                    if (!empty($queryResults)) {
                        echo '<table class="data-table" style="margin-top: 20px;">';
                        echo '<thead><tr><th>Document ID</th><th>Data</th><th>Actions</th></tr></thead>';
                        echo '<tbody>';

                        foreach ($queryResults as $doc) {
                            echo generateDocumentTableRow($doc);
                        }

                        echo '</tbody></table>';
                    }
                } catch (Exception $e) {
                    echo '<div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 6px; border: 1px solid #f5c6cb;">';
                    echo '❌ Error: ' . htmlspecialchars($e->getMessage());
                    echo '</div>';
                }
                ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Browse Tab -->
