<div id="security" class="tab-content">
    <h2 style="margin-bottom: 20px;">🔒 Security & Backup</h2>

    <?php if (!userHasPermission('view_security') && !userHasPermission('manage_security')): ?>
        <div class="alert alert-warning">
            <span class="alert-icon">⚠️</span>
            <span class="alert-text">You don't have permission to access security settings. Contact an administrator.</span>
        </div>
    <?php else: ?>

    <div
        style="background: linear-gradient(135deg, var(--accent-danger)15 0%, var(--accent-danger)15 100%); padding: 20px; border-radius: 12px; margin-bottom: 20px; border-left: 4px solid var(--accent-danger);">
        <p style="color: var(--danger-text); line-height: 1.8;">
            <strong>⚠️ Important:</strong> This panel includes critical database operations.
            Always create backups before making bulk changes.
            <?php
            $csrfEnabled = (bool) getSetting('csrf_enabled', true);
            $rateLimitEnabled = (bool) getSetting('rate_limit_enabled', true);
            ?>
            CSRF protection is <?php echo $csrfEnabled ? 'active' : 'disabled'; ?> and rate limiting is <?php echo $rateLimitEnabled ? 'active' : 'disabled'; ?>.
        </p>
    </div>

    <?php
    // Handle backup action
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'create_backup') {
            $backupResult = createDatabaseBackup($database);
            if ($backupResult['success']) {
                $message = 'Backup created successfully: ' . $backupResult['file'] . ' (' . round($backupResult['size'] / 1024, 2) . ' KB)';
                $messageType = 'success';
                auditLog('backup_created', $backupResult);
            } else {
                $message = 'Backup failed: ' . $backupResult['error'];
                $messageType = 'error';
            }
        }
    }

    $backups = listBackups();
    ?>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
        <!-- Backup Section -->
        <div style="background: var(--card-bg); padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="margin-bottom: 20px; color: var(--text-primary); display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">💾</span> Database Backup
            </h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_backup">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <p style="color: var(--text-secondary); margin-bottom: 15px; font-size: 14px;">
                    Create a complete backup of all collections in this database. Backups are compressed and stored
                    locally.
                </p>
                <button type="submit" class="btn"
                    style="background: linear-gradient(135deg, var(--accent-primary) 0%, var(--accent-secondary) 100%); color: var(--text-on-accent); width: 100%; padding: 15px; font-size: 16px;">
                    💾 Create Backup Now
                </button>
            </form>

            <?php if (!empty($backups)): ?>
                <div style="margin-top: 25px; padding-top: 20px; border-top: 2px solid var(--surface-muted);">
                    <h4 style="color: var(--text-primary); margin-bottom: 15px; font-size: 14px;">📂 Available Backups</h4>
                    <?php foreach ($backups as $backup): ?>
                        <div
                            style="background: var(--surface-muted); padding: 12px; border-radius: 6px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong
                                    style="color: var(--text-primary); font-size: 13px;"><?php echo htmlspecialchars($backup['name']); ?></strong>
                                <p style="color: var(--text-secondary); font-size: 11px; margin-top: 4px;">
                                    <?php echo $backup['date']; ?> • <?php echo round($backup['size'] / 1024, 2); ?> KB
                                </p>
                            </div>
                            <a href="backups/<?php echo htmlspecialchars($backup['name']); ?>" download class="btn"
                                style="background: var(--accent-info); color: var(--text-on-accent); padding: 6px 12px; font-size: 11px; text-decoration: none;">
                                📥 Download
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Security Settings -->
        <div style="background: var(--card-bg); padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="margin-bottom: 20px; color: var(--text-primary); display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">🛡️</span> Security Settings
            </h3>

            <div style="display: flex; flex-direction: column; gap: 15px;">
                <?php
                $csrfEnabled = (bool) getSetting('csrf_enabled', true);
                $csrfLifetime = (int) getSetting('csrf_token_lifetime', 60);
                $sessionValidationEnabled = (bool) getSetting('session_validation_enabled', true);
                $ipTrackingEnabled = (bool) getSetting('ip_tracking_enabled', true);
                $rateLimitEnabled = (bool) getSetting('rate_limit_enabled', true);
                $rateLimitRequests = (int) getSetting('rate_limit_requests', 30);
                $rateLimitLockout = (int) getSetting('rate_limit_lockout', 60);
                ?>
                <div
                    style="padding: 15px; background: linear-gradient(135deg, var(--accent-success)15 0%, var(--accent-teal-bright)15 100%); border-radius: 8px; border-left: 3px solid var(--accent-success);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="color: var(--text-primary);">CSRF Protection</strong>
                            <p style="color: var(--text-secondary); font-size: 12px; margin-top: 4px;">
                                <?php echo $csrfEnabled ? 'Enabled' : 'Disabled'; ?> • Token lifetime: <?php echo $csrfLifetime; ?> minutes
                            </p>
                        </div>
                        <span
                            style="background: <?php echo $csrfEnabled ? 'var(--accent-success)' : 'var(--accent-warning)'; ?>; color: var(--text-on-accent); padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                            <?php echo $csrfEnabled ? 'ACTIVE' : 'DISABLED'; ?>
                        </span>
                    </div>
                </div>

                <div
                    style="padding: 15px; background: linear-gradient(135deg, var(--accent-success)15 0%, var(--accent-teal-bright)15 100%); border-radius: 8px; border-left: 3px solid var(--accent-success);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="color: var(--text-primary);">Rate Limiting</strong>
                            <p style="color: var(--text-secondary); font-size: 12px; margin-top: 4px;">
                                <?php echo $rateLimitEnabled ? 'Enabled' : 'Disabled'; ?> • <?php echo $rateLimitRequests; ?> requests/min • lockout <?php echo $rateLimitLockout; ?>s
                            </p>
                        </div>
                        <span
                            style="background: <?php echo $rateLimitEnabled ? 'var(--accent-success)' : 'var(--accent-warning)'; ?>; color: var(--text-on-accent); padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                            <?php echo $rateLimitEnabled ? 'ACTIVE' : 'DISABLED'; ?>
                        </span>
                    </div>
                </div>

                <div
                    style="padding: 15px; background: linear-gradient(135deg, var(--accent-success)15 0%, var(--accent-teal-bright)15 100%); border-radius: 8px; border-left: 3px solid var(--accent-success);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="color: var(--text-primary);">Session Validation</strong>
                            <p style="color: var(--text-secondary); font-size: 12px; margin-top: 4px;">
                                <?php echo $sessionValidationEnabled ? 'Enabled' : 'Disabled'; ?> • user-agent binding
                            </p>
                        </div>
                        <span
                            style="background: <?php echo $sessionValidationEnabled ? 'var(--accent-success)' : 'var(--accent-warning)'; ?>; color: var(--text-on-accent); padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                            <?php echo $sessionValidationEnabled ? 'ACTIVE' : 'DISABLED'; ?>
                        </span>
                    </div>
                </div>

                <div
                    style="padding: 15px; background: linear-gradient(135deg, var(--accent-success)15 0%, var(--accent-teal-bright)15 100%); border-radius: 8px; border-left: 3px solid var(--accent-success);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="color: var(--text-primary);">IP Tracking</strong>
                            <p style="color: var(--text-secondary); font-size: 12px; margin-top: 4px;">
                                <?php echo $ipTrackingEnabled ? 'Enabled' : 'Disabled'; ?> • session IP binding
                            </p>
                        </div>
                        <span
                            style="background: <?php echo $ipTrackingEnabled ? 'var(--accent-success)' : 'var(--accent-warning)'; ?>; color: var(--text-on-accent); padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                            <?php echo $ipTrackingEnabled ? 'ACTIVE' : 'DISABLED'; ?>
                        </span>
                    </div>
                </div>
            </div>

            <div
                style="margin-top: 20px; padding: 15px; background: var(--warning-bg); border-radius: 8px; border: 1px solid var(--accent-warning);">
                <p style="color: var(--warning-text); font-size: 13px; line-height: 1.6;">
                    <strong>💡 Security Tips:</strong><br>
                    • Change default credentials<br>
                    • Use firewall rules<br>
                    • Enable SSL/TLS connections<br>
                    • Regular security audits
                </p>
            </div>
        </div>
    </div>

    <!-- Audit Log -->
    <div style="background: var(--card-bg); padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <h3 style="margin-bottom: 20px; color: var(--text-primary); display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">📋</span> Recent Activity (Audit Log)
        </h3>

        <?php
        try {
            $auditCollection = $database->selectCollection('_audit_log');
            $recentLogs = $auditCollection->find([], [
                'sort' => ['timestamp' => -1],
                'limit' => 10
            ])->toArray();

            if (!empty($recentLogs)):
                ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: var(--surface-muted); border-bottom: 2px solid var(--table-border);">
                                <th style="padding: 12px; text-align: left; font-size: 13px; color: var(--text-secondary);">Timestamp</th>
                                <th style="padding: 12px; text-align: left; font-size: 13px; color: var(--text-secondary);">Action</th>
                                <th style="padding: 12px; text-align: left; font-size: 13px; color: var(--text-secondary);">User</th>
                                <th style="padding: 12px; text-align: left; font-size: 13px; color: var(--text-secondary);">IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentLogs as $log): ?>
                                <tr style="border-bottom: 1px solid var(--surface-muted);">
                                    <td style="padding: 12px; font-size: 12px;">
                                        <?php echo date('Y-m-d H:i:s', $log->timestamp->toDateTime()->getTimestamp()); ?>
                                    </td>
                                    <td style="padding: 12px; font-size: 12px;">
                                        <span
                                            style="background: var(--accent-primary); color: var(--text-on-accent); padding: 4px 10px; border-radius: 12px; font-size: 11px;">
                                            <?php echo htmlspecialchars($log->action); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 12px; font-size: 12px;"><?php echo htmlspecialchars($log->user->username ?? 'N/A'); ?></td>
                                    <td style="padding: 12px; font-size: 12px; font-family: monospace;">
                                        <?php echo htmlspecialchars($log->request->ip ?? 'N/A'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                    <p>No audit logs available</p>
                </div>
            <?php endif; ?>
        <?php } catch (Exception $e) {
            echo '<p style="color: var(--text-muted);">Audit log not available</p>';
        } ?>
    </div>

    <!-- Security Logs Viewer -->
    <div
        style="background: var(--card-bg); padding: 25px; border-radius: 12px; margin-top: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="color: var(--text-primary); display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">🔍</span> Security Logs
            </h3>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="clear_logs">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <button type="submit" class="btn"
                    style="background: var(--accent-danger); color: var(--text-on-accent); padding: 8px 16px; font-size: 13px;"
                    onclick="return confirm('Clear all security logs?')">🗑️ Clear Logs</button>
            </form>
        </div>

        <?php
        $logFile = __DIR__ . '/../../logs/security.log';
        if (file_exists($logFile)) {
            $logContent = file_get_contents($logFile);
            $logLines = array_filter(explode(PHP_EOL, $logContent));
            $logLines = array_slice(array_reverse($logLines), 0, 20); // Last 20 entries
        
            if (!empty($logLines)):
                ?>
                <div
                    style="max-height: 400px; overflow-y: auto; background: var(--surface-muted); padding: 15px; border-radius: 8px; font-family: monospace; font-size: 12px;">
                    <?php foreach ($logLines as $line):
                        $logEntry = json_decode($line, true);
                        if ($logEntry):
                            $severity = 'info';
                            if (strpos($logEntry['event'], 'failed') !== false || strpos($logEntry['event'], 'violation') !== false) {
                                $severity = 'danger';
                            } elseif (strpos($logEntry['event'], 'warning') !== false) {
                                $severity = 'warning';
                            }
                            $bgColor = $severity === 'danger' ? 'var(--danger-bg)' : ($severity === 'warning' ? 'var(--warning-bg)' : 'var(--info-bg)');
                            $textColor = $severity === 'danger' ? 'var(--danger-text)' : ($severity === 'warning' ? 'var(--warning-text)' : 'var(--info-text)');
                            ?>
                            <div
                                style="background: <?php echo $bgColor; ?>; color: <?php echo $textColor; ?>; padding: 10px; margin-bottom: 8px; border-radius: 6px; border-left: 3px solid currentColor;">
                                <div style="display: flex; justify-content: space-between; align-items: start;">
                                    <div style="flex: 1;">
                                        <strong><?php echo htmlspecialchars($logEntry['event']); ?></strong>
                                        <div style="margin-top: 5px; opacity: 0.8; font-size: 11px;">
                                            IP: <?php echo htmlspecialchars($logEntry['ip']); ?> |
                                            Session: <?php echo substr($logEntry['session'], 0, 10); ?>... |
                                            <?php echo htmlspecialchars($logEntry['timestamp']); ?>
                                        </div>
                                        <?php if (!empty($logEntry['details'])): ?>
                                            <div style="margin-top: 5px; opacity: 0.7; font-size: 11px;">
                                                Details: <?php echo htmlspecialchars(json_encode($logEntry['details'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php
                        endif;
                    endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                    <div style="font-size: 48px; margin-bottom: 15px;">📭</div>
                    <p>No security logs found</p>
                </div>
            <?php endif; ?>
        <?php } else { ?>
            <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                <div style="font-size: 48px; margin-bottom: 15px;">📝</div>
                <p>Log file not created yet</p>
            </div>
        <?php } ?>
    </div>
    <?php endif; ?>
</div>

    <!-- Settings Tab -->
