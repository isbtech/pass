<?php
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require admin access
require_admin();

// Process form submissions
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Generate new access code
    if (isset($_POST['generate'])) {
        $audio_file_id = $_POST['audio_file_id'] ? (int)$_POST['audio_file_id'] : NULL;
        $valid_days = isset($_POST['valid_days']) ? (int)$_POST['valid_days'] : NULL;
        $valid_hours = isset($_POST['valid_hours']) ? (int)$_POST['valid_hours'] : NULL;
        $max_plays = isset($_POST['max_plays']) ? (int)$_POST['max_plays'] : NULL;
        $max_playtime = isset($_POST['max_playtime']) ? (int)$_POST['max_playtime'] : NULL;
        
        // Calculate expiration time
        $valid_until = NULL;
        if ($valid_days || $valid_hours) {
            $valid_until = date('Y-m-d H:i:s', strtotime('+' . ($valid_days ?: 0) . ' days +' . ($valid_hours ?: 0) . ' hours'));
        }
        
        // Generate unique code
        $code = generate_access_code();
        
        // Insert access code
        $access_code_id = db_insert('access_codes', [
            'code' => $code,
            'audio_file_id' => $audio_file_id,
            'created_by' => $_SESSION['user_id'],
            'valid_until' => $valid_until,
            'max_plays' => $max_plays,
            'max_playtime' => $max_playtime
        ]);
        
        if ($access_code_id) {
            $message = "Access code created successfully: $code";
        } else {
            $message = "Failed to create access code.";
        }
    }
    
    // Deactivate access code
    if (isset($_POST['deactivate'])) {
        $access_code_id = (int)$_POST['access_code_id'];
        
        db_update('access_codes', ['is_active' => 0], "id = $access_code_id");
        
        $message = "Access code deactivated successfully.";
    }
}

// Get all access codes
$accessCodes = db_select("
    SELECT ac.*, af.title as audio_title, u.name as created_by_name 
    FROM access_codes ac 
    LEFT JOIN audio_files af ON ac.audio_file_id = af.id
    LEFT JOIN users u ON ac.created_by = u.id
    ORDER BY ac.created_at DESC
");

// Get all active audio files for selection
$audioFiles = db_select("SELECT id, title, duration FROM audio_files WHERE is_active = 1 ORDER BY title");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Access Codes - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo SITE_NAME; ?> - Admin</h1>
            <div class="nav">
                <a href="index.php">Dashboard</a>
                <a href="audio-files.php">Audio Files</a>
                <a href="access-codes.php" class="active">Access Codes</a>
                <a href="stats.php">Statistics</a>
                <a href="../logout.php">Logout</a>
            </div>
        </div>
        
        <div class="content">
            <h2>Manage Access Codes</h2>
            
            <?php if ($message): ?>
                <div class="message">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <div class="admin-panel">
                <div class="panel-section">
                    <h3>Generate New Access Code</h3>
                    
                    <form method="post" action="access-codes.php">
                        <input type="hidden" name="generate" value="1">
                        
                        <div class="form-group">
                            <label for="audio_file_id">Audio File:</label>
                            <select id="audio_file_id" name="audio_file_id">
                                <option value="">-- All Audio Files --</option>
                                <?php foreach ($audioFiles as $file): ?>
                                    <option value="<?php echo $file['id']; ?>">
                                        <?php echo html_escape($file['title']); ?>
                                        <?php if ($file['duration']): ?>
                                            (<?php echo format_duration($file['duration']); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small>Leave blank to grant access to all audio files</small>
                        </div>
                        
                        <h4>Validity Settings</h4>
                        
                        <div class="form-group">
                            <label for="valid_days">Valid for Days:</label>
                            <input type="number" id="valid_days" name="valid_days" min="0">
                            <small>Number of days the code will be valid (leave blank for no day limit)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="valid_hours">Valid for Hours:</label>
                            <input type="number" id="valid_hours" name="valid_hours" min="0">
                            <small>Additional hours the code will be valid (leave blank for no hour limit)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="max_plays">Maximum Plays:</label>
                            <input type="number" id="max_plays" name="max_plays" min="1">
                            <small>Maximum number of times the audio can be played (leave blank for unlimited)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="max_playtime">Maximum Playtime (seconds):</label>
                            <input type="number" id="max_playtime" name="max_playtime" min="1">
                            <small>Maximum playtime in seconds (leave blank for unlimited)</small>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="button primary">Generate Access Code</button>
                        </div>
                    </form>
                </div>
                
                <div class="panel-section">
                    <h3>Existing Access Codes</h3>
                    
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Audio File</th>
                                <th>Created By</th>
                                <th>Valid Until</th>
                                <th>Limits</th>
                                <th>Status</th>
                                <th>Usage</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($accessCodes as $code): ?>
                                <?php
                                // Get usage stats
                                $playCount = db_select_one("SELECT COUNT(*) as count FROM access_logs WHERE access_code_id = {$code['id']}");
                                $totalPlaytime = db_select_one("SELECT SUM(played_duration) as total FROM access_logs WHERE access_code_id = {$code['id']}");
                                ?>
                                <tr>
                                    <td><code><?php echo $code['code']; ?></code></td>
                                    <td>
                                        <?php if ($code['audio_file_id']): ?>
                                            <?php echo html_escape($code['audio_title'] ?: 'Deleted File'); ?>
                                        <?php else: ?>
                                            <span class="badge">All Files</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo html_escape($code['created_by_name']); ?></td>
                                    <td>
                                        <?php if ($code['valid_until']): ?>
                                            <?php echo date('Y-m-d H:i', strtotime($code['valid_until'])); ?>
                                            <?php if (strtotime($code['valid_until']) < time()): ?>
                                                <span class="badge red">Expired</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge green">No Expiry</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($code['max_plays']): ?>
                                            <div>Plays: <?php echo $code['max_plays']; ?></div>
                                        <?php endif; ?>
                                        <?php if ($code['max_playtime']): ?>
                                            <div>Time: <?php echo $code['max_playtime']; ?> sec</div>
                                        <?php endif; ?>
                                        <?php if (!$code['max_plays'] && !$code['max_playtime']): ?>
                                            <span class="badge green">No Limits</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($code['is_active']): ?>
                                            <span class="badge green">Active</span>
                                        <?php else: ?>
                                            <span class="badge grey">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div>Plays: <?php echo $playCount['count']; ?></div>
                                        <div>Time: <?php echo format_duration($totalPlaytime['total'] ?: 0); ?></div>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="button small copy-btn" data-code="<?php echo $code['code']; ?>" title="Copy Code">Copy</button>
                                            
                                            <?php if ($code['is_active']): ?>
                                                <form method="post" action="access-codes.php" onsubmit="return confirm('Are you sure you want to deactivate this code?')">
                                                    <input type="hidden" name="deactivate" value="1">
                                                    <input type="hidden" name="access_code_id" value="<?php echo $code['id']; ?>">
                                                    <button type="submit" class="button small red">Deactivate</button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <a href="stats.php?code_id=<?php echo $code['id']; ?>" class="button small">Stats</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($accessCodes)): ?>
                                <tr>
                                    <td colspan="8" class="center">No access codes found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?></p>
        </div>
    </div>
    
    <script>
        // Copy access code to clipboard
        document.addEventListener('DOMContentLoaded', function() {
            const copyButtons = document.querySelectorAll('.copy-btn');
            
            copyButtons.forEach(function(button) {
                button.addEventListener('click', function() {
                    const code = this.getAttribute('data-code');
                    
                    // Create a temporary input element
                    const input = document.createElement('input');
                    input.value = code;
                    document.body.appendChild(input);
                    
                    // Select and copy the text
                    input.select();
                    document.execCommand('copy');
                    
                    // Remove the temporary element
                    document.body.removeChild(input);
                    
                    // Show feedback
                    const originalText = this.textContent;
                    this.textContent = 'Copied!';
                    this.classList.add('green');
                    
                    // Reset after a short delay
                    setTimeout(() => {
                        this.textContent = originalText;
                        this.classList.remove('green');
                    }, 1500);
                });
            });
        });
    </script>
</body>
</html>