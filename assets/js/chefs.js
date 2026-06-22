/* Extracted from chefs.php inline <script> (refactor). PHP values via window.ChefsConfig. */

    // Track followed chefs
    const followedChefs = new Set((window.ChefsConfig && window.ChefsConfig.following) || []);
    
    // Toggle follow status
    function toggleFollow(chefId) {
        const button = document.querySelector(`.chef-card[data-chef-id="${chefId}"] .follow-btn`);
        const followerCountElement = document.querySelector(`.follower-count[data-chef-id="${chefId}"]`);
        const isFollowing = button.classList.contains('following');
        
        // Show loading state
        const originalContent = button.innerHTML;
        button.innerHTML = '<span class="loading">Processing...</span>';
        button.disabled = true;

        // Create form data
        const formData = new FormData();
        formData.append('action', 'toggle_follow');
        formData.append('chef_id', chefId);

        // Send request to the same page
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update follower count with animation
                if (followerCountElement && data.new_follower_count !== undefined) {
                    followerCountElement.classList.add('updating');
                    setTimeout(() => {
                        followerCountElement.textContent = data.new_follower_count;
                        followerCountElement.classList.remove('updating');
                    }, 250);
                }
                
                if (data.action === 'followed') {
                    button.classList.add('following');
                    button.innerHTML = `Following <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"></path></svg>`;
                    followedChefs.add(chefId);
                } else {
                    button.classList.remove('following');
                    button.innerHTML = `Follow <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>`;
                    followedChefs.delete(chefId);
                }
                
                // Add success animation
                button.style.transform = 'scale(1.05)';
                setTimeout(() => {
                    button.style.transform = '';
                }, 200);
                
                showToast(data.message, 'success');
            } else {
                // Restore original content on error
                button.innerHTML = originalContent;
                showToast(data.message || 'An error occurred. Please try again.', 'error');
            }
            
            button.disabled = false;
        })
        .catch(error => {
            console.error('Error:', error);
            button.innerHTML = originalContent;
            button.disabled = false;
            showToast('Network error. Please check your connection.', 'error');
        });
    }

    // Load more functionality
    document.getElementById('loadMoreBtn')?.addEventListener('click', function() {
        // Show loading state
        this.innerHTML = `<span class="loading">Loading...</span>`;
        this.disabled = true;
        
        // Simulate loading more chefs
        setTimeout(() => {
            this.innerHTML = `Load More Chefs <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>`;
            this.disabled = false;
        
            showToast('All available chefs have been loaded!', 'info');
        }, 1500);
    });

    // Enhanced toast notification system
    function showToast(message, type = 'success') {
        // Create toast container if it doesn't exist
        let toastContainer = document.getElementById('toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toast-container';
            toastContainer.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                z-index: 10000;
                display: flex;
                flex-direction: column;
                gap: 10px;
            `;
            document.body.appendChild(toastContainer);
        }

        // Create toast element
        const toast = document.createElement('div');
        toast.style.cssText = `
            min-width: 300px;
            padding: 16px 20px;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-weight: 500;
            font-family: 'Qurova', sans-serif;
            transform: translateX(100%);
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            backdrop-filter: blur(10px);
        `;

        // Set color based on type
        const colors = {
            success: { bg: '#4CAF50', color: 'white' },
            error: { bg: '#F44336', color: 'white' },
            info: { bg: '#2196F3', color: 'white' },
            warning: { bg: '#FF9800', color: 'white' }
        };
        
        const colorScheme = colors[type] || colors.success;
        toast.style.backgroundColor = colorScheme.bg;
        toast.style.color = colorScheme.color;

        // Add content
        const messageSpan = document.createElement('span');
        messageSpan.textContent = message;
        
        const closeBtn = document.createElement('button');
        closeBtn.innerHTML = '&times;';
        closeBtn.style.cssText = `
            background: none;
            border: none;
            color: inherit;
            font-size: 20px;
            cursor: pointer;
            margin-left: 10px;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background-color 0.2s ease;
        `;
        
        closeBtn.onmouseover = () => closeBtn.style.backgroundColor = 'rgba(255, 255, 255, 0.2)';
        closeBtn.onmouseout = () => closeBtn.style.backgroundColor = 'transparent';
        closeBtn.onclick = () => removeToast(toast);

        toast.appendChild(messageSpan);
        toast.appendChild(closeBtn);

        // Add to container
        toastContainer.appendChild(toast);

        // Animate in
        setTimeout(() => {
            toast.style.transform = 'translateX(0)';
        }, 10);

        // Auto remove after 4 seconds
        setTimeout(() => {
            removeToast(toast);
        }, 4000);
    }

    function removeToast(toast) {
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }

    // Add smooth scroll behavior for better UX
    document.addEventListener('DOMContentLoaded', function() {
        // Add intersection observer for animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe chef cards for staggered animation
        document.querySelectorAll('.chef-card').forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
            observer.observe(card);
        });
    });

  
