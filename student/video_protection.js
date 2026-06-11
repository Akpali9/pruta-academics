// video_protection.js - Enhanced video protection

(function() {
    // Prevent screen recording detection and protection
    class VideoProtection {
        constructor(videoElement) {
            this.video = videoElement;
            this.suspicionCount = 0;
            this.init();
        }
        
        init() {
            this.disableKeyboardShortcuts();
            this.preventContextMenu();
            this.monitorScreenRecording();
            this.addWatermark();
            this.monitorDevTools();
            this.monitorPerformance();
        }
        
        disableKeyboardShortcuts() {
            document.addEventListener('keydown', (e) => {
                // Disable common recording shortcuts
                const forbiddenKeys = [
                    'PrintScreen', 'F12', 'F5', 'F6', 'F7', 'F8', 'F9', 'F10', 'F11',
                    'MediaRecord', 'LaunchApp2'
                ];
                
                // Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+Shift+C, Ctrl+U
                if ((e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'J' || e.key === 'C')) ||
                    (e.ctrlKey && e.key === 'u') ||
                    forbiddenKeys.includes(e.key)) {
                    e.preventDefault();
                    this.reportSuspiciousActivity('keyboard_shortcut');
                    return false;
                }
                
                // Disable Ctrl+S, Ctrl+P, Ctrl+R
                if (e.ctrlKey && (e.key === 's' || e.key === 'p' || e.key === 'r')) {
                    e.preventDefault();
                    return false;
                }
            });
        }
        
        preventContextMenu() {
            document.addEventListener('contextmenu', (e) => {
                if (e.target === this.video || this.video.contains(e.target)) {
                    e.preventDefault();
                    this.reportSuspiciousActivity('right_click');
                    return false;
                }
            });
        }
        
        monitorScreenRecording() {
            // Detect if screen is being shared/recorded
            if (navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia) {
                // Monitor for screen capture attempts
                const originalGetDisplayMedia = navigator.mediaDevices.getDisplayMedia;
                navigator.mediaDevices.getDisplayMedia = function(constraints) {
                    videoProtection.reportSuspiciousActivity('screen_capture_attempt');
                    return originalGetDisplayMedia(constraints);
                };
            }
            
            // Check for multiple monitors
            if (screen.width !== screen.availWidth || screen.height !== screen.availHeight) {
                this.reportSuspiciousActivity('multiple_monitors');
            }
            
            // Monitor for window size changes that might indicate recording
            let lastWidth = window.innerWidth;
            let lastHeight = window.innerHeight;
            
            setInterval(() => {
                if (Math.abs(window.innerWidth - lastWidth) > 100 || 
                    Math.abs(window.innerHeight - lastHeight) > 100) {
                    this.reportSuspiciousActivity('window_resize');
                }
                lastWidth = window.innerWidth;
                lastHeight = window.innerHeight;
            }, 3000);
        }
        
        monitorDevTools() {
            // Detect DevTools opening
            const devtools = /./;
            devtools.toString = function() {
                videoProtection.reportSuspiciousActivity('devtools_opened');
                return '';
            };
            console.log(devtools);
            
            // Check for DevTools via window size
            setInterval(() => {
                const widthDiff = window.outerWidth - window.innerWidth;
                const heightDiff = window.outerHeight - window.innerHeight;
                if (widthDiff > 100 || heightDiff > 100) {
                    this.reportSuspiciousActivity('devtools_detected');
                }
            }, 2000);
        }
        
        monitorPerformance() {
            // Monitor FPS drops (might indicate recording software)
            let lastTimestamp = performance.now();
            let frameCount = 0;
            
            function checkPerformance() {
                frameCount++;
                const now = performance.now();
                if (now - lastTimestamp >= 1000) {
                    const fps = frameCount;
                    if (fps < 20) {
                        videoProtection.reportSuspiciousActivity('low_fps');
                    }
                    frameCount = 0;
                    lastTimestamp = now;
                }
                requestAnimationFrame(checkPerformance);
            }
            requestAnimationFrame(checkPerformance);
        }
        
        addWatermark() {
            // Add dynamic watermark that moves
            const watermark = document.createElement('div');
            watermark.className = 'dynamic-watermark';
            watermark.innerHTML = `
                <div style="position: fixed; bottom: 10px; right: 10px; background: rgba(0,0,0,0.5); color: rgba(255,255,255,0.3); padding: 4px 8px; border-radius: 4px; font-size: 10px; font-family: monospace; pointer-events: none; z-index: 9999;">
                    ${this.getUserIdentifier()} | ${new Date().toLocaleString()}
                </div>
                <div style="position: fixed; top: 10px; left: 10px; background: rgba(0,0,0,0.3); color: rgba(255,255,255,0.2); padding: 4px 8px; border-radius: 4px; font-size: 10px; pointer-events: none; z-index: 9999;">
                    ${this.getUserEmail()} | Confidential
                </div>
            `;
            document.body.appendChild(watermark);
            
            // Update watermark every minute
            setInterval(() => {
                const timeWatermark = document.querySelector('.dynamic-watermark div:first-child');
                if (timeWatermark) {
                    timeWatermark.innerHTML = `${this.getUserIdentifier()} | ${new Date().toLocaleString()}`;
                }
            }, 60000);
        }
        
        getUserIdentifier() {
            // Get user identifier from session
            return document.querySelector('meta[name="user-id"]')?.content || 'User';
        }
        
        getUserEmail() {
            return document.querySelector('meta[name="user-email"]')?.content || 'student@learnhub.com';
        }
        
        reportSuspiciousActivity(type) {
            this.suspicionCount++;
            
            // Send to server
            fetch('../log_suspicious_activity.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    type: type,
                    module_id: this.video?.getAttribute('data-module-id'),
                    suspicion_level: this.suspicionCount
                })
            });
            
            if (this.suspicionCount >= 5) {
                this.pauseVideo();
            }
        }
        
        pauseVideo() {
            if (this.video && !this.video.paused) {
                this.video.pause();
                alert('Suspicious activity detected. Video paused for security.');
            }
        }
    }
    
    // Initialize protection when video is loaded
    window.videoProtection = null;
    document.addEventListener('DOMContentLoaded', () => {
        const video = document.getElementById('videoPlayer');
        if (video) {
            window.videoProtection = new VideoProtection(video);
        }
    });
})();