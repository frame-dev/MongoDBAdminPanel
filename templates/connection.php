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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--accent-primary) 0%, var(--accent-secondary) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .connection-container {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            max-width: 450px;
            width: 100%;
        }
        
        .connection-container h1 {
            color: var(--text-primary);
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .connection-container p {
            color: var(--text-secondary);
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--border-color);
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--accent-primary);
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--accent-primary) 0%, var(--accent-secondary) 100%);
            color: var(--text-on-accent);
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .error-message {
            background: var(--danger-bg);
            color: var(--danger-text);
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid var(--danger-border);
        }
        
        .help-section {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid var(--border-color);
        }
        
        .help-section h3 {
            color: var(--text-primary);
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .help-tips {
            font-size: 13px;
            color: var(--text-secondary);
            line-height: 1.6;
        }
        
        .help-tips li {
            margin-bottom: 10px;
        }
        
        .help-tips strong {
            color: var(--text-primary);
        }
    </style>
</head>
<body>
    <div class="connection-container">
        <h1>🗄️ MongoDB Admin</h1>
        <p>Connect to your MongoDB database to manage collections and documents</p>
        
        <?php if ($connectionError): ?>
            <div class="error-message">
                <strong>⚠️ Connection Error:</strong><br>
                <?php echo htmlspecialchars($connectionError); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="connect" value="1">
            
            <div class="form-group">
                <label>Hostname:</label>
                <input type="text" name="hostname" placeholder="localhost" value="localhost" required>
            </div>
            
            <div class="form-group">
                <label>Port:</label>
                <input type="number" name="port" placeholder="27017" value="27017" required>
            </div>
            
            <div class="form-group">
                <label>Database:</label>
                <input type="text" name="database" placeholder="mydb" required>
            </div>
            
            <div class="form-group">
                <label>Username (optional):</label>
                <input type="text" name="username" placeholder="username">
            </div>
            
            <div class="form-group">
                <label>Password (optional):</label>
                <input type="password" name="password" placeholder="password">
            </div>
            
            <div class="form-group">
                <label>Collection:</label>
                <input type="text" name="collection" placeholder="mycollection" required>
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
</body>
</html>
