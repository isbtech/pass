<?php
// Include configuration
require_once dirname(__DIR__) . '/config/config.php';

// Include database connection
require_once 'database.php';

/**
 * Generate a random access code
 */
function generate_access_code($length = ACCESS_CODE_LENGTH) {
    $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Removed similar-looking characters
    $code = '';
    
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    
    // Check if code already exists
    $existing = db_select_one("SELECT id FROM access_codes WHERE code = '$code'");
    
    if ($existing) {
        // Code exists, generate a new one
        return generate_access_code($length);
    }
    
    return $code;
}

/**
 * Format duration in seconds to MM:SS format
 */
function format_duration($seconds) {
    $seconds = (int)$seconds;
    $minutes = floor($seconds / 60);
    $seconds = $seconds % 60;
    
    return sprintf('%02d:%02d', $minutes, $seconds);
}

/**
 * Get file extension
 */
function get_file_extension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Check if file is an allowed audio file
 */
function is_allowed_audio_file($filename) {
    $extension = get_file_extension($filename);
    return in_array($extension, ALLOWED_EXTENSIONS);
}

/**
 * Generate a unique filename
 */
function generate_unique_filename($original_filename) {
    $extension = get_file_extension($original_filename);
    return uniqid() . '_' . time() . '.' . $extension;
}

/**
 * Get audio file metadata (duration, etc.)
 * Requires getID3 library - you can include this separately
 */
function get_audio_metadata($file_path) {
    // This is a simplified version. In a real implementation,
    // you would use a library like getID3 to get accurate metadata
    $duration = 0;
    $bitrate = 0;
    
    // If getID3 is available
    if (function_exists('getid3_analyze')) {
        $getID3 = new getID3;
        $fileInfo = $getID3->analyze($file_path);
        
        if (isset($fileInfo['playtime_seconds'])) {
            $duration = round($fileInfo['playtime_seconds']);
        }
        
        if (isset($fileInfo['audio']['bitrate'])) {
            $bitrate = round($fileInfo['audio']['bitrate'] / 1000);
        }
    }
    
    return [
        'duration' => $duration,
        'bitrate' => $bitrate . ' kbps'
    ];
}

/**
 * Sanitize output
 */
function html_escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate a secure token
 */
function generate_secure_token($data, $expiry = 300) {
    $expires = time() + $expiry;
    $token_data = $data . $expires . $_SERVER['HTTP_USER_AGENT'];
    $token = hash_hmac('sha256', $token_data, 'secure_secret_key');
    
    return [
        'token' => $token,
        'expires' => $expires
    ];
}

/**
 * Verify a secure token
 */
function verify_secure_token($token, $expires, $data) {
    if (time() > $expires) {
        return false;
    }
    
    $token_data = $data . $expires . $_SERVER['HTTP_USER_AGENT'];
    $expected_token = hash_hmac('sha256', $token_data, 'secure_secret_key');
    
    return hash_equals($expected_token, $token);
}

/**
 * Log an event
 */
function log_event($type, $message, $data = []) {
    $log_file = dirname(__DIR__) . '/logs/app.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    $log_message = "[$timestamp] [$ip] [$type]: $message" . PHP_EOL;
    
    if (!empty($data)) {
        $log_message .= json_encode($data) . PHP_EOL;
    }
    
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

/**
 * Generate a UUID
 */
function generate_uuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}
?>