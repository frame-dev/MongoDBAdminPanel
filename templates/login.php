<?php
/**
 * Login Page Template
 * 
 * Displays login and registration forms for user authentication.
 * Handles both login and registration user actions.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MongoDB Admin Panel - Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--accent-primary) 0%, var(--accent-secondary) 100%);
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .auth-container {
            width: 100%;
            max-width: 420px;
        }
        
        .auth-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            animation: slideIn 0.4s ease;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .auth-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .auth-header h1 {
            color: var(--text-primary);
            font-size: 28px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .auth-header p {
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 18px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 600;
            font-size: 13px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
            font-family: inherit;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--accent-primary);
        }
        
        .form-group input::placeholder {
            color: var(--text-muted);
        }
        
        .error-message {
            color: var(--accent-danger);
            font-size: 13px;
            margin-top: 5px;
            display: none;
        }
        
        .error-message.show {
            display: block;
        }
        
        .success-message {
            color: var(--accent-success);
            font-size: 13px;
            margin-top: 5px;
            display: none;
        }
        
        .success-message.show {
            display: block;
        }
        
        .form-group input.error {
            border-color: var(--accent-danger);
        }
        
        .submit-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--accent-primary) 0%, var(--accent-secondary) 100%);
            color: var(--text-on-accent);
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-top: 10px;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .submit-btn:active {
            transform: translateY(0);
        }
        
        .toggle-form {
            text-align: center;
            margin-top: 20px;
            color: var(--text-secondary);
            font-size: 13px;
        }
        
        .toggle-form a {
            color: var(--accent-primary);
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
        }
        
        .toggle-form a:hover {
            text-decoration: underline;
        }
        
        .form-section {
            display: none;
        }
        
        .form-section.active {
            display: block;
        }
        
        .password-strength {
            height: 4px;
            background: var(--border-color);
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0;
            transition: width 0.3s, background-color 0.3s;
            background-color: var(--accent-danger);
        }
        
        .info-box {
            background: var(--info-bg);
            border-left: 4px solid var(--accent-info);
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 13px;
            color: var(--info-text);
        }
        
        .info-box strong {
            display: block;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>🔐 MongoDB Admin</h1>
                <p>Secure Authentication System</p>
            </div>
            
            <!-- Login Form -->
            <form id="loginForm" class="form-section active" method="POST" action="">
                <input type="hidden" name="action" value="login">
                
                <div class="form-group">
                    <label for="login-username">Username</label>
                    <input type="text" id="login-username" name="username" placeholder="Enter your username" required>
                    <span class="error-message" id="login-username-error"></span>
                </div>
                
                <div class="form-group">
                    <label for="login-password">Password</label>
                    <input type="password" id="login-password" name="password" placeholder="Enter your password" required>
                    <span class="error-message" id="login-password-error"></span>
                </div>
                
                <button type="submit" class="submit-btn">Sign In</button>
                
                <div class="toggle-form">
                    Don't have an account? <a onclick="toggleForms()">Create one</a>
                </div>
            </form>
            
            <!-- Registration Form -->
            <form id="registerForm" class="form-section" method="POST" action="">
                <input type="hidden" name="action" value="register">
                
                <div class="info-box">
                    <strong>Create New Account</strong>
                    First admin account will be created automatically. Subsequent users require admin approval.
                </div>
                
                <div class="form-group">
                    <label for="reg-username">Username</label>
                    <input type="text" id="reg-username" name="username" placeholder="3-32 characters (letters, numbers, underscore)" required>
                    <span class="error-message" id="reg-username-error"></span>
                </div>
                
                <div class="form-group">
                    <label for="reg-email">Email Address</label>
                    <input type="email" id="reg-email" name="email" placeholder="your@email.com" required>
                    <span class="error-message" id="reg-email-error"></span>
                </div>
                
                <div class="form-group">
                    <label for="reg-fullname">Full Name</label>
                    <input type="text" id="reg-fullname" name="full_name" placeholder="Your full name (optional)">
                </div>
                
                <div class="form-group">
                    <label for="reg-password">Password</label>
                    <input type="password" id="reg-password" name="password" placeholder="Min 8 characters" required 
                           onkeyup="checkPasswordStrength(this.value)">
                    <div class="password-strength">
                        <div class="password-strength-bar" id="strengthBar"></div>
                    </div>
                    <span class="error-message" id="reg-password-error"></span>
                </div>
                
                <div class="form-group">
                    <label for="reg-password-confirm">Confirm Password</label>
                    <input type="password" id="reg-password-confirm" name="password_confirm" placeholder="Confirm password" required>
                    <span class="error-message" id="reg-password-confirm-error"></span>
                </div>
                
                <button type="submit" class="submit-btn">Create Account</button>
                
                <div class="toggle-form">
                    Already have an account? <a onclick="toggleForms()">Sign in</a>
                </div>
            </form>
            
            <?php if (isset($_SESSION['auth_message'])): ?>
                <div style="text-align: center; margin-top: 20px; padding: 12px; border-radius: 8px; <?php echo isset($_SESSION['auth_success']) && $_SESSION['auth_success'] ? 'background: var(--success-bg); color: var(--success-text);' : 'background: var(--danger-bg); color: var(--danger-text);'; ?>">
                    <?php echo htmlspecialchars($_SESSION['auth_message']); ?>
                </div>
                <?php unset($_SESSION['auth_message']); unset($_SESSION['auth_success']); ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function toggleForms() {
            document.getElementById('loginForm').classList.toggle('active');
            document.getElementById('registerForm').classList.toggle('active');
        }
        
        function checkPasswordStrength(password) {
            const bar = document.getElementById('strengthBar');
            let strength = 0;
            
            if (password.length >= 8) strength += 20;
            if (password.length >= 12) strength += 20;
            if (/[a-z]/.test(password)) strength += 15;
            if (/[A-Z]/.test(password)) strength += 15;
            if (/[0-9]/.test(password)) strength += 15;
            if (/[^a-zA-Z0-9]/.test(password)) strength += 15;
            
            bar.style.width = Math.min(strength, 100) + '%';
            
            if (strength < 30) bar.style.backgroundColor = 'var(--accent-danger)';
            else if (strength < 60) bar.style.backgroundColor = 'var(--accent-warning)';
            else if (strength < 80) bar.style.backgroundColor = 'var(--accent-info)';
            else bar.style.backgroundColor = 'var(--accent-success)';
        }
        
        // Client-side validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('login-username').value;
            const password = document.getElementById('login-password').value;
            
            let isValid = true;
            
            if (username.length < 3) {
                document.getElementById('login-username-error').textContent = 'Username required';
                document.getElementById('login-username-error').classList.add('show');
                isValid = false;
            }
            
            if (password.length < 8) {
                document.getElementById('login-password-error').textContent = 'Password required';
                document.getElementById('login-password-error').classList.add('show');
                isValid = false;
            }
            
            if (!isValid) e.preventDefault();
        });
        
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const username = document.getElementById('reg-username').value;
            const email = document.getElementById('reg-email').value;
            const password = document.getElementById('reg-password').value;
            const passwordConfirm = document.getElementById('reg-password-confirm').value;
            
            let isValid = true;
            
            if (!/^[a-zA-Z0-9_]{3,32}$/.test(username)) {
                document.getElementById('reg-username-error').textContent = 'Username must be 3-32 chars (letters, numbers, underscore)';
                document.getElementById('reg-username-error').classList.add('show');
                isValid = false;
            }
            
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                document.getElementById('reg-email-error').textContent = 'Invalid email address';
                document.getElementById('reg-email-error').classList.add('show');
                isValid = false;
            }
            
            if (password.length < 8) {
                document.getElementById('reg-password-error').textContent = 'Password must be at least 8 characters';
                document.getElementById('reg-password-error').classList.add('show');
                isValid = false;
            }
            
            if (password !== passwordConfirm) {
                document.getElementById('reg-password-confirm-error').textContent = 'Passwords do not match';
                document.getElementById('reg-password-confirm-error').classList.add('show');
                isValid = false;
            }
            
            if (!isValid) e.preventDefault();
        });
    </script>
</body>
</html>
