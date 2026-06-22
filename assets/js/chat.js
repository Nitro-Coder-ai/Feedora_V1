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
            
            // New message button handler
            newMessageBtn.addEventListener('click', openNewMessageModal);
            
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
        
        // Start message polling
        function startMessagePolling() {
            // Clear any existing polling interval
            if (messagePollingInterval) {
                clearInterval(messagePollingInterval);
            }
            
            // Set up polling for new messages
            messagePollingInterval = setInterval(function() {
                if (lastMessageTimestamp > 0) {
                    loadMessages(lastMessageTimestamp);
                }
            }, 3000); // Poll every 3 seconds
        }
        
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
            
            // Add message image if present
            if (message.IMAGE_URL) {
                const imageContainer = document.createElement('div');
                imageContainer.className = 'message-image';
                imageContainer.innerHTML = `<img src="${message.IMAGE_URL}" alt="Message image" onclick="openFullscreenImage('${message.IMAGE_URL}')">`;
                
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
            if (!activeConversationUserId) return;
            
            const content = messageInput.value.trim();
            if (!content && !selectedFile) return;
            
            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('receiver_id', activeConversationUserId);
            
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
                    
                    // Fetch latest messages including the one we just sent
                    loadMessages();
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
        
        // Handle image upload with improved preview
        function handleImageUpload(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            // Check file type
            const validImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!validImageTypes.includes(file.type)) {
                alert('Please select a valid image file (JPEG, PNG, GIF, WEBP)');
                return;
            }
            
            // Check file size (5MB max)
            if (file.size > 5 * 1024 * 1024) {
                alert('Image size should be less than 5MB');
                return;
            }
            
            // Store selected file
            selectedFile = file;
            
            // Show image preview with animation
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImage.src = e.target.result;
                imagePreview.style.display = 'block';
                imagePreview.style.opacity = '0';
                setTimeout(() => {
                    imagePreview.style.opacity = '1';
                }, 10);
            };
            reader.readAsDataURL(file);
            
            // Focus back to message input
            messageInput.focus();
        }
        
        // Remove selected image
        function removeImage() {
            selectedFile = null;
            messageImageInput.value = '';
            imagePreview.style.display = 'none';
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

        // Update user status every 30 seconds
        setInterval(updateUserStatus, 30000);
        
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
