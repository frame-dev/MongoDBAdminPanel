<div id="security" class="tab-content">
    <h2 style="margin-bottom: 20px;">🔒 Security & Backup</h2>

    <?php if (!userHasPermission('view_security') && !userHasPermission('manage_security')): ?>
        <div class="alert alert-warning">
            <span class="alert-icon">⚠️</span>
            <span class="alert-text">You don't have permission to access security settings. Contact an administrator.</span>
        </div>
    <?php else: ?>

    <div
        style="background: linear-gradient(135deg, #dc354515 0%, #ff000015 100%); padding: 20px; border-radius: 12px; margin-bottom: 20px; border-left: 4px solid #dc3545;">
        <p style="color: #721c24; line-height: 1.8;">
            <strong>⚠️ Important:</strong> This panel includes critical database operations.
            Always create backups before making bulk changes. CSRF protection and rate limiting are active.
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
        <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="margin-bottom: 20px; color: #333; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">💾</span> Database Backup
            </h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_backup">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <p style="color: #666; margin-bottom: 15px; font-size: 14px;">
                    Create a complete backup of all collections in this database. Backups are compressed and stored
                    locally.
                </p>
                <button type="submit" class="btn"
                    style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; width: 100%; padding: 15px; font-size: 16px;">
                    💾 Create Backup Now
                </button>
            </form>

            <?php if (!empty($backups)): ?>
                <div style="margin-top: 25px; padding-top: 20px; border-top: 2px solid #e9ecef;">
                    <h4 style="color: #333; margin-bottom: 15px; font-size: 14px;">📂 Available Backups</h4>
                    <?php foreach ($backups as $backup): ?>
                        <div
                            style="background: #f8f9fa; padding: 12px; border-radius: 6px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong
                                    style="color: #333; font-size: 13px;"><?php echo htmlspecialchars($backup['name']); ?></strong>
                                <p style="color: #666; font-size: 11px; margin-top: 4px;">
                                    <?php echo $backup['date']; ?> • <?php echo round($backup['size'] / 1024, 2); ?> KB
                                </p>
                            </div>
                            <a href="backups/<?php echo htmlspecialchars($backup['name']); ?>" download class="btn"
                                style="background: #17a2b8; color: white; padding: 6px 12px; font-size: 11px; text-decoration: none;">
                                📥 Download
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Security Settings -->
        <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="margin-bottom: 20px; color: #333; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">🛡️</span> Security Settings
            </h3>

            <div style="display: flex; flex-direction: column; gap: 15px;">
                <div
                    style="padding: 15px; background: linear-gradient(135deg, #28a74515 0%, #20c99715 100%); border-radius: 8px; border-left: 3px solid #28a745;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="color: #333;">CSRF Protection</strong>
                            <p style="color: #666; font-size: 12px; margin-top: 4px;">Prevents cross-site request
                                forgery</p>
                        </div>
                        <span
                            style="background: #28a745; color: white; padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: 600;">ACTIVE</span>
                    </div>
                </div>

                <div
                    style="padding: 15px; background: linear-gradient(135deg, #28a74515 0%, #20c99715 100%); border-radius: 8px; border-left: 3px solid #28a745;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="color: #333;">Rate Limiting</strong>
                            <p style="color: #666; font-size: 12px; margin-top: 4px;">30 requests per minute</p>
                        </div>
                        <span
                            style="background: #28a745; color: white; padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: 600;">ACTIVE</span>
                    </div>
                </div>

                <div
                    style="padding: 15px; background: linear-gradient(135deg, #28a74515 0%, #20c99715 100%); border-radius: 8px; border-left: 3px solid #28a745;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="color: #333;">Input Sanitization</strong>
                            <p style="color: #666; font-size: 12px; margin-top: 4px;">XSS and injection prevention
                            </p>
                        </div>
                        <span
                            style="background: #28a745; color: white; padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: 600;">ACTIVE</span>
                    </div>
                </div>

                <div
                    style="padding: 15px; background: linear-gradient(135deg, #28a74515 0%, #20c99715 100%); border-radius: 8px; border-left: 3px solid #28a745;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="color: #333;">Query Validation</strong>
                            <p style="color: #666; font-size: 12px; margin-top: 4px;">Operator whitelisting enabled
                            </p>
                        </div>
                        <span
                            style="background: #28a745; color: white; padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: 600;">ACTIVE</span>
                    </div>
                </div>
            </div>

            <div
                style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 8px; border: 1px solid #ffc107;">
                <p style="color: #856404; font-size: 13px; line-height: 1.6;">
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
    <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <h3 style="margin-bottom: 20px; color: #333; display: flex; align-items: center; gap: 10px;">
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
                            <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                                <th style="padding: 12px; text-align: left; font-size: 13px; color: #666;">Timestamp</th>
                                <th style="padding: 12px; text-align: left; font-size: 13px; color: #666;">Action</th>
                                <th style="padding: 12px; text-align: left; font-size: 13px; color: #666;">User</th>
                                <th style="padding: 12px; text-align: left; font-size: 13px; color: #666;">IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentLogs as $log): ?>
                                <tr style="border-bottom: 1px solid #e9ecef;">
                                    <td style="padding: 12px; font-size: 12px;">
                                        <?php echo date('Y-m-d H:i:s', $log->timestamp->toDateTime()->getTimestamp()); ?>
                                    </td>
                                    <td style="padding: 12px; font-size: 12px;">
                                        <span
                                            style="background: #667eea; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px;">
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
                <div style="text-align: center; padding: 40px; color: #999;">
                    <p>No audit logs available</p>
                </div>
            <?php endif; ?>
        <?php } catch (Exception $e) {
            echo '<p style="color: #999;">Audit log not available</p>';
        } ?>
    </div>

    <!-- Security Logs Viewer -->
    <div
        style="background: white; padding: 25px; border-radius: 12px; margin-top: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="color: #333; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">🔍</span> Security Logs
            </h3>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="clear_logs">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <button type="submit" class="btn"
                    style="background: #dc3545; color: white; padding: 8px 16px; font-size: 13px;"
                    onclick="return confirm('Clear all security logs?')">🗑️ Clear Logs</button>
            </form>
        </div>

        <?php
        $logFile = __DIR__ . '/logs/security.log';
        if (file_exists($logFile)) {
            $logContent = file_get_contents($logFile);
            $logLines = array_filter(explode(PHP_EOL, $logContent));
            $logLines = array_slice(array_reverse($logLines), 0, 20); // Last 20 entries
        
            if (!empty($logLines)):
                ?>
                <div
                    style="max-height: 400px; overflow-y: auto; background: #f8f9fa; padding: 15px; border-radius: 8px; font-family: monospace; font-size: 12px;">
                    <?php foreach ($logLines as $line):
                        $logEntry = json_decode($line, true);
                        if ($logEntry):
                            $severity = 'info';
                            if (strpos($logEntry['event'], 'failed') !== false || strpos($logEntry['event'], 'violation') !== false) {
                                $severity = 'danger';
                            } elseif (strpos($logEntry['event'], 'warning') !== false) {
                                $severity = 'warning';
                            }
                            $bgColor = $severity === 'danger' ? '#f8d7da' : ($severity === 'warning' ? '#fff3cd' : '#d1ecf1');
                            $textColor = $severity === 'danger' ? '#721c24' : ($severity === 'warning' ? '#856404' : '#0c5460');
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
                <div style="text-align: center; padding: 40px; color: #666;">
                    <div style="font-size: 48px; margin-bottom: 15px;">📭</div>
                    <p>No security logs found</p>
                </div>
            <?php endif; ?>
        <?php } else { ?>
            <div style="text-align: center; padding: 40px; color: #666;">
                <div style="font-size: 48px; margin-bottom: 15px;">📝</div>
                <p>Log file not created yet</p>
            </div>
        <?php } ?>
    </div>
    <?php endif; ?>
</div>

    <!-- Settings Tab -->
