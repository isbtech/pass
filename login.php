<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if already logged in
if (is_logged_in()) {
    // Redirect to appropriate page based on user role
    if (is_admin()) {
        header('Location: admin/index.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

$error = '';
$success = '';

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        // Attempt to login
        if (login($email, $password)) {
            // Redirect to appropriate page based on user role
            if (is_admin()) {
                header('Location: admin/index.php');
            } else {
                // Redirect to the page they were trying to access, or the homepage
                $redirect = isset($_SESSION['redirect_after_login']) ? $_SESSION['redirect_after_login'] : 'index.php';
                unset($_SESSION['redirect_after_login']);
                header("Location: $redirect");
            }
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate inputs
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Attempt to register
        $result = register_user($name, $email, $password);
        
        if ($result['success']) {
            $success = 'Registration successful! You can now login.';
        } else {
            $error = $result['message'];
        }
    }
}

$site_name = defined('SITE_NAME') ? SITE_NAME : 'Private Audio Streaming';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo $site_name; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo $site_name; ?></h1>
        </div>
        
        <div class="content">
            <div class="login-container">
                <div class="login-header">
                    <h2><i class="fas fa-headphones-alt"></i> Welcome</h2>
                    <p class="subtitle">Sign in to your account or create a new one</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <div class="auth-container">
                    <!-- Login Form -->
                    <div class="auth-form">
                        <h3><i class="fas fa-sign-in-alt"></i> Login</h3>
                        <form method="post" action="login.php">
                            <input type="hidden" name="login" value="1">
                            
                            <div class="form-group">
                                <label for="login-email">Email</label>
                                <div class="input-icon">
                                    <i class="fas fa-envelope icon"></i>
                                    <input type="email" id="login-email" name="email" placeholder="Your email address" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="login-password">Password</label>
                                <div class="input-icon">
                                    <i class="fas fa-lock icon"></i>
                                    <input type="password" id="login-password" name="password" placeholder="Your password" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="button primary full-width">
                                    <i class="fas fa-sign-in-alt"></i> Login
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Registration Form -->
                    <div class="auth-form">
                        <h3><i class="fas fa-user-plus"></i> Register</h3>
                        <form method="post" action="login.php">
                            <input type="hidden" name="register" value="1">
                            
                            <div class="form-group">
                                <label for="register-name">Name</label>
                                <div class="input-icon">
                                    <i class="fas fa-user icon"></i>
                                    <input type="text" id="register-name" name="name" placeholder="Your full name" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="register-email">Email</label>
                                <div class="input-icon">
                                    <i class="fas fa-envelope icon"></i>
                                    <input type="email" id="register-email" name="email" placeholder="Your email address" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="register-password">Password</label>
                                <div class="input-icon">
                                    <i class="fas fa-lock icon"></i>
                                    <input type="password" id="register-password" name="password" placeholder="Create a password" required minlength="8">
                                </div>
                                <small>Password must be at least 8 characters long</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="register-confirm-password">Confirm Password</label>
                                <div class="input-icon">
                                    <i class="fas fa-lock icon"></i>
                                    <input type="password" id="register-confirm-password" name="confirm_password" placeholder="Confirm your password" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="button full-width">
                                    <i class="fas fa-user-plus"></i> Create Account
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="center mt-20">
                    <a href="index.php" class="text-link"><i class="fas fa-home"></i> Back to Home</a> | 
                    <a href="access.php" class="text-link"><i class="fas fa-key"></i> Enter Access Code</a>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo $site_name; ?> | Secure Audio Streaming Platform</p>
        </div>
    </div>
    
    <style>
        .login-container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .subtitle {
            color: var(--text-muted);
            margin-top: -5px;
        }
        
        .input-icon {
            position: relative;
        }
        
        .input-icon .icon {
            position: absolute;
            left: 12px;
            top: 14px;
            color: var(--text-muted);
        }
        
        .input-icon input {
            padding-left: 40px;
        }
        
        .full-width {
            width: 100%;
        }
        
        .text-link {
            color: var(--primary-color);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .text-link:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
    </style>
</body>
</html>