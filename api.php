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
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || 
        !isset($_SESSION['csrf_token']) || 
        $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
        
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }
}

// Process actions
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'verify_access_code':
            verify_access_code();
            break;
            
        case 'update_session':
            update_session();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'No action specified']);
}

/**
 * Verify an access code
 */
function verify_access_code() {
    if (!isset($_POST['code'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No access code provided']);
        return;
    }
    
    $code = $_POST['code'];
    
    // Verify the access code
    $accessCode = db_select_one("SELECT * FROM access_codes WHERE code = '$code' AND is_active = 1");
    
    if (!$accessCode) {
        echo json_encode([
            'valid' => false,
            'message' => 'Invalid access code'
        ]);
        return;
    }
    
    // Check if code has expired
    if ($accessCode['valid_until'] !== NULL && strtotime($accessCode['valid_until']) < time()) {
        echo json_encode([
            'valid' => false,
            'message' => 'Access code has expired'
        ]);
        return;
    }
    
    // Check if maximum number of plays has been reached
    if ($accessCode['max_plays'] !== NULL) {
        $play_count = db_select_one("SELECT COUNT(*) as count FROM access_logs WHERE access_code_id = {$accessCode['id']}");
        
        if ($play_count['count'] >= $accessCode['max_plays']) {
            echo json_encode([
                'valid' => false,
                'message' => 'Maximum number of plays reached'
            ]);
            return;
        }
    }
    
    // Check if maximum playtime has been reached
    if ($accessCode['max_playtime'] !== NULL) {
        $total_playtime = db_select_one("SELECT SUM(played_duration) as total FROM access_logs WHERE access_code_id = {$accessCode['id']}");
        
        if ($total_playtime['total'] >= $accessCode['max_playtime']) {
            echo json_encode([
                'valid' => false,
                'message' => 'Maximum playtime reached'
            ]);
            return;
        }
    }
    
    // If code is for a specific audio file, return that file info
    if ($accessCode['audio_file_id']) {
        $audio_file = db_select_one("SELECT * FROM audio_files WHERE id = {$accessCode['audio_file_id']} AND is_active = 1");
        
        if (!$audio_file) {
            echo json_encode([
                'valid' => false,
                'message' => 'The associated audio file is no longer available'
            ]);
            return;
        }
        
        echo json_encode([
            'valid' => true,
            'specific_file' => true,
            'audio_file' => [
                'id' => $audio_file['id'],
                'title' => $audio_file['title'],
                'description' => $audio_file['description'],
                'duration' => $audio_file['duration']
            ]
        ]);
        return;
    }
    
    // If code allows access to any audio file, return a list of available files
    $audioFiles = db_select("SELECT id, title, description, duration FROM audio_files WHERE is_active = 1 ORDER BY title");
    
    echo json_encode([
        'valid' => true,
        'specific_file' => false,
        'audio_files' => $audioFiles
    ]);
}

/**
 * Update streaming session status
 */
function update_session() {
    if (!isset($_POST['session_id']) || !isset($_POST['status']) || 
        !isset($_POST['current_position']) || !isset($_POST['played_duration'])) {
        
        http_response_code(400);
        echo json_encode(['error' => 'Missing required parameters']);
        return;
    }
    
    $session_id = $_POST['session_id'];
    $status = $_POST['status'];
    $current_position = (int)$_POST['current_position'];
    $played_duration = (int)$_POST['played_duration'];
    
    // Validate status
    if (!in_array($status, ['active', 'paused', 'completed'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid status']);
        return;
    }
    
    // Get session data
    $session = db_select_one("SELECT * FROM streaming_sessions WHERE session_id = '$session_id'");
    
    if (!$session) {
        http_response_code(404);
        echo json_encode(['error' => 'Invalid session']);
        return;
    }
    
    // Update session status
    db_update('streaming_sessions', [
        'status' => $status,
        'current_position' => $current_position,
        'total_played' => $played_duration,
        'last_active_at' => date('Y-m-d H:i:s')
    ], "id = {$session['id']}");
    
    // If the session is completed, update the access log
    if ($status === 'completed') {
        $accessLog = db_select_one("
            SELECT id FROM access_logs 
            WHERE access_code_id = {$session['access_code_id']}
            AND audio_file_id = {$session['audio_file_id']}
            AND ip_address = '{$_SERVER['REMOTE_ADDR']}'
            ORDER BY created_at DESC
            LIMIT 1
        ");
        
        if ($accessLog) {
            db_update('access_logs', [
                'played_duration' => $played_duration
            ], "id = {$accessLog['id']}");
        }
    }
    
    echo json_encode(['success' => true]);
}