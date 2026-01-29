<?php
/**
 * Connection Form Page
 * 
 * Displays MongoDB connection form for initial database connection setup.
 * Handles connection credentials input and validation.
 * 
 * @package MongoDB Admin Panel
 * @subpackage Templates
 * @version 1.0.0
 * @author Development Team
 * @link https://github.com/frame-dev/MongoDBAdminPanel
 * @license MIT
 */
require_once 'config/security.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MongoDB Admin Panel - Connection</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Newsreader:opsz,wght@6..72,500;600&family=Space+Grotesk:wght@400;500;600;700&display=swap');

        :root {
            --accent-primary: #1f8a70;
            --accent-secondary: #f2a365;
            --card-bg: rgba(255, 255, 255, 0.92);
            --surface-2: rgba(15, 23, 42, 0.04);
            --border-color: rgba(15, 23, 42, 0.12);
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-on-accent: #f8fafc;
            --danger-bg: rgba(220, 38, 38, 0.12);
            --danger-text: #b91c1c;
            --danger-border: rgba(185, 28, 28, 0.3);
            --card-outline: rgba(255, 255, 255, 0.7);
            --glow: rgba(242, 163, 101, 0.35);
            --shadow-strong: 0 30px 80px rgba(8, 23, 44, 0.45);
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
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
            overflow-y: auto;
            color: var(--text-primary);
            perspective: 1200px;
        }

        body::before,
        body::after {
            content: "";
            position: fixed;
            width: 360px;
            height: 360px;
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

        body::after {
            box-shadow: 0 0 120px var(--glow);
        }

        .connection-container {
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.9)),
                var(--card-bg);
            border-radius: 18px;
            box-shadow: var(--shadow-strong);
            padding: 46px 40px;
            max-width: 450px;
            width: 100%;
            position: relative;
            z-index: 1;
            backdrop-filter: blur(18px);
            border: 1px solid var(--card-outline);
            animation: floatIn 0.7s ease both;
            margin: 24px 0;
            background-image:
                radial-gradient(circle at 12% 10%, rgba(242, 163, 101, 0.18), transparent 45%),
                radial-gradient(circle at 90% 10%, rgba(31, 138, 112, 0.12), transparent 40%),
                linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.9));
            background-size: 160% 160%;
            animation: floatIn 0.7s ease both, sheen 12s ease-in-out infinite;
            transform-style: preserve-3d;
            transform: rotateX(var(--tilt-x, 0deg)) rotateY(var(--tilt-y, 0deg));
            transition: transform 0.12s ease;
        }

        .connection-container::before {
            content: "";
            position: absolute;
            inset: 12px;
            border-radius: 16px;
            border: 1px dashed rgba(15, 23, 42, 0.08);
            pointer-events: none;
        }

        .connection-container::after {
            content: "";
            position: absolute;
            inset: -1px;
            border-radius: 20px;
            padding: 1px;
            background: linear-gradient(140deg, rgba(242, 163, 101, 0.6), rgba(31, 138, 112, 0.2), transparent 60%);
            mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            mask-composite: exclude;
            pointer-events: none;
            animation: haloShift 8s ease-in-out infinite;
        }

        .title-row {
            margin-bottom: 22px;
            position: relative;
        }

        .title-row::after {
            content: "";
            display: block;
            height: 1px;
            width: 80px;
            margin-top: 16px;
            background: linear-gradient(90deg, var(--accent-primary), transparent);
        }

        .eyebrow {
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
            animation: fadeSlide 0.6s ease both 0.1s;
        }
        
        .connection-container h1 {
            font-family: 'Newsreader', 'Times New Roman', serif;
            color: var(--text-primary);
            margin-bottom: 8px;
            font-size: 32px;
            letter-spacing: 0.2px;
            animation: fadeSlide 0.6s ease both 0.18s;
        }
        
        .connection-container p {
            color: var(--text-secondary);
            margin-bottom: 24px;
            font-size: 15px;
            line-height: 1.5;
            animation: fadeSlide 0.6s ease both 0.26s;
        }
        
        .form-group {
            margin-bottom: 20px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            padding: 10px;
            border-radius: 12px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
        }
        
        .form-group label {
            display: block;
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            font-size: 11px;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            background: rgba(255, 255, 255, 0.9);
            transition: border-color 0.3s, box-shadow 0.3s, transform 0.2s;
            box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.08);
            padding-left: 40px;
        }

        .form-group input::placeholder {
            color: rgba(71, 85, 105, 0.6);
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 4px rgba(31, 138, 112, 0.15);
            transform: translateY(-1px);
            animation: focusPulse 1.2s ease-out;
        }

        .form-group:focus-within {
            background: rgba(255, 255, 255, 0.65);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
        }

        .form-group:hover {
            transform: translateY(-2px);
        }

        .input-wrap {
            position: relative;
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

        .connection-form {
            display: grid;
            gap: 4px;
            padding: 20px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.7);
            box-shadow: 0 16px 30px rgba(15, 23, 42, 0.08);
            animation: fadeSlide 0.6s ease both 0.32s;
        }

        .connection-form .form-group:nth-child(2) {
            animation: floatUp 0.6s ease both 0.05s;
        }

        .connection-form .form-group:nth-child(3) {
            animation: floatUp 0.6s ease both 0.1s;
        }

        .connection-form .form-group:nth-child(4) {
            animation: floatUp 0.6s ease both 0.15s;
        }

        .connection-form .form-group:nth-child(5) {
            animation: floatUp 0.6s ease both 0.2s;
        }
        
        .btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--accent-primary) 0%, var(--accent-secondary) 100%);
            color: var(--text-on-accent);
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.3s;
            box-shadow: 0 12px 24px rgba(31, 138, 112, 0.3);
            position: relative;
            overflow: hidden;
            animation: buttonGlow 6s ease-in-out infinite;
        }

        .btn::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, transparent 0%, rgba(255, 255, 255, 0.35) 45%, transparent 90%);
            transform: translateX(-120%);
            transition: transform 0.6s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 32px rgba(31, 138, 112, 0.35);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn:hover::after {
            transform: translateX(120%);
        }

        .btn:focus-visible {
            outline: 3px solid rgba(31, 138, 112, 0.35);
            outline-offset: 2px;
        }
        
        .error-message {
            background: var(--danger-bg);
            color: var(--danger-text);
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid var(--danger-border);
            box-shadow: 0 10px 18px rgba(185, 28, 28, 0.12);
            animation: shakeIn 0.45s ease both;
        }
        
        .help-section {
            margin-top: 30px;
            padding: 22px 20px;
            border-top: 1px solid var(--border-color);
            background: var(--surface-2);
            border-radius: 12px;
            position: relative;
            animation: fadeSlide 0.6s ease both 0.4s;
        }

        .help-section::before {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.6);
            pointer-events: none;
        }
        
        .help-section h3 {
            color: var(--text-primary);
            font-size: 14px;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 1.4px;
        }
        
        .help-tips {
            font-size: 13px;
            color: var(--text-secondary);
            line-height: 1.6;
            list-style: none;
            padding-left: 0;
            display: grid;
            gap: 10px;
        }

        .help-tips li {
            margin-bottom: 10px;
            padding-left: 16px;
            position: relative;
            background: rgba(255, 255, 255, 0.6);
            border-radius: 10px;
            padding: 10px 12px 10px 28px;
            border: 1px solid rgba(15, 23, 42, 0.08);
            box-shadow: 0 8px 16px rgba(15, 23, 42, 0.06);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .help-tips li:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 24px rgba(15, 23, 42, 0.12);
        }

        .help-tips li::before {
            content: "-";
            position: absolute;
            left: 0;
            color: var(--accent-primary);
        }
        
        .help-tips strong {
            color: var(--text-primary);
        }

        .help-tips code {
            background: rgba(15, 23, 42, 0.08);
            padding: 2px 6px;
            border-radius: 6px;
            font-family: 'Space Grotesk', 'Trebuchet MS', 'Lucida Sans Unicode', sans-serif;
        }

        @keyframes floatIn {
            from {
                opacity: 0;
                transform: translateY(18px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes floatUp {
            from {
                opacity: 0;
                transform: translateY(8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeSlide {
            from {
                opacity: 0;
                transform: translateY(10px);
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

        @keyframes haloShift {
            0% {
                opacity: 0.55;
            }
            50% {
                opacity: 0.9;
            }
            100% {
                opacity: 0.65;
            }
        }

        @keyframes focusPulse {
            0% {
                box-shadow: 0 0 0 0 rgba(31, 138, 112, 0.22);
            }
            100% {
                box-shadow: 0 0 0 6px rgba(31, 138, 112, 0);
            }
        }

        @keyframes sheen {
            0% {
                background-position: 0% 0%;
            }
            50% {
                background-position: 100% 100%;
            }
            100% {
                background-position: 0% 0%;
            }
        }

        @keyframes buttonGlow {
            0% {
                box-shadow: 0 12px 24px rgba(31, 138, 112, 0.3);
            }
            50% {
                box-shadow: 0 18px 30px rgba(31, 138, 112, 0.45);
            }
            100% {
                box-shadow: 0 12px 24px rgba(31, 138, 112, 0.3);
            }
        }

        @keyframes shakeIn {
            0% {
                opacity: 0;
                transform: translateY(-6px);
            }
            60% {
                opacity: 1;
                transform: translateY(0);
            }
            80% {
                transform: translateX(-3px);
            }
            100% {
                transform: translateX(0);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            * {
                animation: none !important;
                transition: none !important;
            }

            .connection-container {
                transform: none !important;
            }
        }

        @media (max-width: 520px) {
            .connection-container {
                padding: 32px 24px;
            }

            .connection-container h1 {
                font-size: 26px;
            }

            .connection-form {
                padding: 14px 12px;
            }
        }

        @media (min-width: 640px) {
            .form-row {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="connection-container">
        <div class="title-row">
            <span class="eyebrow">Secure Connection</span>
            <h1>🗄️ MongoDB Admin</h1>
            <p>Connect to your MongoDB database to manage collections and documents</p>
        </div>
        
        <?php if ($connectionError): ?>
            <div class="error-message">
                <strong>⚠️ Connection Error:</strong><br>
                <?php echo htmlspecialchars($connectionError); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="connection-form">
            <input type="hidden" name="connect" value="1">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Hostname:</label>
                    <div class="input-wrap">
                        <span class="field-icon">🛰️</span>
                        <input type="text" name="hostname" placeholder="localhost" value="localhost" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Port:</label>
                    <div class="input-wrap">
                        <span class="field-icon">🔌</span>
                        <input type="number" name="port" placeholder="27017" value="27017" required>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label>Database:</label>
                <div class="input-wrap">
                    <span class="field-icon">🧭</span>
                    <input type="text" name="database" placeholder="mydb" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Username (optional):</label>
                    <div class="input-wrap">
                        <span class="field-icon">👤</span>
                        <input type="text" name="username" placeholder="username">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Password (optional):</label>
                    <div class="input-wrap">
                        <span class="field-icon">🔒</span>
                        <input type="password" name="password" placeholder="password">
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label>Collection:</label>
                <div class="input-wrap">
                    <span class="field-icon">🗂️</span>
                    <input type="text" name="collection" placeholder="mycollection" required>
                </div>
            </div>
            
            <button type="submit" class="btn">🔗 Connect</button>
        </form>
        
        <div class="help-section">
            <h3>🔧 Troubleshooting Connection Issues</h3>
            <ul class="help-tips">
                <li><strong>MongoDB Not Running:</strong> Ensure MongoDB service is running on your system. Start it with: <code>mongod</code></li>
                <li><strong>Default Settings:</strong> If connecting locally, use <strong>localhost:27017</strong> as hostname and port</li>
                <li><strong>Authentication:</strong> Leave username/password blank if MongoDB doesn't require authentication (default)</li>
                <li><strong>Database Name:</strong> Must exist in MongoDB or MongoDB will create it automatically</li>
                <li><strong>Collection Name:</strong> This is where data will be stored</li>
                <li><strong>Connection Timeout:</strong> If connection takes too long, verify the hostname is correct</li>
                <li><strong>Firewall:</strong> If MongoDB is on another machine, ensure port 27017 is open in your firewall</li>
            </ul>
        </div>
    </div>
    <script>
        (function () {
            var prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (prefersReduced) {
                return;
            }

            var card = document.querySelector('.connection-container');
            if (!card) {
                return;
            }

            var maxTilt = 6;

            function resetTilt() {
                card.style.setProperty('--tilt-x', '0deg');
                card.style.setProperty('--tilt-y', '0deg');
            }

            card.addEventListener('mousemove', function (event) {
                var rect = card.getBoundingClientRect();
                var x = (event.clientX - rect.left) / rect.width - 0.5;
                var y = (event.clientY - rect.top) / rect.height - 0.5;
                var tiltX = (y * -maxTilt).toFixed(2);
                var tiltY = (x * maxTilt).toFixed(2);
                card.style.setProperty('--tilt-x', tiltX + 'deg');
                card.style.setProperty('--tilt-y', tiltY + 'deg');
            });

            card.addEventListener('mouseleave', resetTilt);
            resetTilt();
        })();
    </script>
</body>
</html>
