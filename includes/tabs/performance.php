<div id="performance" class="tab-content">
    <h2 style="margin-bottom: 25px;">⚡ Performance & Monitoring</h2>

    <!-- Query Profiler -->
    <div
        style="background: var(--card-bg); padding: 25px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <h3 style="color: var(--text-primary); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">🔬</span> Query Profiler
        </h3>
        <form method="POST">
            <input type="hidden" name="action" value="profile_query">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <div class="form-group">
                <label style="font-weight: 600;">MongoDB Query (JSON):</label>
                <textarea name="profile_query" placeholder='{"field": "value"}' required
                    style="width: 100%; padding: 12px; border: 2px solid var(--border-color); border-radius: 6px; min-height: 120px; font-family: monospace; font-size: 13px;"></textarea>
                <small style="color: var(--text-secondary);">Test query performance and execution time</small>
            </div>
            <button type="submit" class="btn"
                style="background: linear-gradient(135deg, var(--accent-primary) 0%, var(--accent-secondary) 100%); color: var(--text-on-accent); width: 100%; padding: 14px;">🔬
                Profile Query</button>
        </form>

        <?php if (isset($_SESSION['profile_result'])): ?>
            <div
                style="margin-top: 20px; padding: 20px; background: linear-gradient(135deg, var(--info-bg) 0%, var(--info-bg-strong) 100%); border-radius: 8px; border-left: 4px solid var(--accent-blue-bright);">
                <h4 style="color: var(--info-text); margin-bottom: 15px;">📊 Profile Results:</h4>
                <div style="display: grid; gap: 10px;">
                    <div style="background: var(--card-bg); padding: 12px; border-radius: 6px;">
                        <strong>Execution Time:</strong> <span
                            style="color: var(--accent-blue-bright); font-size: 18px; font-weight: bold;"><?php echo $_SESSION['profile_result']['execution_time']; ?>ms</span>
                    </div>
                    <div style="background: var(--card-bg); padding: 12px; border-radius: 6px;">
                        <strong>Results Found:</strong> <span
                            style="color: var(--accent-success); font-size: 18px; font-weight: bold;"><?php echo $_SESSION['profile_result']['result_count']; ?></span>
                    </div>
                    <div style="background: var(--card-bg); padding: 12px; border-radius: 6px;">
                        <strong>Query:</strong> <code
                            style="background: var(--surface-muted); padding: 4px 8px; border-radius: 4px; font-size: 12px;"><?php echo htmlspecialchars($_SESSION['profile_result']['query']); ?></code>
                    </div>
                </div>
            </div>
            <?php unset($_SESSION['profile_result']); ?>
        <?php endif; ?>
    </div>

    <!-- Collection Operations -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px;">
        <!-- Compact Collection -->
        <div style="background: var(--card-bg); padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="color: var(--text-primary); margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 20px;">🗜️</span> Compact Collection
            </h3>
            <form method="POST">
                <input type="hidden" name="action" value="compact_collection">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <p style="color: var(--text-secondary); font-size: 14px; margin-bottom: 15px;">
                    Defragments the collection storage and reclaims disk space. Reduces file size and improves
                    performance.
                </p>
                <p
                    style="background: var(--warning-bg); color: var(--warning-text); padding: 10px; border-radius: 6px; font-size: 13px; margin-bottom: 15px;">
                    ⚠️ This operation may take time and block writes temporarily
                </p>
                <button type="submit" class="btn" style="background: var(--accent-warning); color: var(--text-primary); width: 100%; padding: 12px;"
                    onclick="return confirm('Compact collection? This may take a while.')">🗜️ Compact Now</button>
            </form>
        </div>

        <!-- Validate Collection -->
        <div style="background: var(--card-bg); padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="color: var(--text-primary); margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 20px;">✅</span> Validate Collection
            </h3>
            <form method="POST">
                <input type="hidden" name="action" value="validate_collection">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <p style="color: var(--text-secondary); font-size: 14px; margin-bottom: 15px;">
                    Scans the collection's data and indexes for correctness. Checks for errors and corruption.
                </p>
                <p
                    style="background: var(--info-bg); color: var(--info-text); padding: 10px; border-radius: 6px; font-size: 13px; margin-bottom: 15px;">
                    ℹ️ Full validation checks both data and index structures
                </p>
                <button type="submit" class="btn"
                    style="background: var(--accent-info); color: var(--text-on-accent); width: 100%; padding: 12px;">✅ Validate Now</button>
            </form>
        </div>
    </div>

    <?php if (isset($_SESSION['validate_result'])): ?>
        <div
            style="background: var(--card-bg); padding: 25px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="color: var(--text-primary); margin-bottom: 15px;">📋 Validation Results:</h3>
            <pre
                style="background: var(--surface-muted); padding: 20px; border-radius: 8px; overflow-x: auto; font-size: 12px; border: 1px solid var(--table-border);"><code><?php echo htmlspecialchars($_SESSION['validate_result']); ?></code></pre>
        </div>
        <?php unset($_SESSION['validate_result']); ?>
    <?php endif; ?>

    <!-- Connection & Server Stats -->
    <div style="background: var(--card-bg); padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <h3 style="color: var(--text-primary); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">📊</span> Server Statistics
        </h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
            <?php
            try {
                $serverStatus = $database->command(['serverStatus' => 1])->toArray()[0];
                $connections = $serverStatus->connections ?? null;
                $network = $serverStatus->network ?? null;
                $opcounters = $serverStatus->opcounters ?? null;
                ?>

                <?php if ($connections): ?>
                    <div
                        style="background: linear-gradient(135deg, var(--accent-primary) 0%, var(--accent-secondary) 100%); color: var(--text-on-accent); padding: 20px; border-radius: 10px;">
                        <p style="font-size: 13px; opacity: 0.9;">Active Connections</p>
                        <p style="font-size: 32px; font-weight: bold; margin: 8px 0;">
                            <?php echo $connections->current ?? 0; ?>
                        </p>
                        <p style="font-size: 11px; opacity: 0.8;">Available: <?php echo $connections->available ?? 0; ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($network): ?>
                    <div
                        style="background: linear-gradient(135deg, var(--gradient-pink-start) 0%, var(--gradient-pink-end) 100%); color: var(--text-on-accent); padding: 20px; border-radius: 10px;">
                        <p style="font-size: 13px; opacity: 0.9;">Network Traffic</p>
                        <p style="font-size: 32px; font-weight: bold; margin: 8px 0;">
                            <?php echo round(($network->bytesIn ?? 0) / 1024 / 1024, 1); ?> MB
                        </p>
                        <p style="font-size: 11px; opacity: 0.8;">In | Out:
                            <?php echo round(($network->bytesOut ?? 0) / 1024 / 1024, 1); ?> MB
                        </p>
                    </div>
                <?php endif; ?>

                <?php if ($opcounters): ?>
                    <div
                        style="background: linear-gradient(135deg, var(--gradient-sky-start) 0%, var(--gradient-sky-end) 100%); color: var(--text-on-accent); padding: 20px; border-radius: 10px;">
                        <p style="font-size: 13px; opacity: 0.9;">Query Operations</p>
                        <p style="font-size: 32px; font-weight: bold; margin: 8px 0;">
                            <?php echo number_format($opcounters->query ?? 0); ?>
                        </p>
                        <p style="font-size: 11px; opacity: 0.8;">Inserts:
                            <?php echo number_format($opcounters->insert ?? 0); ?>
                        </p>
                    </div>

                    <div
                        style="background: linear-gradient(135deg, var(--gradient-green-start) 0%, var(--gradient-green-end) 100%); color: var(--text-on-accent); padding: 20px; border-radius: 10px;">
                        <p style="font-size: 13px; opacity: 0.9;">Update Operations</p>
                        <p style="font-size: 32px; font-weight: bold; margin: 8px 0;">
                            <?php echo number_format($opcounters->update ?? 0); ?>
                        </p>
                        <p style="font-size: 11px; opacity: 0.8;">Deletes:
                            <?php echo number_format($opcounters->delete ?? 0); ?>
                        </p>
                    </div>
                <?php endif; ?>
                <?php
            } catch (Exception $e) {
                echo '<div style="background: var(--danger-bg); color: var(--danger-text); padding: 15px; border-radius: 8px;">Unable to fetch server statistics</div>';
            }
            ?>
        </div>
    </div>
</div>

<!-- Analytics Tab -->
