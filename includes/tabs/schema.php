<div id="schema" class="tab-content">
    <h2 style="margin-bottom: 20px;">📐 Schema Explorer</h2>

    <div
        style="background: linear-gradient(135deg, var(--accent-primary)15 0%, var(--accent-secondary)15 100%); padding: 20px; border-radius: 12px; margin-bottom: 20px; border-left: 4px solid var(--accent-primary);">
        <p style="color: var(--text-secondary); line-height: 1.8;">
            <strong>💡 Info:</strong> Automatically detect and analyze the structure of your documents.
            Shows field types, frequencies, and nested structures.
        </p>
    </div>

    <?php
    // Analyze schema from sample documents
    $sampleSizeSetting = (int) getSetting('schema_sample_size', 100);
    $sampleSizeSetting = max(10, min(500, $sampleSizeSetting));
    $sampleSize = min($sampleSizeSetting, $documentCount);
    $sampleDocs = $collection->find([], ['limit' => $sampleSize])->toArray();

    $schemaAnalysis = [];
    foreach ($sampleDocs as $doc) {
        $docArray = json_decode(json_encode($doc), true);
        foreach ($docArray as $field => $value) {
            if (!isset($schemaAnalysis[$field])) {
                $schemaAnalysis[$field] = [
                    'count' => 0,
                    'types' => [],
                    'samples' => []
                ];
            }
            $schemaAnalysis[$field]['count']++;

            $type = gettype($value);
            if ($type === 'object' || $type === 'array') {
                $type = is_array($value) ? 'array' : 'object';
            }

            if (!in_array($type, $schemaAnalysis[$field]['types'])) {
                $schemaAnalysis[$field]['types'][] = $type;
            }

            if (count($schemaAnalysis[$field]['samples']) < 3) {
                $sampleValue = $value;
                if (is_array($sampleValue) || is_object($sampleValue)) {
                    $sampleValue = json_encode($sampleValue);
                    if (strlen($sampleValue) > 50) {
                        $sampleValue = substr($sampleValue, 0, 50) . '...';
                    }
                } else {
                    $sampleValue = (string) $sampleValue;
                    if (strlen($sampleValue) > 50) {
                        $sampleValue = substr($sampleValue, 0, 50) . '...';
                    }
                }
                $schemaAnalysis[$field]['samples'][] = $sampleValue;
            }
        }
    }

    // Sort by frequency
    uasort($schemaAnalysis, function ($a, $b) {
        return $b['count'] - $a['count'];
    });
    ?>

    <div style="background: var(--card-bg); padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
            <h3 style="color: var(--text-primary); display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">🔍</span> Detected Fields
            </h3>
            <span
                style="background: var(--accent-primary); color: var(--text-on-accent); padding: 8px 16px; border-radius: 20px; font-size: 14px; font-weight: 600;">
                <?php echo count($schemaAnalysis); ?> fields found
            </span>
        </div>

        <div style="display: grid; gap: 15px;">
            <?php foreach ($schemaAnalysis as $fieldName => $fieldInfo): ?>
                <div style="background: linear-gradient(135deg, var(--surface-muted) 0%, var(--surface-muted) 100%); padding: 20px; border-radius: 10px; border-left: 4px solid var(--accent-primary); transition: all 0.3s;"
                    onmouseover="this.style.transform='translateX(5px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'"
                    onmouseout="this.style.transform='translateX(0)'; this.style.boxShadow='none'">
                    <?php echo renderSchemaField($fieldName, $fieldInfo, $sampleSize); ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($schemaAnalysis)): ?>
            <div style="text-align: center; padding: 60px 20px; color: var(--text-muted);">
                <div style="font-size: 64px; margin-bottom: 20px;">📭</div>
                <p style="font-size: 18px; color: var(--text-secondary);">No documents found to analyze</p>
                <p style="font-size: 14px; color: var(--text-muted); margin-top: 10px;">Add some documents to see the schema
                    structure
                </p>
            </div>
        <?php endif; ?>
    </div>

    <div
        style="background: var(--card-bg); padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-top: 20px;">
        <h3 style="margin-bottom: 20px; color: var(--text-primary); display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">📊</span> Schema Statistics
        </h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <div
                style="background: linear-gradient(135deg, var(--accent-primary)15 0%, var(--accent-secondary)15 100%); padding: 20px; border-radius: 8px; border: 2px solid var(--accent-primary);">
                <p style="color: var(--text-secondary); font-size: 13px;">Total Fields</p>
                <p style="font-size: 32px; font-weight: bold; color: var(--accent-primary); margin-top: 8px;">
                    <?php echo count($schemaAnalysis); ?>
                </p>
            </div>
            <div
                style="background: linear-gradient(135deg, var(--gradient-pink-start)15 0%, var(--gradient-pink-end)15 100%); padding: 20px; border-radius: 8px; border: 2px solid var(--gradient-pink-end);">
                <p style="color: var(--text-secondary); font-size: 13px;">Analyzed Docs</p>
                <p style="font-size: 32px; font-weight: bold; color: var(--gradient-pink-end); margin-top: 8px;">
                    <?php echo $sampleSize; ?>
                </p>
            </div>
            <div
                style="background: linear-gradient(135deg, var(--gradient-green-start)15 0%, var(--gradient-green-end)15 100%); padding: 20px; border-radius: 8px; border: 2px solid var(--gradient-green-start);">
                <p style="color: var(--text-secondary); font-size: 13px;">Collection</p>
                <p style="font-size: 20px; font-weight: bold; color: var(--gradient-green-start); margin-top: 8px;">
                    <?php echo htmlspecialchars($collectionName); ?>
                </p>
            </div>
        </div>
    </div>

    <div
        style="background: var(--card-bg); padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-top: 20px;">
        <h3 style="margin-bottom: 20px; color: var(--text-primary); display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">📋</span> Collection Indexes
        </h3>
        <?php
        try {
            if (method_exists($collection, 'listIndexes')) {
                $indexes = $collection->listIndexes();
                $hasIndexes = false;
                foreach ($indexes as $index) {
                    $hasIndexes = true;
                    $indexName = isset($index['name']) ? $index['name'] : 'Unknown';
                    $indexKeys = isset($index['key']) ? $index['key'] : [];
                    echo '<div style="background: linear-gradient(135deg, var(--accent-primary)15 0%, var(--accent-secondary)15 100%); padding: 15px; border-radius: 8px; margin-bottom: 12px; border-left: 4px solid var(--accent-primary);">';
                    echo '<p style="font-weight: 600; margin-bottom: 8px; color: var(--text-primary); font-size: 15px;">📌 ' . htmlspecialchars($indexName) . '</p>';
                    echo '<div style="background: var(--card-bg); padding: 10px; border-radius: 4px; margin-top: 8px;">';
                    echo renderIndexKeys($indexKeys);
                    echo '</div>';
                    echo '</div>';
                }
                if (!$hasIndexes) {
                    echo '<p style="color: var(--text-secondary); text-align: center; padding: 20px;">No indexes found</p>';
                }
            } else {
                echo '<p style="color: var(--text-secondary); text-align: center; padding: 20px;">Index management not available</p>';
            }
        } catch (Exception $e) {
            echo '<p style="color: var(--text-secondary); text-align: center; padding: 20px;">Unable to load indexes</p>';
        }
        ?>
    </div>
</div>

<!-- Security & Backup Tab -->
