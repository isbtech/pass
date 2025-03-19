/**
 * Audio Protection JavaScript
 * Implements client-side security measures to prevent easy downloading
 */

(function() {
    // Run when DOM is fully loaded
    document.addEventListener('DOMContentLoaded', function() {
        // Find all audio players with protection class
        const audioPlayers = document.querySelectorAll('audio.protected-audio');
        
        audioPlayers.forEach(function(player) {
            // Apply protection to each player
            protectAudioPlayer(player);
        });
        
        // Apply screen capture protection
        applyScreenCaptureProtection();
    });
    
    /**
     * Apply protection measures to an audio player
     */
    function protectAudioPlayer(player) {
        // Disable context menu on audio player
        player.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            return false;
        });
        
        // Block right-click on player
        player.addEventListener('mousedown', function(e) {
            if (e.button === 2) { // Right mouse button
                e.preventDefault();
                return false;
            }
        });
        
        // Disable keyboard shortcuts for saving
        player.addEventListener('keydown', function(e) {
            // Block Ctrl+S, Cmd+S, Ctrl+U, etc.
            if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'u' || e.key === 'p')) {
                e.preventDefault();
                return false;
            }
        });
        
        // Add invisible watermark to the audio (visual overlaid text)
        if (player.hasAttribute('data-session-id')) {
            addWatermark(player);
        }
    }
    
    /**
     * Add a visual watermark on top of the audio player
     * This is useful for screen recordings
     */
    function addWatermark(player) {
        const sessionId = player.getAttribute('data-session-id');
        const userId = player.getAttribute('data-user-id') || 'Guest';
        
        // Create watermark element
        const watermark = document.createElement('div');
        watermark.className = 'audio-watermark';
        watermark.style.position = 'absolute';
        watermark.style.top = '0';
        watermark.style.left = '0';
        watermark.style.width = '100%';
        watermark.style.height = '100%';
        watermark.style.pointerEvents = 'none';
        watermark.style.color = 'rgba(128, 128, 128, 0.3)';
        watermark.style.fontSize = '16px';
        watermark.style.display = 'flex';
        watermark.style.alignItems = 'center';
        watermark.style.justifyContent = 'center';
        watermark.style.overflow = 'hidden';
        watermark.style.zIndex = '999';
        
        // Create dynamic content with user info and timestamp
        const timestamp = new Date().toISOString();
        const shortSessionId = sessionId.substring(0, 8);
        watermark.textContent = `${userId} • ${shortSessionId} • ${timestamp}`;
        
        // Position the player relatively to allow absolute positioning of watermark
        const playerParent = player.parentElement;
        playerParent.style.position = 'relative';
        
        // Add watermark
        playerParent.appendChild(watermark);
    }
    
    /**
     * Apply screen capture protection
     * Note: This cannot fully prevent screen recording but adds a layer of protection
     */
    function applyScreenCaptureProtection() {
        // Add dynamic watermark to the page when audio is playing
        document.addEventListener('play', function(e) {
            if (e.target.tagName === 'AUDIO' && e.target.classList.contains('protected-audio')) {
                addDynamicPageWatermark();
            }
        }, true);
    }
    
    /**
     * Add a dynamic watermark to the entire page
     */
    function addDynamicPageWatermark() {
        // Only add if not already exists
        if (document.querySelector('.page-watermark')) return;
        
        const watermark = document.createElement('div');
        watermark.className = 'page-watermark';
        watermark.style.position = 'fixed';
        watermark.style.top = '0';
        watermark.style.left = '0';
        watermark.style.width = '100%';
        watermark.style.height = '100%';
        watermark.style.pointerEvents = 'none';
        watermark.style.zIndex = '9999';
        
        // Create a pattern of watermarks
        const pattern = document.createElement('div');
        pattern.style.width = '100%';
        pattern.style.height = '100%';
        pattern.style.background = 'repeating-linear-gradient(45deg, rgba(255,255,255,0.02), rgba(255,255,255,0.02) 10px, rgba(0,0,0,0.02) 10px, rgba(0,0,0,0.02) 20px)';
        watermark.appendChild(pattern);
        
        // Timestamp and info
        const userInfo = document.createElement('div');
        userInfo.style.position = 'absolute';
        userInfo.style.bottom = '10px';
        userInfo.style.right = '10px';
        userInfo.style.fontSize = '12px';
        userInfo.style.color = 'rgba(128, 128, 128, 0.5)';
        userInfo.style.textShadow = '1px 1px 1px rgba(255, 255, 255, 0.5)';
        userInfo.textContent = `${new Date().toISOString()} • Protected Audio`;
        watermark.appendChild(userInfo);
        
        document.body.appendChild(watermark);
        
        // Remove watermark when all audio is paused
        document.addEventListener('pause', function(e) {
            if (e.target.tagName === 'AUDIO') {
                // Check if any protected audio is still playing
                const playingAudio = document.querySelector('audio.protected-audio:not([paused])');
                if (!playingAudio) {
                    const watermark = document.querySelector('.page-watermark');
                    if (watermark) {
                        watermark.remove();
                    }
                }
            }
        }, true);
    }
})();