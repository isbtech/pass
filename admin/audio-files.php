<?php
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require admin access
require_admin();

// Process form submissions
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Upload new audio file
    if (isset($_POST['upload']) && isset($_FILES['audio_file'])) {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $file = $_FILES['audio_file'];
        
        if (empty($title)) {
            $message = "Please enter a title for the audio file.";
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $message = "File upload failed with error code: " . $file['error'];
        } elseif (!is_allowed_audio_file($file['name'])) {
            $message = "Invalid file type. Allowed types: " . implode(', ', ALLOWED_EXTENSIONS);
        } elseif ($file['size'] > MAX_UPLOAD_SIZE) {
            $message = "File too large. Maximum size: " . (MAX_UPLOAD_SIZE / 1024 / 1024) . "MB";
        } else {
            // Generate unique filename
            $filename = generate_unique_filename($file['name']);
            
            // Ensure audio directory exists
            if (!file_exists(AUDIO_DIR)) {
                mkdir(AUDIO_DIR, 0755, true);
            }
            
            // Move file to audio directory
            $destination = AUDIO_DIR . '/' . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                // Get audio metadata
                $metadata = get_audio_metadata($destination);
                
                // Save to database
                $audio_id = db_insert('audio_files', [
                    'title' => $title,
                    'description' => $description,
                    'file_path' => $filename,
                    'duration' => $metadata['duration'],
                    'user_id' => $_SESSION['user_id']
                ]);
                
                if ($audio_id) {
                    $message = "Audio file uploaded successfully!";
                } else {
                    $message = "Failed to save audio file to database.";
                    unlink($destination);
                }
            } else {
                $message = "Failed to move uploaded file.";
            }
        }
    }
    
    // Update audio file
    if (isset($_POST['update'])) {
        $audio_id = (int)$_POST['audio_id'];
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($title)) {
            $message = "Please enter a title for the audio file.";
        } else {
            $updated = db_update('audio_files', [
                'title' => $title,
                'description' => $description,
                'is_active' => $is_active
            ], "id = $audio_id");
            
            if ($updated) {
                $message = "Audio file updated successfully!";
            } else {
                $message = "Failed to update audio file.";
            }
        }
    }
    
    // Delete audio file
    if (isset($_POST['delete'])) {
        $audio_id = (int)$_POST['audio_id'];
        
        // Get file path
        $audio_file = db_select_one("SELECT file_path FROM audio_files WHERE id = $audio_id");
        
        if ($audio_file) {
            // Delete from database
            $deleted = db_delete('audio_files', "id = $audio_id");
            
            if ($deleted) {
                // Delete file from disk
                $file_path = AUDIO_DIR . '/' . $audio_file['file_path'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
                
                $message = "Audio file deleted successfully!";
            } else {
                $message = "Failed to delete audio file from database.";
            }
        } else {
            $message = "Audio file not found.";
        }
    }
}

// Get all audio files
$audio_files = db_select("
    SELECT af.*, u.name as uploaded_by 
    FROM audio_files af 
    LEFT JOIN users u ON af.user_id = u.id
    ORDER BY af.created_at DESC
");

// Check if we're editing a file
$editing = false;
$edit_file = null;

if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $edit_file = db_select_one("SELECT * FROM audio_files WHERE id = $edit_id");
    
    if ($edit_file) {
        $editing = true;
    }
}

// Check if we're adding a new file
$adding = isset($_GET['action']) && $_GET['action'] === 'add';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Audio Files - Private Audio Streaming</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Private Audio Streaming - Admin</h1>
            <div class="nav">
                <a href="index.php">Dashboard</a>
                <a href="audio-files.php" class="active">Audio Files</a>
                <a href="access-codes.php">Access Codes</a>
                <a href="stats.php">Statistics</a>
                <a href="../logout.php">Logout</a>
            </div>
        </div>
        
        <div class="content">
            <h2>Manage Audio Files</h2>
            
            <?php if ($message): ?>
                <div class="message">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($editing): ?>
                <!-- Edit Audio File Form -->
                <div class="form-container">
                    <h3>Edit Audio File</h3>
                    
                    <form method="post" action="audio-files.php">
                        <input type="hidden" name="update" value="1">
                        <input type="hidden" name="audio_id" value="<?php echo $edit_file['id']; ?>">
                        
                        <div class="form-group">
                            <label for="title">Title:</label>
                            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($edit_file['title']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description:</label>
                            <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($edit_file['description']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_active" <?php echo $edit_file['is_active'] ? 'checked' : ''; ?>>
                                Active (visible to users)
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="button primary">Update Audio File</button>
                            <a href="audio-files.php" class="button">Cancel</a>
                        </div>
                    </form>
                </div>
            <?php elseif ($adding): ?>
                <!-- Upload Audio File Form -->
                <div class="form-container">
                    <h3>Upload New Audio File</h3>
                    
                    <form method="post" action="audio-files.php" enctype="multipart/form-data">
                        <input type="hidden" name="upload" value="1">
                        
                        <div class="form-group">
                            <label for="title">Title:</label>
                            <input type="text" id="title" name="title" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description:</label>
                            <textarea id="description" name="description" rows="4"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="audio_file">Audio File:</label>
                            <input type="file" id="audio_file" name="audio_file" required accept=".mp3,.wav,.ogg,.m4a,.flac">
                            <small>
                                Allowed formats: <?php echo implode(', ', ALLOWED_EXTENSIONS); ?><br>
                                Maximum size: <?php echo MAX_UPLOAD_SIZE / 1024 / 1024; ?>MB
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="button primary">Upload Audio File</button>
                            <a href="audio-files.php" class="button">Cancel</a>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- Audio Files List -->
                <div class="action-bar">
                    <a href="audio-files.php?action=add" class="button primary">
                        <i class="icon-plus"></i> Upload New Audio File
                    </a>
                </div>
                
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Duration</th>
                            <th>Uploaded By</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($audio_files as $file): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($file['title']); ?></td>
                                <td><?php echo format_duration($file['duration']); ?></td>
                                <td><?php echo htmlspecialchars($file['uploaded_by']); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($file['created_at'])); ?></td>
                                <td>
                                    <?php if ($file['is_active']): ?>
                                        <span class="badge green">Active</span>
                                    <?php else: ?>
                                        <span class="badge grey">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="audio-files.php?edit=<?php echo $file['id']; ?>" class="button small">Edit</a>
                                        
                                        <form method="post" action="audio-files.php" onsubmit="return confirm('Are you sure you want to delete this audio file? This cannot be undone.')">
                                            <input type="hidden" name="delete" value="1">
                                            <input type="hidden" name="audio_id" value="<?php echo $file['id']; ?>">
                                            <button type="submit" class="button small red">Delete</button>
                                        </form>
                                        
                                        <a href="stats.php?file_id=<?php echo $file['id']; ?>" class="button small">Stats</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($audio_files)): ?>
                            <tr>
                                <td colspan="6" class="center">No audio files found. <a href="audio-files.php?action=add">Upload one now</a>.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> Private Audio Streaming</p>
        </div>
    </div>
    
    <style>
        .action-bar {
            margin-bottom: 20px;
        }
        
        .icon-plus:before {
            content: "+";
            font-weight: bold;
            margin-right: 5px;
        }
    </style>
</body>
</html>