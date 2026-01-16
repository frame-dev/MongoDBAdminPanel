    <div id="settings" class="tab-content">
        <h2 style="margin-bottom: 25px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 32px;">⚙️</span> Application Settings
        </h2>

        <?php $canEditSettings = userHasPermission('edit_settings'); ?>

        <!-- Connection Settings -->
        <div
            style="background: white; padding: 25px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="color: #333; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">🔌</span> Connection Settings
            </h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #667eea;">
                    <h4 style="color: #667eea; margin-bottom: 15px;">Current Connection</h4>
                    <div style="display: grid; gap: 10px; font-size: 14px;">
                        <div><strong>Host:</strong> <span
                                style="font-family: monospace; color: #666;"><?php echo htmlspecialchars($_SESSION['hostname'] ?? 'localhost'); ?></span>
                        </div>
                        <div><strong>Port:</strong> <span
                                style="font-family: monospace; color: #666;"><?php echo htmlspecialchars($_SESSION['port'] ?? '27017'); ?></span>
                        </div>
                        <div><strong>Database:</strong> <span
                                style="font-family: monospace; color: #28a745;"><?php echo htmlspecialchars($_SESSION['database'] ?? 'N/A'); ?></span>
                        </div>
                        <div><strong>Collection:</strong> <span
                                style="font-family: monospace; color: #17a2b8;"><?php echo htmlspecialchars($_SESSION['collection'] ?? 'N/A'); ?></span>
                        </div>
                        <div><strong>Username:</strong> <span
                                style="font-family: monospace; color: #666;"><?php echo !empty($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : '<i>None</i>'; ?></span>
                        </div>
                    </div>
                </div>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #28a745;">
                    <h4 style="color: #28a745; margin-bottom: 15px;">Connection Options</h4>
                    <div style="display: grid; gap: 8px; font-size: 13px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" checked disabled>
                            <span>Persistent Connections</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" checked disabled>
                            <span>Auto-reconnect on Timeout</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" checked disabled>
                            <span>Connection Pooling</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" checked disabled>
                            <span>SSL/TLS Encryption</span>
                        </label>
                    </div>
                    <a href="templates/connection.php" class="btn"
                        style="display: inline-block; margin-top: 15px; background: #28a745; color: white; padding: 8px 16px; text-decoration: none; border-radius: 6px; font-size: 13px;">🔄
                        Change Connection</a>
                </div>
            </div>
        </div>

        <!-- Display Preferences -->
        <div
            style="background: white; padding: 25px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="color: #333; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">🎨</span> Display Preferences
            </h3>
            <form method="POST">
                <input type="hidden" name="action" value="save_display_settings">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                    <div>
                        <label style="font-weight: 600; margin-bottom: 8px; display: block;">Items per Page:</label>
                        <select name="items_per_page"
                            style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                            <option value="25" <?php echo ($_SESSION['settings']['items_per_page'] ?? 50) == 25 ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo ($_SESSION['settings']['items_per_page'] ?? 50) == 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo ($_SESSION['settings']['items_per_page'] ?? 50) == 100 ? 'selected' : ''; ?>>100</option>
                            <option value="200" <?php echo ($_SESSION['settings']['items_per_page'] ?? 50) == 200 ? 'selected' : ''; ?>>200</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-weight: 600; margin-bottom: 8px; display: block;">Date Format:</label>
                        <select name="date_format"
                            style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                            <option value="Y-m-d H:i:s" <?php echo ($_SESSION['settings']['date_format'] ?? 'Y-m-d H:i:s') == 'Y-m-d H:i:s' ? 'selected' : ''; ?>>YYYY-MM-DD HH:MM:SS</option>
                            <option value="d/m/Y H:i" <?php echo ($_SESSION['settings']['date_format'] ?? '') == 'd/m/Y H:i' ? 'selected' : ''; ?>>DD/MM/YYYY HH:MM</option>
                            <option value="m/d/Y h:i A" <?php echo ($_SESSION['settings']['date_format'] ?? '') == 'm/d/Y h:i A' ? 'selected' : ''; ?>>MM/DD/YYYY HH:MM AM/PM</option>
                            <option value="relative" <?php echo ($_SESSION['settings']['date_format'] ?? '') == 'relative' ? 'selected' : ''; ?>>Relative (2 hours ago)</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-weight: 600; margin-bottom: 8px; display: block;">Theme:</label>
                        <select name="theme"
                            style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                            <option value="light" <?php echo ($_SESSION['settings']['theme'] ?? 'light') == 'light' ? 'selected' : ''; ?>>Light</option>
                            <option value="dark" <?php echo ($_SESSION['settings']['theme'] ?? 'light') == 'dark' ? 'selected' : ''; ?>>Dark</option>
                            <option value="auto" <?php echo ($_SESSION['settings']['theme'] ?? 'light') == 'auto' ? 'selected' : ''; ?>>Auto (System)</option>
                        </select>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                    <div>
                        <label style="font-weight: 600; margin-bottom: 8px; display: block;">JSON Display:</label>
                        <div style="display: grid; gap: 8px;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="syntax_highlighting" value="1" <?php echo ($_SESSION['settings']['syntax_highlighting'] ?? true) ? 'checked' : ''; ?>>
                                <span>Syntax Highlighting</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="pretty_print" value="1" <?php echo ($_SESSION['settings']['pretty_print'] ?? true) ? 'checked' : ''; ?>>
                                <span>Pretty Print (Formatted)</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="show_objectid_as_string" value="1" <?php echo ($_SESSION['settings']['show_objectid_as_string'] ?? false) ? 'checked' : ''; ?>>
                                <span>Show ObjectId as String</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="collapsible_json" value="1" <?php echo ($_SESSION['settings']['collapsible_json'] ?? false) ? 'checked' : ''; ?>>
                                <span>Collapsible JSON Trees</span>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label style="font-weight: 600; margin-bottom: 8px; display: block;">Table Display:</label>
                        <div style="display: grid; gap: 8px;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="zebra_stripes" value="1" <?php echo ($_SESSION['settings']['zebra_stripes'] ?? true) ? 'checked' : ''; ?>>
                                <span>Zebra Stripes</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="row_hover" value="1" <?php echo ($_SESSION['settings']['row_hover'] ?? true) ? 'checked' : ''; ?>>
                                <span>Row Hover Effect</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="fixed_header" value="1" <?php echo ($_SESSION['settings']['fixed_header'] ?? false) ? 'checked' : ''; ?>>
                                <span>Fixed Header</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="compact_mode" value="1" <?php echo ($_SESSION['settings']['compact_mode'] ?? false) ? 'checked' : ''; ?>>
                                <span>Compact Mode</span>
                            </label>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn"
                    style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 30px; margin-top: 20px;"
                    <?php echo !$canEditSettings ? 'disabled title="You do not have permission to edit settings"' : ''; ?>>💾
                    Save Display Settings</button>
            </form>
            <?php if (!$canEditSettings): ?>
                <div style="margin-top: 15px; padding: 12px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 6px; font-size: 13px; color: #856404;">
                    ⚠️ You have read-only access to settings. Contact an administrator to modify these values.
                </div>
            <?php endif; ?>
        </div>

        <!-- Performance Settings -->
        <div
            style="background: white; padding: 25px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="color: #333; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">⚡</span> Performance Settings
            </h3>
            <form method="POST">
                <input type="hidden" name="action" value="save_performance_settings">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                        <h4 style="color: #333; margin-bottom: 15px;">Query Optimization</h4>
                        <div style="display: grid; gap: 12px;">
                            <div>
                                <label
                                    style="font-weight: 600; margin-bottom: 8px; display: block; font-size: 14px;">Query
                                    Timeout (seconds):</label>
                                <input type="number" name="query_timeout"
                                    value="<?php echo $_SESSION['settings']['query_timeout'] ?? 30; ?>" min="5"
                                    max="300"
                                    style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 6px;">
                            </div>
                            <div>
                                <label
                                    style="font-weight: 600; margin-bottom: 8px; display: block; font-size: 14px;">Max
                                    Results Limit:</label>
                                <input type="number" name="max_results"
                                    value="<?php echo $_SESSION['settings']['max_results'] ?? 1000; ?>" min="100"
                                    max="10000"
                                    style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 6px;">
                            </div>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="query_cache" value="1" <?php echo ($_SESSION['settings']['query_cache'] ?? true) ? 'checked' : ''; ?>>
                                <span style="font-size: 14px;">Enable Query Caching</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="auto_indexes" value="1" <?php echo ($_SESSION['settings']['auto_indexes'] ?? true) ? 'checked' : ''; ?>>
                                <span style="font-size: 14px;">Use Indexes Automatically</span>
                            </label>
                        </div>
                    </div>
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                        <h4 style="color: #333; margin-bottom: 15px;">Memory & Cache</h4>
                        <div style="display: grid; gap: 12px;">
                            <div>
                                <label
                                    style="font-weight: 600; margin-bottom: 8px; display: block; font-size: 14px;">Memory
                                    Limit (MB):</label>
                                <input type="number" name="memory_limit"
                                    value="<?php echo $_SESSION['settings']['memory_limit'] ?? 256; ?>" min="128"
                                    max="2048"
                                    style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 6px;">
                            </div>
                            <div>
                                <label
                                    style="font-weight: 600; margin-bottom: 8px; display: block; font-size: 14px;">Cache
                                    TTL (minutes):</label>
                                <input type="number" name="cache_ttl"
                                    value="<?php echo $_SESSION['settings']['cache_ttl'] ?? 15; ?>" min="1" max="1440"
                                    style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 6px;">
                            </div>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="schema_cache" value="1" <?php echo ($_SESSION['settings']['schema_cache'] ?? false) ? 'checked' : ''; ?>>
                                <span style="font-size: 14px;">Enable Schema Caching</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="lazy_load" value="1" <?php echo ($_SESSION['settings']['lazy_load'] ?? false) ? 'checked' : ''; ?>>
                                <span style="font-size: 14px;">Lazy Load Large Documents</span>
                            </label>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn"
                    style="background: #ffc107; color: #333; padding: 12px 30px; margin-top: 20px;">⚡ Save
                    Performance
                    Settings</button>
            </form>
        </div>

        <!-- Security Settings -->
        <div
            style="background: white; padding: 25px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="color: #333; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">🔒</span> Security Settings
            </h3>
            <form method="POST">
                <input type="hidden" name="action" value="save_security_settings">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div
                        style="background: #fff3cd; padding: 20px; border-radius: 8px; border-left: 4px solid #ffc107;">
                        <h4 style="color: #856404; margin-bottom: 15px;">CSRF Protection</h4>
                        <div style="display: grid; gap: 10px;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" checked disabled>
                                <span style="font-size: 14px;">✅ CSRF Tokens Enabled</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" checked disabled>
                                <span style="font-size: 14px;">✅ Session Validation</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" checked disabled>
                                <span style="font-size: 14px;">✅ IP Address Tracking</span>
                            </label>
                            <div style="margin-top: 10px; padding: 10px; background: white; border-radius: 6px;">
                                <label
                                    style="font-weight: 600; margin-bottom: 8px; display: block; font-size: 13px;">Token
                                    Lifetime (minutes):</label>
                                <input type="number" name="csrf_token_lifetime"
                                    value="<?php echo $_SESSION['settings']['csrf_token_lifetime'] ?? 60; ?>" min="10"
                                    max="1440"
                                    style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 6px;">
                            </div>
                        </div>
                    </div>
                    <div
                        style="background: #f8d7da; padding: 20px; border-radius: 8px; border-left: 4px solid #dc3545;">
                        <h4 style="color: #721c24; margin-bottom: 15px;">Rate Limiting</h4>
                        <div style="display: grid; gap: 10px;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" checked disabled>
                                <span style="font-size: 14px;">✅ Rate Limiting Active</span>
                            </label>
                            <div style="background: white; padding: 10px; border-radius: 6px; margin-top: 5px;">
                                <label
                                    style="font-weight: 600; margin-bottom: 8px; display: block; font-size: 13px;">Max
                                    Requests/Minute:</label>
                                <input type="number" name="rate_limit_requests"
                                    value="<?php echo $_SESSION['settings']['rate_limit_requests'] ?? 30; ?>" min="10"
                                    max="1000"
                                    style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 6px;">
                            </div>
                            <div style="background: white; padding: 10px; border-radius: 6px;">
                                <label
                                    style="font-weight: 600; margin-bottom: 8px; display: block; font-size: 13px;">Lockout
                                    Duration (seconds):</label>
                                <input type="number" name="rate_limit_lockout"
                                    value="<?php echo $_SESSION['settings']['rate_limit_lockout'] ?? 60; ?>" min="30"
                                    max="3600"
                                    style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 6px;">
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    style="margin-top: 20px; padding: 15px; background: #d1ecf1; border-radius: 8px; border-left: 4px solid #17a2b8;">
                    <h4 style="color: #0c5460; margin-bottom: 10px;">Audit Logging</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="log_all_actions" value="1" <?php echo ($_SESSION['settings']['log_all_actions'] ?? true) ? 'checked' : ''; ?>>
                            <span style="font-size: 14px;">Log All Actions</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="log_failed_logins" value="1" <?php echo ($_SESSION['settings']['log_failed_logins'] ?? true) ? 'checked' : ''; ?>>
                            <span style="font-size: 14px;">Log Failed Logins</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="log_security_events" value="1" <?php echo ($_SESSION['settings']['log_security_events'] ?? true) ? 'checked' : ''; ?>>
                            <span style="font-size: 14px;">Log Security Events</span>
                        </label>
                    </div>
                </div>
                <button type="submit" class="btn"
                    style="background: #dc3545; color: white; padding: 12px 30px; margin-top: 20px;">🔒 Save
                    Security
                    Settings</button>
            </form>
        </div>

        <!-- Editor & Behavior Settings -->
        <div
            style="background: white; padding: 25px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="color: #333; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">📝</span> Editor & Behavior Settings
            </h3>
            <form method="POST">
                <input type="hidden" name="action" value="save_editor_settings">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                        <h4 style="color: #333; margin-bottom: 15px;">Code Editor</h4>
                        <div style="display: grid; gap: 12px;">
                            <div>
                                <label style="font-weight: 600; margin-bottom: 8px; display: block; font-size: 14px;">Editor Theme:</label>
                                <select name="editor_theme" style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 6px;">
                                    <option value="monokai" <?php echo ($_SESSION['settings']['editor_theme'] ?? 'monokai') == 'monokai' ? 'selected' : ''; ?>>Monokai (Dark)</option>
                                    <option value="github" <?php echo ($_SESSION['settings']['editor_theme'] ?? '') == 'github' ? 'selected' : ''; ?>>GitHub (Light)</option>
                                    <option value="dracula" <?php echo ($_SESSION['settings']['editor_theme'] ?? '') == 'dracula' ? 'selected' : ''; ?>>Dracula (Dark)</option>
                                    <option value="solarized" <?php echo ($_SESSION['settings']['editor_theme'] ?? '') == 'solarized' ? 'selected' : ''; ?>>Solarized</option>
                                </select>
                            </div>
                            <div>
                                <label style="font-weight: 600; margin-bottom: 8px; display: block; font-size: 14px;">Font Size:</label>
                                <select name="editor_font_size" style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 6px;">
                                    <option value="12" <?php echo ($_SESSION['settings']['editor_font_size'] ?? 14) == 12 ? 'selected' : ''; ?>>12px</option>
                                    <option value="14" <?php echo ($_SESSION['settings']['editor_font_size'] ?? 14) == 14 ? 'selected' : ''; ?>>14px</option>
                                    <option value="16" <?php echo ($_SESSION['settings']['editor_font_size'] ?? 14) == 16 ? 'selected' : ''; ?>>16px</option>
                                    <option value="18" <?php echo ($_SESSION['settings']['editor_font_size'] ?? 14) == 18 ? 'selected' : ''; ?>>18px</option>
                                </select>
                            </div>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="line_numbers" value="1" <?php echo ($_SESSION['settings']['line_numbers'] ?? true) ? 'checked' : ''; ?>>
                                <span style="font-size: 14px;">Show Line Numbers</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="auto_format" value="1" <?php echo ($_SESSION['settings']['auto_format'] ?? true) ? 'checked' : ''; ?>>
                                <span style="font-size: 14px;">Auto-format JSON</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="validate_on_type" value="1" <?php echo ($_SESSION['settings']['validate_on_type'] ?? false) ? 'checked' : ''; ?>>
                                <span style="font-size: 14px;">Validate while typing</span>
                            </label>
                        </div>
                    </div>
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                        <h4 style="color: #333; margin-bottom: 15px;">Interface Behavior</h4>
                        <div style="display: grid; gap: 12px;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="auto_refresh" value="1" <?php echo ($_SESSION['settings']['auto_refresh'] ?? false) ? 'checked' : ''; ?>>
                                <span style="font-size: 14px;">Auto-refresh data</span>
                            </label>
                            <div>
                                <label style="font-weight: 600; margin-bottom: 8px; display: block; font-size: 14px;">Refresh Interval (seconds):</label>
                                <input type="number" name="refresh_interval" value="<?php echo $_SESSION['settings']['refresh_interval'] ?? 30; ?>" 
                                    min="5" max="300" style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 6px;">
                            </div>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="confirm_deletions" value="1" <?php echo ($_SESSION['settings']['confirm_deletions'] ?? true) ? 'checked' : ''; ?>>
                                <span style="font-size: 14px;">Confirm before deleting</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="show_tooltips" value="1" <?php echo ($_SESSION['settings']['show_tooltips'] ?? true) ? 'checked' : ''; ?>>
                                <span style="font-size: 14px;">Show tooltips</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="keyboard_shortcuts" value="1" <?php echo ($_SESSION['settings']['keyboard_shortcuts'] ?? true) ? 'checked' : ''; ?>>
                                <span style="font-size: 14px;">Enable keyboard shortcuts</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="save_scroll_position" value="1" <?php echo ($_SESSION['settings']['save_scroll_position'] ?? false) ? 'checked' : ''; ?>>
                                <span style="font-size: 14px;">Remember scroll position</span>
                            </label>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn"
                    style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 30px; margin-top: 20px;">💾
                    Save Editor Settings</button>
            </form>
        </div>

        <!-- Notifications & Alerts Settings -->
        <div
            style="background: white; padding: 25px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="color: #333; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">🔔</span> Notifications & Alerts
            </h3>
            <form method="POST">
                <input type="hidden" name="action" value="save_notification_settings">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div style="background: #e3f2fd; padding: 20px; border-radius: 8px; border-left: 4px solid #2196f3;">
                        <h4 style="color: #1565c0; margin-bottom: 15px;">Alert Preferences</h4>
                        <div style="display: grid; gap: 10px;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="show_success_messages" value="1" <?php echo ($_SESSION['settings']['show_success_messages'] ?? true) ? 'checked' : ''; ?>>
                                <span style="font-size: 14px;">Show success messages</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="show_error_messages" value="1" <?php echo ($_SESSION['settings']['show_error_messages'] ?? true) ? 'checked' : ''; ?>>
                                <span style="font-size: 14px;">Show error messages</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="show_warning_messages" value="1" <?php echo ($_SESSION['settings']['show_warning_messages'] ?? true) ? 'checked' : ''; ?>>
                                <span style="font-size: 14px;">Show warning messages</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="auto_dismiss_alerts" value="1" <?php echo ($_SESSION['settings']['auto_dismiss_alerts'] ?? true) ? 'checked' : ''; ?>>
                                <span style="font-size: 14px;">Auto-dismiss alerts</span>
                            </label>
                            <div>
                                <label style="font-weight: 600; margin-bottom: 8px; display: block; font-size: 14px;">Alert Duration (seconds):</label>
                                <input type="number" name="alert_duration" value="<?php echo $_SESSION['settings']['alert_duration'] ?? 5; ?>" 
                                    min="2" max="30" style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 6px;">
                            </div>
                        </div>
                    </div>
                    <div style="background: #fff3cd; padding: 20px; border-radius: 8px; border-left: 4px solid #ffc107;">
                        <h4 style="color: #856404; margin-bottom: 15px;">Sound & Visual</h4>
                        <div style="display: grid; gap: 10px;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="enable_sounds" value="1" <?php echo ($_SESSION['settings']['enable_sounds'] ?? false) ? 'checked' : ''; ?>>
                                <span style="font-size: 14px;">Enable sound alerts</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="desktop_notifications" value="1" <?php echo ($_SESSION['settings']['desktop_notifications'] ?? false) ? 'checked' : ''; ?>>
                                <span style="font-size: 14px;">Desktop notifications</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="animation_effects" value="1" <?php echo ($_SESSION['settings']['animation_effects'] ?? true) ? 'checked' : ''; ?>>
                                <span style="font-size: 14px;">Enable animations</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="loading_indicators" value="1" <?php echo ($_SESSION['settings']['loading_indicators'] ?? true) ? 'checked' : ''; ?>>
                                <span style="font-size: 14px;">Show loading indicators</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="progress_bars" value="1" <?php echo ($_SESSION['settings']['progress_bars'] ?? true) ? 'checked' : ''; ?>>
                                <span style="font-size: 14px;">Show progress bars</span>
                            </label>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn"
                    style="background: #17a2b8; color: white; padding: 12px 30px; margin-top: 20px;">🔔 Save
                    Notification Settings</button>
            </form>
        </div>

        <!-- Export & Backup Settings -->
        <div
            style="background: white; padding: 25px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="color: #333; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">💾</span> Export & Backup Settings
            </h3>
            <form method="POST">
                <input type="hidden" name="action" value="save_export_settings">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                        <h4 style="color: #333; margin-bottom: 15px;">Export Preferences</h4>
                        <div style="display: grid; gap: 12px;">
                            <div>
                                <label style="font-weight: 600; margin-bottom: 8px; display: block; font-size: 14px;">Default Export Format:</label>
                                <select name="default_export_format" style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 6px;">
                                    <option value="json" <?php echo ($_SESSION['settings']['default_export_format'] ?? 'json') == 'json' ? 'selected' : ''; ?>>JSON</option>
                                    <option value="csv" <?php echo ($_SESSION['settings']['default_export_format'] ?? '') == 'csv' ? 'selected' : ''; ?>>CSV</option>
                                    <option value="excel" <?php echo ($_SESSION['settings']['default_export_format'] ?? '') == 'excel' ? 'selected' : ''; ?>>Excel</option>
                                    <option value="xml" <?php echo ($_SESSION['settings']['default_export_format'] ?? '') == 'xml' ? 'selected' : ''; ?>>XML</option>
                                </select>
                            </div>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="include_metadata" value="1" <?php echo ($_SESSION['settings']['include_metadata'] ?? true) ? 'checked' : ''; ?>>
                                <span style="font-size: 14px;">Include metadata in exports</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="compress_exports" value="1" <?php echo ($_SESSION['settings']['compress_exports'] ?? false) ? 'checked' : ''; ?>>
                                <span style="font-size: 14px;">Compress export files</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="timestamp_exports" value="1" <?php echo ($_SESSION['settings']['timestamp_exports'] ?? true) ? 'checked' : ''; ?>>
                                <span style="font-size: 14px;">Add timestamp to filenames</span>
                            </label>
                        </div>
                    </div>
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                        <h4 style="color: #333; margin-bottom: 15px;">Automatic Backups</h4>
                        <div style="display: grid; gap: 12px;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="auto_backup" value="1" <?php echo ($_SESSION['settings']['auto_backup'] ?? false) ? 'checked' : ''; ?>>
                                <span style="font-size: 14px;">Enable automatic backups</span>
                            </label>
                            <div>
                                <label style="font-weight: 600; margin-bottom: 8px; display: block; font-size: 14px;">Backup Frequency:</label>
                                <select name="backup_frequency" style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 6px;">
                                    <option value="daily" <?php echo ($_SESSION['settings']['backup_frequency'] ?? 'weekly') == 'daily' ? 'selected' : ''; ?>>Daily</option>
                                    <option value="weekly" <?php echo ($_SESSION['settings']['backup_frequency'] ?? 'weekly') == 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                    <option value="monthly" <?php echo ($_SESSION['settings']['backup_frequency'] ?? 'weekly') == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                </select>
                            </div>
                            <div>
                                <label style="font-weight: 600; margin-bottom: 8px; display: block; font-size: 14px;">Keep Backups (days):</label>
                                <input type="number" name="backup_retention" value="<?php echo $_SESSION['settings']['backup_retention'] ?? 30; ?>" 
                                    min="1" max="365" style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 6px;">
                            </div>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn"
                    style="background: #28a745; color: white; padding: 12px 30px; margin-top: 20px;">💾 Save
                    Export Settings</button>
            </form>
        </div>

        <!-- System Information -->
        <div
            style="background: white; padding: 25px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="color: #333; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">💻</span> System Information
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                <div
                    style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px;">
                    <p style="font-size: 13px; opacity: 0.9; margin-bottom: 5px;">PHP Version</p>
                    <p style="font-size: 24px; font-weight: bold;"><?php echo phpversion(); ?></p>
                </div>
                <div
                    style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 20px; border-radius: 10px;">
                    <p style="font-size: 13px; opacity: 0.9; margin-bottom: 5px;">MongoDB Extension</p>
                    <p style="font-size: 24px; font-weight: bold;"><?php echo phpversion('mongodb') ?: 'N/A'; ?></p>
                </div>
                <div
                    style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 20px; border-radius: 10px;">
                    <p style="font-size: 13px; opacity: 0.9; margin-bottom: 5px;">Server Software</p>
                    <p style="font-size: 16px; font-weight: bold;">
                        <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?>
                    </p>
                </div>
                <div
                    style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; padding: 20px; border-radius: 10px;">
                    <p style="font-size: 13px; opacity: 0.9; margin-bottom: 5px;">Memory Limit</p>
                    <p style="font-size: 24px; font-weight: bold;"><?php echo ini_get('memory_limit'); ?></p>
                </div>
            </div>

            <div style="margin-top: 20px; background: #f8f9fa; padding: 20px; border-radius: 8px;">
                <h4 style="color: #333; margin-bottom: 15px;">Loaded Extensions</h4>
                <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                    <?php
                    $extensions = ['mongodb', 'json', 'mbstring', 'openssl', 'curl', 'session', 'fileinfo'];
                    foreach ($extensions as $ext) {
                        $loaded = extension_loaded($ext);
                        $bgColor = $loaded ? '#d4edda' : '#f8d7da';
                        $textColor = $loaded ? '#155724' : '#721c24';
                        $icon = $loaded ? '✅' : '❌';
                        echo '<span style="background: ' . $bgColor . '; color: ' . $textColor . '; padding: 6px 12px; border-radius: 16px; font-size: 12px; font-weight: 600;">' . $icon . ' ' . $ext . '</span>';
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Export/Import Settings -->
        <div
            style="background: white; padding: 25px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="color: #333; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">📦</span> Settings Management
            </h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div style="background: #e8f5e9; padding: 20px; border-radius: 8px; border-left: 4px solid #4caf50;">
                    <h4 style="color: #2e7d32; margin-bottom: 15px;">📤 Export Settings</h4>
                    <p style="color: #666; font-size: 14px; margin-bottom: 15px;">Download all your application
                        settings
                        as
                        a JSON file for backup or migration.</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="export_settings">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <button type="submit" class="btn"
                            style="background: #4caf50; color: white; width: 100%; padding: 12px;">📥 Export
                            Settings
                            JSON</button>
                    </form>
                </div>
                <div style="background: #e3f2fd; padding: 20px; border-radius: 8px; border-left: 4px solid #2196f3;">
                    <h4 style="color: #1565c0; margin-bottom: 15px;">📥 Import Settings</h4>
                    <p style="color: #666; font-size: 14px; margin-bottom: 15px;">Upload a settings JSON file to
                        restore
                        your configuration.</p>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="import_settings">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="file" name="settings_file" accept=".json" required
                            style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 6px; margin-bottom: 10px;">
                        <button type="submit" class="btn"
                            style="background: #2196f3; color: white; width: 100%; padding: 12px;">⬆️ Import
                            Settings</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Danger Zone -->
        <div
            style="background: white; padding: 25px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: 2px solid #dc3545;">
            <h3 style="color: #dc3545; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">⚠️</span> Danger Zone
            </h3>
            <div style="background: #f8d7da; padding: 20px; border-radius: 8px; border-left: 4px solid #dc3545;">
                <div style="display: grid; gap: 15px;">
                    <div>
                        <h4 style="color: #721c24; margin-bottom: 10px;">🗑️ Clear Application Cache</h4>
                        <p style="color: #666; font-size: 14px; margin-bottom: 10px;">Remove all cached data
                            including
                            query
                            results and schema information.</p>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Clear all cache?');">
                            <input type="hidden" name="action" value="clear_cache">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <button type="submit" class="btn"
                                style="background: #ffc107; color: #333; padding: 10px 20px;">🗑️ Clear
                                Cache</button>
                        </form>
                    </div>
                    <div style="border-top: 1px solid #f5c6cb; padding-top: 15px;">
                        <h4 style="color: #721c24; margin-bottom: 10px;">🔄 Reset All Settings</h4>
                        <p style="color: #666; font-size: 14px; margin-bottom: 10px;">Reset all application settings
                            to
                            default values. This cannot be undone!</p>
                        <form method="POST" style="display: inline;"
                            onsubmit="return confirm('Reset ALL settings to defaults? This cannot be undone!');">
                            <input type="hidden" name="action" value="reset_settings">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <button type="submit" class="btn"
                                style="background: #dc3545; color: white; padding: 10px 20px;">⚠️ Reset to
                                Defaults</button>
                        </form>
                    </div>
                    <div style="border-top: 1px solid #f5c6cb; padding-top: 15px;">
                        <h4 style="color: #721c24; margin-bottom: 10px;">🧹 Clear Session Data</h4>
                        <p style="color: #666; font-size: 14px; margin-bottom: 10px;">End current session and clear
                            all
                            stored session data.</p>
                        <a href="templates/connection.php" class="btn"
                            style="background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; display: inline-block;">🚪
                            Logout & Clear Session</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
