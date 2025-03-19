<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if session ID is provided
if (!isset($_GET['sid'])) {
    header('HTTP/1.1 400 Bad Request');
    exit('Session ID not provided');
}

$session_id = $_GET['sid'];

// Get session data
$session = db_select_one("SELECT * FROM streaming_sessions WHERE session_id = '$session_id'");

if (!$session) {
    header('HTTP/1.1 404 Not Found');
    exit('Session not found');
}

// Get audio file data
$audio_file = db_select_one("SELECT * FROM audio_files WHERE id = {$session['audio_file_id']}");

if (!$audio_file) {
    header('HTTP/1.1 404 Not Found');
    exit('Audio file not found');
}

// Define file path
$file_path = AUDIO_DIR . '/' . $audio_file['file_path'];

// Debug - check if file exists
if (!file_exists($file_path)) {
    header('HTTP/1.1 404 Not Found');
    exit("File not found: $file_path");
}

// Debug - log file info
error_log("Streaming file: $file_path");
error_log("File size: " . filesize($file_path) . " bytes");

// Set headers for streaming
header('Content-Type: audio/mpeg');
header('Content-Length: ' . filesize($file_path));
header('Content-Disposition: inline; filename="audio.mp3"');
header('Accept-Ranges: bytes');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Update session
db_update('streaming_sessions', [
    'last_active_at' => date('Y-m-d H:i:s')
], "id = {$session['id']}");

// Stream the file
readfile($file_path);
exit;