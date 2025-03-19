<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'passrevivalworks_audiodbs');
define('DB_USER', 'passrevivalworks_usrs');
define('DB_PASS', 'wsMws4ynNiVIyzT');

// Application settings
define('SITE_NAME', 'Private Audio Streaming');  // This is the missing constant
define('SITE_URL', 'https://pass.revivalworks.in');
define('ADMIN_EMAIL', 'admin@yourdomain.com');

// Security settings
define('AUDIO_DIR', dirname(__DIR__) . '/audio'); // Store outside web root if possible
define('SESSION_EXPIRY', 30); // Session expiry time in minutes
define('ACCESS_CODE_LENGTH', 12); // Length of generated access codes
define('SECURE_COOKIES', true); // Use secure cookies

// Audio settings
define('ALLOWED_EXTENSIONS', ['mp3', 'wav', 'ogg', 'm4a', 'flac']);
define('MAX_UPLOAD_SIZE', 1000 * 1024 * 1024); // 100MB

// Default access code settings
define('DEFAULT_VALIDITY_HOURS', 24);
define('MAX_VALIDITY_HOURS', 720); // 30 days
?>