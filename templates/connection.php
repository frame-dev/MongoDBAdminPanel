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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .connection-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            max-width: 450px;
            width: 100%;
        }
        
        .connection-container h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .connection-container p {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
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
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="connection-container">
        <h1>🗄️ MongoDB Admin</h1>
        <p>Connect to your MongoDB database to manage collections and documents</p>
        
        <?php if ($connectionError): ?>
            <div class="error-message">
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
    </div>
</body>
</html>
