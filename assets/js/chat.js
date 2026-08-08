/* Extracted from chat.php inline <script> (refactor). PHP values via window.ChatConfig. */

        // Enhanced JavaScript for improved functionality with Fixed Sidebar Responsiveness
        
        // Global variables
        let currentReceiverId = null;
        let selectedMessageId = null;
        let selectedFile = null;
        let lastMessageTimestamp = 0;
        let messagePollingInterval = null;
        let selectedReaction = null;
        let activeConversationUserId = null;
        let activeConversationUserName = null;
        let activeConversationUserImage = null;
        
        // DOM Elements
        const messagesContainer = document.getElementById('messages-container');
        const messageInput = document.getElementById('message-input');
        const sendMessageBtn = document.getElementById('send-message-btn');
        const searchUsersInput = document.getElementById('search-users-input');
        const usersList = document.getElementById('users-list');
        const newMessageBtn = document.getElementById('new-message-btn');
        const newMessageModal = document.getElementById('new-message-modal');
        const messageImageInput = document.getElementById('message-image');
        const imagePreview = document.getElementById('image-preview');
        const previewImage = document.getElementById('preview-image');
        const removeImageBtn = document.getElementById('remove-image-btn');
        const showEmojiBtn = document.getElementById('show-emoji-btn');
        const closeEmojiBtn = document.getElementById('close-emoji-btn');
        const reactionsMenu = document.getElementById('reactions-menu');
        const messageArea = document.getElementById('message-area');
        const conversationItems = document.querySelectorAll('.conversation-item');
        const noMessageSelected = document.getElementById('no-message-selected');
        const activeConversation = document.getElementById('active-conversation');
        const mobileToggle = document.getElementById('mobile-conversations-toggle');
        const mobileOverlay = document.getElementById('mobile-overlay');
        const conversationsList = document.getElementById('conversations-list');
        
        // Initialize the chat interface
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize sidebar functionality
            initializeSidebar();
            
            // Initialize mobile conversations toggle
            initializeMobileConversationsToggle();
            
            // Initialize online status
            updateUserStatus();
            
            // Initialize emoji picker functionality
            initEmojiPicker();
            
            // Auto-resize textarea
            messageInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            });
            
            // Mobile conversations toggle
            if (mobileToggle) {
                mobileToggle.addEventListener('click', function() {
                    conversationsList.classList.add('mobile-open');
                    mobileOverlay.classList.add('active');
                });
            }
            
            if (mobileOverlay) {
                mobileOverlay.addEventListener('click', function() {
                    conversationsList.classList.remove('mobile-open');
                    mobileOverlay.classList.remove('active');
                });
            }
            
            // Set up emoji button click handler
            if (showEmojiBtn) {
                showEmojiBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    toggleEmojiPicker();
                });
            }
            
            // Set up close emoji button click handler
            if (closeEmojiBtn) {
                closeEmojiBtn.addEventListener('click', function() {
                    const emojiPicker = document.getElementById('emoji-picker');
                    if (emojiPicker) {
                        emojiPicker.classList.remove('active');
                    }
                });
            }
            
            // FIXED: Close emoji picker and reactions menu when clicking outside
            document.addEventListener('click', function(e) {
                const emojiPicker = document.getElementById('emoji-picker');
                
                // Close emoji picker if clicking outside
                if (emojiPicker && !emojiPicker.contains(e.target) && 
                    !showEmojiBtn.contains(e.target)) {
                    emojiPicker.classList.remove('active');
                }
                
                // FIXED: Close reactions menu if clicking outside
                if (reactionsMenu && !reactionsMenu.contains(e.target) && 
                    !e.target.classList.contains('reaction-hover-btn') && 
                    !e.target.closest('.reaction-hover-btn')) {
                    reactionsMenu.classList.remove('active');
                    reactionsMenu.style.display = 'none';
                }
            });
            
            // Set up conversation item click handlers
            conversationItems.forEach(item => {
                item.addEventListener('click', function() {
                    // Remove active class from all items
                    conversationItems.forEach(i => i.classList.remove('active'));
                    
                    // Add active class to clicked item
                    this.classList.add('active');
                    
                    // Get user data from data attributes
                    const userId = this.dataset.userId;
                    const userName = this.dataset.userName;
                    const userImage = this.dataset.userImage;
                    
                    if (userId) {
                        // Load active conversation with user data
                        loadActiveConversation(userId, userName, userImage);
                        
                        // Close mobile conversations list
                        if (window.innerWidth <= 767) {
                            conversationsList.classList.remove('mobile-open');
                            mobileOverlay.classList.remove('active');
                        }
                    } else {
                        console.error('No user ID found for this conversation');
                    }
                });
            });
            
            // Set up message input handlers
            messageInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });
            
            // Send message button click handler
            sendMessageBtn.addEventListener('click', sendMessage);
            
            // Image upload handler
            messageImageInput.addEventListener('change', handleImageUpload);
            
            // Remove image button handler
            removeImageBtn.addEventListener('click', removeImage);
            
            // New message button handler (button removed from UI; guard for null)
            if (newMessageBtn) newMessageBtn.addEventListener('click', openNewMessageModal);
            
            // Back to messages button handler
            const backToMessagesBtn = document.getElementById('back-to-messages-btn');
            if (backToMessagesBtn) {
                backToMessagesBtn.addEventListener('click', closeModal);
            }
            
            // Close modal when clicking outside
            window.addEventListener('click', function(e) {
                if (e.target === newMessageModal) {
                    closeModal();
                }
            });
            
            // Search users input handler
            searchUsersInput.addEventListener('input', debounce(searchUsers, 300));
            
            // Add event listener for mobile new message button
            const mobileBtn = document.getElementById('new-message-mobile-btn');
            if (mobileBtn) {
                mobileBtn.addEventListener('click', function() {
                    openNewMessageModal();
                });
            }
            
            // Emoji category buttons
            document.querySelectorAll('.emoji-category').forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all categories
                    document.querySelectorAll('.emoji-category').forEach(btn => {
                        btn.classList.remove('active');
                    });
                    
                    // Add active class to clicked category
                    this.classList.add('active');
                    
                    // Hide all emoji groups
                    document.querySelectorAll('.emoji-group').forEach(group => {
                        group.style.display = 'none';
                    });
                    
                    // Show selected emoji group
                    const category = this.dataset.category;
                    const emojiGroup = document.getElementById(`${category}-emojis`);
                    if (emojiGroup) {
                        emojiGroup.style.display = 'flex';
                    }
                });
            });
            
            // FIXED: Reaction item click handlers
            document.querySelectorAll('.reaction-item').forEach(item => {
                item.addEventListener('click', function(event) {
                    if (selectedMessageId) {
                        addReaction(selectedMessageId, this.dataset.reaction);
                    } else {
                        // Store reaction to add to the next sent message
                        selectedReaction = this.dataset.reaction;
                    }
                    
                    // FIXED: Hide reactions menu after selection
                    reactionsMenu.classList.remove('active');
                    reactionsMenu.style.display = 'none';
                    event.stopPropagation();
                });
            });
            
            // Handle conversation search functionality
            const searchInput = document.getElementById('search-conversations');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    
                    conversationItems.forEach(item => {
                        const conversationName = item.querySelector('.conversation-name').textContent.toLowerCase();
                        const conversationMessage = item.querySelector('.conversation-last-message').textContent.toLowerCase();
                        
                        if (conversationName.includes(searchTerm) || conversationMessage.includes(searchTerm)) {
                            item.style.display = 'flex';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            }
        });
        
        // FIXED: Initialize Sidebar Functionality
        function initializeSidebar() {
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const sidebar = document.querySelector('.sidebar');
            const sidebarOverlay = document.getElementById('sidebar-overlay');
            
            if (sidebarToggle && sidebar && sidebarOverlay) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                    sidebarOverlay.classList.toggle('active');
                });
                
                sidebarOverlay.addEventListener('click', function() {
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                });
                
                // Close sidebar when clicking on sidebar links (mobile)
                const sidebarLinks = sidebar.querySelectorAll('a');
                sidebarLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        if (window.innerWidth <= 991) {
                            sidebar.classList.remove('active');
                            sidebarOverlay.classList.remove('active');
                        }
                    });
                });
            }
        }
        
        // Initialize Mobile Conversations Toggle
        function initializeMobileConversationsToggle() {
            const conversationsList = document.getElementById('conversations-list');
            const mobileToggle = document.getElementById('mobile-conversations-toggle');
            const mobileOverlay = document.getElementById('mobile-overlay');
            const backButton = document.querySelector('.sidebar-toggle');
            
            if (mobileToggle && conversationsList && mobileOverlay) {
                // Toggle conversations list on mobile
                mobileToggle.addEventListener('click', function() {
                    conversationsList.classList.add('mobile-open');
                    mobileOverlay.classList.add('active');
                });
                
                // Close conversations list when clicking overlay
                mobileOverlay.addEventListener('click', function() {
                    conversationsList.classList.remove('mobile-open');
                    mobileOverlay.classList.remove('active');
                });
                
                // Close conversations list when clicking back button (at small screen sizes)
                if (backButton) {
                    backButton.addEventListener('click', function() {
                        if (window.innerWidth <= 767) {
                            conversationsList.classList.remove('mobile-open');
                            mobileOverlay.classList.remove('active');
                        }
                    });
                }
                
                // Close conversation list when a conversation is selected
                const conversationItems = document.querySelectorAll('.conversation-item');
                conversationItems.forEach(item => {
                    item.addEventListener('click', function() {
                        if (window.innerWidth <= 767) {
                            setTimeout(() => {
                                conversationsList.classList.remove('mobile-open');
                                mobileOverlay.classList.remove('active');
                            }, 100);
                        }
                    });
                });
            }
            
            // Re-initialize on window resize to handle orientation changes
            window.addEventListener('resize', function() {
                if (window.innerWidth > 767) {
                    conversationsList.classList.remove('mobile-open');
                    mobileOverlay.classList.remove('active');
                }
            });
        }
        
        // Load active conversation
        function loadActiveConversation(userId, userName, userImage) {
            activeConversationUserId = userId;
            activeConversationUserName = userName;
            activeConversationUserImage = userImage || 'uploads/profiles/default.png';
            
            // Update active conversation header
            const headerNameElement = document.getElementById('active-conversation-name');
            headerNameElement.textContent = userName;
            
            // Update header avatar if it exists
            const headerAvatarImg = document.querySelector('.conversation-header-avatar img');
            if (headerAvatarImg) {
                headerAvatarImg.src = activeConversationUserImage;
                headerAvatarImg.alt = userName;
            }
            
            // Show active conversation and hide no message selected
            document.getElementById('no-message-selected').style.display = 'none';
            document.getElementById('active-conversation').style.display = 'flex';
            
            // Ensure message input is visible and focused
            document.querySelector('.message-input-container').style.display = 'flex';
            setTimeout(() => messageInput.focus(), 100);
            
            // Clear messages container
            messagesContainer.innerHTML = '';
            
            // Load messages
            loadMessages();
            
            // Start polling for new messages
            startMessagePolling();
            
            // Update conversation list to highlight active conversation
            const conversationItems = document.querySelectorAll('.conversation-item');
            conversationItems.forEach(item => {
                if (item.dataset.userId == userId) {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            });
        }
        
        // Start message polling — NO-OP now. The unified adaptive poller at the
        // bottom of this file handles new messages (id-based), the conversation
        // list, unread counts and presence in a single visibility-aware request.
        function startMessagePolling() { /* replaced by adaptive poller */ }
        
        // Load messages for selected conversation
        function loadMessages(lastTimestamp = 0) {
            if (!activeConversationUserId) return;
            
            // Show loading indicator on first load
            if (lastTimestamp === 0) {
                messagesContainer.innerHTML = '<div class="loading-messages"><i class="fas fa-spinner fa-spin"></i> Loading messages...</div>';
                // Reset last message timestamp
                lastMessageTimestamp = 0;
            }
            
            const formData = new FormData();
            formData.append('action', 'get_messages');
            formData.append('receiver_id', activeConversationUserId);
            if (lastTimestamp > 0) {
                formData.append('last_timestamp', lastTimestamp);
            }
            
            fetch('chat.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Track id-based cursor + active partner for the adaptive poller.
                    if (typeof data.last_message_id !== 'undefined' && data.last_message_id) {
                        window.__lastMessageId = data.last_message_id;
                    }
                    window.__activeUserId = activeConversationUserId;
                    // If this is the first load, clear the container
                    if (lastTimestamp === 0) {
                        messagesContainer.innerHTML = '';
                    }
                    
                    // Add messages
                    if (data.messages && data.messages.length > 0) {
                        data.messages.forEach(message => {
                            appendMessage(message);
                        });
                        
                        // Scroll to bottom of messages container
                        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    } else if (lastTimestamp === 0) {
                        // No messages yet
                        messagesContainer.innerHTML = '<div class="no-messages"><i class="fas fa-comments"></i><br>No messages yet. Start the conversation!</div>';
                    }
                } else {
                    console.error('Error loading messages:', data.message);
                }
            })
            .catch(error => {
                console.error('Error loading messages:', error);
            });
        }
        
        // Append a message to the messages container
        function appendMessage(message) {
            const isSent = message.SENDER_ID == window.ChatConfig.currentUserId;
            const messageClass = isSent ? 'message-sent' : 'message-received';
            const messageTime = new Date(message.SENT_AT);
            const formattedTime = messageTime.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            
            // Get user profile image
            let profileImage = isSent ? 
                window.ChatConfig.currentUserProfileImage :
                activeConversationUserImage;
                
            // Default image if none available
            if (!profileImage || profileImage.trim() === '') {
                profileImage = 'uploads/profiles/default.png';
            }
            
            // Create message element
            const messageElement = document.createElement('div');
            messageElement.className = `message ${messageClass}`;
            messageElement.dataset.messageId = message.MESSAGE_ID;
            
            // Create avatar element
            const avatarElement = document.createElement('div');
            avatarElement.className = 'message-avatar';
            avatarElement.innerHTML = `<img src="${profileImage}" alt="User avatar">`;
            
            // Create message content wrapper
            const contentWrapper = document.createElement('div');
            contentWrapper.className = 'message-content';
            
            // Create message bubble
            const bubbleElement = document.createElement('div');
            bubbleElement.className = 'message-bubble';
            
            // Create message text element if content exists
            if (message.CONTENT) {
                const textElement = document.createElement('div');
                textElement.className = 'message-text';
                textElement.textContent = message.CONTENT;
                bubbleElement.appendChild(textElement);
            }
            
            // Add message media (image OR video) if present
            if (message.IMAGE_URL) {
                const imageContainer = document.createElement('div');
                imageContainer.className = 'message-image';
                const _u = message.IMAGE_URL;
                const isAudio = /\/feedora\/voice\//.test(_u) || /\.(mp3|m4a|wav|oga)(\?.*)?$/i.test(_u);
                const isVideo = !isAudio && (/\/video\/upload\//.test(_u) ||
                                /\.(mp4|webm|mov|ogg|m4v)(\?.*)?$/i.test(_u));
                if (isAudio) {
                    imageContainer.classList.add('is-audio');
                    imageContainer.innerHTML = `<audio src="${_u}" controls preload="metadata"></audio>`;
                } else if (isVideo) {
                    imageContainer.classList.add('is-video');
                    imageContainer.innerHTML = `<video src="${_u}" controls playsinline preload="metadata"></video>`;
                } else {
                    imageContainer.innerHTML = `<img src="${_u}" alt="Message image" onclick="openFullscreenImage('${_u}')">`;
                }
                
                // Add reaction hover button for image messages
                const reactionBtn = document.createElement('button');
                reactionBtn.className = 'reaction-hover-btn';
                reactionBtn.innerHTML = '<i class="fas fa-smile"></i>';
                reactionBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    showReactionsMenu(message.MESSAGE_ID, this);
                });
                
                imageContainer.appendChild(reactionBtn);
                bubbleElement.appendChild(imageContainer);
            }
            
            // Add reaction hover button to bubble (only if there's text content)
            if (message.CONTENT) {
                const reactionBtn = document.createElement('button');
                reactionBtn.className = 'reaction-hover-btn';
                reactionBtn.innerHTML = '<i class="fas fa-smile"></i>';
                reactionBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    showReactionsMenu(message.MESSAGE_ID, this);
                });
                
                bubbleElement.appendChild(reactionBtn);
            }
            
            contentWrapper.appendChild(bubbleElement);
            
            // Add message time
            const timeElement = document.createElement('div');
            timeElement.className = 'message-time';
            timeElement.textContent = formattedTime;
            contentWrapper.appendChild(timeElement);
            
            // Add reactions if present
            if (message.REACTIONS) {
                try {
                    const reactions = typeof message.REACTIONS === 'string' ? 
                        JSON.parse(message.REACTIONS) : message.REACTIONS;
                    
                    if (reactions && Object.keys(reactions).length > 0) {
                        const reactionsContainer = document.createElement('div');
                        reactionsContainer.className = 'message-reactions';
                        
                        for (const [reaction, count] of Object.entries(reactions)) {
                            const reactionElement = document.createElement('span');
                            reactionElement.className = 'message-reaction';
                            reactionElement.dataset.reaction = reaction;
                            reactionElement.innerHTML = `${reaction} <span class="message-reaction-count">${count}</span>`;
                            reactionElement.onclick = () => addReaction(message.MESSAGE_ID, reaction);
                            reactionsContainer.appendChild(reactionElement);
                        }
                        
                        contentWrapper.appendChild(reactionsContainer);
                    }
                } catch (e) {
                    console.error('Error parsing reactions:', e);
                }
            }
            
            // Append avatar and content to message element
            messageElement.appendChild(avatarElement);
            messageElement.appendChild(contentWrapper);
            messagesContainer.appendChild(messageElement);

            // P1/P2: pin affordance + edited marker / deleted tombstone.
            if (window.ChatEnhance) window.ChatEnhance.decorateMessage(messageElement, message, isSent);
            
            // Update last message timestamp
            try {
                const timestamp = new Date(message.SENT_AT).getTime();
                if (timestamp > lastMessageTimestamp) {
                    lastMessageTimestamp = timestamp;
                }
            } catch (e) {
                console.error('Error parsing timestamp:', e);
            }
        }
        
        // FIXED: Send a message function
        function sendMessage() {
            // Either a DM (activeConversationUserId) or a group (window.__activeGroupId).
            if (!activeConversationUserId && !window.__activeGroupId) return;

            const content = messageInput.value.trim();
            if (!content && !selectedFile) return;

            const formData = new FormData();
            formData.append('action', 'send_message');
            if (window.__activeGroupId) {
                formData.append('group_id', window.__activeGroupId);
            } else {
                formData.append('receiver_id', activeConversationUserId);
            }
            
            if (content) {
                formData.append('content', content);
            }
            
            // FIXED: Properly append the file
            if (selectedFile) {
                formData.append('image', selectedFile);
            }
            
            // Add reaction if one is selected
            if (selectedReaction) {
                formData.append('reaction', selectedReaction);
                selectedReaction = null;
            }
            
            // Disable send button while sending
            sendMessageBtn.disabled = true;
            sendMessageBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            fetch('chat.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Clear input and image preview
                    messageInput.value = '';
                    messageInput.style.height = 'auto';
                    removeImage();

                    // Pull the just-sent message (and any new ones) + refresh the
                    // conversation list via one fast adaptive poll — avoids a full
                    // reload and keeps the list ordering/unread accurate.
                    if (window.__chatPollNow) window.__chatPollNow(); else loadMessages();
                } else {
                    alert('Error sending message: ' + data.message);
                }
                
                // Re-enable send button
                sendMessageBtn.disabled = false;
                sendMessageBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
            })
            .catch(error => {
                console.error('Error sending message:', error);
                sendMessageBtn.disabled = false;
                sendMessageBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
                alert('Error sending message. Please try again.');
            });
        }
        
        // Handle image OR video upload with preview
        function handleImageUpload(e) {
            const file = e.target.files[0];
            if (!file) return;

            const isImage = file.type.startsWith('image/');
            const isVideo = file.type.startsWith('video/');
            if (!isImage && !isVideo) {
                alert('Please select an image or video file.');
                return;
            }
            // Size limits: images 10MB, videos 50MB.
            const max = isVideo ? 50 * 1024 * 1024 : 10 * 1024 * 1024;
            if (file.size > max) {
                alert(isVideo ? 'Video must be under 50MB.' : 'Image must be under 10MB.');
                return;
            }

            // Store selected file
            selectedFile = file;

            // Build preview (image or muted looping video)
            const url = URL.createObjectURL(file);
            const existingVid = imagePreview.querySelector('video.preview-video');
            if (existingVid) existingVid.remove();
            if (isVideo) {
                previewImage.style.display = 'none';
                const v = document.createElement('video');
                v.className = 'preview-video';
                v.src = url;
                v.muted = true;
                v.loop = true;
                v.autoplay = true;
                v.playsInline = true;
                imagePreview.insertBefore(v, imagePreview.firstChild);
            } else {
                previewImage.style.display = 'block';
                previewImage.src = url;
            }
            imagePreview.style.display = 'block';
            imagePreview.style.opacity = '0';
            setTimeout(() => { imagePreview.style.opacity = '1'; }, 10);

            // Focus back to message input
            messageInput.focus();
        }
        
        // Remove selected image/video
        function removeImage() {
            selectedFile = null;
            messageImageInput.value = '';
            imagePreview.style.display = 'none';
            const vid = imagePreview.querySelector('video.preview-video');
            if (vid) vid.remove();
            if (previewImage) { previewImage.src = ''; previewImage.style.display = 'block'; }
        }
        
        // Add or toggle a reaction to a message
        function addReaction(messageId, reaction) {
            const formData = new FormData();
            formData.append('action', 'add_reaction');
            formData.append('message_id', messageId);
            formData.append('reaction', reaction);
            
            fetch('chat.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the reactions display
                    const message = document.querySelector(`.message[data-message-id="${messageId}"]`);
                    if (!message) return;
                    
                    let reactionsContainer = message.querySelector('.message-reactions');
                    
                    if (!reactionsContainer) {
                        reactionsContainer = document.createElement('div');
                        reactionsContainer.className = 'message-reactions';
                        message.querySelector('.message-content').appendChild(reactionsContainer);
                    }
                    
                    // Clear existing reactions
                    reactionsContainer.innerHTML = '';
                    
                    // Add updated reactions
                    const reactions = data.reactions;
                    for (const [reaction, count] of Object.entries(reactions)) {
                        const reactionElement = document.createElement('span');
                        reactionElement.className = 'message-reaction';
                        reactionElement.dataset.reaction = reaction;
                        reactionElement.innerHTML = `${reaction} <span class="message-reaction-count">${count}</span>`;
                        
                        // Add click handler to toggle reaction
                        reactionElement.addEventListener('click', function() {
                            addReaction(messageId, reaction);
                        });
                        
                        reactionsContainer.appendChild(reactionElement);
                    }
                    
                    // Hide reactions menu
                    reactionsMenu.classList.remove('active');
                    reactionsMenu.style.display = 'none';
                } else {
                    console.error('Error adding reaction:', data.message);
                }
            })
            .catch(error => {
                console.error('Error adding reaction:', error);
            });
        }
        
        // FIXED: Create and show reactions menu dynamically with enhanced animations
        function showReactionsMenu(messageId, buttonElement) {
            selectedMessageId = messageId;
            
            // Close emoji picker if open
            document.getElementById('emoji-picker').classList.remove('active');
            
            // Position the reactions menu
            const rect = buttonElement.getBoundingClientRect();
            reactionsMenu.style.position = 'fixed';
            reactionsMenu.style.top = `${rect.top - reactionsMenu.offsetHeight - 10}px`;
            reactionsMenu.style.left = `${rect.left - (reactionsMenu.offsetWidth / 2) + (rect.width / 2)}px`;
            
            // Ensure menu stays within viewport
            const menuRect = reactionsMenu.getBoundingClientRect();
            if (menuRect.left < 10) {
                reactionsMenu.style.left = '10px';
            }
            if (menuRect.right > window.innerWidth - 10) {
                reactionsMenu.style.left = `${window.innerWidth - menuRect.width - 10}px`;
            }
            
            // Show reactions menu with animation
            reactionsMenu.style.opacity = '0';
            reactionsMenu.style.transform = 'translateY(10px) scale(0.95)';
            reactionsMenu.style.display = 'flex';
            
            setTimeout(() => {
                reactionsMenu.classList.add('active');
                reactionsMenu.style.opacity = '1';
                reactionsMenu.style.transform = 'translateY(0) scale(1)';
            }, 10);
            
            // Add bounce effect to reaction items
            const reactionItems = reactionsMenu.querySelectorAll('.reaction-item');
            reactionItems.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'scale(0.8)';
                
                setTimeout(() => {
                    item.style.opacity = '1';
                    item.style.transform = 'scale(1)';
                }, 50 + (index * 30));
            });
        }
        
        function toggleEmojiPicker() {
            const emojiPicker = document.getElementById('emoji-picker');
            if (!emojiPicker) return;
            
            emojiPicker.classList.toggle('active');
            
            // Hide reactions menu if open
            if (reactionsMenu) {
                reactionsMenu.classList.remove('active');
                reactionsMenu.style.display = 'none';
            }
        }
        
        function initEmojiPicker() {
            // Add click handlers for all emojis
            document.querySelectorAll('.emoji').forEach(emoji => {
                emoji.addEventListener('click', function() {
                    const emojiChar = this.dataset.emoji;
                    insertEmojiIntoMessage(emojiChar);
                    const emojiPicker = document.getElementById('emoji-picker');
                    if (emojiPicker) {
                        emojiPicker.classList.remove('active');
                    }
                });
            });
        }
        
        function insertEmojiIntoMessage(emoji) {
            if (!messageInput) return;
            
            // Get current cursor position
            const cursorPos = messageInput.selectionStart;
            const textBefore = messageInput.value.substring(0, cursorPos);
            const textAfter = messageInput.value.substring(cursorPos, messageInput.value.length);
            
            // Insert emoji at cursor position
            messageInput.value = textBefore + emoji + textAfter;
            
            // Move cursor after inserted emoji
            messageInput.selectionStart = cursorPos + emoji.length;
            messageInput.selectionEnd = cursorPos + emoji.length;
            
            // Focus back on the input
            messageInput.focus();
            
            // Trigger input event to resize textarea
            messageInput.dispatchEvent(new Event('input'));
        }
        
        // Open new message modal
        function openNewMessageModal() {
            newMessageModal.style.display = 'flex';
            searchUsersInput.focus();
            loadUsers();
        }
        
        // Close modal
        function closeModal() {
            newMessageModal.style.display = 'none';
            searchUsersInput.value = '';
        }
        
        // Load users for new message
        function loadUsers(searchTerm = '') {
            usersList.innerHTML = '<div class="loading-users"><i class="fas fa-spinner fa-spin"></i> Loading users...</div>';
            
            const formData = new FormData();
            formData.append('action', 'get_users');
            if (searchTerm) {
                formData.append('search', searchTerm);
            }
            
            fetch('chat.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderUsersList(data.users);
                } else {
                    usersList.innerHTML = '<div class="error-loading">Error loading users</div>';
                    console.error('Error loading users:', data.message);
                }
            })
            .catch(error => {
                usersList.innerHTML = '<div class="error-loading">Error loading users</div>';
                console.error('Error loading users:', error);
            });
        }
        
        // Search users
        function searchUsers() {
            const searchTerm = searchUsersInput.value.trim();
            loadUsers(searchTerm);
        }
        
        // Render users list
        function renderUsersList(users) {
            if (!users || users.length === 0) {
                usersList.innerHTML = '<div class="no-users-found">No users found</div>';
                return;
            }
            
            usersList.innerHTML = '';
            
            users.forEach(user => {
                const userId = user.USER_ID || '';
                const userName = user.NAME || 'Unknown User';
                const profileImage = user.PROFILE_IMAGE && user.PROFILE_IMAGE.trim() !== '' ? 
                    user.PROFILE_IMAGE : 'uploads/profiles/default.png';
                
                const userItem = document.createElement('div');
                userItem.className = 'user-item';
                userItem.dataset.userId = userId;
                
                userItem.innerHTML = `
                    <div class="user-avatar">
                        <img src="${profileImage}" alt="${userName}">
                    </div>
                    <div class="user-info">
                        <div class="user-name">${userName}</div>
                        <div class="user-status">Click to start chatting</div>
                    </div>
                `;
                
                userItem.addEventListener('click', function() {
                    if (userId) {
                        startNewConversation(userId);
                    }
                });
                
                usersList.appendChild(userItem);
            });
        }
        
        // Start a new conversation
        function startNewConversation(userId) {
            closeModal();
            
            // Check if conversation already exists
            const existingConversation = document.querySelector(`.conversation-item[data-user-id="${userId}"]`);
            if (existingConversation) {
                existingConversation.click();
                return;
            }
            
            // Load the conversation directly
            loadConversation(userId);
        }
        
        // Load conversation with a user
        function loadConversation(userId) {
            // Fetch user details first
            const formData = new FormData();
            formData.append('action', 'get_user_details');
            formData.append('user_id', userId);
            
            fetch('chat.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.user) {
                    const user = data.user;
                    // Load active conversation with user data
                    loadActiveConversation(user.USER_ID, user.NAME, user.PROFILE_IMAGE || 'uploads/profiles/default.png');
                } else {
                    console.error('Error loading user details:', data.message || 'Unknown error');
                }
            })
            .catch(error => {
                console.error('Error loading conversation:', error);
            });
        }
        
        // Helper function: Debounce function to limit how often a function can be called
        function debounce(func, delay) {
            let timeout;
            return function() {
                const context = this;
                const args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(context, args), delay);
            };
        }
        
        // Open image in fullscreen
        function openFullscreenImage(src) {
            const fullscreenDiv = document.createElement('div');
            fullscreenDiv.className = 'fullscreen-image';
            fullscreenDiv.innerHTML = `<img src="${src}" alt="Fullscreen Image">`;
            fullscreenDiv.addEventListener('click', function() {
                document.body.removeChild(fullscreenDiv);
            });
            document.body.appendChild(fullscreenDiv);
        }
        
        // Function to update user online status
        function updateUserStatus() {
            // Fetch online users
            const formData = new FormData();
            formData.append('action', 'get_online_users');
            
            fetch('chat.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update status for all conversation items
                    document.querySelectorAll('.conversation-item').forEach(item => {
                        const userId = item.dataset.userId;
                        const statusElement = item.querySelector('.conversation-status');
                        
                        if (statusElement) {
                            if (data.online_users && data.online_users.includes(parseInt(userId))) {
                                statusElement.innerHTML = '<i class="fas fa-circle status-indicator status-online"></i> <span class="status-text">Online</span>';
                                statusElement.classList.add('online');
                            } else {
                                const lastSeen = data.last_seen && data.last_seen[userId] ? data.last_seen[userId] : 'a while ago';
                                statusElement.innerHTML = '<i class="fas fa-circle status-indicator status-offline"></i> <span class="status-text">Last seen ' + lastSeen + '</span>';
                                statusElement.classList.remove('online');
                            }
                        }
                    });
                    
                    // Update active conversation status if open
                    if (activeConversationUserId) {
                        const statusElement = document.querySelector('.conversation-header-info .conversation-status');
                        if (statusElement) {
                            if (data.online_users && data.online_users.includes(parseInt(activeConversationUserId))) {
                                statusElement.innerHTML = '<i class="fas fa-circle status-indicator status-online"></i> <span class="status-text">Online</span>';
                                statusElement.classList.add('online');
                            } else {
                                const lastSeen = data.last_seen && data.last_seen[activeConversationUserId] ? data.last_seen[activeConversationUserId] : 'a while ago';
                                statusElement.innerHTML = '<i class="fas fa-circle status-indicator status-offline"></i> <span class="status-text">Last seen ' + lastSeen + '</span>';
                                statusElement.classList.remove('online');
                            }
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error fetching online status:', error);
            });
            
            // Update current user's online status
            updateUserActivity();
        }
        
        // Function to update the current user's activity timestamp
        function updateUserActivity() {
            const formData = new FormData();
            formData.append('action', 'update_user_activity');
            
            fetch('chat.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    console.error('Failed to update user activity:', data.message);
                }
            })
            .catch(error => {
                console.error('Error updating user activity:', error);
            });
        }

        // (Old 30s status timer removed — the unified adaptive poller below
        //  refreshes presence + online status as part of each poll.)
        
        // FIXED: Handle window resize for mobile responsiveness
        window.addEventListener('resize', function() {
            // Close mobile conversations list when resizing to desktop
            if (window.innerWidth > 767) {
                conversationsList.classList.remove('mobile-open');
                mobileOverlay.classList.remove('active');
            }
            
            // Close sidebar when resizing to desktop
            if (window.innerWidth > 991) {
                const sidebar = document.querySelector('.sidebar');
                const sidebarOverlay = document.getElementById('sidebar-overlay');
                if (sidebar && sidebarOverlay) {
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                }
            }
        });

/* ── Unified adaptive chat engine (refactor) ──────────────────────────────────
 * ONE visibility-aware poller replaces the old 3s + 30s timers. It backs off
 * when idle, pauses when the tab is hidden, and returns conversations + new
 * messages + presence in a single request — so deployments aren't flooded. */
(function () {
  "use strict";
  const cfg = window.ChatConfig || {};
  const ME = cfg.currentUserId;
  const DEF = cfg.defaultAvatar || "uploads/profiles/default.png";

  let conversations = Array.isArray(cfg.initialConversations) ? cfg.initialConversations : [];
  let activeCategory = "primary";
  let searchTerm = "";

  const FAST = 3000, SLOW = 15000;
  let interval = FAST;
  let inFlight = false;
  let pollTimer = null;

  const itemsEl = () => document.getElementById("conversation-items");
  const reqBadge = () => document.getElementById("requests-badge");

  // Attribute-safe HTML escaper: also encodes " and ' so values placed in
  // attribute contexts (data-*="...", aria-label="...") can't break out → no XSS.
  function esc(x) {
    return String(x == null ? "" : x)
      .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
  }

  // Prefer the server epoch (sent_ts) — timezone-safe — over the raw datetime.
  function relTime(c) {
    let secs;
    if (c && c.sent_ts) {
      secs = Math.floor(Date.now() / 1000) - c.sent_ts;
    } else if (c && c.sent_at) {
      const t = new Date(String(c.sent_at).replace(" ", "T"));
      if (isNaN(t.getTime())) return "";
      secs = (Date.now() - t.getTime()) / 1000;
    } else return "";
    if (secs < 0) secs = 0;
    if (secs < 60) return "now";
    if (secs < 3600) return Math.floor(secs / 60) + "m";
    if (secs < 86400) return Math.floor(secs / 3600) + "h";
    if (secs < 604800) return Math.floor(secs / 86400) + "d";
    return new Date((c.sent_ts ? c.sent_ts * 1000 : Date.now())).toLocaleDateString([], { month: "short", day: "numeric" });
  }

  function renderConversations() {
    const host = itemsEl();
    if (!host) return;

    const reqUnread = conversations
      .filter((c) => c.category === "requests")
      .reduce((n, c) => n + (c.unread || 0), 0);
    const rb = reqBadge();
    if (rb) {
      if (reqUnread > 0) {
        const grew = Number(rb.textContent) < reqUnread;
        rb.style.display = "inline-flex";
        rb.textContent = reqUnread;
        // pop animation when the count increases
        if (grew) { rb.classList.remove("pop"); void rb.offsetWidth; rb.classList.add("pop"); }
      } else rb.style.display = "none";
    }

    const term = searchTerm.toLowerCase();
    const list = conversations.filter(
      (c) =>
        c.category === activeCategory &&
        (!term || (c.name || "").toLowerCase().includes(term) || (c.preview || "").toLowerCase().includes(term))
    );

    if (!list.length) {
      const ico = activeCategory === "requests" ? "fa-inbox" : "fa-comment-dots";
      const txt = activeCategory === "requests" ? "No message requests" : "No conversations yet";
      host.innerHTML = '<div class="conv-empty"><i class="conv-empty-ico fas ' + ico + '"></i><span>' + txt + "</span></div>";
      return;
    }

    host.innerHTML = list
      .map((c) => {
        const img = c.profile_image || DEF;
        const unread = c.unread > 0 ? '<span class="conv-unread">' + (c.unread > 9 ? "9+" : c.unread) + "</span>" : "";
        const dot = c.online ? '<span class="conv-online"></span>' : "";
        const prefix = c.outgoing ? "You: " : "";
        const cls = "conversation-item" + (c.unread > 0 ? " is-unread" : "") + (String(c.user_id) === String(window.__activeUserId) ? " active" : "");
        return (
          '<div class="' + cls + '" role="button" tabindex="0" aria-label="Open chat with ' + esc(c.name) + '" data-user-id="' + c.user_id + '" data-user-name="' + esc(c.name) + '" data-user-image="' + esc(img) + '">' +
          '<div class="conversation-avatar">' + dot + '<img src="' + esc(img) + "\" onerror=\"this.src='" + DEF + "'\" alt=\"\"></div>" +
          '<div class="conversation-info"><div class="conversation-header">' +
          '<div class="conversation-name">' + esc(c.name) + "</div>" +
          '<div class="conversation-time">' + relTime(c) + "</div></div>" +
          '<div class="conversation-last-message">' + esc(prefix + (c.preview || "")) + "</div></div>" +
          unread + "</div>"
        );
      })
      .join("");
  }

  function openConversation(userId, name, image) {
    window.__activeUserId = String(userId);
    window.__activeGroupId = null; // leaving any open group
    window.__lastMessageId = 0;
    if (typeof loadActiveConversation === "function") loadActiveConversation(userId, name, image);
    if (window.ChatEnhance) window.ChatEnhance.onScopeChange();
    const c = conversations.find((x) => String(x.user_id) === String(userId));
    if (c) c.unread = 0;
    renderConversations();
    fetch("chat.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: "action=mark_read&user_id=" + encodeURIComponent(userId),
    }).catch(() => {});
  }

  function applyPoll(data) {
    if (!data || !data.success) return;
    if (Array.isArray(data.conversations)) { conversations = data.conversations; renderConversations(); }

    // Facebook-style presence in the open conversation header.
    if (data.active_status && window.__activeUserId) {
      const st = document.querySelector(".conversation-header-info .conversation-status");
      if (st) {
        if (data.active_status.online) {
          st.classList.add("online");
          st.innerHTML = '<i class="fas fa-circle status-indicator status-online"></i> <span class="status-text">Active now</span>';
        } else {
          st.classList.remove("online");
          const seen = data.active_status.last_seen ? "Active " + data.active_status.last_seen : "Offline";
          st.innerHTML = '<i class="fas fa-circle status-indicator status-offline"></i> <span class="status-text">' + seen + "</span>";
        }
      }
    }

    if (window.__activeUserId && Array.isArray(data.messages) && data.messages.length) {
      let appended = false;
      data.messages.forEach((m) => {
        const partner = String(m.SENDER_ID) === String(ME) ? String(m.RECEIVER_ID) : String(m.SENDER_ID);
        if (partner === String(window.__activeUserId) && typeof appendMessage === "function") {
          if (!document.querySelector('.message[data-message-id="' + m.MESSAGE_ID + '"]')) { appendMessage(m); appended = true; }
        }
      });
      if (data.last_message_id) window.__lastMessageId = data.last_message_id;
      if (appended) { const mc = document.getElementById("messages-container"); if (mc) mc.scrollTop = mc.scrollHeight; }
    }

    // P3: typing (DM or group — backend scopes to the open conversation) + seen.
    if (window.ChatEnhance) {
      window.ChatEnhance.applyTyping(Array.isArray(data.typing) ? data.typing : []);
      window.ChatEnhance.applySeen(typeof data.seen === "undefined" ? 0 : data.seen);
    }
  }

  function pollNow() {
    if (inFlight || document.hidden) { schedule(); return; }
    inFlight = true;
    const body =
      "action=poll" +
      (window.__activeUserId ? "&active_user_id=" + encodeURIComponent(window.__activeUserId) : "") +
      (window.__activeGroupId ? "&active_group_id=" + encodeURIComponent(window.__activeGroupId) : "") +
      "&last_message_id=" + encodeURIComponent(window.__lastMessageId || 0);
    fetch("chat.php", { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body })
      .then((r) => r.json())
      .then((data) => {
        const had = data && data.messages && data.messages.length > 0;
        applyPoll(data);
        // Broadcast the full poll payload so the groups/calls module can react
        // (groups list, group messages, incoming calls) — no extra requests.
        document.dispatchEvent(new CustomEvent("chat:poll", { detail: data }));
        interval = had ? FAST : Math.min(SLOW, interval + 3000); // back off when quiet
      })
      .catch(() => { interval = Math.min(SLOW, interval + 3000); })
      .finally(() => { inFlight = false; schedule(); });
  }

  function schedule() {
    clearTimeout(pollTimer);
    if (document.hidden) return; // paused while the tab is hidden — no flood
    pollTimer = setTimeout(pollNow, interval);
  }

  function start() {
    renderConversations();

    const host = itemsEl();
    const openFromItem = (it) => {
      if (!it) return;
      openConversation(it.dataset.userId, it.dataset.userName, it.dataset.userImage);
      if (window.innerWidth <= 767) {
        const cl = document.getElementById("conversations-list");
        const ov = document.getElementById("mobile-overlay");
        cl && cl.classList.remove("mobile-open");
        ov && ov.classList.remove("active");
      }
    };
    if (host) {
      host.addEventListener("click", (e) => openFromItem(e.target.closest(".conversation-item")));
      // Keyboard: rows are role="button" tabindex="0" — Enter/Space activate them.
      host.addEventListener("keydown", (e) => {
        if (e.key !== "Enter" && e.key !== " ") return;
        const it = e.target.closest(".conversation-item");
        if (!it) return;
        e.preventDefault();
        openFromItem(it);
      });
    }

    const tabs = document.getElementById("conversation-tabs");
    if (tabs) tabs.addEventListener("click", (e) => {
      const b = e.target.closest(".conv-tab");
      if (!b) return;
      tabs.querySelectorAll(".conv-tab").forEach((x) => { x.classList.remove("active"); x.setAttribute("aria-selected", "false"); });
      b.classList.add("active");
      b.setAttribute("aria-selected", "true");
      activeCategory = b.dataset.category;
      renderConversations();
    });

    const search = document.getElementById("search-conversations");
    if (search) search.addEventListener("input", function () { searchTerm = this.value || ""; renderConversations(); });

    const roundBtn = document.getElementById("new-chat-round");
    if (roundBtn && typeof openNewMessageModal === "function") roundBtn.addEventListener("click", openNewMessageModal);

    document.addEventListener("visibilitychange", () => {
      if (!document.hidden) { interval = FAST; pollNow(); } else clearTimeout(pollTimer);
    });

    window.__chatPollNow = function () { interval = FAST; if (!inFlight) pollNow(); };

    pollNow();
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", start);
  else start();
})();

/* ── Messaging enhancements P1–P4 (window.ChatEnhance) ─────────────────────────
 * Shared client logic for: pinned messages (P1), edit/delete + edited/tombstone
 * markers (P2), typing + seen receipts (P3), in-message search (P4). All AJAX is
 * form-encoded POST to chat.php (CSRF header auto-attached by csrf.js). Consumed
 * by chat.js (DM render + applyPoll) and chat-overhaul.js (group render).
 * ============================================================================*/
(function () {
  "use strict";
  var cfg = window.ChatConfig || {};
  var ME = String(cfg.currentUserId);
  var DEF = cfg.defaultAvatar || "uploads/profiles/default.png";

  var pinnedIds = new Set();   // MESSAGE_IDs pinned in the OPEN scope
  var lastTypingTs = 0;
  var menu = null;             // single floating actions menu

  // Attribute-safe HTML escaper (encodes " and ' too) — used in attribute contexts.
  function esc(x) {
    return String(x == null ? "" : x)
      .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
  }
  function truthy(v) { return v === 1 || v === "1" || v === true; }
  function el(id) { return document.getElementById(id); }
  function mcEl() { return el("messages-container"); }

  function post(body) {
    return fetch("chat.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: body,
    }).then(function (r) { return r.json(); });
  }

  // ── Scope helpers (server derives CONV_KEY from these) ──────────────────────
  function activeGid() { return window.__activeGroupId ? String(window.__activeGroupId) : ""; }
  function activeUid() { return window.__activeUserId ? String(window.__activeUserId) : ""; }
  function hasScope() { return !!(activeGid() || activeUid()); }
  function scopeBody() {
    if (activeGid()) return "group_id=" + encodeURIComponent(activeGid());
    if (activeUid()) return "receiver_id=" + encodeURIComponent(activeUid());
    return "";
  }

  function fmtTime(s) {
    if (!s) return "";
    var t = new Date(String(s).replace(" ", "T"));
    if (isNaN(t.getTime())) return "";
    return t.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
  }

  // ── P1/P2: decorate a freshly rendered message ──────────────────────────────
  function decorateMessage(node, m, mine) {
    if (!node || !m) return;
    var id = String(m.MESSAGE_ID);
    node.dataset.senderId = m.SENDER_ID;
    var bubble = node.querySelector(".message-bubble");

    if (truthy(m.IS_DELETED)) { renderTombstone(node, bubble); return; }

    // Edited marker (EDITED_AT non-null ⇒ "edited").
    var ea = m.EDITED_AT;
    var edited = ea != null && ea !== "" && ea !== "0000-00-00 00:00:00";
    if (edited && bubble && !bubble.querySelector(".edited-marker")) {
      var mk = document.createElement("span");
      mk.className = "edited-marker";
      mk.textContent = "edited";
      bubble.appendChild(mk);
    }

    if (pinnedIds.has(id)) node.classList.add("is-pinned");

    // Per-message actions kebab.
    if (!node.querySelector(".msg-actions")) {
      var b = document.createElement("button");
      b.type = "button";
      b.className = "msg-actions";
      b.setAttribute("aria-label", "Message actions");
      b.dataset.mine = mine ? "1" : "0";
      b.innerHTML = '<i class="fas fa-ellipsis-h"></i>';
      node.appendChild(b);
    }
  }

  function renderTombstone(node, bubble) {
    node.classList.add("is-deleted");
    if (bubble) bubble.innerHTML = '<span class="message-tombstone"><i class="fas fa-ban"></i> This message was deleted</span>';
    var a = node.querySelector(".msg-actions"); if (a) a.remove();
    var r = node.querySelector(".message-reactions"); if (r) r.remove();
  }

  function markPinnedState(id) {
    if (!id) return;
    var node = document.querySelector('#messages-container .message[data-message-id="' + id + '"]');
    if (node) node.classList.toggle("is-pinned", pinnedIds.has(String(id)));
  }

  // ── Floating actions menu ───────────────────────────────────────────────────
  function ensureMenu() {
    if (menu) return menu;
    menu = document.createElement("div");
    menu.className = "msg-menu";
    menu.id = "msg-menu";
    document.body.appendChild(menu);
    menu.addEventListener("click", function (e) {
      var b = e.target.closest("button"); if (!b) return;
      var act = b.dataset.act;
      var id = menu.dataset.messageId;
      if (act === "pin") { togglePin(id, menu.dataset.pinned === "1"); closeMenu(); }
      else if (act === "edit") { closeMenu(); startEdit(id); }
      else if (act === "delete") { renderDeleteConfirm(id); }
      else if (act === "confirm-delete") { closeMenu(); doDelete(id); }
      else if (act === "cancel") { closeMenu(); }
    });
    return menu;
  }

  function openMenuFor(btn) {
    ensureMenu();
    var node = btn.closest(".message"); if (!node) return;
    var id = node.dataset.messageId;
    var mine = btn.dataset.mine === "1";
    var pinned = pinnedIds.has(String(id));
    var html = '<button data-act="pin"><i class="fas fa-thumbtack"></i> ' + (pinned ? "Unpin" : "Pin") + "</button>";
    if (mine) {
      html += '<button data-act="edit"><i class="fas fa-pen"></i> Edit</button>';
      html += '<button class="danger" data-act="delete"><i class="fas fa-trash"></i> Delete</button>';
    }
    menu.innerHTML = html;
    menu.dataset.messageId = id;
    menu.dataset.pinned = pinned ? "1" : "0";
    positionMenu(btn);
    btn.classList.add("open");
  }

  function renderDeleteConfirm(id) {
    if (!menu) return;
    menu.innerHTML =
      '<div style="padding:7px 12px;font-size:13px;color:var(--tx-muted)">Delete this message?</div>' +
      '<button data-act="cancel"><i class="fas fa-times"></i> Cancel</button>' +
      '<button class="danger" data-act="confirm-delete"><i class="fas fa-trash"></i> Delete</button>';
    menu.dataset.messageId = id;
  }

  function positionMenu(btn) {
    var r = btn.getBoundingClientRect();
    menu.style.visibility = "hidden";
    menu.classList.add("open");
    var mw = menu.offsetWidth, mh = menu.offsetHeight;
    var left = r.left, top = r.bottom + 6;
    if (left + mw > window.innerWidth - 8) left = window.innerWidth - mw - 8;
    if (top + mh > window.innerHeight - 8) top = r.top - mh - 6;
    if (left < 8) left = 8;
    if (top < 8) top = 8;
    menu.style.left = left + "px";
    menu.style.top = top + "px";
    menu.style.visibility = "visible";
  }

  function closeMenu() {
    if (menu) menu.classList.remove("open");
    document.querySelectorAll(".msg-actions.open").forEach(function (b) { b.classList.remove("open"); });
  }

  // ── P1: pin / unpin / list ──────────────────────────────────────────────────
  function togglePin(id, pinned) {
    if (!hasScope() || !id) return;
    var action = pinned ? "unpin_message" : "pin_message";
    post("action=" + action + "&message_id=" + encodeURIComponent(id) + "&" + scopeBody()).then(function (data) {
      if (!data || !data.success) return;
      if (data.pinned) pinnedIds.add(String(id)); else pinnedIds.delete(String(id));
      markPinnedState(id);
      if (!panelHidden()) loadPins(true);
    }).catch(function () {});
  }

  function panelHidden() { var p = el("pinned-panel"); return !p || p.hasAttribute("hidden"); }

  function loadPins(render) {
    if (!hasScope()) return;
    post("action=list_pins&" + scopeBody()).then(function (data) {
      if (!data || !data.success) return;
      var pins = data.pins || [];
      pinnedIds = new Set(pins.map(function (p) { return String(p.MESSAGE_ID); }));
      document.querySelectorAll("#messages-container .message").forEach(function (n) { markPinnedState(n.dataset.messageId); });
      if (render) renderPins(pins);
    }).catch(function () {});
  }

  function renderPins(pins) {
    var host = el("pinned-list"); if (!host) return;
    if (!pins.length) { host.innerHTML = '<div class="pinned-empty">No pinned messages yet. Open a message menu and choose Pin.</div>'; return; }
    host.innerHTML = pins.map(function (p) {
      var img = p.SENDER_IMAGE || DEF;
      var snippet;
      if (truthy(p.IS_DELETED)) snippet = "This message was deleted";
      else if (p.CONTENT) snippet = p.CONTENT;
      else if (p.IMAGE_URL) snippet = "📎 Attachment";
      else snippet = "(no text)";
      var ed = (p.EDITED_AT != null && p.EDITED_AT !== "") ? ' <span class="edited-marker">edited</span>' : "";
      return '<div class="pinned-item" role="button" tabindex="0" data-mid="' + p.MESSAGE_ID + '">' +
        '<img class="pin-avatar" src="' + esc(img) + "\" onerror=\"this.src='" + DEF + "'\" alt=\"\">" +
        '<div class="pin-body"><div class="pin-name">' + esc(p.SENDER_NAME || "User") + "</div>" +
        '<div class="pin-snippet">' + esc(snippet) + ed + "</div>" +
        '<div class="pin-time">' + fmtTime(p.SENT_AT) + "</div></div>" +
        '<button class="pin-unpin" type="button" data-mid="' + p.MESSAGE_ID + '" title="Unpin" aria-label="Unpin message"><i class="fas fa-thumbtack"></i></button>' +
        "</div>";
    }).join("");
  }

  // ── P2: edit ────────────────────────────────────────────────────────────────
  function startEdit(id) {
    var node = document.querySelector('#messages-container .message[data-message-id="' + id + '"]');
    if (!node) return;
    var bubble = node.querySelector(".message-bubble"); if (!bubble) return;
    if (bubble.querySelector(".msg-inline-editor")) return;
    var textEl = bubble.querySelector(".message-text");
    var current = textEl ? textEl.textContent : "";
    var backup = bubble.innerHTML;

    var editor = document.createElement("div");
    editor.className = "msg-inline-editor";
    editor.innerHTML =
      "<textarea></textarea>" +
      '<div class="edit-row"><button type="button" class="edit-cancel">Cancel</button>' +
      '<button type="button" class="edit-save">Save</button></div>';
    bubble.innerHTML = "";
    bubble.appendChild(editor);
    var ta = editor.querySelector("textarea");
    ta.value = current;
    ta.focus();
    ta.setSelectionRange(ta.value.length, ta.value.length);

    function cancel() { bubble.innerHTML = backup; }
    function save() {
      var val = ta.value.trim();
      if (!val) { ta.focus(); return; }
      post("action=edit_message&message_id=" + encodeURIComponent(id) + "&content=" + encodeURIComponent(val)).then(function (data) {
        if (!data || !data.success) { alert((data && data.message) || "Could not edit message."); bubble.innerHTML = backup; return; }
        bubble.innerHTML = backup;
        var t = bubble.querySelector(".message-text");
        if (!t) { t = document.createElement("div"); t.className = "message-text"; bubble.insertBefore(t, bubble.firstChild); }
        t.textContent = (typeof data.content === "string") ? data.content : val;
        if (!bubble.querySelector(".edited-marker")) {
          var mk = document.createElement("span"); mk.className = "edited-marker"; mk.textContent = "edited"; bubble.appendChild(mk);
        }
      }).catch(function () { bubble.innerHTML = backup; });
    }
    editor.querySelector(".edit-cancel").addEventListener("click", cancel);
    editor.querySelector(".edit-save").addEventListener("click", save);
    ta.addEventListener("keydown", function (e) {
      if (e.key === "Escape") { e.preventDefault(); cancel(); }
      else if (e.key === "Enter" && (e.ctrlKey || e.metaKey)) { e.preventDefault(); save(); }
    });
  }

  // ── P2: delete ────────────────────────────────────────────────────────────────
  function doDelete(id) {
    post("action=delete_message&message_id=" + encodeURIComponent(id)).then(function (data) {
      if (!data || !data.success) { alert((data && data.message) || "Could not delete message."); return; }
      pinnedIds.delete(String(id));
      var node = document.querySelector('#messages-container .message[data-message-id="' + id + '"]');
      if (node) renderTombstone(node, node.querySelector(".message-bubble"));
      if (!panelHidden()) loadPins(true);
    }).catch(function () {});
  }

  // ── P3: typing heartbeat (throttled) ────────────────────────────────────────
  function sendTyping() {
    if (!hasScope()) return;
    var inp = el("message-input");
    if (inp && !inp.value.trim()) return;
    var now = Date.now();
    if (now - lastTypingTs < 2500) return;
    lastTypingTs = now;
    post("action=set_typing&" + scopeBody()).catch(function () {});
  }

  // ── P3: apply typing array from the poll ────────────────────────────────────
  function applyTyping(arr) {
    var ind = el("typing-indicator");
    var info = document.querySelector(".conversation-header-info");
    if (!ind || !info) return;
    if (!hasScope() || !arr || !arr.length) { ind.hidden = true; info.classList.remove("is-typing"); return; }
    var names = arr.map(function (t) { return t.NAME || "Someone"; });
    var text;
    if (names.length === 1) text = activeGid() ? (names[0] + " is typing…") : "typing…";
    else if (names.length === 2) text = names[0] + " and " + names[1] + " are typing…";
    else text = names.length + " people are typing…";
    var tt = ind.querySelector(".typing-text");
    if (tt) tt.textContent = text;
    ind.hidden = false;
    info.classList.add("is-typing");
  }

  // ── P3: apply seen cursor (DM only) ─────────────────────────────────────────
  function applySeen(seenId) {
    var old = document.querySelector(".seen-label");
    var sid = Number(seenId) || 0;
    if (!activeUid() || sid <= 0) { if (old) old.remove(); return; }
    var node = document.querySelector('#messages-container .message[data-message-id="' + sid + '"]');
    if (!node || !node.classList.contains("message-sent")) { if (old) old.remove(); return; }
    if (old && old.dataset.forId === String(sid)) return;
    if (old) old.remove();
    var lab = document.createElement("div");
    lab.className = "seen-label";
    lab.dataset.forId = String(sid);
    lab.innerHTML = '<i class="fas fa-check-circle"></i> Seen';
    node.insertAdjacentElement("afterend", lab);
  }

  // ── P4: search within messages ──────────────────────────────────────────────
  function highlight(content, q) {
    var safe = esc(content);
    if (!q) return safe;
    try {
      var re = new RegExp("(" + q.replace(/[.*+?^${}()|[\]\\]/g, "\\$&") + ")", "ig");
      return safe.replace(re, "<mark>$1</mark>");
    } catch (e) { return safe; }
  }

  function renderSearchResults(rows, q) {
    var host = el("message-search-results"); if (!host) return;
    if (!rows.length) { host.hidden = false; host.innerHTML = '<div class="msr-head">Messages</div><div class="msr-empty">No messages found</div>'; return; }
    var html = '<div class="msr-head">Messages</div>';
    rows.forEach(function (r) {
      var peer = String(r.SENDER_ID) === ME ? r.RECEIVER_ID : r.SENDER_ID;
      var img = r.SENDER_IMAGE || DEF;
      html += '<div class="msr-item" role="button" tabindex="0" data-mid="' + r.MESSAGE_ID + '" data-gid="' + (r.GROUP_ID || "") + '" data-uid="' + (peer || "") + '">' +
        '<img src="' + esc(img) + "\" onerror=\"this.src='" + DEF + "'\" alt=\"\">" +
        '<div class="msr-body"><div class="msr-top"><span class="msr-name">' + esc(r.SENDER_NAME || "User") + "</span>" +
        '<span class="msr-time">' + fmtTime(r.SENT_AT) + "</span></div>" +
        '<div class="msr-snippet">' + highlight(r.CONTENT || "(attachment)", q) + "</div></div></div>";
    });
    host.innerHTML = html;
    host.hidden = false;
  }

  function runSearch() {
    var inp = el("search-conversations");
    var host = el("message-search-results");
    if (!inp || !host) return;
    var q = (inp.value || "").trim();
    if (q.length < 2) { host.hidden = true; host.innerHTML = ""; return; }
    post("action=search_messages&q=" + encodeURIComponent(q)).then(function (data) {
      if (!data || !data.success) { host.hidden = true; return; }
      renderSearchResults(data.results || [], q);
    }).catch(function () {});
  }

  function openSearchHit(item) {
    var mid = item.dataset.mid, gid = item.dataset.gid, uid = item.dataset.uid;
    var host = el("message-search-results"); if (host) { host.hidden = true; }
    var inp = el("search-conversations"); if (inp) inp.value = "";
    if (gid) {
      var rail = document.querySelector('.rail-group[data-group-id="' + gid + '"]');
      if (rail) rail.click();
    } else if (uid) {
      var conv = document.querySelector('.conversation-item[data-user-id="' + uid + '"]');
      if (conv) conv.click();
      else if (typeof loadConversation === "function") loadConversation(uid);
    }
    jumpWhenReady(mid);
  }

  function jumpWhenReady(mid) {
    var tries = 0;
    var iv = setInterval(function () {
      tries++;
      var node = document.querySelector('#messages-container .message[data-message-id="' + mid + '"]');
      if (node) { clearInterval(iv); scrollToMessage(mid); }
      else if (tries > 24) clearInterval(iv);
    }, 150);
  }

  function scrollToMessage(id) {
    var node = document.querySelector('#messages-container .message[data-message-id="' + id + '"]');
    if (!node) return;
    node.scrollIntoView({ behavior: "smooth", block: "center" });
    node.classList.remove("msg-highlight");
    void node.offsetWidth;
    node.classList.add("msg-highlight");
    setTimeout(function () { node.classList.remove("msg-highlight"); }, 1800);
  }

  // ── Scope change (DM or group opened) ───────────────────────────────────────
  function onScopeChange() {
    pinnedIds = new Set();
    var p = el("pinned-panel"); if (p) p.setAttribute("hidden", "");
    closeMenu();
    var ind = el("typing-indicator"); if (ind) ind.hidden = true;
    var info = document.querySelector(".conversation-header-info"); if (info) info.classList.remove("is-typing");
    var sl = document.querySelector(".seen-label"); if (sl) sl.remove();
    lastTypingTs = 0;
    loadPins(false); // populate pinnedIds + mark messages for the new scope
  }

  function debounce(fn, ms) {
    var t;
    return function () { var a = arguments, c = this; clearTimeout(t); t = setTimeout(function () { fn.apply(c, a); }, ms); };
  }

  // ── Wire up ─────────────────────────────────────────────────────────────────
  function init() {
    ensureMenu();

    // P1: pinned panel toggle.
    var pinBtn = el("pinned-btn");
    if (pinBtn) pinBtn.addEventListener("click", function () {
      var p = el("pinned-panel"); if (!p) return;
      if (p.hasAttribute("hidden")) { p.removeAttribute("hidden"); pinBtn.classList.add("is-active"); loadPins(true); }
      else { p.setAttribute("hidden", ""); pinBtn.classList.remove("is-active"); }
    });
    var pinClose = el("pinned-close");
    if (pinClose) pinClose.addEventListener("click", function () {
      var p = el("pinned-panel"); if (p) p.setAttribute("hidden", "");
      if (pinBtn) pinBtn.classList.remove("is-active");
    });

    var pinList = el("pinned-list");
    if (pinList) {
      pinList.addEventListener("click", function (e) {
        var unpin = e.target.closest(".pin-unpin");
        if (unpin) { e.stopPropagation(); togglePin(unpin.dataset.mid, true); return; }
        var item = e.target.closest(".pinned-item");
        if (item) scrollToMessage(item.dataset.mid);
      });
      pinList.addEventListener("keydown", function (e) {
        if (e.key !== "Enter" && e.key !== " ") return;
        var item = e.target.closest(".pinned-item");
        if (item) { e.preventDefault(); scrollToMessage(item.dataset.mid); }
      });
    }

    // P2: open the per-message actions menu.
    var mc = mcEl();
    if (mc) {
      mc.addEventListener("click", function (e) {
        var btn = e.target.closest(".msg-actions");
        if (!btn) return;
        e.stopPropagation();
        if (btn.classList.contains("open")) { closeMenu(); return; }
        closeMenu();
        openMenuFor(btn);
      });
      mc.addEventListener("scroll", closeMenu, { passive: true });
    }
    document.addEventListener("click", function (e) {
      if (!e.target.closest(".msg-menu") && !e.target.closest(".msg-actions")) closeMenu();
    });
    window.addEventListener("resize", closeMenu);

    // P3: typing heartbeat on keystroke.
    var inp = el("message-input");
    if (inp) inp.addEventListener("input", sendTyping);

    // P4: in-message search (additive — conv-list filtering stays in chat.js).
    var search = el("search-conversations");
    if (search) search.addEventListener("input", debounce(runSearch, 300));
    var results = el("message-search-results");
    if (results) {
      results.addEventListener("click", function (e) { var it = e.target.closest(".msr-item"); if (it) openSearchHit(it); });
      results.addEventListener("keydown", function (e) {
        if (e.key !== "Enter" && e.key !== " ") return;
        var it = e.target.closest(".msr-item"); if (it) { e.preventDefault(); openSearchHit(it); }
      });
    }
  }

  window.ChatEnhance = {
    decorateMessage: decorateMessage,
    applyTyping: applyTyping,
    applySeen: applySeen,
    onScopeChange: onScopeChange,
    scrollToMessage: scrollToMessage,
  };

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
  else init();
})();
