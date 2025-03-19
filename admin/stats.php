<?php
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require admin access
require_admin();

// Set default date range (last 30 days)
$end_date = date('Y-m-d');
$start_date = date('Y-m-d', strtotime('-30 days'));

// Handle date range selection
if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $start_date = $_GET['start_date'];
    $end_date = $_GET['end_date'];
}

// Check if we're viewing stats for a specific audio file
$file_stats = false;
$file_data = null;

if (isset($_GET['file_id'])) {
    $file_id = (int)$_GET['file_id'];
    $file_data = db_select_one("SELECT * FROM audio_files WHERE id = $file_id");
    
    if ($file_data) {
        $file_stats = true;
    }
}

// Check if we're viewing stats for a specific access code
$code_stats = false;
$code_data = null;

if (isset($_GET['code_id'])) {
    $code_id = (int)$_GET['code_id'];
    $code_data = db_select_one("SELECT * FROM access_codes WHERE id = $code_id");
    
    if ($code_data) {
        $code_stats = true;
    }
}

// Function to get stats based on date range
function get_overall_stats($start_date, $end_date) {
    // Get total plays
    $plays_query = "SELECT COUNT(*) as count FROM access_logs WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'";
    $total_plays = db_select_one($plays_query)['count'];
    
    // Get total playback time
    $playtime_query = "SELECT SUM(played_duration) as total FROM access_logs WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'";
    $total_playtime = db_select_one($playtime_query)['total'] ?: 0;
    
    // Get unique listeners (by IP)
    $unique_query = "SELECT COUNT(DISTINCT ip_address) as count FROM access_logs WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'";
    $unique_listeners = db_select_one($unique_query)['count'];
    
    // Get top audio files
    $top_files_query = "
        SELECT af.id, af.title, COUNT(al.id) as play_count, SUM(al.played_duration) as total_playtime
        FROM audio_files af
        JOIN access_logs al ON af.id = al.audio_file_id
        WHERE DATE(al.created_at) BETWEEN '$start_date' AND '$end_date'
        GROUP BY af.id, af.title
        ORDER BY play_count DESC
        LIMIT 10
    ";
    $top_files = db_select($top_files_query);
    
    // Get top access codes
    $top_codes_query = "
        SELECT ac.id, ac.code, COUNT(al.id) as usage_count
        FROM access_codes ac
        JOIN access_logs al ON ac.id = al.access_code_id
        WHERE DATE(al.created_at) BETWEEN '$start_date' AND '$end_date'
        GROUP BY ac.id, ac.code
        ORDER BY usage_count DESC
        LIMIT 10
    ";
    $top_codes = db_select($top_codes_query);
    
    // Get daily activity
    $daily_activity_query = "
        SELECT DATE(created_at) as date, COUNT(*) as plays
        FROM access_logs
        WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'
        GROUP BY DATE(created_at)
        ORDER BY date
    ";
    $daily_activity = db_select($daily_activity_query);
    
    return [
        'total_plays' => $total_plays,
        'total_playtime' => $total_playtime,
        'unique_listeners' => $unique_listeners,
        'top_files' => $top_files,
        'top_codes' => $top_codes,
        'daily_activity' => $daily_activity
    ];
}

// Get file-specific stats
function get_file_stats($file_id, $start_date, $end_date) {
    // Get total plays for this file
    $plays_query = "SELECT COUNT(*) as count FROM access_logs WHERE audio_file_id = $file_id AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'";
    $total_plays = db_select_one($plays_query)['count'];
    
    // Get total playback time
    $playtime_query = "SELECT SUM(played_duration) as total FROM access_logs WHERE audio_file_id = $file_id AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'";
    $total_playtime = db_select_one($playtime_query)['total'] ?: 0;
    
    // Get unique listeners (by IP)
    $unique_query = "SELECT COUNT(DISTINCT ip_address) as count FROM access_logs WHERE audio_file_id = $file_id AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'";
    $unique_listeners = db_select_one($unique_query)['count'];
    
    // Get top access codes for this file
    $top_codes_query = "
        SELECT ac.id, ac.code, COUNT(al.id) as usage_count
        FROM access_codes ac
        JOIN access_logs al ON ac.id = al.access_code_id
        WHERE al.audio_file_id = $file_id AND DATE(al.created_at) BETWEEN '$start_date' AND '$end_date'
        GROUP BY ac.id, ac.code
        ORDER BY usage_count DESC
        LIMIT 10
    ";
    $top_codes = db_select($top_codes_query);
    
    // Get daily activity
    $daily_activity_query = "
        SELECT DATE(created_at) as date, COUNT(*) as plays
        FROM access_logs
        WHERE audio_file_id = $file_id AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'
        GROUP BY DATE(created_at)
        ORDER BY date
    ";
    $daily_activity = db_select($daily_activity_query);
    
    // Get file duration for completion rate calculation
    $duration_query = "SELECT duration FROM audio_files WHERE id = $file_id";
    $duration = db_select_one($duration_query)['duration'];
    
    // Get completion breakdown
    $completion_ranges = [25, 50, 75, 90, 100];
    $completion_breakdown = [];
    
    foreach ($completion_ranges as $range) {
        $play_threshold = ($duration * $range) / 100;
        $count_query = "
            SELECT COUNT(*) as count
            FROM access_logs
            WHERE audio_file_id = $file_id 
            AND played_duration >= $play_threshold
            AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'
        ";
        $count = db_select_one($count_query)['count'];
        
        $completion_breakdown[] = [
            'range' => $range,
            'count' => $count,
            'percentage' => $total_plays > 0 ? round(($count / $total_plays) * 100, 1) : 0
        ];
    }
    
    return [
        'total_plays' => $total_plays,
        'total_playtime' => $total_playtime,
        'unique_listeners' => $unique_listeners,
        'top_codes' => $top_codes,
        'daily_activity' => $daily_activity,
        'completion_breakdown' => $completion_breakdown
    ];
}

// Get access code specific stats
function get_code_stats($code_id, $start_date, $end_date) {
    // Get total plays using this code
    $plays_query = "SELECT COUNT(*) as count FROM access_logs WHERE access_code_id = $code_id AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'";
    $total_plays = db_select_one($plays_query)['count'];
    
    // Get total playback time
    $playtime_query = "SELECT SUM(played_duration) as total FROM access_logs WHERE access_code_id = $code_id AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'";
    $total_playtime = db_select_one($playtime_query)['total'] ?: 0;
    
    // Get unique listeners (by IP)
    $unique_query = "SELECT COUNT(DISTINCT ip_address) as count FROM access_logs WHERE access_code_id = $code_id AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'";
    $unique_listeners = db_select_one($unique_query)['count'];
    
    // Get usage by audio file
    $file_usage_query = "
        SELECT af.id, af.title, COUNT(al.id) as play_count, SUM(al.played_duration) as total_playtime
        FROM audio_files af
        JOIN access_logs al ON af.id = al.audio_file_id
        WHERE al.access_code_id = $code_id AND DATE(al.created_at) BETWEEN '$start_date' AND '$end_date'
        GROUP BY af.id, af.title
        ORDER BY play_count DESC
    ";
    $file_usage = db_select($file_usage_query);
    
    // Get daily activity
    $daily_activity_query = "
        SELECT DATE(created_at) as date, COUNT(*) as plays
        FROM access_logs
        WHERE access_code_id = $code_id AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'
        GROUP BY DATE(created_at)
        ORDER BY date
    ";
    $daily_activity = db_select($daily_activity_query);
    
    // Get recent logs
    $recent_logs_query = "
        SELECT al.*, af.title as audio_title
        FROM access_logs al
        JOIN audio_files af ON al.audio_file_id = af.id
        WHERE al.access_code_id = $code_id
        ORDER BY al.created_at DESC
        LIMIT 20
    ";
    $recent_logs = db_select($recent_logs_query);
    
    return [
        'total_plays' => $total_plays,
        'total_playtime' => $total_playtime,
        'unique_listeners' => $unique_listeners,
        'file_usage' => $file_usage,
        'daily_activity' => $daily_activity,
        'recent_logs' => $recent_logs
    ];
}

// Get appropriate stats based on context
if ($file_stats) {
    $stats = get_file_stats($file_data['id'], $start_date, $end_date);
} elseif ($code_stats) {
    $stats = get_code_stats($code_data['id'], $start_date, $end_date);
} else {
    $stats = get_overall_stats($start_date, $end_date);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistics - Private Audio Streaming</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Private Audio Streaming - Admin</h1>
            <div class="nav">
                <a href="index.php">Dashboard</a>
                <a href="audio-files.php">Audio Files</a>
                <a href="access-codes.php">Access Codes</a>
                <a href="stats.php" class="active">Statistics</a>
                <a href="../logout.php">Logout</a>
            </div>
        </div>
        
        <div class="content">
            <?php if ($file_stats): ?>
                <h2>Statistics for "<?php echo htmlspecialchars($file_data['title']); ?>"</h2>
            <?php elseif ($code_stats): ?>
                <h2>Statistics for Access Code: <?php echo $code_data['code']; ?></h2>
            <?php else: ?>
                <h2>Overall Statistics</h2>
            <?php endif; ?>
            
            <!-- Date Range Filter -->
            <div class="date-filter">
                <form method="get" action="stats.php">
                    <?php if ($file_stats): ?>
                        <input type="hidden" name="file_id" value="<?php echo $file_data['id']; ?>">
                    <?php elseif ($code_stats): ?>
                        <input type="hidden" name="code_id" value="<?php echo $code_data['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="filter-group">
                        <label for="start_date">Start Date:</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="end_date">End Date:</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <button type="submit" class="button">Apply Filter</button>
                    </div>
                </form>
            </div>
            
            <!-- Statistics Overview -->
            <div class="stats-overview">
                <div class="stat-box">
                    <h3>Total Plays</h3>
                    <div class="stat-number"><?php echo $stats['total_plays']; ?></div>
                </div>
                
                <div class="stat-box">
                    <h3>Total Playtime</h3>
                    <div class="stat-number"><?php echo format_duration($stats['total_playtime']); ?></div>
                </div>
                
                <div class="stat-box">
                    <h3>Unique Listeners</h3>
                    <div class="stat-number"><?php echo $stats['unique_listeners']; ?></div>
                </div>
            </div>
            
            <?php if ($file_stats): ?>
                <!-- File-specific stats -->
                <div class="stats-section">
                    <h3>Completion Rates</h3>
                    <div class="completion-chart">
                        <?php foreach ($stats['completion_breakdown'] as $item): ?>
                            <div class="completion-bar">
                                <div class="completion-label"><?php echo $item['range']; ?>%</div>
                                <div class="completion-value" style="width: <?php echo $item['percentage']; ?>%;">
                                    <?php echo $item['count']; ?> (<?php echo $item['percentage']; ?>%)
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="chart-note">Number of users who listened to at least X% of the audio</p>
                </div>
                
                <div class="stats-section">
                    <h3>Top Access Codes</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Access Code</th>
                                <th>Usage Count</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats['top_codes'] as $code): ?>
                                <tr>
                                    <td><code><?php echo $code['code']; ?></code></td>
                                    <td><?php echo $code['usage_count']; ?></td>
                                    <td>
                                        <a href="stats.php?code_id=<?php echo $code['id']; ?>" class="button small">View Stats</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($stats['top_codes'])): ?>
                                <tr>
                                    <td colspan="3" class="center">No access codes used yet</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($code_stats): ?>
                <!-- Access code specific stats -->
                <div class="stats-section">
                    <h3>Usage by Audio File</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Audio Title</th>
                                <th>Play Count</th>
                                <th>Total Playtime</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats['file_usage'] as $file): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($file['title']); ?></td>
                                    <td><?php echo $file['play_count']; ?></td>
                                    <td><?php echo format_duration($file['total_playtime']); ?></td>
                                    <td>
                                        <a href="stats.php?file_id=<?php echo $file['id']; ?>" class="button small">View Stats</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($stats['file_usage'])): ?>
                                <tr>
                                    <td colspan="4" class="center">No audio files played with this code yet</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="stats-section">
                    <h3>Recent Activity</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>Audio Title</th>
                                <th>IP Address</th>
                                <th>Played Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats['recent_logs'] as $log): ?>
                                <tr>
                                    <td><?php echo date('Y-m-d H:i', strtotime($log['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($log['audio_title']); ?></td>
                                    <td><?php echo $log['ip_address']; ?></td>
                                    <td><?php echo format_duration($log['played_duration']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($stats['recent_logs'])): ?>
                                <tr>
                                    <td colspan="4" class="center">No recent activity for this access code</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <!-- Overall stats -->
                <div class="stats-section">
                    <h3>Top Audio Files</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Audio Title</th>
                                <th>Play Count</th>
                                <th>Total Playtime</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats['top_files'] as $file): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($file['title']); ?></td>
                                    <td><?php echo $file['play_count']; ?></td>
                                    <td><?php echo format_duration($file['total_playtime']); ?></td>
                                    <td>
                                        <a href="stats.php?file_id=<?php echo $file['id']; ?>" class="button small">View Stats</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($stats['top_files'])): ?>
                                <tr>
                                    <td colspan="4" class="center">No audio files played yet</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="stats-section">
                    <h3>Top Access Codes</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Access Code</th>
                                <th>Usage Count</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats['top_codes'] as $code): ?>
                                <tr>
                                    <td><code><?php echo $code['code']; ?></code></td>
                                    <td><?php echo $code['usage_count']; ?></td>
                                    <td>
                                        <a href="stats.php?code_id=<?php echo $code['id']; ?>" class="button small">View Stats</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($stats['top_codes'])): ?>
                                <tr>
                                    <td colspan="3" class="center">No access codes used yet</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <!-- Daily Activity Chart (Simple Text-Based Version) -->
            <div class="stats-section">
                <h3>Daily Activity</h3>
                <div class="daily-chart">
                    <?php foreach ($stats['daily_activity'] as $day): ?>
                        <div class="daily-bar">
                            <div class="daily-label"><?php echo date('M d', strtotime($day['date'])); ?></div>
                            <div class="daily-value" style="width: <?php echo min(100, $day['plays'] * 5); ?>%;">
                                <?php echo $day['plays']; ?> plays
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($stats['daily_activity'])): ?>
                        <p class="center">No activity data available for the selected date range</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> Private Audio Streaming</p>
        </div>
    </div>
    
    <style>
        .date-filter {
            display: flex;
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f5f5f5;
            border-radius: 5px;
        }
        
        .date-filter form {
            display: flex;
            gap: 15px;
            align-items: flex-end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stats-section {
            margin-bottom: 30px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }
        
        .completion-chart, .daily-chart {
            margin-top: 20px;
        }
        
        .completion-bar, .daily-bar {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .completion-label, .daily-label {
            width: 50px;
            text-align: right;
            padding-right: 10px;
            font-weight: bold;
        }
        
        .completion-value, .daily-value {
            background-color: #4a90e2;
            color: white;
            padding: 5px 10px;
            border-radius: 3px;
            min-width: 40px;
            text-align: center;
        }
        
        .chart-note {
            font-size: 12px;
            color: #777;
            margin-top: 10px;
        }
        
        @media (max-width: 768px) {
            .stats-overview {
                grid-template-columns: 1fr;
            }
            
            .date-filter form {
                flex-direction: column;
            }
        }
    </style>
</body>
</html>