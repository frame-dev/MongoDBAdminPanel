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
        @import url('https://fonts.googleapis.com/css2?family=Newsreader:opsz,wght@6..72,500;600&family=Space+Grotesk:wght@400;500;600;700&display=swap');

        :root {
            --accent-primary: #1f8a70;
            --accent-secondary: #f2a365;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: rgba(71, 85, 105, 0.65);
            --text-on-accent: #f8fafc;
            --border-color: rgba(15, 23, 42, 0.12);
            --danger-bg: rgba(220, 38, 38, 0.12);
            --danger-text: #b91c1c;
            --success-bg: rgba(16, 185, 129, 0.15);
            --success-text: #047857;
            --info-bg: rgba(59, 130, 246, 0.12);
            --info-text: #1e40af;
            --accent-danger: #dc2626;
            --accent-warning: #f59e0b;
            --accent-info: #3b82f6;
            --accent-success: #10b981;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Space Grotesk', 'Trebuchet MS', 'Lucida Sans Unicode', sans-serif;
            background:
                radial-gradient(1200px circle at 10% 10%, rgba(242, 163, 101, 0.35), transparent 60%),
                radial-gradient(900px circle at 90% 20%, rgba(31, 138, 112, 0.3), transparent 60%),
                linear-gradient(120deg, #0f172a 0%, #0b1f2a 60%, #091318 100%);
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            overflow-x: hidden;
            overflow-y: auto;
            position: relative;
            color: var(--text-primary);
        }

        body::before,
        body::after {
            content: "";
            position: fixed;
            width: 340px;
            height: 340px;
            border-radius: 50%;
            filter: blur(40px);
            opacity: 0.35;
            z-index: 0;
            animation: drift 12s ease-in-out infinite alternate;
        }

        body::before {
            background: #f2a365;
            top: -120px;
            left: -80px;
        }

        body::after {
            background: #1f8a70;
            bottom: -140px;
            right: -120px;
            animation-delay: 2s;
        }
        
        .auth-container {
            width: 100%;
            max-width: 460px;
            position: relative;
            z-index: 1;
        }
        
        .auth-card {
            background:
                radial-gradient(circle at 12% 10%, rgba(242, 163, 101, 0.18), transparent 45%),
                radial-gradient(circle at 90% 10%, rgba(31, 138, 112, 0.12), transparent 40%),
                linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.92));
            border-radius: 20px;
            box-shadow: 0 30px 80px rgba(8, 23, 44, 0.45);
            padding: 44px 40px;
            border: 1px solid rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(18px);
            animation: floatIn 0.6s ease both;
            position: relative;
        }
        
        .auth-card::before {
            content: "";
            position: absolute;
            inset: 12px;
            border-radius: 16px;
            border: 1px dashed rgba(15, 23, 42, 0.08);
            pointer-events: none;
        }
        
        .auth-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .auth-header .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(15, 23, 42, 0.08);
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 11px;
            letter-spacing: 1.6px;
            text-transform: uppercase;
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 12px;
        }
        
        .auth-header h1 {
            font-family: 'Newsreader', 'Times New Roman', serif;
            color: var(--text-primary);
            font-size: 30px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .auth-header p {
            color: var(--text-secondary);
            font-size: 15px;
        }
        
        .form-group {
            margin-bottom: 18px;
            padding: 10px;
            border-radius: 12px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .form-group:focus-within {
            background: rgba(255, 255, 255, 0.6);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
            transform: translateY(-2px);
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1.2px;
        }

        .input-wrap {
            position: relative;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            transition: border-color 0.2s, box-shadow 0.2s, transform 0.2s;
            font-family: inherit;
            background: rgba(255, 255, 255, 0.9);
            box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.08);
            padding-left: 40px;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 4px rgba(31, 138, 112, 0.15);
            transform: translateY(-1px);
        }
        
        .form-group input::placeholder {
            color: var(--text-muted);
        }

        .field-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%) scale(1);
            font-size: 16px;
            color: rgba(71, 85, 105, 0.7);
            transition: transform 0.3s ease, color 0.3s ease, filter 0.3s ease;
        }

        .form-group:focus-within .field-icon {
            color: var(--accent-primary);
            transform: translateY(-50%) scale(1.15) rotate(-6deg);
            filter: drop-shadow(0 6px 10px rgba(31, 138, 112, 0.25));
        }

        .input-wrap::after {
            content: "";
            position: absolute;
            left: 12px;
            right: 12px;
            bottom: 6px;
            height: 2px;
            background: linear-gradient(90deg, var(--accent-primary), var(--accent-secondary));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease;
            border-radius: 999px;
            opacity: 0.8;
        }

        .form-group:focus-within .input-wrap::after {
            transform: scaleX(1);
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
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-top: 10px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 14px 26px rgba(31, 138, 112, 0.3);
        }

        .submit-btn::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, transparent 0%, rgba(255, 255, 255, 0.35) 45%, transparent 90%);
            transform: translateX(-120%);
            transition: transform 0.6s ease;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 34px rgba(31, 138, 112, 0.4);
        }

        .submit-btn:hover::after {
            transform: translateX(120%);
        }
        
        .submit-btn:active {
            transform: translateY(0);
        }

        .submit-btn:focus-visible {
            outline: 3px solid rgba(31, 138, 112, 0.35);
            outline-offset: 2px;
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
            animation: fadeSlide 0.45s ease both;
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
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            color: var(--info-text);
            box-shadow: 0 12px 22px rgba(30, 64, 175, 0.12);
        }
        
        .info-box strong {
            display: block;
            margin-bottom: 5px;
        }

        .auth-alert {
            text-align: center;
            margin-top: 20px;
            padding: 12px;
            border-radius: 10px;
            box-shadow: 0 12px 22px rgba(15, 23, 42, 0.12);
        }

        @keyframes floatIn {
            from {
                opacity: 0;
                transform: translateY(16px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes fadeSlide {
            from {
                opacity: 0;
                transform: translateY(12px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes drift {
            0% {
                transform: translate(0, 0) scale(1);
            }
            50% {
                transform: translate(12px, -8px) scale(1.04);
            }
            100% {
                transform: translate(-8px, 10px) scale(0.98);
            }
        }

        @media (max-width: 520px) {
            .auth-card {
                padding: 34px 28px;
            }

            .auth-header h1 {
                font-size: 26px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            * {
                animation: none !important;
                transition: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <span class="eyebrow">Secure Access</span>
                <h1>🔐 MongoDB Admin</h1>
                <p>Secure Authentication System</p>
            </div>
            
            <!-- Login Form -->
            <form id="loginForm" class="form-section active" method="POST" action="">
                <input type="hidden" name="action" value="login">
                
                <div class="form-group">
                    <label for="login-username">Username</label>
                    <div class="input-wrap">
                        <span class="field-icon">👤</span>
                        <input type="text" id="login-username" name="username" placeholder="Enter your username" required>
                    </div>
                    <span class="error-message" id="login-username-error"></span>
                </div>
                
                <div class="form-group">
                    <label for="login-password">Password</label>
                    <div class="input-wrap">
                        <span class="field-icon">🔒</span>
                        <input type="password" id="login-password" name="password" placeholder="Enter your password" required>
                    </div>
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
                    <div class="input-wrap">
                        <span class="field-icon">👤</span>
                        <input type="text" id="reg-username" name="username" placeholder="3-32 characters (letters, numbers, underscore)" required>
                    </div>
                    <span class="error-message" id="reg-username-error"></span>
                </div>
                
                <div class="form-group">
                    <label for="reg-email">Email Address</label>
                    <div class="input-wrap">
                        <span class="field-icon">✉️</span>
                        <input type="email" id="reg-email" name="email" placeholder="your@email.com" required>
                    </div>
                    <span class="error-message" id="reg-email-error"></span>
                </div>
                
                <div class="form-group">
                    <label for="reg-fullname">Full Name</label>
                    <div class="input-wrap">
                        <span class="field-icon">🪪</span>
                        <input type="text" id="reg-fullname" name="full_name" placeholder="Your full name (optional)">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="reg-password">Password</label>
                    <div class="input-wrap">
                        <span class="field-icon">🔐</span>
                        <input type="password" id="reg-password" name="password" placeholder="Min 8 characters" required 
                               onkeyup="checkPasswordStrength(this.value)">
                    </div>
                    <div class="password-strength">
                        <div class="password-strength-bar" id="strengthBar"></div>
                    </div>
                    <span class="error-message" id="reg-password-error"></span>
                </div>
                
                <div class="form-group">
                    <label for="reg-password-confirm">Confirm Password</label>
                    <div class="input-wrap">
                        <span class="field-icon">✅</span>
                        <input type="password" id="reg-password-confirm" name="password_confirm" placeholder="Confirm password" required>
                    </div>
                    <span class="error-message" id="reg-password-confirm-error"></span>
                </div>
                
                <button type="submit" class="submit-btn">Create Account</button>
                
                <div class="toggle-form">
                    Already have an account? <a onclick="toggleForms()">Sign in</a>
                </div>
            </form>
            
            <?php if (isset($_SESSION['auth_message'])): ?>
                <div class="auth-alert" style="<?php echo isset($_SESSION['auth_success']) && $_SESSION['auth_success'] ? 'background: var(--success-bg); color: var(--success-text);' : 'background: var(--danger-bg); color: var(--danger-text);'; ?>">
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
