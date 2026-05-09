<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_POST) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    require_once '../config/database.php';
    require_once '../config/auth.php';
    
    $database = new Database();
    $db = $database->getConnection();
    $auth = new Auth($db);
    
    $login_result = $auth->login($username, $password);
    
    if ($login_result === true) {
        header("Location: dashboard.php");
        exit();
    } else {
        // More specific error messages
        if ($login_result === 'inactive') {
            $error = "This account is inactive. Please contact the administrator.";
        } elseif ($login_result === 'invalid_password') {
            $error = "Invalid password.";
        } elseif ($login_result === 'user_not_found') {
            $error = "Username not found.";
        } else {
            $error = "Invalid username or password";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - BarangayHub</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
 <style>
    :root {
        --primary: #2c5aa0;
        --primary-dark: #1e3d72;
        --secondary: #d4af37;
        --light: #f8f9fa;
        --dark: #212529;
        --gray: #6c757d;
        --light-gray: #e9ecef;
        --success: #28a745;
        --danger: #dc3545;
        --border-radius: 8px;
        --shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        --transition: all 0.3s ease;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    body {
        background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        color: #333;
    }

    .login-container {
        display: flex;
        width: 100%;
        max-width: 1000px;
        min-height: 600px;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: var(--shadow);
    }

    .login-left {
        flex: 1;
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        color: white;
        padding: 50px 40px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        position: relative;
        overflow: hidden;
    }

    .login-left::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="100" height="100" opacity="0.1"><path fill="white" d="M20,20 L80,20 L80,80 L20,80 Z M30,30 L70,30 L70,70 L30,70 Z"/></svg>');
        background-size: 50px;
    }

    .logo {
        display: flex;
        align-items: center;
        margin-bottom: 30px;
        position: relative;
        z-index: 1;
    }

    .logo i {
        font-size: 36px;
        margin-right: 15px;
        background: rgba(255, 255, 255, 0.2);
        padding: 15px;
        border-radius: 10px;
    }

    .logo h1 {
        font-size: 28px;
        font-weight: 700;
    }

    .tagline {
        font-size: 18px;
        margin-bottom: 10px;
        position: relative;
        z-index: 1;
    }

    .description {
        font-size: 16px;
        line-height: 1.6;
        margin-bottom: 30px;
        opacity: 0.9;
        position: relative;
        z-index: 1;
    }

    .features {
        list-style: none;
        margin-top: 30px;
        position: relative;
        z-index: 1;
    }

    .features li {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
        font-size: 15px;
    }

    .features i {
        margin-right: 10px;
        color: var(--secondary);
    }

    .login-right {
        flex: 1;
        background: white;
        padding: 50px 40px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .login-header {
        margin-bottom: 30px;
    }

    .login-title {
        font-size: 24px;
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 10px;
    }

    .login-subtitle {
        font-size: 14px;
        color: var(--gray);
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: var(--dark);
        font-size: 14px;
    }

    .form-control {
        width: 100%;
        padding: 14px 16px;
        border: 1px solid var(--light-gray);
        border-radius: var(--border-radius);
        font-size: 15px;
        transition: var(--transition);
        background-color: #fafafa;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(44, 90, 160, 0.15);
        background-color: white;
    }

    .input-icon {
        position: relative;
    }

    .input-icon i {
        position: absolute;
        right: 15px;
        top: 14px;
        color: var(--gray);
    }

    .password-toggle {
        position: absolute;
        right: 15px;
        top: 14px;
        cursor: pointer;
        color: var(--gray);
        transition: var(--transition);
    }

    .password-toggle:hover {
        color: var(--primary);
    }

    .btn {
        padding: 14px 20px;
        border: none;
        border-radius: var(--border-radius);
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        color: white;
        width: 100%;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(44, 90, 160, 0.3);
    }

    .notification {
        padding: 12px 16px;
        border-radius: var(--border-radius);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 14px;
        animation: slideIn 1.2s ease;
    }

    @keyframes slideIn {
        0% { 
            opacity: 0; 
            transform: translateY(-20px); 
        }
        50% {
            opacity: 0.5;
            transform: translateY(-10px);
        }
        100% { 
            opacity: 1; 
            transform: translateY(0); 
        }
    }

    .notification.error {
        background-color: rgba(220, 53, 69, 0.1);
        color: var(--danger);
        border-left: 4px solid var(--danger);
    }

    .notification.warning {
        background-color: rgba(255, 193, 7, 0.1);
        color: #856404;
        border-left: 4px solid #ffc107;
    }

    .login-footer {
        margin-top: 30px;
        text-align: center;
        font-size: 14px;
        color: var(--gray);
        border-top: 1px solid var(--light-gray);
        padding-top: 20px;
    }

    .login-footer a {
        color: var(--primary);
        text-decoration: none;
        font-weight: 500;
    }

    .login-footer a:hover {
        text-decoration: underline;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .login-container {
            flex-direction: column;
            max-width: 500px;
        }
        
        .login-left, .login-right {
            padding: 40px 30px;
        }
        
        .login-left {
            text-align: center;
        }
        
        .logo {
            justify-content: center;
        }
    }

    @media (max-width: 480px) {
        .login-left, .login-right {
            padding: 30px 20px;
        }
        
        .logo h1 {
            font-size: 24px;
        }
        
        .tagline {
            font-size: 16px;
        }
        
        .description {
            font-size: 14px;
        }
    }

    /* Loading animation for button */
    .btn-loading {
        position: relative;
        color: transparent;
    }

    .btn-loading::after {
        content: '';
        position: absolute;
        width: 20px;
        height: 20px;
        top: 50%;
        left: 50%;
        margin-left: -10px;
        margin-top: -10px;
        border: 2px solid #ffffff;
        border-radius: 50%;
        border-top-color: transparent;
        animation: spin 1s ease-in-out infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }
</style>
</head>
<body>
    <div class="login-container">
        <div class="login-left">
            <div class="logo">
                <i class="fas fa-home"></i>
                <h1>BarangayHub</h1>
            </div>
            <p class="tagline">Secure Document Management System</p>
            <p class="description">Quick, easy and secure: BarangayHub's Online Document Request System for administrators.</p>
            
            <ul class="features">
                <li><i class="fas fa-shield-alt"></i> Secure authentication system</li>
                <li><i class="fas fa-tachometer-alt"></i> Comprehensive admin dashboard</li>
                <li><i class="fas fa-file-contract"></i> Document request management</li>
                <li><i class="fas fa-user-check"></i> Resident verification</li>
            </ul>
        </div>
        
        <div class="login-right">
            <div class="login-header">
                <h2 class="login-title">ADMIN LOGIN</h2>
                <p class="login-subtitle">Access the administrative dashboard</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="notification <?php echo (strpos($error, 'inactive') !== false) ? 'warning' : 'error'; ?>">
                    <i class="fas <?php echo (strpos($error, 'inactive') !== false) ? 'fa-user-slash' : 'fa-exclamation-triangle'; ?>"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="loginForm">
                <div class="form-group">
                    <label for="username">Admin Username</label>
                    <div class="input-icon">
                        <input type="text" id="username" name="username" class="form-control" placeholder="Enter your admin username" required>
                        <i class="fas fa-user"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-icon">
                        <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                        <i class="fas fa-lock"></i>
                        <span class="password-toggle" id="passwordToggle">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary" id="loginButton">
                    <i class="fas fa-sign-in-alt"></i> Login to Dashboard
                </button>
            </form>
            
            <div class="login-footer">
                <p>BarangayHub Admin Portal | © Copyright 2023 BarangayHub</p>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        document.getElementById('passwordToggle').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        // Form validation and loading state
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            const loginButton = document.getElementById('loginButton');
            
            if (!username || !password) {
                e.preventDefault();
                alert('Please fill in all fields');
                return;
            }
            
            // Add loading state to button
            loginButton.classList.add('btn-loading');
            loginButton.disabled = true;
            
            // Form will submit normally, this is just for visual feedback
        });
    </script>
</body>
</html>