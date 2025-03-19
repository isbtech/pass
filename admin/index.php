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
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require admin access
require_admin();

// Get system statistics
$total_audio_files = db_select_one("SELECT COUNT(*) as count FROM audio_files")['count'];
$active_audio_files = db_select_one("SELECT COUNT(*) as count FROM audio_files WHERE is_active = 1")['count'];

$total_access_codes = db_select_one("SELECT COUNT(*) as count FROM access_codes")['count'];
$active_access_codes = db_select_one("SELECT COUNT(*) as count FROM access_codes WHERE is_active = 1")['count'];

$total_plays = db_select_one("SELECT COUNT(*) as count FROM access_logs")['count'];

// Get recent access logs
$recent_logs = db_select("
    SELECT al.*, af.title as audio_title, ac.code as access_code 
    FROM access_logs al
    JOIN audio_files af ON al.audio_file_id = af.id
    JOIN access_codes ac ON al.access_code_id = ac.id
    ORDER BY al.created_at DESC
    LIMIT 10
");

// Get top audio files
$top_audio_files = db_select("
    SELECT af.id, af.title, COUNT(al.id) as play_count
    FROM audio_files af
    LEFT JOIN access_logs al ON af.id = al.audio_file_id
    GROUP BY af.id, af.title
    ORDER BY play_count DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Private Audio Streaming</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Private Audio Streaming - Admin</h1>
            <div class="nav">
                <a href="index.php" class="active">Dashboard</a>
                <a href="audio-files.php">Audio Files</a>
                <a href="access-codes.php">Access Codes</a>
                <a href="stats.php">Statistics</a>
                <a href="../logout.php">Logout</a>
            </div>
        </div>
        
        <div class="content">
            <h2>Admin Dashboard</h2>
            
            <div class="dashboard-stats">
                <div class="stat-box">
                    <h3>Audio Files</h3>
                    <div class="stat-number"><?php echo $active_audio_files; ?> / <?php echo $total_audio_files; ?></div>
                    <div class="stat-label">Active / Total</div>
                    <div class="stat-action">
                        <a href="audio-files.php" class="button small">Manage Files</a>
                    </div>
                </div>
                
                <div class="stat-box">
                    <h3>Access Codes</h3>
                    <div class="stat-number"><?php echo $active_access_codes; ?> / <?php echo $total_access_codes; ?></div>
                    <div class="stat-label">Active / Total</div>
                    <div class="stat-action">
                        <a href="access-codes.php" class="button small">Manage Codes</a>
                    </div>
                </div>
                
                <div class="stat-box">
                    <h3>Total Plays</h3>
                    <div class="stat-number"><?php echo $total_plays; ?></div>
                    <div class="stat-label">All Time</div>
                    <div class="stat-action">
                        <a href="stats.php" class="button small">View Stats</a>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-panels">
                <div class="panel-section">
                    <h3>Recent Activity</h3>
                    
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>Audio</th>
                                <th>Access Code</th>
                                <th>IP Address</th>
                                <th>Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_logs as $log): ?>
                                <tr>
                                    <td><?php echo date('Y-m-d H:i', strtotime($log['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($log['audio_title']); ?></td>
                                    <td><code><?php echo $log['access_code']; ?></code></td>
                                    <td><?php echo $log['ip_address']; ?></td>
                                    <td><?php echo format_duration($log['played_duration']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($recent_logs)): ?>
                                <tr>
                                    <td colspan="5" class="center">No recent activity</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="panel-section">
                    <h3>Top Audio Files</h3>
                    
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Audio Title</th>
                                <th>Play Count</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_audio_files as $file): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($file['title']); ?></td>
                                    <td><?php echo $file['play_count']; ?></td>
                                    <td>
                                        <a href="stats.php?file_id=<?php echo $file['id']; ?>" class="button small">Stats</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($top_audio_files)): ?>
                                <tr>
                                    <td colspan="3" class="center">No audio files played yet</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="admin-actions">
                <h3>Quick Actions</h3>
                <div class="action-buttons">
                    <a href="audio-files.php?action=add" class="button">Upload Audio File</a>
                    <a href="access-codes.php?action=generate" class="button">Generate Access Code</a>
                    <a href="stats.php" class="button">View Detailed Stats</a>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> Private Audio Streaming</p>
        </div>
    </div>
    
    <style>
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-box {
            background-color: #f5f5f5;
            border-radius: 5px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            margin: 10px 0;
            color: #4a90e2;
        }
        
        .stat-label {
            color: #777;
            margin-bottom: 15px;
        }
        
        .dashboard-panels {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .admin-actions {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
        }
        
        .admin-actions .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        @media (max-width: 768px) {
            .dashboard-stats,
            .dashboard-panels {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html>