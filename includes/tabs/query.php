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
                            <option value="equals">Equals (=)</option>
                            <option value="contains">Contains</option>
                            <option value="starts">Starts With</option>
                            <option value="ends">Ends With</option>
                            <option value="gt">Greater Than (&gt;)</option>
                            <option value="lt">Less Than (&lt;)</option>
                            <option value="gte">Greater or Equal (&ge;)</option>
                            <option value="lte">Less or Equal (&le;)</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #555;">Sort
                            By:</label>
                        <select name="sort"
                            style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px;">
                            <option value="_id">_id</option>
                            <option value="created_at">Created Date</option>
                            <option value="updated_at">Updated Date</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #555;">Limit:</label>
                        <input type="number" name="limit" value="50" min="1" max="1000"
                            style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #555;">Value
                            Type:</label>
                        <select name="value_type"
                            style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px;">
                            <option value="string" selected>String</option>
                            <option value="number">Number</option>
                            <option value="bool">Boolean</option>
                            <option value="null">Null</option>
                            <option value="objectid">ObjectId</option>
                            <option value="date">Date</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #555;">Sort
                            Order:</label>
                        <select name="sort_order"
                            style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px;">
                            <option value="desc" selected>Descending</option>
                            <option value="asc">Ascending</option>
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
                            <option value="desc" selected>Descending</option>
                            <option value="asc">Ascending</option>
                        </select>
                    </div>
                    <div>
                        <label
                            style="display: block; font-weight: 600; margin-bottom: 6px; color: #555; font-size: 13px;">Limit:</label>
                        <input type="number" name="limit" value="100" min="1" max="5000"
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
                        $limit = (int) ($_POST['limit'] ?? 50);

                        // Projection (comma-separated fields)
                        $projection = null;
                        $projectionRaw = trim((string) ($_POST['projection'] ?? ''));
                        if ($projectionRaw !== '') {
                            $fields = array_filter(array_map('trim', explode(',', $projectionRaw)));
                            $proj = [];
                            foreach ($fields as $f) {
                                $f = sanitizeInput($f);
                                if ($f !== '' && validateFieldName($f)) {
                                    $proj[$f] = 1;
                                }
                            }
                            if (!empty($proj)) {
                                $projection = $proj;
                            }
                        }

                        // Type-coerce value
                        $value = $rawValue;
                        switch ($valueType) {
                            case 'number':
                                if (!is_numeric($rawValue)) {
                                    throw new Exception('Value is not numeric');
                                }
                                $value = (strpos($rawValue, '.') !== false) ? (float) $rawValue : (int) $rawValue;
                                break;
                            case 'bool':
                                $v = strtolower(trim($rawValue));
                                $value = in_array($v, ['1', 'true', 'yes', 'y', 'on'], true);
                                break;
                            case 'null':
                                $value = null;
                                break;
                            case 'objectid':
                                $value = new MongoDB\BSON\ObjectId($rawValue);
                                break;
                            case 'date':
                                $ts = strtotime($rawValue);
                                if ($ts === false) {
                                    throw new Exception('Invalid date value');
                                }
                                $value = new MongoDB\BSON\UTCDateTime($ts * 1000);
                                break;
                            case 'string':
                            default:
                                $value = $rawValue;
                                break;
                        }

                        // Build MongoDB query
                        $mongoQuery = [];
                        switch ($operator) {
                            case 'equals':
                                $mongoQuery[$field] = $value;
                                break;
                            case 'contains':
                                $mongoQuery[$field] = ['$regex' => $rawValue, '$options' => 'i'];
                                break;
                            case 'starts':
                                $mongoQuery[$field] = ['$regex' => '^' . $rawValue, '$options' => 'i'];
                                break;
                            case 'ends':
                                $mongoQuery[$field] = ['$regex' => $rawValue . '$', '$options' => 'i'];
                                break;
                            case 'gt':
                                $mongoQuery[$field] = ['$gt' => $value];
                                break;
                            case 'lt':
                                $mongoQuery[$field] = ['$lt' => $value];
                                break;
                            case 'gte':
                                $mongoQuery[$field] = ['$gte' => $value];
                                break;
                            case 'lte':
                                $mongoQuery[$field] = ['$lte' => $value];
                                break;
                        }

                        $findOptions = [
                            'sort' => [$sortField => $sortOrder],
                            'limit' => $limit
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
                        $limit = (int) ($_POST['limit'] ?? 100);
                        if ($limit < 1) {
                            $limit = 1;
                        }
                        if ($limit > 5000) {
                            $limit = 5000;
                        }

                        // Projection (comma-separated fields)
                        $projection = null;
                        $projectionRaw = trim((string) ($_POST['projection'] ?? ''));
                        if ($projectionRaw !== '') {
                            $fields = array_filter(array_map('trim', explode(',', $projectionRaw)));
                            $proj = [];
                            foreach ($fields as $f) {
                                $f = sanitizeInput($f);
                                if ($f !== '' && validateFieldName($f)) {
                                    $proj[$f] = 1;
                                }
                            }
                            if (!empty($proj)) {
                                $projection = $proj;
                            }
                        }

                        $findOptions = ['limit' => $limit, 'sort' => [$sortField => $sortOrder]];
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
                            $docId = (string) $doc['_id'];
                            $docJson = json_encode($doc, JSON_PRETTY_PRINT);
                            echo '<tr data-json="' . htmlspecialchars($docJson) . '">';
                            echo '<td style="font-family: monospace; font-size: 12px;">' . htmlspecialchars($docId) . '</td>';
                            echo '<td><pre style="background: #f8f9fa; padding: 10px; border-radius: 6px; max-height: 200px; overflow-y: auto; font-size: 11px;">' . htmlspecialchars(substr($docJson, 0, 500)) . '...</pre></td>';
                            echo '<td>';
                            echo '<button type="button" class="btn" style="background-color: #6c757d; color: white; font-size: 11px; padding: 4px 8px;" onclick="viewDocument(\'' . htmlspecialchars($docId) . '\', event); return false;">View</button> ';
                            echo '<button type="button" class="btn btn-edit" style="font-size: 11px; padding: 4px 8px;" onclick="editDocument(\'' . htmlspecialchars($docId) . '\', event); return false;">Edit</button>';
                            echo '</td>';
                            echo '</tr>';
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
