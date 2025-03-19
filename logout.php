<?php
require_once 'includes/auth.php';

// Log out the user
logout();

// Redirect to the login page
header('Location: login.php');
exit;