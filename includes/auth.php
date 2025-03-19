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
session_start();

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if user is an admin
 */
function is_admin() {
    return is_logged_in() && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

/**
 * Authenticate user
 */
function login($email, $password) {
    $email = mysqli_real_escape_string(db_connect(), $email);
    
    $user = db_select_one("SELECT * FROM users WHERE email = '$email'");
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['is_admin'] = (bool)$user['is_admin'];
        
        // Update last login time
        db_update('users', ['last_login' => date('Y-m-d H:i:s')], "id = {$user['id']}");
        
        return true;
    }
    
    return false;
}

/**
 * Log out user
 */
function logout() {
    session_unset();
    session_destroy();
    
    // If using cookies, delete them
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
}

/**
 * Register a new user
 */
function register_user($name, $email, $password, $is_admin = false) {
    $email = mysqli_real_escape_string(db_connect(), $email);
    
    // Check if email already exists
    $existing = db_select_one("SELECT id FROM users WHERE email = '$email'");
    
    if ($existing) {
        return ['success' => false, 'message' => 'Email already exists'];
    }
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user
    $user_id = db_insert('users', [
        'name' => $name,
        'email' => $email,
        'password' => $hashed_password,
        'is_admin' => $is_admin ? 1 : 0
    ]);
    
    if ($user_id) {
        return ['success' => true, 'user_id' => $user_id];
    }
    
    return ['success' => false, 'message' => 'Failed to create user'];
}

/**
 * Require login to access a page
 */
function require_login() {
    if (!is_logged_in()) {
        // Store the current URL for redirect after login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        
        header('Location: /login.php');
        exit;
    }
}

/**
 * Require admin access to access a page
 */
function require_admin() {
    require_login();
    
    if (!is_admin()) {
        header('Location: /index.php?error=unauthorized');
        exit;
    }
}
?>