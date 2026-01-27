<!-- User Management Tab -->
<div id="users" class="tab-content">
    <div class="card">
        <div class="card-header">
            <h2>👥 User Management</h2>
            <p style="margin: 8px 0 0 0; color: var(--text-secondary); font-size: 14px;">Manage user accounts, roles, and permissions</p>
        </div>
        <div class="card-body">
            <div style="background: var(--surface-muted); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                <h3 style="margin: 0 0 10px 0;">🔒 Change Your Password</h3>
                <form method="POST" onsubmit="return validateChangePasswordForm(this)">
                    <input type="hidden" name="action" value="change_password">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px;">
                        <div class="form-group" style="margin: 0;">
                            <label>Current Password <span style="color: var(--accent-danger);">*</span></label>
                            <input type="password" name="old_password" required minlength="8" class="form-control" placeholder="Current password">
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label>New Password <span style="color: var(--accent-danger);">*</span></label>
                            <input type="password" name="new_password" required minlength="8" class="form-control" placeholder="New password">
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label>Confirm New Password <span style="color: var(--accent-danger);">*</span></label>
                            <input type="password" name="new_password_confirm" required minlength="8" class="form-control" placeholder="Confirm new password">
                        </div>
                    </div>
                    <button type="submit" class="btn" style="margin-top: 12px; background: var(--accent-primary); color: var(--text-on-accent);">
                        🔑 Update Password
                    </button>
                </form>
            </div>
            <?php if (!userHasRole('admin')): ?>
                <div class="alert alert-warning">
                    <span class="alert-icon">⚠️</span>
                    <span class="alert-text">You don't have permission to access user management. Admin role required.</span>
                </div>
            <?php else: ?>
                <!-- Add New User Button -->
                <div style="margin-bottom: 20px;">
                    <button type="button" class="btn" onclick="openAddUserModal()" 
                        style="background: var(--accent-success); color: var(--text-on-accent); padding: 12px 24px;">
                        ➕ Add New User
                    </button>
                </div>

                <!-- Users Table -->
                <?php 
                $allUsers = getAllUsers();
                if (empty($allUsers)): 
                ?>
                    <div class="alert alert-info">
                        <span class="alert-icon">ℹ️</span>
                        <span class="alert-text">No users found. Add your first user to get started.</span>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Full Name</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                    <th>Login Count</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allUsers as $user): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                        <td>
                                            <span style="padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; 
                                                background: <?php 
                                                    echo $user['role'] === 'admin' ? 'var(--accent-primary)' : 
                                                        ($user['role'] === 'editor' ? 'var(--accent-amber)' : 'var(--text-muted)'); 
                                                ?>; color: var(--text-on-accent);">
                                                <?php echo strtoupper($user['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span style="padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; 
                                                background: <?php echo $user['is_active'] ? 'var(--accent-success)' : 'var(--accent-danger)'; ?>; color: var(--text-on-accent);">
                                                <?php echo $user['is_active'] ? '✓ Active' : '✗ Inactive'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['last_login']); ?></td>
                                        <td><?php echo $user['login_count']; ?></td>
                                        <td>
                                            <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                                <button type="button" class="btn-icon" 
                                                    onclick="openEditUserModal('<?php echo $user['id']; ?>', 
                                                        '<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>', 
                                                        '<?php echo htmlspecialchars($user['email'], ENT_QUOTES); ?>', 
                                                        '<?php echo htmlspecialchars($user['full_name'], ENT_QUOTES); ?>', 
                                                        '<?php echo $user['role']; ?>')"
                                                    title="Edit User">
                                                    ✏️
                                                </button>
                                                <button type="button" class="btn-icon" 
                                                    onclick="openResetPasswordModal('<?php echo $user['id']; ?>', '<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>')"
                                                    title="Reset Password">
                                                    🔑
                                                </button>
                                                <?php if ($user['is_active']): ?>
                                                    <button type="button" class="btn-icon" 
                                                        onclick="toggleUserStatus('<?php echo $user['id']; ?>', false)"
                                                        title="Deactivate User"
                                                        style="background: var(--accent-warning);">
                                                        ⏸️
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn-icon" 
                                                        onclick="toggleUserStatus('<?php echo $user['id']; ?>', true)"
                                                        title="Activate User"
                                                        style="background: var(--accent-success);">
                                                        ▶️
                                                    </button>
                                                <?php endif; ?>
                                                <button type="button" class="btn-icon" 
                                                    onclick="deleteUserConfirm('<?php echo $user['id']; ?>', '<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>')"
                                                    title="Delete User"
                                                    style="background: var(--accent-danger);">
                                                    🗑️
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- User Statistics -->
                    <div style="margin-top: 30px;">
                        <h3 style="margin-bottom: 15px;">📊 User Statistics</h3>
                        <div class="stats">
                            <div class="stat-card">
                                <p>Total Users</p>
                                <p><?php echo count($allUsers); ?></p>
                            </div>
                            <div class="stat-card">
                                <p>Active Users</p>
                                <p><?php echo count(array_filter($allUsers, fn($u) => $u['is_active'])); ?></p>
                            </div>
                            <div class="stat-card">
                                <p>Admins</p>
                                <p><?php echo count(array_filter($allUsers, fn($u) => $u['role'] === 'admin')); ?></p>
                            </div>
                            <div class="stat-card">
                                <p>Editors</p>
                                <p><?php echo count(array_filter($allUsers, fn($u) => $u['role'] === 'editor')); ?></p>
                            </div>
                            <div class="stat-card">
                                <p>Developers</p>
                                <p><?php echo count(array_filter($allUsers, fn($u) => $u['role'] === 'developer')); ?></p>
                            </div>
                            <div class="stat-card">
                                <p>Analysts</p>
                                <p><?php echo count(array_filter($allUsers, fn($u) => $u['role'] === 'analyst')); ?></p>
                            </div>
                            <div class="stat-card">
                                <p>Viewers</p>
                                <p><?php echo count(array_filter($allUsers, fn($u) => $u['role'] === 'viewer')); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Roles & Permissions Overview -->
                    <div style="margin-top: 30px;">
                        <h3 style="margin-bottom: 15px;">🔐 Roles & Permissions Overview</h3>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px;">
                            <?php 
                            $allRoles = getAllRoles();
                            foreach ($allRoles as $roleKey => $roleData): 
                            ?>
                                <div style="background: var(--card-bg); border: 2px solid var(--border-color); border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                                        <div>
                                            <h4 style="margin: 0 0 5px 0; color: var(--text-primary); font-size: 18px;">
                                                <?php 
                                                $icons = ['admin' => '👑', 'editor' => '✏️', 'developer' => '👨‍💻', 'analyst' => '📊', 'viewer' => '👁️'];
                                                echo ($icons[$roleKey] ?? '👤') . ' ' . htmlspecialchars($roleData['name']); 
                                                ?>
                                            </h4>
                                            <p style="margin: 0; color: var(--text-secondary); font-size: 13px;"><?php echo htmlspecialchars($roleData['description']); ?></p>
                                        </div>
                                        <span style="background: <?php 
                                            $colors = ['admin' => 'var(--accent-primary)', 'editor' => 'var(--accent-amber)', 'developer' => 'var(--accent-teal)', 'analyst' => 'var(--accent-sky)', 'viewer' => 'var(--text-muted)'];
                                            echo $colors[$roleKey] ?? 'var(--text-muted)'; 
                                        ?>; color: var(--text-on-accent); padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                                            Level <?php echo $roleData['level']; ?>
                                        </span>
                                    </div>
                                    
                                    <div style="background: var(--surface-muted); padding: 15px; border-radius: 8px; margin-top: 15px;">
                                        <h5 style="margin: 0 0 10px 0; font-size: 13px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px;">Key Permissions:</h5>
                                        <div style="display: grid; gap: 6px; font-size: 12px;">
                                            <?php 
                                            $keyPermissions = ['view_data', 'create_data', 'edit_data', 'delete_data', 'manage_users', 'export_data'];
                                            foreach ($keyPermissions as $perm): 
                                                $hasPermission = $roleData['permissions'][$perm] ?? false;
                                            ?>
                                                <div style="display: flex; align-items: center; gap: 6px;">
                                                    <span style="color: <?php echo $hasPermission ? 'var(--accent-success)' : 'var(--accent-danger)'; ?>; font-size: 14px;">
                                                        <?php echo $hasPermission ? '✓' : '✗'; ?>
                                                    </span>
                                                    <span style="color: var(--text-secondary);"><?php echo ucwords(str_replace('_', ' ', $perm)); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                            <button type="button" onclick="showFullPermissions('<?php echo $roleKey; ?>')" 
                                                style="margin-top: 8px; background: var(--info-bg); border: none; padding: 6px 12px; border-radius: 6px; color: var(--info-text); cursor: pointer; font-size: 11px; font-weight: 600;">
                                                View All Permissions →
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Role Permissions Modal -->
<div id="rolePermissionsModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h2 id="rolePermissionsTitle">🔐 Role Permissions</h2>
            <button type="button" class="close-btn" onclick="closeRolePermissionsModal()">&times;</button>
        </div>
        <div id="rolePermissionsContent" style="padding: 20px;">
            <!-- Content will be populated by JavaScript -->
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div id="addUserModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h2>➕ Add New User</h2>
            <button type="button" class="close-btn" onclick="closeAddUserModal()">&times;</button>
        </div>
        <form method="POST" onsubmit="return validateAddUserForm(this)">
            <input type="hidden" name="action" value="create_user">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="form-group">
                <label>Username <span style="color: var(--accent-danger);">*</span></label>
                <input type="text" name="username" required minlength="3" maxlength="50" 
                    class="form-control" placeholder="Enter username">
            </div>
            
            <div class="form-group">
                <label>Email <span style="color: var(--accent-danger);">*</span></label>
                <input type="email" name="email" required 
                    class="form-control" placeholder="Enter email address">
            </div>
            
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" maxlength="100" 
                    class="form-control" placeholder="Enter full name">
            </div>
            
            <div class="form-group">
                <label>Password <span style="color: var(--accent-danger);">*</span></label>
                <input type="password" name="password" required minlength="6" 
                    class="form-control" placeholder="Minimum 6 characters">
            </div>
            
            <div class="form-group">
                <label>Confirm Password <span style="color: var(--accent-danger);">*</span></label>
                <input type="password" name="password_confirm" required minlength="6" 
                    class="form-control" placeholder="Confirm password">
            </div>
            
            <div class="form-group">
                <label>Role <span style="color: var(--accent-danger);">*</span></label>
                <select name="role" required class="form-control">
                    <option value="viewer">👁️ Viewer - Read-only access</option>
                    <option value="analyst">📊 Analyst - View data & analytics, export capabilities</option>
                    <option value="editor">✏️ Editor - Can modify data and perform bulk operations</option>
                    <option value="developer">👨‍💻 Developer - Advanced access, can manage collections</option>
                    <option value="admin">👑 Admin - Full system access</option>
                </select>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn" style="flex: 1; background: var(--accent-success); color: var(--text-on-accent); padding: 12px;">
                    ➕ Create User
                </button>
                <button type="button" class="btn" onclick="closeAddUserModal()" 
                    style="flex: 1; background: var(--text-muted); color: var(--text-on-accent); padding: 12px;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h2>✏️ Edit User</h2>
            <button type="button" class="close-btn" onclick="closeEditUserModal()">&times;</button>
        </div>
        <form method="POST" id="editUserForm">
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="user_id" id="editUserId">
            
            <div class="form-group">
                <label>Username <span style="color: var(--accent-danger);">*</span></label>
                <input type="text" name="username" id="editUsername" required minlength="3" maxlength="50" 
                    class="form-control">
            </div>
            
            <div class="form-group">
                <label>Email <span style="color: var(--accent-danger);">*</span></label>
                <input type="email" name="email" id="editEmail" required 
                    class="form-control">
            </div>
            
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" id="editFullName" maxlength="100" 
                    class="form-control">
            </div>
            
            <div class="form-group">
                <label>Role <span style="color: var(--accent-danger);">*</span></label>
                <select name="role" id="editRole" required class="form-control">
                    <option value="viewer">👁️ Viewer - Read-only access</option>
                    <option value="analyst">📊 Analyst - View data & analytics, export capabilities</option>
                    <option value="editor">✏️ Editor - Can modify data and perform bulk operations</option>
                    <option value="developer">👨‍💻 Developer - Advanced access, can manage collections</option>
                    <option value="admin">👑 Admin - Full system access</option>
                </select>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn" style="flex: 1; background: var(--accent-primary); color: var(--text-on-accent); padding: 12px;">
                    💾 Save Changes
                </button>
                <button type="button" class="btn" onclick="closeEditUserModal()" 
                    style="flex: 1; background: var(--text-muted); color: var(--text-on-accent); padding: 12px;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="resetPasswordModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h2>🔑 Reset Password</h2>
            <button type="button" class="close-btn" onclick="closeResetPasswordModal()">&times;</button>
        </div>
        <form method="POST" onsubmit="return validateResetPasswordForm(this)">
            <input type="hidden" name="action" value="reset_user_password">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="user_id" id="resetPasswordUserId">
            
            <p style="margin-bottom: 20px; color: var(--text-secondary);">
                Reset password for user: <strong id="resetPasswordUsername"></strong>
            </p>
            
            <div class="form-group">
                <label>New Password <span style="color: var(--accent-danger);">*</span></label>
                <input type="password" name="new_password" required minlength="6" 
                    class="form-control" placeholder="Minimum 6 characters">
            </div>
            
            <div class="form-group">
                <label>Confirm New Password <span style="color: var(--accent-danger);">*</span></label>
                <input type="password" name="new_password_confirm" required minlength="6" 
                    class="form-control" placeholder="Confirm new password">
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn" style="flex: 1; background: var(--accent-danger); color: var(--text-on-accent); padding: 12px;">
                    🔑 Reset Password
                </button>
                <button type="button" class="btn" onclick="closeResetPasswordModal()" 
                    style="flex: 1; background: var(--text-muted); color: var(--text-on-accent); padding: 12px;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Add User Modal Functions
function openAddUserModal() {
    document.getElementById('addUserModal').style.display = 'flex';
}

function closeAddUserModal() {
    document.getElementById('addUserModal').style.display = 'none';
}

function validateAddUserForm(form) {
    const password = form.querySelector('input[name="password"]').value;
    const passwordConfirm = form.querySelector('input[name="password_confirm"]').value;
    
    if (password !== passwordConfirm) {
        alert('Passwords do not match!');
        return false;
    }
    
    if (password.length < 6) {
        alert('Password must be at least 6 characters!');
        return false;
    }
    
    return true;
}

// Edit User Modal Functions
function openEditUserModal(userId, username, email, fullName, role) {
    document.getElementById('editUserId').value = userId;
    document.getElementById('editUsername').value = username;
    document.getElementById('editEmail').value = email;
    document.getElementById('editFullName').value = fullName;
    document.getElementById('editRole').value = role;
    document.getElementById('editUserModal').style.display = 'flex';
}

function closeEditUserModal() {
    document.getElementById('editUserModal').style.display = 'none';
}

// Reset Password Modal Functions
function openResetPasswordModal(userId, username) {
    document.getElementById('resetPasswordUserId').value = userId;
    document.getElementById('resetPasswordUsername').textContent = username;
    document.getElementById('resetPasswordModal').style.display = 'flex';
}

function closeResetPasswordModal() {
    document.getElementById('resetPasswordModal').style.display = 'none';
}

function validateResetPasswordForm(form) {
    const password = form.querySelector('input[name="new_password"]').value;
    const passwordConfirm = form.querySelector('input[name="new_password_confirm"]').value;
    
    if (password !== passwordConfirm) {
        alert('Passwords do not match!');
        return false;
    }
    
    if (password.length < 6) {
        alert('Password must be at least 6 characters!');
        return false;
    }
    
    return confirm('Are you sure you want to reset this user\'s password?');
}

function validateChangePasswordForm(form) {
    const password = form.querySelector('input[name="new_password"]').value;
    const passwordConfirm = form.querySelector('input[name="new_password_confirm"]').value;
    
    if (password !== passwordConfirm) {
        alert('Passwords do not match!');
        return false;
    }
    
    if (password.length < 8) {
        alert('Password must be at least 8 characters!');
        return false;
    }
    
    return true;
}

// Toggle User Status
function toggleUserStatus(userId, activate) {
    const action = activate ? 'activate_user' : 'deactivate_user';
    const message = activate ? 'activate' : 'deactivate';
    
    if (confirm(`Are you sure you want to ${message} this user?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="${action}">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="user_id" value="${userId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Delete User
function deleteUserConfirm(userId, username) {
    if (confirm(`⚠️ Are you sure you want to DELETE user "${username}"?\n\nThis action cannot be undone!`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_user">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="user_id" value="${userId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modals on background click
document.getElementById('addUserModal').onclick = function(e) {
    if (e.target === this) closeAddUserModal();
};
document.getElementById('editUserModal').onclick = function(e) {
    if (e.target === this) closeEditUserModal();
};
document.getElementById('resetPasswordModal').onclick = function(e) {
    if (e.target === this) closeResetPasswordModal();
};

// Role Permissions Modal Functions
function showFullPermissions(roleKey) {
    const roles = <?php echo json_encode(getAllRoles()); ?>;
    const role = roles[roleKey];
    
    if (!role) return;
    
    const icons = {
        'admin': '👑',
        'editor': '✏️',
        'developer': '👨‍💻',
        'analyst': '📊',
        'viewer': '👁️'
    };
    
    const colors = {
        'admin': 'var(--accent-primary)',
        'editor': 'var(--accent-amber)',
        'developer': 'var(--accent-teal)',
        'analyst': 'var(--accent-sky)',
        'viewer': 'var(--text-muted)'
    };
    
    document.getElementById('rolePermissionsTitle').innerHTML = 
        `${icons[roleKey] || '👤'} ${role.name} - All Permissions`;
    
    let permissionsHTML = `
        <div style="background: var(--surface-muted); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <p style="margin: 0 0 10px 0; color: var(--text-secondary); font-size: 14px;"><strong>Description:</strong> ${role.description}</p>
            <p style="margin: 0; color: var(--text-secondary); font-size: 14px;"><strong>Access Level:</strong> 
                <span style="background: ${colors[roleKey]}; color: var(--text-on-accent); padding: 2px 8px; border-radius: 8px; font-size: 12px;">
                    Level ${role.level}
                </span>
            </p>
        </div>
        
        <h4 style="margin: 20px 0 15px 0; color: var(--text-primary); font-size: 16px;">📋 Full Permissions List:</h4>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
    `;
    
    for (const [permission, hasAccess] of Object.entries(role.permissions)) {
        const permissionName = permission.split('_').map(word => 
            word.charAt(0).toUpperCase() + word.slice(1)
        ).join(' ');
        
        permissionsHTML += `
            <div style="display: flex; align-items: center; gap: 8px; padding: 8px; background: ${hasAccess ? 'var(--success-bg)' : 'var(--danger-bg)'}; border-radius: 6px;">
                <span style="font-size: 16px; color: ${hasAccess ? 'var(--accent-success)' : 'var(--accent-danger)'};">
                    ${hasAccess ? '✓' : '✗'}
                </span>
                <span style="font-size: 13px; color: var(--text-primary); font-weight: 500;">${permissionName}</span>
            </div>
        `;
    }
    
    permissionsHTML += `</div>`;
    
    document.getElementById('rolePermissionsContent').innerHTML = permissionsHTML;
    document.getElementById('rolePermissionsModal').style.display = 'flex';
}

function closeRolePermissionsModal() {
    document.getElementById('rolePermissionsModal').style.display = 'none';
}

// Close role permissions modal on background click
document.getElementById('rolePermissionsModal').onclick = function(e) {
    if (e.target === this) closeRolePermissionsModal();
};
</script>

