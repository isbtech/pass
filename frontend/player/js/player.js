document.addEventListener('DOMContentLoaded', function() {
    // DOM Elements
    const audioPlayer = document.getElementById('audio-player');
    const progressBar = document.getElementById('progress-bar');
    const currentTimeDisplay = document.getElementById('current-time');
    const durationDisplay = document.getElementById('duration');
    const playPauseBtn = document.getElementById('play-pause-btn');
    const restartBtn = document.getElementById('restart-btn');
    const accessCodeInput = document.getElementById('access-code');
    const verifyBtn = document.getElementById('verify-code-btn');
    const codeForm = document.getElementById('code-form');
    const playerSection = document.getElementById('player-section');
    const errorMessage = document.getElementById('error-message');
    
    // Variables
    let accessCode = '';
    let fileId = null;
    let logId = null;
    let statsInterval = null;
    let playStartTime = null;
    
    // Check if code is in URL parameter
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('code')) {
        accessCodeInput.value = urlParams.get('code');
        // Auto-verify if code is in URL
        verifyCode();
    }
    
    // Event listeners
    verifyBtn.addEventListener('click', verifyCode);
    accessCodeInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            verifyCode();
        }
    });
    
    playPauseBtn.addEventListener('click', togglePlayPause);
    restartBtn.addEventListener('click', restartAudio);
    
    // Audio player events
    audioPlayer.addEventListener('loadedmetadata', function() {
        updateDurationDisplay();
    });
    
    audioPlayer.addEventListener('timeupdate', function() {
        updateTimeDisplay();
        updateProgress();
    });
    
    audioPlayer.addEventListener('ended', function() {
        playPauseBtn.innerHTML = '<i class="bi bi-play-fill"></i> Play';
        sendPlayStats(true);
    });
    
    audioPlayer.addEventListener('play', function() {
        playStartTime = new Date();
        // Start stats reporting interval (every 30 seconds)
        if (statsInterval === null) {
            statsInterval = setInterval(function() {
                sendPlayStats(false);
            }, 30000);
        }
    });
    
    audioPlayer.addEventListener('pause', function() {
        // Send stats when paused
        sendPlayStats(false);
        
        // Clear stats interval if not playing
        if (statsInterval !== null) {
            clearInterval(statsInterval);
            statsInterval = null;
        }
    });
    
    // Prevent audio downloading
    audioPlayer.addEventListener('contextmenu', function(e) {
        e.preventDefault();
        return false;
    });
    
    // Functions
    function verifyCode() {
        accessCode = accessCodeInput.value.trim();
        
        if (!accessCode) {
            showError('Please enter an access code');
            return;
        }
        
        fetch(`${API_URL}/access/verify`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ code: accessCode })
        })
        .then(response => {
            if (!response.ok) {
                throw response;
            }
            return response.json();
        })
        .then(data => {
            if (data.valid) {
                // Code is valid
                fileId = data.file_info.id;
                logId = data.file_info.log_id;
                
                // Set audio title
                document.getElementById('audio-title').textContent = data.file_info.title;
                
                // Show player
                codeForm.classList.add('hidden');
                playerSection.classList.remove('hidden');
                
                // Set audio source with the code as authentication
                audioPlayer.src = `${API_URL}/access/file/${fileId}?code=${accessCode}&log_id=${logId}`;
                
                // Preload audio
                audioPlayer.load();
                
                // Clear any previous error
                errorMessage.classList.add('hidden');
            } else {
                showError(data.message || 'Invalid access code');
            }
        })
        .catch(error => {
            if (error.json) {
                error.json().then(data => {
                    showError(data.message || 'Error verifying code');
                }).catch(() => {
                    showError('Error verifying code');
                });
            } else {
                showError('Error verifying code: ' + error.message);
            }
        });
    }
    
    function togglePlayPause() {
        if (audioPlayer.paused) {
            audioPlayer.play()
                .then(() => {
                    playPauseBtn.innerHTML = '<i class="bi bi-pause-fill"></i> Pause';
                })
                .catch(error => {
                    showError('Error playing audio: ' + error.message);
                });
        } else {
            audioPlayer.pause();
            playPauseBtn.innerHTML = '<i class="bi bi-play-fill"></i> Play';
        }
    }
    
    function restartAudio() {
        audioPlayer.currentTime = 0;
        if (audioPlayer.paused) {
            audioPlayer.play()
                .then(() => {
                    playPauseBtn.innerHTML = '<i class="bi bi-pause-fill"></i> Pause';
                })
                .catch(error => {
                    showError('Error playing audio: ' + error.message);
                });
        }
    }
    
    function updateTimeDisplay() {
        currentTimeDisplay.textContent = formatTime(audioPlayer.currentTime);
    }
    
    function updateDurationDisplay() {
        durationDisplay.textContent = formatTime(audioPlayer.duration);
    }
    
    function updateProgress() {
        const percent = (audioPlayer.currentTime / audioPlayer.duration) * 100;
        progressBar.style.width = percent + '%';
    }
    
    function showError(message) {
        errorMessage.textContent = message;
        errorMessage.classList.remove('hidden');
    }
    
    function sendPlayStats(isComplete) {
        if (!logId) return;
        
        // Calculate play duration
        let playDuration = 0;
        if (playStartTime) {
            const now = new Date();
            const elapsedMs = now - playStartTime;
            playDuration = Math.floor(elapsedMs / 1000);
            
           // Reset play start time if tracking continues
            if (!isComplete) {
                playStartTime = now;
            }
        }
        
        fetch(`${API_URL}/access/stats`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                log_id: logId,
                duration: playDuration,
                is_complete: isComplete
            })
        })
        .then(response => {
            if (!response.ok) {
                throw response;
            }
            return response.json();
        })
        .then(data => {
            console.log('Stats updated:', data);
            
            // If complete, clear the interval
            if (isComplete && statsInterval !== null) {
                clearInterval(statsInterval);
                statsInterval = null;
            }
        })
        .catch(error => {
            console.error('Error updating stats:', error);
        });
    }
    
    // Security measures against unauthorized downloading
    // Disable right-click on the page
    document.addEventListener('contextmenu', function(e) {
        e.preventDefault();
        return false;
    });
    
    // Detect when user leaves the page to clean up
    window.addEventListener('beforeunload', function() {
        if (statsInterval !== null) {
            clearInterval(statsInterval);
            sendPlayStats(false); // Send final stats
        }
    });
});