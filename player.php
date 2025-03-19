<?php
// Include required files
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if session ID is provided
if (!isset($_GET['session_id'])) {
    header('Location: access.php');
    exit;
}

$session_id = $_GET['session_id'];

// Get session data
$session = db_select_one("SELECT * FROM streaming_sessions WHERE session_id = '" . 
    mysqli_real_escape_string(db_connect(), $session_id) . "'");

if (!$session) {
    header('Location: access.php?error=invalid_session');
    exit;
}

// Check if session has expired
$session_expiry = strtotime($session['last_active_at']) + (SESSION_EXPIRY * 60);
if (time() > $session_expiry) {
    // Update session status
    db_update('streaming_sessions', ['status' => 'completed'], "id = {$session['id']}");
    
    header('Location: access.php?error=session_expired');
    exit;
}

// Get audio file data
$audio_file = db_select_one("SELECT * FROM audio_files WHERE id = {$session['audio_file_id']}");

if (!$audio_file || !$audio_file['is_active']) {
    header('Location: access.php?error=file_unavailable');
    exit;
}

// Get access code data
$access_code = db_select_one("SELECT * FROM access_codes WHERE id = {$session['access_code_id']}");

if (!$access_code || !$access_code['is_active']) {
    header('Location: access.php?error=invalid_access');
    exit;
}

// Check if access code has expired
if ($access_code['valid_until'] !== NULL && strtotime($access_code['valid_until']) < time()) {
    header('Location: access.php?error=access_expired');
    exit;
}

// Generate streaming token with longer validity and better security
$token_data = [
    'session_id' => $session_id,
    'audio_id' => $audio_file['id'],
    'ip' => $_SERVER['REMOTE_ADDR'],
    'time' => time()
];
$token = hash_hmac('sha256', json_encode($token_data), 'secure_streaming_secret');
$expires = time() + 3600; // 1 hour

// Update session status to active
db_update('streaming_sessions', [
    'status' => 'active',
    'last_active_at' => date('Y-m-d H:i:s')
], "id = {$session['id']}");

$site_name = defined('SITE_NAME') ? SITE_NAME : 'Private Audio Streaming';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($audio_file['title']); ?> - <?php echo $site_name; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); ?>">
</head>
<body class="player-page">
    <div class="container">
        <div class="header">
            <div class="logo">
                <a href="index.php">
                    <i class="fas fa-headphones-alt"></i>
                    <span><?php echo $site_name; ?></span>
                </a>
            </div>
            
            <div class="nav-actions">
                <a href="access.php" class="nav-link">
                    <i class="fas fa-key"></i> Enter Access Code
                </a>
            </div>
        </div>
        
        <div class="content player-content">
            <div class="audio-player-container">
                <div class="player-card">
                    <div class="player-artwork">
                        <div class="artwork-placeholder">
                            <i class="fas fa-music"></i>
                            <?php if (isset($audio_file['duration']) && $audio_file['duration']): ?>
                                <div class="duration-badge">
                                    <?php echo format_duration($audio_file['duration']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="player-info">
                        <h2 class="audio-title"><?php echo htmlspecialchars($audio_file['title']); ?></h2>
                        
                        <?php if (isset($audio_file['description']) && !empty($audio_file['description'])): ?>
                            <p class="audio-description"><?php echo htmlspecialchars($audio_file['description']); ?></p>
                        <?php endif; ?>
                        
                        <div class="player-controls-wrapper">
                            <div class="playback-controls">
                                <button id="prev-btn" class="control-btn" title="Previous 15 seconds">
                                    <i class="fas fa-backward"></i>
                                </button>
                                
                                <button id="play-pause-btn" class="play-button" title="Play/Pause">
                                    <i class="fas fa-play play-icon"></i>
                                    <i class="fas fa-pause pause-icon" style="display: none;"></i>
                                </button>
                                
                                <button id="next-btn" class="control-btn" title="Next 15 seconds">
                                    <i class="fas fa-forward"></i>
                                </button>
                            </div>
                            
                            <div class="progress-controls">
                                <span id="current-time" class="time-display">0:00</span>
                                
                                <div class="progress-container">
                                    <div class="progress-bar">
                                        <div id="progress" class="progress"></div>
                                        <div id="buffered-progress" class="buffered-progress"></div>
                                    </div>
                                    <div id="progress-handle" class="progress-handle"></div>
                                </div>
                                
                                <span id="duration" class="time-display"><?php echo format_duration($audio_file['duration']); ?></span>
                            </div>
                            
                            <div class="secondary-controls">
                                <div class="volume-control">
                                    <button id="mute-btn" class="control-btn" title="Mute/Unmute">
                                        <i class="fas fa-volume-up"></i>
                                    </button>
                                    
                                    <div class="volume-slider-container">
                                        <input type="range" id="volume-slider" min="0" max="1" step="0.05" value="1">
                                    </div>
                                </div>
                                
                                <div class="playback-options">
                                    <div class="playback-rate">
                                        <select id="playback-rate" title="Playback Speed">
                                            <option value="0.5">0.5x</option>
                                            <option value="0.75">0.75x</option>
                                            <option value="1" selected>1.0x</option>
                                            <option value="1.25">1.25x</option>
                                            <option value="1.5">1.5x</option>
                                            <option value="2">2.0x</option>
                                        </select>
                                    </div>
                                    
                                    <button id="restart-btn" class="control-btn" title="Restart">
                                        <i class="fas fa-redo"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Hidden audio element -->
                        <audio id="audio-player" class="protected-audio" controlsList="nodownload" 
                               data-session-id="<?php echo $session_id; ?>" 
                               data-user-id="<?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'Guest'; ?>">
                            <source src="stream.php?sid=<?php echo $session_id; ?>&token=<?php echo $token; ?>&expires=<?php echo $expires; ?>" type="audio/mpeg">
                            Your browser does not support the audio element.
                        </audio>
                    </div>
                </div>
                
                <div class="player-footer">
                    <div class="player-info-bar">
                        <div class="playback-info">
                            <span class="status-indicator">
                                <i class="fas fa-lock"></i> Secure Playback
                            </span>
                            <div class="listening-stats">
                                <span id="played-stats">00:00 listened</span>
                            </div>
                        </div>
                        
                        <div class="player-links">
                            <a href="access.php" class="text-link">
                                <i class="fas fa-list"></i> Access more audio
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo $site_name; ?> | Secure Audio Streaming Platform</p>
        </div>
    </div>
    
    <script src="assets/js/audio-protection.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const audioPlayer = document.getElementById('audio-player');
            const playPauseBtn = document.getElementById('play-pause-btn');
            const playIcon = document.querySelector('.play-icon');
            const pauseIcon = document.querySelector('.pause-icon');
            const restartBtn = document.getElementById('restart-btn');
            const prevBtn = document.getElementById('prev-btn');
            const nextBtn = document.getElementById('next-btn');
            const progressBar = document.getElementById('progress');
            const bufferedProgress = document.getElementById('buffered-progress');
            const progressHandle = document.getElementById('progress-handle');
            const progressContainer = document.querySelector('.progress-container');
            const currentTimeDisplay = document.getElementById('current-time');
            const durationDisplay = document.getElementById('duration');
            const volumeBtn = document.getElementById('mute-btn');
            const volumeSlider = document.getElementById('volume-slider');
            const playbackRateSelect = document.getElementById('playback-rate');
            const playedStats = document.getElementById('played-stats');
            const sessionId = audioPlayer.getAttribute('data-session-id');
            
            let playedDuration = 0;
            let lastUpdateTime = 0;
            let updateTimer = null;
            let sessionActive = true;
            let playbackStartTime = 0;
            let cumulativePlayTime = 0;
            let isBuffering = false;
            let isDragging = false;
            
            // Setup buffering detection
            let bufferCheckInterval = null;
            let lastPlayPos = 0;
            let bufferDetected = false;
            
            function startBufferCheck() {
                bufferCheckInterval = setInterval(() => {
                    // Compare current position with position from 1 second ago
                    if (!audioPlayer.paused && lastPlayPos === audioPlayer.currentTime && !bufferDetected) {
                        bufferDetected = true;
                        handleBuffering(true);
                    }
                    
                    if (bufferDetected && lastPlayPos !== audioPlayer.currentTime) {
                        bufferDetected = false;
                        handleBuffering(false);
                    }
                    
                    lastPlayPos = audioPlayer.currentTime;
                }, 1000);
            }
            
            function handleBuffering(isBuffering) {
                if (isBuffering) {
                    // Buffering started, pause the play time tracking
                    pausePlayTimeTracking();
                    document.body.classList.add('buffering');
                } else {
                    // Buffering ended, resume play time tracking
                    resumePlayTimeTracking();
                    document.body.classList.remove('buffering');
                }
            }
            
            // Start tracking actual play time (not counting buffer time)
            function startPlayTimeTracking() {
                playbackStartTime = Date.now();
            }
            
            // Pause play time tracking during pauses or buffering
            function pausePlayTimeTracking() {
                if (playbackStartTime > 0) {
                    // Add elapsed time to cumulative play time
                    cumulativePlayTime += (Date.now() - playbackStartTime) / 1000;
                    playbackStartTime = 0;
                }
            }
            
            // Resume play time tracking after pauses or buffering
            function resumePlayTimeTracking() {
                playbackStartTime = Date.now();
            }
            
            // Get total played time including current session
            function getTotalPlayedTime() {
                let currentSessionTime = 0;
                
                // If currently playing, add the current session time
                if (playbackStartTime > 0) {
                    currentSessionTime = (Date.now() - playbackStartTime) / 1000;
                }
                
                return Math.floor(cumulativePlayTime + currentSessionTime);
            }
            
            // Update played stats display
            function updatePlayedStats() {
                const totalSeconds = getTotalPlayedTime();
                playedStats.textContent = formatTime(totalSeconds) + ' listened';
            }
            
            // Update progress bar and time
            audioPlayer.addEventListener('timeupdate', function() {
                if (isDragging) return;
                
                const currentTime = audioPlayer.currentTime;
                const duration = audioPlayer.duration || 1;
                const percent = (currentTime / duration) * 100;
                
                progressBar.style.width = percent + '%';
                progressHandle.style.left = percent + '%';
                currentTimeDisplay.textContent = formatTime(currentTime);
                
                // Update buffer progress
                updateBufferProgress();
                
                // Update played duration for accurate stats
                playedDuration = Math.max(playedDuration, Math.floor(currentTime));
                
                // Update played stats every second
                updatePlayedStats();
            });
            
            // Update buffer progress
            function updateBufferProgress() {
                if (audioPlayer.buffered.length > 0) {
                    const bufferedEnd = audioPlayer.buffered.end(audioPlayer.buffered.length - 1);
                    const duration = audioPlayer.duration;
                    const bufferedPercent = (bufferedEnd / duration) * 100;
                    
                    bufferedProgress.style.width = bufferedPercent + '%';
                }
            }
            
            // Set up progress bar seeking with dragging
            progressContainer.addEventListener('mousedown', function(e) {
                isDragging = true;
                updateSeekPosition(e);
                document.addEventListener('mousemove', updateSeekPosition);
                document.addEventListener('mouseup', stopDragging);
            });
            
            function updateSeekPosition(e) {
                if (!isDragging) return;
                
                const rect = progressContainer.getBoundingClientRect();
                let pos = (e.clientX - rect.left) / rect.width;
                
                // Clamp between 0 and 1
                pos = Math.max(0, Math.min(1, pos));
                
                const percent = pos * 100;
                progressBar.style.width = percent + '%';
                progressHandle.style.left = percent + '%';
                
                // Update time display during drag
                currentTimeDisplay.textContent = formatTime(pos * audioPlayer.duration);
            }
            
            function stopDragging() {
                if (!isDragging) return;
                
                document.removeEventListener('mousemove', updateSeekPosition);
                document.removeEventListener('mouseup', stopDragging);
                
                // Set the actual position
                const handlePos = parseFloat(progressHandle.style.left) / 100;
                audioPlayer.currentTime = audioPlayer.duration * handlePos;
                
                isDragging = false;
            }
            
            // Touch support for seeking
            progressContainer.addEventListener('touchstart', function(e) {
                isDragging = true;
                updateTouchSeekPosition(e);
                document.addEventListener('touchmove', updateTouchSeekPosition);
                document.addEventListener('touchend', stopTouchDragging);
                e.preventDefault();
            });
            
            function updateTouchSeekPosition(e) {
                if (!isDragging) return;
                
                const rect = progressContainer.getBoundingClientRect();
                const touch = e.touches[0];
                let pos = (touch.clientX - rect.left) / rect.width;
                
                // Clamp between 0 and 1
                pos = Math.max(0, Math.min(1, pos));
                
                const percent = pos * 100;
                progressBar.style.width = percent + '%';
                progressHandle.style.left = percent + '%';
                
                // Update time display during drag
                currentTimeDisplay.textContent = formatTime(pos * audioPlayer.duration);
                
                e.preventDefault();
            }
            
            function stopTouchDragging() {
                if (!isDragging) return;
                
                document.removeEventListener('touchmove', updateTouchSeekPosition);
                document.removeEventListener('touchend', stopTouchDragging);
                
                // Set the actual position
                const handlePos = parseFloat(progressHandle.style.left) / 100;
                audioPlayer.currentTime = audioPlayer.duration * handlePos;
                
                isDragging = false;
            }
            
            // Previous/Next buttons (skip 15 seconds)
            prevBtn.addEventListener('click', function() {
                audioPlayer.currentTime = Math.max(0, audioPlayer.currentTime - 15);
            });
            
            nextBtn.addEventListener('click', function() {
                audioPlayer.currentTime = Math.min(audioPlayer.duration, audioPlayer.currentTime + 15);
            });
            
            // Handle play/pause button
            playPauseBtn.addEventListener('click', function() {
                if (audioPlayer.paused) {
                    playPauseBtn.classList.add('playing');
                    audioPlayer.play().catch(e => console.error("Play error:", e));
                } else {
                    playPauseBtn.classList.remove('playing');
                    audioPlayer.pause();
                }
            });
            
            // Handle restart button
            restartBtn.addEventListener('click', function() {
                audioPlayer.currentTime = 0;
                if (audioPlayer.paused) {
                    playPauseBtn.classList.add('playing');
                    audioPlayer.play().catch(e => console.error("Play error:", e));
                }
            });
            
            // Handle volume button and slider
            volumeBtn.addEventListener('click', function() {
                if (audioPlayer.volume > 0) {
                    audioPlayer.dataset.previousVolume = audioPlayer.volume;
                    audioPlayer.volume = 0;
                    volumeSlider.value = 0;
                    volumeBtn.innerHTML = '<i class="fas fa-volume-mute"></i>';
                    volumeBtn.classList.add('muted');
                } else {
                    const previousVolume = audioPlayer.dataset.previousVolume || 1;
                    audioPlayer.volume = previousVolume;
                    volumeSlider.value = previousVolume;
                    updateVolumeIcon(previousVolume);
                    volumeBtn.classList.remove('muted');
                }
            });
            
            volumeSlider.addEventListener('input', function() {
                audioPlayer.volume = volumeSlider.value;
                updateVolumeIcon(volumeSlider.value);
                
                if (audioPlayer.volume === 0) {
                    volumeBtn.classList.add('muted');
                } else {
                    volumeBtn.classList.remove('muted');
                }
            });
            
            function updateVolumeIcon(volume) {
                if (volume === 0) {
                    volumeBtn.innerHTML = '<i class="fas fa-volume-mute"></i>';
                } else if (volume < 0.5) {
                    volumeBtn.innerHTML = '<i class="fas fa-volume-down"></i>';
                } else {
                    volumeBtn.innerHTML = '<i class="fas fa-volume-up"></i>';
                }
            }
            
            // Handle playback rate
            playbackRateSelect.addEventListener('change', function() {
                audioPlayer.playbackRate = parseFloat(playbackRateSelect.value);
            });
            
            // Handle keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Space bar: Play/Pause
                if (e.code === 'Space') {
                    if (audioPlayer.paused) {
                        audioPlayer.play();
                    } else {
                        audioPlayer.pause();
                    }
                    e.preventDefault();
                }
                
                // Left arrow: Rewind 5 seconds
                if (e.code === 'ArrowLeft') {
                    audioPlayer.currentTime = Math.max(0, audioPlayer.currentTime - 5);
                    e.preventDefault();
                }
                
                // Right arrow: Forward 5 seconds
                if (e.code === 'ArrowRight') {
                    audioPlayer.currentTime = Math.min(audioPlayer.duration, audioPlayer.currentTime + 5);
                    e.preventDefault();
                }
                
                // M key: Mute/Unmute
                if (e.code === 'KeyM') {
                    volumeBtn.click();
                    e.preventDefault();
                }
            });
            
            // Handle waiting (buffering) events
            audioPlayer.addEventListener('waiting', function() {
                isBuffering = true;
                pausePlayTimeTracking();
                document.body.classList.add('buffering');
            });
            
            audioPlayer.addEventListener('playing', function() {
                isBuffering = false;
                document.body.classList.remove('buffering');
                if (!audioPlayer.paused) {
                    resumePlayTimeTracking();
                }
            });
            
            // Handle canplaythrough event
            audioPlayer.addEventListener('canplaythrough', function() {
                document.body.classList.remove('buffering');
                updateBufferProgress();
            });
            
            // Handle loadedmetadata event
            audioPlayer.addEventListener('loadedmetadata', function() {
                durationDisplay.textContent = formatTime(audioPlayer.duration);
                updateBufferProgress();
            });
            
            // Handle play event
            audioPlayer.addEventListener('play', function() {
                playIcon.style.display = 'none';
                pauseIcon.style.display = 'inline-block';
                playPauseBtn.classList.add('playing');
                document.body.classList.add('playing');
                
                // Start tracking real play time
                resumePlayTimeTracking();
                
                // Start buffer detection
                if (!bufferCheckInterval) {
                    startBufferCheck();
                }
                
                // Start updating session status
                startSessionUpdates();
            });
            
            // Handle pause event
            audioPlayer.addEventListener('pause', function() {
                playIcon.style.display = 'inline-block';
                pauseIcon.style.display = 'none';
                playPauseBtn.classList.remove('playing');
                document.body.classList.remove('playing');
                
                // Pause play time tracking
                pausePlayTimeTracking();
                
                // Stop updating session status
                stopSessionUpdates();
                
                // Update session status to paused
                updateSessionStatus('paused');
                
                // Update played stats display
                updatePlayedStats();
            });
            
            // Handle ended event
            audioPlayer.addEventListener('ended', function() {
                playIcon.style.display = 'inline-block';
                pauseIcon.style.display = 'none';
                playPauseBtn.classList.remove('playing');
                document.body.classList.remove('playing');
                
                // Pause play time tracking
                pausePlayTimeTracking();
                
                // Stop updating session status
                stopSessionUpdates();
                
                // Update session status to completed
                updateSessionStatus('completed');
                
                // Update played stats display
                updatePlayedStats();
            });
            
            // Start periodic updates
            function startSessionUpdates() {
                if (updateTimer) return;
                
                // Update immediately
                updateSessionStatus('active');
                
                // Then set interval
                updateTimer = setInterval(function() {
                    if (sessionActive) {
                        updateSessionStatus('active');
                        updatePlayedStats();
                    }
                }, 10000); // Update every 10 seconds
            }
            
            // Stop periodic updates
            function stopSessionUpdates() {
                if (updateTimer) {
                    clearInterval(updateTimer);
                    updateTimer = null;
                }
            }
            
            // Update session status with accurate playtime
            function updateSessionStatus(status) {
                if (!sessionActive) return;
                
                const currTime = Date.now();
                // Throttle updates to prevent too many requests
                if (status !== 'completed' && status !== 'paused' && currTime - lastUpdateTime < 5000) {
                    return;
                }
                lastUpdateTime = currTime;
                
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'api.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.setRequestHeader('X-CSRF-Token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
                
                const actualPlayedTime = getTotalPlayedTime();
                
                xhr.send(
                    'action=update_session' +
                    '&session_id=' + encodeURIComponent(sessionId) +
                    '&status=' + encodeURIComponent(status) +
                    '&current_position=' + encodeURIComponent(Math.floor(audioPlayer.currentTime)) +
                    '&played_duration=' + encodeURIComponent(actualPlayedTime)
                );
                
                // For completed status, mark session as inactive
                if (status === 'completed') {
                    sessionActive = false;
                }
            }
            
            // Format time in MM:SS format
            function formatTime(seconds) {
                seconds = Math.floor(seconds);
                const minutes = Math.floor(seconds / 60);
                seconds = seconds % 60;
                return minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
            }
            
            // Handle page visibility changes
            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'hidden' && !audioPlayer.paused) {
                    // User switched tabs while playing, update session
                    updateSessionStatus('active');
                    updatePlayedStats();
                }
            });
            
            // Update session status when leaving the page
            window.addEventListener('beforeunload', function() {
                if (sessionActive) {
                    // Use sendBeacon for more reliable delivery when page is unloading
                    if (navigator.sendBeacon) {
                        const formData = new FormData();
                        formData.append('action', 'update_session');
                        formData.append('session_id', sessionId);
                        formData.append('status', 'paused');
                        formData.append('current_position', Math.floor(audioPlayer.currentTime));
                        formData.append('played_duration', getTotalPlayedTime());
                        formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
                        
                        navigator.sendBeacon('api.php', formData);
                    } else {
                        // Fallback to synchronous XHR (not recommended but better than nothing)
                        updateSessionStatus('paused');
                    }
                }
            });
            
            // Initialize played stats
            updatePlayedStats();
        });
    </script>
    
    <style>
        /* Modern Player Theme */
        :root {
            --player-primary: #4361ee;
            --player-secondary: #3a0ca3;
            --player-background: #f8f9fa;
            --player-surface: #ffffff;
            --player-text: #333333;
            --player-text-secondary: #6c757d;
            --player-border: #dee2e6;
            --player-progress: #4361ee;
            --player-progress-bg: #e9ecef;
            --player-buffered: #bac8ff;
            --player-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --player-radius: 12px;
            --player-transition: all 0.2s ease;
        }
        
        body.player-page {
            background-color: #f0f2f5;
            color: var(--player-text);
        }
        
        /* Header styling */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            margin-bottom: 30px;
        }
        
        .logo a {
            display: flex;
            align-items: center;
            color: var(--player-primary);
            font-size: 1.5rem;
            font-weight: 700;
            text-decoration: none;
        }
        
        .logo i {
            font-size: 1.8rem;
            margin-right: 10px;
        }
        
        .nav-actions {
            display: flex;
            gap: 15px;
        }
        
        .nav-link {
            padding: 8px 15px;
            color: var(--player-text);
            border-radius: 20px;
            text-decoration: none;
            transition: var(--player-transition);
            font-weight: 500;
        }
        
        .nav-link:hover {
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--player-primary);
        }
        
        /* Player content */
        .player-content {
            padding: 0;
            background: transparent;
            box-shadow: none;
        }
        
        .audio-player-container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .player-card {
            display: flex;
            flex-direction: column;
            background: var(--player-surface);
            border-radius: var(--player-radius);
            overflow: hidden;
            box-shadow: var(--player-shadow);
            margin-bottom: 20px;
        }
        
        @media (min-width: 768px) {
            .player-card {
                flex-direction: row;
                align-items: stretch;
            }
        }
        
        .player-artwork {
            background-color: #e9ecef;
            flex: 0 0 auto;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        @media (min-width: 768px) {
            .player-artwork {
                width: 300px;
            }
        }
        
        .artwork-placeholder {
            width: 100%;
            aspect-ratio: 1/1;
            background: linear-gradient(135deg, #4361ee, #3a0ca3);
            border-radius: var(--player-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        
        .artwork-placeholder i {
            font-size: 64px;
            color: white;
            opacity: 0.8;
        }
        
        .duration-badge {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(0,0,0,0.5);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .player-info {
            flex: 1;
            padding: 30px;
            display: flex;
            flex-direction: column;
        }
        
        .audio-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--player-text);
            margin-bottom: 10px;
        }
        
        .audio-description {
            color: var(--player-text-secondary);
            margin-bottom: 20px;
            font-size: 0.95rem;
            line-height: 1.6;
        }
        
        .player-controls-wrapper {
            margin-top: auto;
        }
        
        .playback-controls {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .control-btn {
            background: none;
            border: none;
            color: var(--player-text);
            font-size: 1rem;
            cursor: pointer;
            padding: 10px;
            border-radius: 50%;
            transition: var(--player-transition);
        }
        
        .control-btn:hover {
            background-color: rgba(0,0,0,0.05);
            color: var(--player-primary);
        }
        
        .play-button {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--player-primary);
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--player-transition);
            box-shadow: 0 2px 10px rgba(67, 97, 238, 0.4);
        }
        
        .play-button:hover {
            transform: scale(1.05);
            background: var(--player-secondary);
        }
        
        .play-button i {
            font-size: 1.4rem;
        }
        
        .progress-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .time-display {
            color: var(--player-text-secondary);
            font-size: 0.85rem;
            font-variant-numeric: tabular-nums;
            min-width: 45px;
        }
        
        .progress-container {
            flex: 1;
            position: relative;
            height: 8px;
            cursor: pointer;
        }
        
        .progress-bar {
            width: 100%;
            height: 100%;
            background-color: var(--player-progress-bg);
            border-radius: 4px;
            overflow: hidden;
            position: relative;
        }
        
        .progress {
            height: 100%;
            width: 0%;
            background-color: var(--player-progress);
            border-radius: 4px;
            position: absolute;
            top: 0;
            left: 0;
            z-index: 2;
            transition: width 0.1s linear;
        }
        
        .buffered-progress {
            height: 100%;
            width: 0%;
            background-color: var(--player-buffered);
            border-radius: 4px;
            position: absolute;
            top: 0;
            left: 0;
            z-index: 1;
        }
        
        .progress-handle {
            width: 16px;
            height: 16px;
            background-color: var(--player-primary);
            border-radius: 50%;
            position: absolute;
            top: 50%;
            left: 0%;
            transform: translate(-50%, -50%);
            z-index: 3;
            box-shadow: 0 0 5px rgba(0,0,0,0.2);
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        
        .progress-container:hover .progress-handle {
            opacity: 1;
        }
        
        .secondary-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .volume-control {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .volume-slider-container {
            width: 80px;
        }
        
        .volume-slider-container input {
            width: 100%;
            height: 4px;
            -webkit-appearance: none;
            background: var(--player-progress-bg);
            outline: none;
            border-radius: 2px;
            transition: var(--player-transition);
        }
        
        .volume-slider-container input::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--player-primary);
            cursor: pointer;
            transition: var(--player-transition);
        }
        
        .volume-slider-container input:hover {
            background: var(--player-buffered);
        }
        
        .volume-slider-container input:hover::-webkit-slider-thumb {
            transform: scale(1.2);
        }
        
        .playback-options {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .playback-rate select {
            padding: 6px 10px;
            border-radius: 15px;
            border: 1px solid var(--player-border);
            background-color: var(--player-surface);
            color: var(--player-text);
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--player-transition);
        }
        
        .playback-rate select:hover {
            border-color: var(--player-primary);
        }
        
        .player-footer {
            background: var(--player-surface);
            border-radius: var(--player-radius);
            padding: 15px 20px;
            box-shadow: var(--player-shadow);
        }
        
        .player-info-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .playback-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .status-indicator {
            color: var(--player-text-secondary);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .status-indicator i {
            color: var(--player-primary);
        }
        
        .listening-stats {
            color: var(--player-text-secondary);
            font-size: 0.9rem;
        }
        
        .player-links {
            font-size: 0.9rem;
        }
        
        .text-link {
            color: var(--player-primary);
            text-decoration: none;
            transition: var(--player-transition);
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .text-link:hover {
            color: var(--player-secondary);
            text-decoration: underline;
        }
        
        /* Buffering animation */
        body.buffering .player-artwork::after {
            content: "Buffering...";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--player-radius);
            font-size: 1.2rem;
        }
        
        /* Responsive adjustments */
        @media (max-width: 767px) {
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .logo a {
                justify-content: center;
            }
            
            .nav-actions {
                justify-content: center;
            }
            
            .player-info {
                padding: 20px;
            }
            
            .audio-title {
                font-size: 1.5rem;
            }
            
            .playback-controls {
                gap: 15px;
            }
            
            .play-button {
                width: 50px;
                height: 50px;
            }
            
            .secondary-controls {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
            
            .volume-control {
                justify-content: center;
            }
            
            .playback-options {
                justify-content: center;
            }
            
            .player-info-bar {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
            
            .playback-info {
                flex-direction: column;
                gap: 5px;
            }
        }
    </style>
</body>
</html>