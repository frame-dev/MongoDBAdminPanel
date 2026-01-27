<div id="stats" class="tab-content">
    <h2 style="color: var(--text-primary); margin-bottom: 20px;">📊 Analytics & Statistics</h2>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
        <div
            style="background: linear-gradient(135deg, var(--accent-primary) 0%, var(--accent-secondary) 100%); color: var(--text-on-accent); padding: 20px; border-radius: 8px;">
            <p style="opacity: 0.9;">Total Documents</p>
            <p style="font-size: 32px; font-weight: bold; margin: 10px 0;">
                <?php echo number_format($documentCount); ?>
            </p>
        </div>
        <div
            style="background: linear-gradient(135deg, var(--gradient-pink-start) 0%, var(--gradient-pink-end) 100%); color: var(--text-on-accent); padding: 20px; border-radius: 8px;">
            <p style="opacity: 0.9;">Total Storage</p>
            <p style="font-size: 32px; font-weight: bold; margin: 10px 0;">
                <?php echo number_format($totalSize / 1024 / 1024, 2); ?> MB
            </p>
        </div>
        <div
            style="background: linear-gradient(135deg, var(--gradient-sky-start) 0%, var(--gradient-sky-end) 100%); color: var(--text-on-accent); padding: 20px; border-radius: 8px;">
            <p style="opacity: 0.9;">Avg Doc Size</p>
            <p style="font-size: 32px; font-weight: bold; margin: 10px 0;">
                <?php echo number_format($avgDocSize / 1024, 2); ?> KB
            </p>
        </div>
        <div
            style="background: linear-gradient(135deg, var(--gradient-green-start) 0%, var(--gradient-green-end) 100%); color: var(--text-on-accent); padding: 20px; border-radius: 8px;">
            <p style="opacity: 0.9;">Connected Collections</p>
            <p style="font-size: 32px; font-weight: bold; margin: 10px 0;"><?php echo count($collectionNames); ?>
            </p>
        </div>
    </div>

    <div
        style="background: var(--card-bg); padding: 20px; border-radius: 8px; margin-top: 20px; box-shadow: 0 2px 8px var(--shadow-color);">
        <h3 style="color: var(--text-primary);">Collections in Database</h3>
        <div style="display: flex; flex-wrap: wrap; gap: 10px;">
            <?php foreach ($collectionNames as $collName): ?>
                <span
                    style="background: var(--table-header-bg); color: var(--text-primary); padding: 8px 12px; border-radius: 20px; border: 1px solid var(--table-border);">
                    📦 <?php echo htmlspecialchars($collName); ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Data Visualization -->
    <div
        style="background: var(--card-bg); padding: 25px; border-radius: 12px; margin-top: 20px; box-shadow: 0 4px 15px var(--shadow-color);">
        <h3 style="color: var(--text-primary); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">📊</span> Data Visualization
        </h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
            <input type="hidden" name="action" value="visualize_data">
            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 12px; margin-bottom: 20px;">
                <div>
                    <label style="color: var(--text-secondary); font-weight: 600; font-size: 13px;">Group By
                        Field:</label>
                    <input type="text" name="viz_field" placeholder="e.g., status, category, type" required
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px; font-size: 14px;">
                </div>
                <div>
                    <label style="color: var(--text-secondary); font-weight: 600; font-size: 13px;">Chart
                        Type:</label>
                    <select name="viz_type"
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px; font-size: 14px;">
                        <option value="bar">📊 Bar Chart</option>
                        <option value="pie">🥧 Pie Chart</option>
                        <option value="list">📋 List View</option>
                    </select>
                </div>
                <div>
                    <label style="color: var(--text-secondary); font-weight: 600; font-size: 13px;">Max
                        Items:</label>
                    <input type="number" name="viz_limit" value="10" min="5" max="50"
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px; font-size: 14px;">
                </div>
                <div style="display: flex; align-items: end;">
                    <button type="submit" class="btn"
                        style="background: linear-gradient(135deg, var(--accent-primary) 0%, var(--accent-secondary) 100%); color: var(--text-on-accent); padding: 10px 20px; font-weight: 600;">📈
                        Visualize</button>
                </div>
            </div>
        </form>

        <?php if (isset($_SESSION['viz_data'])): ?>
            <?php $vizData = $_SESSION['viz_data']; ?>
            <div
                style="padding: 25px; background: var(--table-header-bg); border-radius: 10px; margin-top: 20px; border: 1px solid var(--table-border);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h4 style="color: var(--text-primary);">Results for
                        "<?php echo htmlspecialchars($vizData['field']); ?>"
                    </h4>
                    <span
                        style="background: var(--accent-primary); color: var(--text-on-accent); padding: 6px 16px; border-radius: 20px; font-size: 14px; font-weight: 600;">Total:
                        <?php echo $vizData['total']; ?></span>
                </div>
                <div style="display: grid; gap: 12px;">
                    <?php foreach ($vizData['results'] as $item): ?>
                        <?php $percentage = $vizData['total'] > 0 ? ($item['count'] / $vizData['total'] * 100) : 0; ?>
                        <div style="background: var(--card-bg); padding: 18px; border-radius: 10px; box-shadow: 0 2px 8px var(--shadow-color); transition: all 0.3s;"
                            onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.15)'; this.style.transform='translateY(-2px)'"
                            onmouseout="this.style.boxShadow='0 2px 8px var(--shadow-color)'; this.style.transform='translateY(0)'">
                            <div
                                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <span
                                    style="font-weight: 600; color: var(--text-primary); font-size: 15px;"><?php echo htmlspecialchars((string) $item['_id']); ?></span>
                                <span
                                    style="background: linear-gradient(135deg, var(--accent-primary) 0%, var(--accent-secondary) 100%); color: var(--text-on-accent); padding: 6px 14px; border-radius: 16px; font-size: 13px; font-weight: bold; box-shadow: 0 2px 6px rgba(102,126,234,0.3);"><?php echo number_format($item['count']); ?></span>
                            </div>
                            <div
                                style="background: var(--table-border); height: 12px; border-radius: 6px; overflow: hidden; box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);">
                                <div
                                    style="background: linear-gradient(90deg, var(--accent-primary) 0%, var(--accent-secondary) 100%); height: 100%; width: <?php echo $percentage; ?>%; transition: width 0.5s ease-out; box-shadow: 0 0 10px rgba(102,126,234,0.5);">
                                </div>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-top: 8px;">
                                <span
                                    style="font-size: 12px; color: var(--text-secondary);"><?php echo number_format($percentage, 1); ?>%
                                    of total</span>
                                <span
                                    style="font-size: 12px; color: var(--text-secondary); font-weight: 600;"><?php echo number_format($item['count']); ?>
                                    documents</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php unset($_SESSION['viz_data']); ?>
        <?php endif; ?>
    </div>

    <!-- Time Series Analysis -->
    <div
        style="background: var(--card-bg); padding: 25px; border-radius: 12px; margin-top: 20px; box-shadow: 0 4px 15px var(--shadow-color);">
        <h3 style="color: var(--text-primary); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">📅</span> Time Series Analysis
        </h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
            <input type="hidden" name="action" value="timeseries">
            <div style="display: grid; grid-template-columns: 2fr 1fr auto; gap: 12px; margin-bottom: 20px;">
                <div>
                    <label style="color: var(--text-secondary); font-weight: 600; font-size: 13px;">Date
                        Field:</label>
                    <input type="text" name="date_field" placeholder="e.g., createdAt, timestamp" required
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px; font-size: 14px;">
                </div>
                <div>
                    <label style="color: var(--text-secondary); font-weight: 600; font-size: 13px;">Group
                        By:</label>
                    <select name="time_group"
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px; font-size: 14px;">
                        <option value="day">📆 Day</option>
                        <option value="week">📅 Week</option>
                        <option value="month" selected>📊 Month</option>
                        <option value="year">📈 Year</option>
                    </select>
                </div>
                <div style="display: flex; align-items: end;">
                    <button type="submit" class="btn"
                        style="background: linear-gradient(135deg, var(--gradient-pink-start) 0%, var(--gradient-pink-end) 100%); color: var(--text-on-accent); padding: 10px 20px; font-weight: 600;">📅
                        Analyze</button>
                </div>
            </div>
        </form>

        <?php if (isset($_SESSION['timeseries_data'])): ?>
            <?php $tsData = $_SESSION['timeseries_data']; ?>
            <div style="padding: 25px; background: var(--table-header-bg); border-radius: 10px; margin-top: 20px; border: 1px solid var(--table-border);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h4 style="color: var(--text-primary);">Time Series: "<?php echo htmlspecialchars($tsData['field']); ?>" (Grouped by <?php echo htmlspecialchars($tsData['grouping']); ?>)</h4>
                </div>
                <div style="display: grid; gap: 12px;">
                    <?php foreach ($tsData['results'] as $item): ?>
                        <div style="background: var(--card-bg); padding: 15px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-weight: 600; color: var(--text-primary);"><?php echo htmlspecialchars($item['_id']); ?></span>
                            <span style="background: linear-gradient(135deg, var(--gradient-pink-start) 0%, var(--gradient-pink-end) 100%); color: var(--text-on-accent); padding: 6px 14px; border-radius: 16px; font-weight: bold;"><?php echo number_format($item['count']); ?> docs</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php unset($_SESSION['timeseries_data']); ?>
        <?php endif; ?>
    </div>

    <!-- Field Correlation Analysis -->
    <div
        style="background: var(--card-bg); padding: 25px; border-radius: 12px; margin-top: 20px; box-shadow: 0 4px 15px var(--shadow-color);">
        <h3 style="color: var(--text-primary); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">🔗</span> Field Correlation Analysis
        </h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
            <input type="hidden" name="action" value="correlation">
            <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 12px;">
                <div>
                    <label style="color: var(--text-secondary); font-weight: 600; font-size: 13px;">Field 1:</label>
                    <input type="text" name="field1" placeholder="e.g., status" required
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px;">
                </div>
                <div>
                    <label style="color: var(--text-secondary); font-weight: 600; font-size: 13px;">Field 2:</label>
                    <input type="text" name="field2" placeholder="e.g., priority" required
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px;">
                </div>
                <div style="display: flex; align-items: end;">
                    <button type="submit" class="btn"
                        style="background: linear-gradient(135deg, var(--gradient-sky-start) 0%, var(--gradient-sky-end) 100%); color: var(--text-on-accent); padding: 10px 20px; font-weight: 600;">🔗
                        Correlate</button>
                </div>
            </div>
        </form>

        <?php if (isset($_SESSION['correlation_data'])): ?>
            <?php $corrData = $_SESSION['correlation_data']; ?>
            <div style="padding: 25px; background: var(--table-header-bg); border-radius: 10px; margin-top: 20px; border: 1px solid var(--table-border);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h4 style="color: var(--text-primary);">Correlation: "<?php echo htmlspecialchars($corrData['field1']); ?>" × "<?php echo htmlspecialchars($corrData['field2']); ?>"</h4>
                    <span style="background: var(--gradient-sky-start); color: var(--text-on-accent); padding: 6px 16px; border-radius: 20px; font-size: 14px; font-weight: 600;"><?php echo count($corrData['results']); ?> combinations</span>
                </div>
                <div style="display: grid; gap: 10px;">
                    <?php foreach ($corrData['results'] as $item): ?>
                        <div style="background: var(--card-bg); padding: 15px; border-radius: 8px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <span style="font-weight: 600; color: var(--accent-primary);"><?php echo htmlspecialchars(json_encode($item['_id']['field1'])); ?></span>
                                    <span style="color: var(--text-secondary); margin: 0 8px;">×</span>
                                    <span style="font-weight: 600; color: var(--gradient-pink-end);"><?php echo htmlspecialchars(json_encode($item['_id']['field2'])); ?></span>
                                </div>
                                <span style="background: var(--accent-info); color: var(--text-on-accent); padding: 6px 12px; border-radius: 12px; font-size: 13px; font-weight: bold;"><?php echo number_format($item['count']); ?> occurrences</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php unset($_SESSION['correlation_data']); ?>
        <?php endif; ?>
    </div>

    <!-- Data Quality Metrics -->
    <div
        style="background: var(--card-bg); padding: 25px; border-radius: 12px; margin-top: 20px; box-shadow: 0 4px 15px var(--shadow-color);">
        <h3 style="color: var(--text-primary); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">✓</span> Data Quality Metrics
        </h3>
        <?php
        // Calculate data quality metrics
        $qualityMetrics = [
            'total_docs' => $documentCount,
            'empty_docs' => 0,
            'null_fields' => 0,
            'missing_fields' => []
        ];

        // Sample documents for quality check
        $qualitySample = $collection->find([], ['limit' => min(100, $documentCount)])->toArray();
        foreach ($qualitySample as $doc) {
            $docArray = json_decode(json_encode($doc), true);
            if (count($docArray) <= 1) { // Only _id
                $qualityMetrics['empty_docs']++;
            }
            foreach ($docArray as $key => $value) {
                if ($value === null || $value === '') {
                    $qualityMetrics['null_fields']++;
                }
            }
        }

        $completeness = $qualityMetrics['total_docs'] > 0
            ? (($qualityMetrics['total_docs'] - $qualityMetrics['empty_docs']) / $qualityMetrics['total_docs'] * 100)
            : 100;
        ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <div
                style="background: linear-gradient(135deg, var(--gradient-green-start)15 0%, var(--gradient-green-end)15 100%); padding: 20px; border-radius: 10px; border-left: 4px solid var(--gradient-green-start);">
                <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 8px;">Data Completeness</p>
                <p style="font-size: 32px; font-weight: bold; color: var(--gradient-green-start); margin-bottom: 8px;">
                    <?php echo number_format($completeness, 1); ?>%
                </p>
                <div style="background: var(--table-border); height: 8px; border-radius: 4px; overflow: hidden;">
                    <div
                        style="background: linear-gradient(90deg, var(--gradient-green-start) 0%, var(--gradient-green-end) 100%); height: 100%; width: <?php echo $completeness; ?>%; transition: width 0.5s;">
                    </div>
                </div>
            </div>
            <div
                style="background: linear-gradient(135deg, var(--accent-primary)15 0%, var(--accent-secondary)15 100%); padding: 20px; border-radius: 10px; border-left: 4px solid var(--accent-primary);">
                <p style="color: var(--text-secondary); font-size: 13px;">Empty Documents</p>
                <p style="font-size: 32px; font-weight: bold; color: var(--accent-primary);">
                    <?php echo number_format($qualityMetrics['empty_docs']); ?>
                </p>
                <p style="color: var(--text-muted); font-size: 12px;">Out of
                    <?php echo number_format(count($qualitySample)); ?> sampled
                </p>
            </div>
            <div
                style="background: linear-gradient(135deg, var(--gradient-pink-start)15 0%, var(--gradient-pink-end)15 100%); padding: 20px; border-radius: 10px; border-left: 4px solid var(--gradient-pink-end);">
                <p style="color: var(--text-secondary); font-size: 13px;">Null/Empty Fields</p>
                <p style="font-size: 32px; font-weight: bold; color: var(--gradient-pink-end);">
                    <?php echo number_format($qualityMetrics['null_fields']); ?>
                </p>
                <p style="color: var(--text-muted); font-size: 12px;">Fields with null/empty values</p>
            </div>
            <div
                style="background: linear-gradient(135deg, var(--gradient-sunset-start)15 0%, var(--gradient-sunset-end)15 100%); padding: 20px; border-radius: 10px; border-left: 4px solid var(--gradient-sunset-start);">
                <p style="color: var(--text-secondary); font-size: 13px;">Avg Fields per Doc</p>
                <?php
                $avgFields = count($qualitySample) > 0 ? array_sum(array_map(function ($d) {
                    return count(json_decode(json_encode($d), true));
                }, $qualitySample)) / count($qualitySample) : 0;
                ?>
                <p style="font-size: 32px; font-weight: bold; color: var(--gradient-sunset-start);">
                    <?php echo number_format($avgFields, 1); ?>
                </p>
                <p style="color: var(--text-muted); font-size: 12px;">Average field count</p>
            </div>
        </div>
    </div>

    <!-- Top Values Analysis -->
    <div
        style="background: var(--card-bg); padding: 25px; border-radius: 12px; margin-top: 20px; box-shadow: 0 4px 15px var(--shadow-color);">
        <h3 style="color: var(--text-primary); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">🔝</span> Top Values Analysis
        </h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
            <input type="hidden" name="action" value="topvalues">
            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 12px;">
                <div>
                    <label style="color: var(--text-secondary); font-weight: 600; font-size: 13px;">Field
                        Name:</label>
                    <input type="text" name="top_field" placeholder="e.g., country, category" required
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px;">
                </div>
                <div>
                    <label style="color: var(--text-secondary); font-weight: 600; font-size: 13px;">Show
                        Top:</label>
                    <select name="top_count"
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px;">
                        <option value="5">Top 5</option>
                        <option value="10" selected>Top 10</option>
                        <option value="20">Top 20</option>
                        <option value="50">Top 50</option>
                    </select>
                </div>
                <div>
                    <label style="color: var(--text-secondary); font-weight: 600; font-size: 13px;">Sort By:</label>
                    <select name="sort_by"
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px;">
                        <option value="count">Count</option>
                        <option value="value">Value</option>
                    </select>
                </div>
                <div style="display: flex; align-items: end;">
                    <button type="submit" class="btn"
                        style="background: linear-gradient(135deg, var(--gradient-sunset-start) 0%, var(--gradient-sunset-end) 100%); color: var(--text-on-accent); padding: 10px 20px; font-weight: 600;">🔝
                        Analyze</button>
                </div>
            </div>
        </form>

        <?php if (isset($_SESSION['top_values_data'])): ?>
            <?php $topData = $_SESSION['top_values_data']; ?>
            <div style="padding: 25px; background: var(--table-header-bg); border-radius: 10px; margin-top: 20px; border: 1px solid var(--table-border);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h4 style="color: var(--text-primary);">Top Values: "<?php echo htmlspecialchars($topData['field']); ?>"</h4>
                    <span style="background: var(--gradient-sunset-start); color: var(--text-on-accent); padding: 6px 16px; border-radius: 20px; font-size: 14px; font-weight: 600;"><?php echo count($topData['results']); ?> values</span>
                </div>
                <div style="display: grid; gap: 12px;">
                    <?php foreach ($topData['results'] as $idx => $item): ?>
                        <div style="background: var(--card-bg); padding: 15px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <span style="background: linear-gradient(135deg, var(--gradient-sunset-start) 0%, var(--gradient-sunset-end) 100%); color: var(--text-on-accent); padding: 6px 12px; border-radius: 50%; font-weight: bold; font-size: 14px; min-width: 36px; text-align: center;"><?php echo $idx + 1; ?></span>
                                <span style="font-weight: 600; color: var(--text-primary); font-size: 15px;"><?php echo htmlspecialchars(json_encode($item['_id'])); ?></span>
                            </div>
                            <span style="background: var(--accent-success); color: var(--text-on-accent); padding: 8px 16px; border-radius: 16px; font-weight: bold;"><?php echo number_format($item['count']); ?> docs</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php unset($_SESSION['top_values_data']); ?>
        <?php endif; ?>
    </div>

    <!-- Comparison Analytics -->
    <div
        style="background: var(--card-bg); padding: 25px; border-radius: 12px; margin-top: 20px; box-shadow: 0 4px 15px var(--shadow-color);">
        <h3 style="color: var(--text-primary); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">⚖️</span> Compare Collections
        </h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
            <input type="hidden" name="action" value="comparecollections">
            <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 12px;">
                <div>
                    <label style="color: var(--text-secondary); font-weight: 600; font-size: 13px;">Collection
                        1:</label>
                    <select name="compare_coll1" required
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px;">
                        <option value="">Select collection...</option>
                        <?php foreach ($collectionNames as $cName): ?>
                            <option value="<?php echo htmlspecialchars($cName); ?>" <?php echo $cName === $collectionName ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="color: var(--text-secondary); font-weight: 600; font-size: 13px;">Collection
                        2:</label>
                    <select name="compare_coll2" required
                        style="width: 100%; padding: 10px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); border-radius: 6px;">
                        <option value="">Select collection...</option>
                        <?php foreach ($collectionNames as $cName): ?>
                            <option value="<?php echo htmlspecialchars($cName); ?>">
                                <?php echo htmlspecialchars($cName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display: flex; align-items: end;">
                    <button type="submit" class="btn"
                        style="background: linear-gradient(135deg, var(--accent-primary) 0%, var(--accent-secondary) 100%); color: var(--text-on-accent); padding: 10px 20px; font-weight: 600;">⚖️
                        Compare</button>
                </div>
            </div>
        </form>

        <?php if (isset($_SESSION['comparison_data'])): ?>
            <?php $compData = $_SESSION['comparison_data']; ?>
            <div style="padding: 25px; background: var(--table-header-bg); border-radius: 10px; margin-top: 20px; border: 1px solid var(--table-border);">
                <h4 style="color: var(--text-primary); margin-bottom: 20px;">⚖️ Collection Comparison Results</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div style="background: linear-gradient(135deg, var(--accent-primary)15 0%, var(--accent-secondary)15 100%); padding: 20px; border-radius: 10px; border-left: 4px solid var(--accent-primary);">
                        <h5 style="color: var(--accent-primary); margin-bottom: 15px; font-size: 18px;">📦 <?php echo htmlspecialchars($compData['collection1']['name']); ?></h5>
                        <div style="display: grid; gap: 10px;">
                            <div style="display: flex; justify-content: space-between; padding: 8px; background: var(--card-bg); border-radius: 6px;">
                                <span style="color: var(--text-secondary);">Documents:</span>
                                <span style="font-weight: bold; color: var(--text-primary);"><?php echo number_format($compData['collection1']['count']); ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 8px; background: var(--card-bg); border-radius: 6px;">
                                <span style="color: var(--text-secondary);">Size:</span>
                                <span style="font-weight: bold; color: var(--text-primary);"><?php echo number_format($compData['collection1']['size'] / 1024, 2); ?> KB</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 8px; background: var(--card-bg); border-radius: 6px;">
                                <span style="color: var(--text-secondary);">Avg Doc Size:</span>
                                <span style="font-weight: bold; color: var(--text-primary);"><?php echo number_format($compData['collection1']['avgSize'], 2); ?> bytes</span>
                            </div>
                        </div>
                    </div>
                    <div style="background: linear-gradient(135deg, var(--gradient-pink-start)15 0%, var(--gradient-pink-end)15 100%); padding: 20px; border-radius: 10px; border-left: 4px solid var(--gradient-pink-end);">
                        <h5 style="color: var(--gradient-pink-end); margin-bottom: 15px; font-size: 18px;">📦 <?php echo htmlspecialchars($compData['collection2']['name']); ?></h5>
                        <div style="display: grid; gap: 10px;">
                            <div style="display: flex; justify-content: space-between; padding: 8px; background: var(--card-bg); border-radius: 6px;">
                                <span style="color: var(--text-secondary);">Documents:</span>
                                <span style="font-weight: bold; color: var(--text-primary);"><?php echo number_format($compData['collection2']['count']); ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 8px; background: var(--card-bg); border-radius: 6px;">
                                <span style="color: var(--text-secondary);">Size:</span>
                                <span style="font-weight: bold; color: var(--text-primary);"><?php echo number_format($compData['collection2']['size'] / 1024, 2); ?> KB</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 8px; background: var(--card-bg); border-radius: 6px;">
                                <span style="color: var(--text-secondary);">Avg Doc Size:</span>
                                <span style="font-weight: bold; color: var(--text-primary);"><?php echo number_format($compData['collection2']['avgSize'], 2); ?> bytes</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php unset($_SESSION['comparison_data']); ?>
        <?php endif; ?>
    </div>

    <!-- Export Analytics Report -->
    <div
        style="background: linear-gradient(135deg, var(--accent-primary)15 0%, var(--accent-secondary)15 100%); padding: 20px; border-radius: 12px; margin-top: 20px; border-left: 4px solid var(--accent-primary);">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h4 style="color: var(--text-primary); margin-bottom: 8px;">📄 Generate Analytics Report</h4>
                <p style="color: var(--text-secondary); font-size: 13px; line-height: 1.6;">
                    Export comprehensive analytics including all metrics, visualizations, and quality assessments
                </p>
            </div>
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                <input type="hidden" name="action" value="exportanalytics">
                <button type="submit" class="btn"
                    style="background: linear-gradient(135deg, var(--gradient-green-start) 0%, var(--gradient-green-end) 100%); color: var(--text-on-accent); padding: 12px 24px; font-weight: 600; white-space: nowrap;">
                    📥 Export Report
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Schema Explorer Tab -->
