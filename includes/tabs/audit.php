<!-- Audit Log Tab -->
<div id="audit" class="tab-content">
    <div class="card">
        <div class="card-header">
            <h2>📜 Audit Log</h2>
            <p style="margin: 8px 0 0 0; color: var(--text-secondary); font-size: 14px;">View and analyze system activity logs with detailed tracking</p>
        </div>
        <div class="card-body">
            <?php if (!userHasRole('admin')): ?>
                <div class="alert alert-warning">
                    <span class="alert-icon">⚠️</span>
                    <span class="alert-text">You don't have permission to access audit logs. Admin role required.</span>
                </div>
            <?php else: ?>
                
                <!-- Audit Log Statistics -->
                <?php $auditStats = getAuditLogStats(); ?>
                <div style="margin-bottom: 30px;">
                    <h3 style="margin-bottom: 15px;">📊 Audit Statistics</h3>
                    <div class="stats">
                        <div class="stat-card">
                            <p>Total Entries</p>
                            <p><?php echo number_format($auditStats['total_entries'] ?? 0); ?></p>
                        </div>
                        <div class="stat-card">
                            <p>Today</p>
                            <p><?php echo number_format($auditStats['today'] ?? 0); ?></p>
                        </div>
                        <div class="stat-card">
                            <p>This Week</p>
                            <p><?php echo number_format($auditStats['this_week'] ?? 0); ?></p>
                        </div>
                        <div class="stat-card">
                            <p>Critical Events</p>
                            <p style="color: var(--accent-danger);"><?php echo number_format($auditStats['by_severity']['critical'] ?? 0); ?></p>
                        </div>
                        <div class="stat-card">
                            <p>Errors</p>
                            <p style="color: var(--accent-warning);"><?php echo number_format($auditStats['by_severity']['error'] ?? 0); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Statistics by Category -->
                <div style="margin-bottom: 30px;">
                    <h3 style="margin-bottom: 15px;">📂 Events by Category</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <?php 
                        $categoryIcons = ['auth' => '🔐', 'data' => '💾', 'system' => '⚙️', 'security' => '🔒', 'user' => '👤'];
                        $categoryColors = ['auth' => 'var(--accent-primary)', 'data' => 'var(--accent-teal)', 'system' => 'var(--accent-amber)', 'security' => 'var(--accent-danger)', 'user' => 'var(--accent-sky)'];
                        foreach ($auditStats['by_category'] as $category => $count): 
                        ?>
                            <div style="background: <?php echo $categoryColors[$category] ?? 'var(--text-muted)'; ?>; color: var(--text-on-accent); padding: 20px; border-radius: 10px; text-align: center;">
                                <div style="font-size: 32px; margin-bottom: 10px;"><?php echo $categoryIcons[$category] ?? '📌'; ?></div>
                                <div style="font-size: 24px; font-weight: bold; margin-bottom: 5px;"><?php echo number_format($count); ?></div>
                                <div style="font-size: 13px; opacity: 0.9; text-transform: capitalize;"><?php echo $category; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Top Actions & Users -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                    <div style="background: var(--surface-muted); padding: 20px; border-radius: 12px;">
                        <h4 style="margin: 0 0 15px 0; color: var(--text-primary);">🔥 Top 5 Actions</h4>
                        <div style="display: grid; gap: 10px;">
                            <?php foreach ($auditStats['top_actions'] as $action => $count): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; background: var(--card-bg); border-radius: 6px;">
                                    <span style="font-size: 13px; color: var(--text-secondary);"><?php echo htmlspecialchars($action); ?></span>
                                    <span style="background: var(--accent-primary); color: var(--text-on-accent); padding: 2px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                        <?php echo number_format($count); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div style="background: var(--surface-muted); padding: 20px; border-radius: 12px;">
                        <h4 style="margin: 0 0 15px 0; color: var(--text-primary);">👥 Top 5 Active Users</h4>
                        <div style="display: grid; gap: 10px;">
                            <?php foreach ($auditStats['top_users'] as $user => $count): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; background: var(--card-bg); border-radius: 6px;">
                                    <span style="font-size: 13px; color: var(--text-secondary);">👤 <?php echo htmlspecialchars($user); ?></span>
                                    <span style="background: var(--accent-success); color: var(--text-on-accent); padding: 2px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                        <?php echo number_format($count); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div style="background: var(--card-bg); padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0 0 15px 0;">🔍 Filter Audit Logs</h3>
                    <form method="GET" id="auditFilterForm">
                        <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionName); ?>">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Action:</label>
                                <input type="text" name="filter_action" value="<?php echo htmlspecialchars($_GET['filter_action'] ?? ''); ?>" 
                                    placeholder="Search action..." style="width: 100%; padding: 8px; border: 2px solid var(--border-color); border-radius: 6px;">
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">User:</label>
                                <input type="text" name="filter_user" value="<?php echo htmlspecialchars($_GET['filter_user'] ?? ''); ?>" 
                                    placeholder="Username..." style="width: 100%; padding: 8px; border: 2px solid var(--border-color); border-radius: 6px;">
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Category:</label>
                                <select name="filter_category" style="width: 100%; padding: 8px; border: 2px solid var(--border-color); border-radius: 6px;">
                                    <option value="">All Categories</option>
                                    <option value="auth" <?php echo ($_GET['filter_category'] ?? '') === 'auth' ? 'selected' : ''; ?>>🔐 Authentication</option>
                                    <option value="data" <?php echo ($_GET['filter_category'] ?? '') === 'data' ? 'selected' : ''; ?>>💾 Data</option>
                                    <option value="system" <?php echo ($_GET['filter_category'] ?? '') === 'system' ? 'selected' : ''; ?>>⚙️ System</option>
                                    <option value="security" <?php echo ($_GET['filter_category'] ?? '') === 'security' ? 'selected' : ''; ?>>🔒 Security</option>
                                    <option value="user" <?php echo ($_GET['filter_category'] ?? '') === 'user' ? 'selected' : ''; ?>>👤 User</option>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Severity:</label>
                                <select name="filter_severity" style="width: 100%; padding: 8px; border: 2px solid var(--border-color); border-radius: 6px;">
                                    <option value="">All Levels</option>
                                    <option value="info" <?php echo ($_GET['filter_severity'] ?? '') === 'info' ? 'selected' : ''; ?>>ℹ️ Info</option>
                                    <option value="warning" <?php echo ($_GET['filter_severity'] ?? '') === 'warning' ? 'selected' : ''; ?>>⚠️ Warning</option>
                                    <option value="error" <?php echo ($_GET['filter_severity'] ?? '') === 'error' ? 'selected' : ''; ?>>❌ Error</option>
                                    <option value="critical" <?php echo ($_GET['filter_severity'] ?? '') === 'critical' ? 'selected' : ''; ?>>🚨 Critical</option>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Date From:</label>
                                <input type="date" name="filter_date_from" value="<?php echo htmlspecialchars($_GET['filter_date_from'] ?? ''); ?>" 
                                    style="width: 100%; padding: 8px; border: 2px solid var(--border-color); border-radius: 6px;">
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Date To:</label>
                                <input type="date" name="filter_date_to" value="<?php echo htmlspecialchars($_GET['filter_date_to'] ?? ''); ?>" 
                                    style="width: 100%; padding: 8px; border: 2px solid var(--border-color); border-radius: 6px;">
                            </div>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="btn" style="background: var(--accent-primary); color: var(--text-on-accent); padding: 10px 20px;">
                                🔍 Apply Filters
                            </button>
                            <button type="button" onclick="resetAuditFilters()" class="btn" style="background: var(--text-muted); color: var(--text-on-accent); padding: 10px 20px;">
                                🔄 Reset
                            </button>
                            <button type="button" onclick="exportAuditLog()" class="btn" style="background: var(--accent-success); color: var(--text-on-accent); padding: 10px 20px;">
                                📥 Export JSON
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Audit Log Entries -->
                <?php 
                $filters = [];
                if (!empty($_GET['filter_action'])) $filters['action'] = $_GET['filter_action'];
                if (!empty($_GET['filter_user'])) $filters['user'] = $_GET['filter_user'];
                if (!empty($_GET['filter_category'])) $filters['category'] = $_GET['filter_category'];
                if (!empty($_GET['filter_severity'])) $filters['severity'] = $_GET['filter_severity'];
                if (!empty($_GET['filter_date_from'])) $filters['date_from'] = $_GET['filter_date_from'];
                if (!empty($_GET['filter_date_to'])) $filters['date_to'] = $_GET['filter_date_to'];
                
                $auditLogs = getAuditLogs($filters, 100);
                ?>
                
                <div style="background: var(--card-bg); padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0 0 20px 0;">📋 Recent Audit Entries (<?php echo count($auditLogs); ?>)</h3>
                    
                    <?php if (empty($auditLogs)): ?>
                        <div class="alert alert-info">
                            <span class="alert-icon">ℹ️</span>
                            <span class="alert-text">No audit log entries found matching your filters.</span>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <?php foreach ($auditLogs as $log): ?>
                                <?php
                                $severityColors = [
                                    'info' => 'var(--accent-info)',
                                    'warning' => 'var(--accent-warning)',
                                    'error' => 'var(--accent-danger)',
                                    'critical' => 'var(--accent-indigo)'
                                ];
                                $severityIcons = [
                                    'info' => 'ℹ️',
                                    'warning' => '⚠️',
                                    'error' => '❌',
                                    'critical' => '🚨'
                                ];
                                $categoryIcons = [
                                    'auth' => '🔐',
                                    'data' => '💾',
                                    'system' => '⚙️',
                                    'security' => '🔒',
                                    'user' => '👤'
                                ];
                                
                                $timestamp = $log['timestamp']->toDateTime()->format('Y-m-d H:i:s');
                                $severity = $log['severity'] ?? 'info';
                                $category = $log['category'] ?? 'system';
                                ?>
                                <div style="background: var(--surface-muted); padding: 15px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid <?php echo $severityColors[$severity]; ?>;">
                                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                                        <div style="flex: 1;">
                                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                                                <span style="font-size: 18px;"><?php echo $severityIcons[$severity]; ?></span>
                                                <strong style="font-size: 15px; color: var(--text-primary);"><?php echo htmlspecialchars($log['action']); ?></strong>
                                                <span style="background: <?php echo $categoryColors[$category] ?? 'var(--text-muted)'; ?>; color: var(--text-on-accent); padding: 2px 8px; border-radius: 12px; font-size: 11px;">
                                                    <?php echo $categoryIcons[$category]; ?> <?php echo strtoupper($category); ?>
                                                </span>
                                                <span style="background: <?php echo $severityColors[$severity]; ?>; color: var(--text-on-accent); padding: 2px 8px; border-radius: 12px; font-size: 11px;">
                                                    <?php echo strtoupper($severity); ?>
                                                </span>
                                            </div>
                                            <div style="font-size: 12px; color: var(--text-secondary); display: flex; gap: 15px; flex-wrap: wrap;">
                                                <span>🕐 <?php echo $timestamp; ?></span>
                                                <span>👤 <?php echo htmlspecialchars($log['user']['username'] ?? 'unknown'); ?></span>
                                                <span>🎭 <?php echo htmlspecialchars($log['user']['role'] ?? 'unknown'); ?></span>
                                                <span>🌐 <?php echo htmlspecialchars($log['request']['ip'] ?? 'unknown'); ?></span>
                                            </div>
                                        </div>
                                        <button type="button" onclick="showAuditDetails('<?php echo (string)$log['_id']; ?>')" 
                                            style="background: var(--accent-primary); color: var(--text-on-accent); border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 12px;">
                                            📄 Details
                                        </button>
                                    </div>
                                    
                                    <?php if (!empty($log['details'])): ?>
                                        <div style="background: var(--card-bg); padding: 10px; border-radius: 6px; margin-top: 10px;">
                                            <div style="font-size: 12px; color: var(--text-secondary); font-family: monospace;">
                                                <?php foreach ($log['details'] as $key => $value): ?>
                                                    <div><strong><?php echo htmlspecialchars($key); ?>:</strong> <?php echo htmlspecialchars(is_array($value) ? json_encode($value) : $value); ?></div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Maintenance Section -->
                <div style="background: var(--warning-bg); padding: 20px; border-radius: 12px; margin-top: 30px; border-left: 4px solid var(--accent-warning);">
                    <h3 style="color: var(--warning-text); margin: 0 0 15px 0;">🧹 Audit Log Maintenance</h3>
                    <p style="color: var(--text-secondary); font-size: 14px; margin-bottom: 15px;">Clear old audit log entries to save database space. Entries older than the specified days will be permanently deleted.</p>
                    <form method="POST" onsubmit="return confirm('Are you sure you want to clear old audit logs? This cannot be undone!');">
                        <input type="hidden" name="action" value="clear_old_audit_logs">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <div style="display: flex; gap: 10px; align-items: end;">
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Keep logs for (days):</label>
                                <input type="number" name="days_to_keep" value="90" min="1" max="365" 
                                    style="padding: 8px; border: 2px solid var(--border-color); border-radius: 6px; width: 150px;">
                            </div>
                            <button type="submit" class="btn" style="background: var(--accent-warning); color: var(--text-primary); padding: 10px 20px;">
                                🗑️ Clear Old Logs
                            </button>
                        </div>
                    </form>
                </div>

            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Audit Details Modal -->
<div id="auditDetailsModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 800px; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header">
            <h2>📄 Audit Entry Details</h2>
            <button type="button" class="close-btn" onclick="closeAuditDetailsModal()">&times;</button>
        </div>
        <div id="auditDetailsContent" style="padding: 20px;">
            <!-- Content will be populated by JavaScript -->
        </div>
    </div>
</div>

<script>
// Store all audit logs for JavaScript access
const allAuditLogs = <?php echo json_encode($auditLogs ?? []); ?>;

function resetAuditFilters() {
    const form = document.getElementById('auditFilterForm');
    form.querySelectorAll('input[type="text"], input[type="date"]').forEach(input => input.value = '');
    form.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
    form.submit();
}

function exportAuditLog() {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="export_audit_log">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="filter_action" value="${document.querySelector('input[name="filter_action"]')?.value || ''}">
        <input type="hidden" name="filter_user" value="${document.querySelector('input[name="filter_user"]')?.value || ''}">
        <input type="hidden" name="filter_category" value="${document.querySelector('select[name="filter_category"]')?.value || ''}">
        <input type="hidden" name="filter_severity" value="${document.querySelector('select[name="filter_severity"]')?.value || ''}">
        <input type="hidden" name="filter_date_from" value="${document.querySelector('input[name="filter_date_from"]')?.value || ''}">
        <input type="hidden" name="filter_date_to" value="${document.querySelector('input[name="filter_date_to"]')?.value || ''}">
    `;
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

function showAuditDetails(logId) {
    const log = allAuditLogs.find(l => l._id.$oid === logId);
    if (!log) return;
    
    let html = `
        <div style="background: var(--surface-muted); padding: 15px; border-radius: 8px; margin-bottom: 15px;">
            <h3 style="margin: 0 0 10px 0; color: var(--text-primary);">Basic Information</h3>
            <div style="display: grid; gap: 8px; font-size: 14px;">
                <div><strong>Action:</strong> ${log.action}</div>
                <div><strong>Timestamp:</strong> ${log.server_time}</div>
                <div><strong>Category:</strong> ${log.category}</div>
                <div><strong>Severity:</strong> ${log.severity}</div>
            </div>
        </div>
        
        <div style="background: var(--info-bg); padding: 15px; border-radius: 8px; margin-bottom: 15px;">
            <h3 style="margin: 0 0 10px 0; color: var(--info-text);">User Information</h3>
            <div style="display: grid; gap: 8px; font-size: 14px;">
                <div><strong>Username:</strong> ${log.user.username}</div>
                <div><strong>User ID:</strong> ${log.user.user_id || 'N/A'}</div>
                <div><strong>Role:</strong> ${log.user.role}</div>
                <div><strong>Session ID:</strong> ${log.user.session_id || 'N/A'}</div>
            </div>
        </div>
        
        <div style="background: var(--warning-bg); padding: 15px; border-radius: 8px; margin-bottom: 15px;">
            <h3 style="margin: 0 0 10px 0; color: var(--warning-text);">Request Information</h3>
            <div style="display: grid; gap: 8px; font-size: 14px;">
                <div><strong>Method:</strong> ${log.request.method}</div>
                <div><strong>URI:</strong> <code>${log.request.uri}</code></div>
                <div><strong>IP Address:</strong> ${log.request.ip}</div>
                <div><strong>Referer:</strong> ${log.request.referer}</div>
                <div><strong>User Agent:</strong> <code style="font-size: 11px;">${log.request.user_agent}</code></div>
            </div>
        </div>
        
        <div style="background: var(--success-bg); padding: 15px; border-radius: 8px; margin-bottom: 15px;">
            <h3 style="margin: 0 0 10px 0; color: var(--success-text);">Database Context</h3>
            <div style="display: grid; gap: 8px; font-size: 14px;">
                <div><strong>Database:</strong> ${log.database.name}</div>
                <div><strong>Collection:</strong> ${log.database.collection}</div>
            </div>
        </div>
        
        <div style="background: var(--danger-bg); padding: 15px; border-radius: 8px; margin-bottom: 15px;">
            <h3 style="margin: 0 0 10px 0; color: var(--danger-text);">System Information</h3>
            <div style="display: grid; gap: 8px; font-size: 14px;">
                <div><strong>PHP Version:</strong> ${log.php_version}</div>
                <div><strong>Memory Usage:</strong> ${(log.memory_usage / 1024 / 1024).toFixed(2)} MB</div>
                <div><strong>Execution Time:</strong> ${log.execution_time.toFixed(4)} seconds</div>
            </div>
        </div>
    `;
    
    if (log.details && Object.keys(log.details).length > 0) {
        html += `
            <div style="background: var(--card-bg); padding: 15px; border-radius: 8px; border: 2px solid var(--accent-primary);">
                <h3 style="margin: 0 0 10px 0; color: var(--accent-primary);">Additional Details</h3>
                <pre style="background: var(--surface-muted); padding: 15px; border-radius: 6px; overflow-x: auto; font-size: 12px; line-height: 1.5;">${JSON.stringify(log.details, null, 2)}</pre>
            </div>
        `;
    }
    
    document.getElementById('auditDetailsContent').innerHTML = html;
    document.getElementById('auditDetailsModal').style.display = 'flex';
}

function closeAuditDetailsModal() {
    document.getElementById('auditDetailsModal').style.display = 'none';
}

// Close modal on background click
document.getElementById('auditDetailsModal').onclick = function(e) {
    if (e.target === this) closeAuditDetailsModal();
};
</script>
