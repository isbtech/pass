<?php
/******************************************************************************
* Software: Pass    - Private Audio Streaming Service                         *
* Version:  0.1 Alpha                                                         *
* Date:     2025-03-15                                                        *
* Author:   Sandeep - sans                                                    *
* License:  Free for All Church and Ministry Usage                            *
*                                                                             *
*  You may use and modify this software as you wish.   						  *
*  But Give Credits  and Feedback                                             *
*******************************************************************************/
// Enable error reporting during development (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Initialize variables
$error = '';
$success = '';
$audioFiles = [];
$site_name = defined('SITE_NAME') ? SITE_NAME : 'Private Audio Streaming';

// Process form submissions
if (isset($_POST['audio_file_id']) && isset($_SESSION['verified_access_code'])) {
    $audio_file_id = (int)$_POST['audio_file_id'];
    $access_code_id = (int)$_SESSION['verified_access_code'];
    
    // Get audio file data
    $audio_file = db_select_one("SELECT * FROM audio_files WHERE id = $audio_file_id AND is_active = 1");
    
    // Get access code data
    $accessCode = db_select_one("SELECT * FROM access_codes WHERE id = $access_code_id AND is_active = 1");
    
    if ($audio_file && $accessCode) {
        // Generate a unique session ID
        $session_id = generate_uuid();
        
        // Create streaming session
        db_insert('streaming_sessions', [
            'session_id' => $session_id,
            'access_code_id' => $accessCode['id'],
            'audio_file_id' => $audio_file['id'],
            'user_id' => is_logged_in() ? $_SESSION['user_id'] : NULL,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'status' => 'active'
        ]);
        
        // Log the access
        db_insert('access_logs', [
            'access_code_id' => $accessCode['id'],
            'audio_file_id' => $audio_file['id'],
            'user_id' => is_logged_in() ? $_SESSION['user_id'] : NULL,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'played_duration' => 0
        ]);
        
        // Clear the verified access code from session
        unset($_SESSION['verified_access_code']);
        
        // Redirect to player
        header("Location: player.php?session_id=$session_id");
        exit;
    } else {
        $error = 'Invalid audio file or access code selection.';
    }
}

// Check if access code is provided in URL
if (isset($_GET['code'])) {
    $code = trim($_GET['code']);
    
    // Verify the access code
    $accessCode = db_select_one("SELECT * FROM access_codes WHERE code = '" . 
        mysqli_real_escape_string(db_connect(), $code) . "' AND is_active = 1");
    
    if (!$accessCode) {
        $error = 'Invalid access code.';
    } else {
        // Check if code has expired
        if ($accessCode['valid_until'] !== NULL && strtotime($accessCode['valid_until']) < time()) {
            $error = 'This access code has expired.';
        } else {
            // Check if maximum plays has been reached
            if ($accessCode['max_plays'] !== NULL) {
                $play_count = db_select_one("SELECT COUNT(*) as count FROM access_logs WHERE access_code_id = {$accessCode['id']}");
                
                if ($play_count && $play_count['count'] >= $accessCode['max_plays']) {
                    $error = 'This access code has reached its maximum number of plays.';
                }
            }
            
            // Check if maximum playtime has been reached
            if ($accessCode['max_playtime'] !== NULL && empty($error)) {
                $total_playtime = db_select_one("SELECT SUM(played_duration) as total FROM access_logs WHERE access_code_id = {$accessCode['id']}");
                
                if ($total_playtime && $total_playtime['total'] >= $accessCode['max_playtime']) {
                    $error = 'This access code has reached its maximum playtime.';
                }
            }
            
            // If all checks pass, proceed
            if (empty($error)) {
                // If code is for a specific audio file, redirect to player
                if ($accessCode['audio_file_id']) {
                    $audio_file = db_select_one("SELECT * FROM audio_files WHERE id = {$accessCode['audio_file_id']} AND is_active = 1");
                    
                    if ($audio_file) {
                        // Generate a unique session ID
                        $session_id = generate_uuid();
                        
                        // Create streaming session
                        db_insert('streaming_sessions', [
                            'session_id' => $session_id,
                            'access_code_id' => $accessCode['id'],
                            'audio_file_id' => $audio_file['id'],
                            'user_id' => is_logged_in() ? $_SESSION['user_id'] : NULL,
                            'ip_address' => $_SERVER['REMOTE_ADDR'],
                            'status' => 'active'
                        ]);
                        
                        // Log the access
                        db_insert('access_logs', [
                            'access_code_id' => $accessCode['id'],
                            'audio_file_id' => $audio_file['id'],
                            'user_id' => is_logged_in() ? $_SESSION['user_id'] : NULL,
                            'ip_address' => $_SERVER['REMOTE_ADDR'],
                            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                            'played_duration' => 0
                        ]);
                        
                        // Redirect to player
                        header("Location: player.php?session_id=$session_id");
                        exit;
                    } else {
                        $error = 'The associated audio file is no longer available.';
                    }
                } else {
                    // Code allows access to multiple files, show selection
                    $audioFiles = db_select("SELECT * FROM audio_files WHERE is_active = 1 ORDER BY title");
                    
                    if (empty($audioFiles)) {
                        $error = 'No audio files are currently available.';
                    } else {
                        // Store the access code in session for file selection
                        $_SESSION['verified_access_code'] = $accessCode['id'];
                        
                        $success = 'Access code verified. Please select an audio file.';
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Verification - <?php echo $site_name; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo $site_name; ?></h1>
        </div>
        
        <div class="content">
            <div class="access-container">
                <div class="access-header">
                    <h2><i class="fas fa-key"></i> Audio Access Verification</h2>
                    <p class="subtitle">Enter your access code to listen to protected audio content</p>
                </div>
                
                <?php if (!empty($error)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($success)): ?>
                    <!-- Access Code Form -->
                    <div class="verification-form">
                        <form method="get" action="access.php">
                            <div class="form-group">
                                <label for="code">Enter Access Code</label>
                                <div class="input-group">
                                    <div class="input-icon">
                                        <i class="fas fa-unlock-alt icon"></i>
                                        <input type="text" id="code" name="code" placeholder="Enter your access code" required autocomplete="off">
                                    </div>
                                    <button type="submit" class="button primary">
                                        <i class="fas fa-check"></i> Verify Code
                                    </button>
                                </div>
                                <small>Enter the access code you received to unlock audio content</small>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <!-- Audio File Selection -->
                    <?php if (isset($audioFiles) && !empty($audioFiles)): ?>
                        <div class="audio-selection">
                            <h3><i class="fas fa-music"></i> Select an Audio File</h3>
                            
                            <form method="post" action="access.php">
                                <div class="audio-list">
                                    <?php foreach ($audioFiles as $file): ?>
                                        <div class="audio-item">
                                            <input type="radio" id="file-<?php echo $file['id']; ?>" name="audio_file_id" value="<?php echo $file['id']; ?>" required>
                                            <label for="file-<?php echo $file['id']; ?>">
                                                <div class="audio-info">
                                                    <span class="title"><?php echo htmlspecialchars($file['title']); ?></span>
                                                    <?php if (isset($file['duration']) && $file['duration']): ?>
                                                        <span class="duration"><i class="far fa-clock"></i> <?php echo format_duration($file['duration']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (isset($file['description']) && $file['description']): ?>
                                                    <p class="description"><?php echo htmlspecialchars($file['description']); ?></p>
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="form-group center mt-20">
                                    <button type="submit" class="button primary">
                                        <i class="fas fa-play"></i> Play Selected Audio
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <div class="center mt-20">
                <a href="index.php" class="text-link"><i class="fas fa-home"></i> Back to Home</a> | 
                <a href="login.php" class="text-link"><i class="fas fa-sign-in-alt"></i> Login</a>
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo $site_name; ?> | Secure Audio Streaming Platform</p>
        </div>
    </div>
    
    <style>
        .access-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .access-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .subtitle {
            color: var(--text-muted);
            margin-top: -5px;
        }
        
        .verification-form {
            max-width: 600px;
            margin: 30px auto;
            padding: 25px;
            background-color: #f8f9fa;
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
        }
        
        .input-group {
            display: flex;
            gap: 10px;
        }
        
        .input-group .input-icon {
            flex: 1;
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
        
        .audio-item {
            display: flex;
            align-items: flex-start;
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            transition: var(--transition);
        }
        
        .audio-item:last-child {
            border-bottom: none;
        }
        
        .audio-item:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .audio-item input[type="radio"] {
            margin-top: 3px;
            margin-right: 15px;
        }
        
        .audio-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }
        
        .audio-item .title {
            font-weight: 500;
            font-size: 1.1rem;
            color: var(--dark-color);
        }
        
        .audio-item .duration {
            color: var(--text-muted);
            font-size: 0.875rem;
        }
        
        .audio-item .description {
            margin-top: 8px;
            color: var(--text-muted);
            font-size: 0.875rem;
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
        
        @media (max-width: 768px) {
            .input-group {
                flex-direction: column;
            }
        }
    </style>
</body>
</html>
