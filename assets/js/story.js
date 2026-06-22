/* Extracted from story.php inline <script> (refactor). PHP values via window.StoryConfig. */

      // ENHANCED Story JavaScript with Professional Video Sync
      const stories = window.StoryConfig.stories;
      let currentIndex = window.StoryConfig.currentIndex;
      const userId = window.StoryConfig.userId;

      // ENHANCED Progress tracking variables
      let progressInterval;
      let isVideoPaused = false;
      let storyStartTime;
      let videoDuration = 0;
      let isVideo = false;

      // Initialize story on load
      document.addEventListener('DOMContentLoaded', function() {
        console.log('🎬 Enhanced story viewer initialized');
        initializeStory();
      });

      // ENHANCED: Initialize current story with proper media handling
      function initializeStory() {
        const video = document.getElementById('storyVideo');
        const image = document.getElementById('storyImage');

        if (video) {
          isVideo = true;
          initializeVideoStory(video);
        } else if (image) {
          isVideo = false;
          initializeImageStory();
        }

        // Record view for current story
        recordStoryView(window.StoryConfig.currentStoryId);

        // Set story start time
        storyStartTime = Date.now();
      }

      // ENHANCED: Initialize video story with synchronized progress
      function initializeVideoStory(video) {
        console.log('🎥 Initializing video story with sync');

        // Show loading
        showLoading(true);

        // Add volume control
        video.volume = 0.5; // Set default volume to 50%

        // Add volume control button
        const volumeBtn = document.createElement('button');
        volumeBtn.className = 'volume-control';
        volumeBtn.innerHTML = '🔊';
        volumeBtn.onclick = function(e) {
          e.stopPropagation();
          if (video.muted) {
            video.muted = false;
            this.innerHTML = '🔊';
          } else {
            video.muted = true;
            this.innerHTML = '🔈';
          }
        };
        document.querySelector('.story-content').appendChild(volumeBtn);

        video.addEventListener('loadedmetadata', function() {
          videoDuration = video.duration * 1000; // Convert to milliseconds
          console.log(`📹 Video duration: ${videoDuration}ms`);
          showLoading(false);

          // Auto-play video
          video.play().then(() => {
            startVideoProgress();
          }).catch(error => {
            console.error('Video autoplay failed:', error);
            // Fallback to manual play
            showPlayButton();
          });
        });

        video.addEventListener('ended', function() {
          console.log('🎬 Video ended, moving to next story');
          setTimeout(() => {
            nextStory();
          }, 500);
        });

        video.addEventListener('error', function(e) {
          console.error('Video error:', e);
          showError('Error loading video');
        });

        // Handle video pause/play
        video.addEventListener('pause', function() {
          isVideoPaused = true;
          if (progressInterval) {
            clearInterval(progressInterval);
          }
        });

        video.addEventListener('play', function() {
          isVideoPaused = false;
          startVideoProgress();
        });
      }

      // ENHANCED: Initialize image story with better timing
      function initializeImageStory() {
        console.log('🖼️ Initializing image story');

        const image = document.getElementById('storyImage');

        if (image.complete) {
          showLoading(false);
          startImageProgress();
        } else {
          image.addEventListener('load', function() {
            showLoading(false);
            startImageProgress();
          });

          image.addEventListener('error', function() {
            showError('Error loading image');
          });
        }
      }

      // ENHANCED: Video progress synchronized with actual video time
      function startVideoProgress() {
        if (progressInterval) {
          clearInterval(progressInterval);
        }

        const video = document.getElementById('storyVideo');
        const currentProgressBar = document.querySelector(`.story-progress-bar[data-index="${currentIndex}"] .story-progress-fill`);

        if (!video || !currentProgressBar) return;

        progressInterval = setInterval(() => {
          if (isVideoPaused) return;

          const currentTime = video.currentTime * 1000; // Convert to milliseconds
          const progress = (currentTime / videoDuration) * 100;

          if (progress >= 100) {
            clearInterval(progressInterval);
            currentProgressBar.style.width = '100%';
            setTimeout(() => {
              nextStory();
            }, 200);
          } else {
            currentProgressBar.style.width = progress + '%';
          }
        }, 50); // Update every 50ms for smooth animation
      }

      // ENHANCED: Image progress with better timing
      function startImageProgress() {
        if (progressInterval) {
          clearInterval(progressInterval);
        }

        let progress = 0;
        const duration = 5000; // 5 seconds for images
        const interval = 50; // Update every 50ms
        const increment = (interval / duration) * 100;

        progressInterval = setInterval(() => {
          progress += increment;

          if (progress >= 100) {
            clearInterval(progressInterval);
            const currentProgressBar = document.querySelector(`.story-progress-bar[data-index="${currentIndex}"] .story-progress-fill`);
            if (currentProgressBar) {
              currentProgressBar.style.width = '100%';
            }
            setTimeout(() => {
              nextStory();
            }, 200);
          } else {
            const currentProgressBar = document.querySelector(`.story-progress-bar[data-index="${currentIndex}"] .story-progress-fill`);
            if (currentProgressBar) {
              currentProgressBar.style.width = progress + '%';
            }
          }
        }, interval);
      }

      // ENHANCED: Navigation functions with better transitions
      function nextStory() {
        if (currentIndex < stories.length - 1) {
          console.log('➡️ Moving to next story');
          showLoading(true);
          currentIndex++;
          navigateToStory(stories[currentIndex].STORIES_ID);
        } else {
          console.log('🏁 Reached end of stories');
          closeStory();
        }
      }

      function previousStory() {
        if (currentIndex > 0) {
          console.log('⬅️ Moving to previous story');
          showLoading(true);
          currentIndex--;
          navigateToStory(stories[currentIndex].STORIES_ID);
        }
      }

      function navigateToStory(storyId) {
        // Clear current progress
        if (progressInterval) {
          clearInterval(progressInterval);
        }

        // Smooth transition
        document.querySelector('.story-content').style.opacity = '0.5';

        setTimeout(() => {
          window.location.href = `story.php?id=${storyId}`;
        }, 150);
      }

      function closeStory() {
        console.log('❌ Closing story viewer');

        // Clear progress
        if (progressInterval) {
          clearInterval(progressInterval);
        }

        // Fade out animation
        document.querySelector('.story-container').style.animation = 'fadeOut 0.3s ease forwards';

        setTimeout(() => {
          window.location.href = 'dashboard.php';
        }, 300);
      }

      // ENHANCED: Record story view with better error handling
      function recordStoryView(storyId) {
        if (!storyId) return;

        fetch('story.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=record_individual_view&story_id=${storyId}`
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              console.log('✅ Story view recorded');
            } else {
              console.warn('⚠️ Failed to record view:', data.error);
            }
          })
          .catch(error => {
            console.error('❌ Error recording view:', error);
          });
      }

      // ENHANCED: Loading and error states
      function showLoading(show) {
        const loading = document.getElementById('storyLoading');
        if (loading) {
          loading.style.display = show ? 'block' : 'none';
        }
      }

      function showError(message) {
        const container = document.querySelector('.story-container');
        container.innerHTML = `
        <div class="story-error">
            <h3>⚠️ ${message}</h3>
            <p>Please try again or go back to dashboard</p>
            <button onclick="closeStory()" style="background: white; color: #f44336; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin-top: 15px;">
                Back to Dashboard
            </button>
        </div>
    `;
      }

      function showPlayButton() {
        const container = document.querySelector('.story-content');
        const playBtn = document.createElement('div');
        playBtn.innerHTML = '▶️';
        playBtn.style.cssText = `
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: 64px;
        cursor: pointer;
        background: rgba(0,0,0,0.7);
        border-radius: 50%;
        width: 100px;
        height: 100px;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 100;
    `;

        playBtn.onclick = function() {
          const video = document.getElementById('storyVideo');
          if (video) {
            video.play();
            this.remove();
          }
        };

        container.appendChild(playBtn);
      }

      // ENHANCED: Keyboard navigation
      document.addEventListener('keydown', function(e) {
        switch (e.key) {
          case 'ArrowRight':
          case ' ':
            e.preventDefault();
            nextStory();
            break;
          case 'ArrowLeft':
            e.preventDefault();
            previousStory();
            break;
          case 'Escape':
            e.preventDefault();
            closeStory();
            break;
        }
      });

      // ENHANCED: Touch/swipe navigation for mobile
      let startX = 0;
      let endX = 0;
      let startY = 0;
      let endY = 0;

      document.addEventListener('touchstart', function(e) {
        startX = e.changedTouches[0].screenX;
        startY = e.changedTouches[0].screenY;
      });

      document.addEventListener('touchend', function(e) {
        endX = e.changedTouches[0].screenX;
        endY = e.changedTouches[0].screenY;
        handleSwipe();
      });

      function handleSwipe() {
        const threshold = 50;
        const diffX = startX - endX;
        const diffY = startY - endY;

        // Only handle horizontal swipes (ignore vertical scrolling)
        if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > threshold) {
          if (diffX > 0) {
            // Swipe left - next story
            nextStory();
          } else {
            // Swipe right - previous story
            previousStory();
          }
        }
      }

      // Add CSS animations
      const style = document.createElement('style');
      style.textContent = `
    @keyframes fadeOut {
        from { opacity: 1; }
        to { opacity: 0; }
    }
`;
      document.head.appendChild(style);

      console.log('✅ FIXED: Enhanced Story Viewer with Proper Notifications System Loaded!');
