    <div id="dashboard" class="tab-content">
        <h2 style="margin-bottom: 25px; display: flex; align-items: center; gap: 10px;">
            🎯 Dashboard Overview
            <span style="font-size: 14px; color: var(--text-secondary); font-weight: normal;">Real-time collection insights</span>
        </h2>

        <div
            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div
                style="background: linear-gradient(135deg, var(--accent-primary) 0%, var(--accent-secondary) 100%); color: var(--text-on-accent); padding: 25px; border-radius: 12px; box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <p style="opacity: 0.9; font-size: 14px;">Total Documents</p>
                        <p style="font-size: 42px; font-weight: bold; margin: 10px 0;">
                            <?php echo number_format($documentCount); ?>
                        </p>
                        <p style="opacity: 0.8; font-size: 12px;">📈 Active Records</p>
                    </div>
                    <div style="font-size: 48px; opacity: 0.2;">📄</div>
                </div>
            </div>

            <div
                style="background: linear-gradient(135deg, var(--gradient-pink-start) 0%, var(--gradient-pink-end) 100%); color: var(--text-on-accent); padding: 25px; border-radius: 12px; box-shadow: 0 8px 20px rgba(240, 147, 251, 0.3);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <p style="opacity: 0.9; font-size: 14px;">Storage Size</p>
                        <p style="font-size: 42px; font-weight: bold; margin: 10px 0;">
                            <?php echo number_format($totalSize / 1024 / 1024, 1); ?> MB
                        </p>
                        <p style="opacity: 0.8; font-size: 12px;">💾 Total Disk Usage</p>
                    </div>
                    <div style="font-size: 48px; opacity: 0.2;">💽</div>
                </div>
            </div>

            <div
                style="background: linear-gradient(135deg, var(--gradient-sky-start) 0%, var(--gradient-sky-end) 100%); color: var(--text-on-accent); padding: 25px; border-radius: 12px; box-shadow: 0 8px 20px rgba(79, 172, 254, 0.3);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <p style="opacity: 0.9; font-size: 14px;">Avg Document Size</p>
                        <p style="font-size: 42px; font-weight: bold; margin: 10px 0;">
                            <?php echo number_format($avgDocSize / 1024, 1); ?> KB
                        </p>
                        <p style="opacity: 0.8; font-size: 12px;">📊 Per Record</p>
                    </div>
                    <div style="font-size: 48px; opacity: 0.2;">📏</div>
                </div>
            </div>

            <div
                style="background: linear-gradient(135deg, var(--gradient-green-start) 0%, var(--gradient-green-end) 100%); color: var(--text-on-accent); padding: 25px; border-radius: 12px; box-shadow: 0 8px 20px rgba(67, 233, 123, 0.3);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <p style="opacity: 0.9; font-size: 14px;">Collections</p>
                        <p style="font-size: 42px; font-weight: bold; margin: 10px 0;">
                            <?php echo count($collectionNames); ?>
                        </p>
                        <p style="opacity: 0.8; font-size: 12px;">📦 In Database</p>
                    </div>
                    <div style="font-size: 48px; opacity: 0.2;">🗂️</div>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div style="background: var(--card-bg); padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <h3 style="margin-bottom: 20px; color: var(--text-primary); display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 24px;">📈</span> Quick Actions
                </h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                    <button type="button" class="btn"
                        onclick="switchTab('add', document.querySelectorAll('.tab-btn')[3]); return false;"
                        style="background: var(--accent-success); color: var(--text-on-accent); padding: 15px; justify-content: center; display: flex; align-items: center; gap: 8px;">
                        ➕ Add New
                    </button>
                    <button type="button" class="btn"
                        onclick="switchTab('query', document.querySelectorAll('.tab-btn')[2]); return false;"
                        style="background: var(--accent-info); color: var(--text-on-accent); padding: 15px; justify-content: center; display: flex; align-items: center; gap: 8px;">
                        🔍 Query
                    </button>
                    <button type="button" class="btn"
                        onclick="switchTab('tools', document.querySelectorAll('.tab-btn')[5]); return false;"
                        style="background: var(--accent-warning); color: var(--text-primary); padding: 15px; justify-content: center; display: flex; align-items: center; gap: 8px;">
                        🛠️ Tools
                    </button>
                    <button type="button" class="btn"
                        onclick="switchTab('schema', document.querySelectorAll('.tab-btn')[8]); return false;"
                        style="background: var(--accent-indigo); color: var(--text-on-accent); padding: 15px; justify-content: center; display: flex; align-items: center; gap: 8px;">
                        📐 Schema
                    </button>
                </div>
            </div>

            <div style="background: var(--card-bg); padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <h3 style="margin-bottom: 20px; color: var(--text-primary); display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 24px;">⏱️</span> Status
                </h3>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <div
                        style="display: flex; justify-content: space-between; padding: 10px; background: var(--surface-muted); border-radius: 6px;">
                        <span style="color: var(--text-secondary);">Connection:</span>
                        <span style="color: var(--accent-success); font-weight: 600;">● Active</span>
                    </div>
                    <div
                        style="display: flex; justify-content: space-between; padding: 10px; background: var(--surface-muted); border-radius: 6px;">
                        <span style="color: var(--text-secondary);">Last Updated:</span>
                        <span style="font-weight: 600;"><?php echo date('H:i:s'); ?></span>
                    </div>
                    <div
                        style="display: flex; justify-content: space-between; padding: 10px; background: var(--surface-muted); border-radius: 6px;">
                        <span style="color: var(--text-secondary);">Database:</span>
                        <span style="font-weight: 600;"><?php echo htmlspecialchars($db); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div style="background: var(--card-bg); padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="margin-bottom: 20px; color: var(--text-primary); display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">📚</span> Collections Overview
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px;">
                <?php foreach ($collectionNames as $collName): ?>
                    <div onclick="switchCollection('<?php echo htmlspecialchars($collName); ?>')"
                        style="padding: 15px; background: linear-gradient(135deg, var(--surface-muted) 0%, var(--surface-muted) 100%); border-radius: 8px; cursor: pointer; transition: all 0.3s; border: 2px solid <?php echo $collName === $collectionName ? 'var(--accent-primary)' : 'transparent'; ?>; box-shadow: 0 2px 8px rgba(0,0,0,0.05);"
                        onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'"
                        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.05)'">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <span style="font-size: 20px;">📦</span>
                            <?php if ($collName === $collectionName): ?>
                                <span
                                    style="background: var(--accent-primary); color: var(--text-on-accent); padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: 600;">ACTIVE</span>
                            <?php endif; ?>
                        </div>
                        <p style="margin-top: 8px; font-weight: 600; color: var(--text-primary); font-size: 14px;">
                            <?php echo htmlspecialchars($collName); ?>
                        </p>
                        <p style="color: var(--text-secondary); font-size: 12px; margin-top: 4px;">Click to switch</p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Query Builder Tab -->
