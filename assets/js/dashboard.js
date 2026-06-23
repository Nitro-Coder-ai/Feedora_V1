/* Extracted from dashboard.php inline <script> (refactor). PHP values via window.DashboardConfig. */

// XSS guard: escape user-controlled values before they enter innerHTML.
function escapeHtml(value) {
  if (value === null || value === undefined) return "";
  return String(value).replace(/[&<>"']/g, function (c) {
    return {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;",
    }[c];
  });
}

// Enhanced JavaScript for dashboard functionality with fixed reactions
document.addEventListener("DOMContentLoaded", function () {
  // Enhanced reaction handling function with better feedback
  window.handleReaction = function (postId, reactionType) {
    // Add loading state to the clicked reaction
    const clickedReactionIcon = document.querySelector(
      `[data-post-id="${postId}"] [data-reaction="${reactionType}"]`,
    );
    if (clickedReactionIcon) {
      clickedReactionIcon.style.opacity = "0.6";
      clickedReactionIcon.style.pointerEvents = "none";
    }

    const formData = new FormData();
    formData.append("action", "add_reaction");
    formData.append("post_id", postId);
    formData.append("reaction_type", reactionType);

    fetch("post_reaction.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          // Update the UI with new reaction data
          updateReactionUI(
            postId,
            data.reaction_data,
            reactionType,
            data.action_type,
          );

          // Hide the reaction panel
          const container = document.querySelector(
            `[data-post-id="${postId}"].post-likes-container`,
          );
          if (container) {
            container.classList.remove("active");
          }

          // Show appropriate message
          let message = "";
          switch (data.action_type) {
            case "added":
              message = `You reacted with ${reactionType}! ✨`;
              break;
            case "updated":
              message = `Changed reaction to ${reactionType}! 🔄`;
              break;
            case "removed":
              message = `Reaction removed! 👋`;
              break;
            default:
              message = "Reaction updated!";
          }
          showToast(message, "success");
        } else {
          showToast(data.message || "Error adding reaction", "error");
        }
      })
      .catch((error) => {
        console.error("Error handling reaction:", error);
        showToast("An error occurred while adding the reaction.", "error");
      })
      .finally(() => {
        // Remove loading state
        if (clickedReactionIcon) {
          clickedReactionIcon.style.opacity = "1";
          clickedReactionIcon.style.pointerEvents = "auto";
        }
      });
  };

  // Enhanced UI update function with better visual feedback
  function updateReactionUI(postId, reactionData, newReaction, actionType) {
    const container = document.querySelector(
      `[data-post-id="${postId}"].post-likes-container`,
    );
    if (!container) return;

    const likeButton = container.querySelector(".post-likes");
    const likeCount = container.querySelector(".like-count");
    const likeIcon = container.querySelector(".like-icon");

    // Update like button state based on user reaction
    if (reactionData.user_reaction) {
      likeButton.classList.add("active");
      likeButton.setAttribute("data-user-reaction", reactionData.user_reaction);
      if (likeIcon) {
        likeIcon.setAttribute("fill", "var(--primary-color)");
        likeIcon.setAttribute("stroke", "var(--primary-color)");
      }
    } else {
      likeButton.classList.remove("active");
      likeButton.setAttribute("data-user-reaction", "");
      if (likeIcon) {
        likeIcon.setAttribute("fill", "none");
        likeIcon.setAttribute("stroke", "currentColor");
      }
    }

    // Update reaction count with animation
    const totalReactions = reactionData.total_reactions;
    if (likeCount) {
      // Add a subtle animation to the count change
      likeCount.style.transform = "scale(1.1)";
      likeCount.style.transition = "transform 0.2s ease";

      setTimeout(() => {
        if (totalReactions > 0) {
          likeCount.textContent =
            totalReactions +
            (totalReactions === 1 ? " reaction" : " reactions");
        } else {
          likeCount.textContent = "0 reactions";
        }

        // Reset animation
        likeCount.style.transform = "scale(1)";
      }, 100);
    }

    // Add animation to the clicked reaction icon
    const clickedReactionIcon = container.querySelector(
      `[data-reaction="${newReaction}"]`,
    );
    if (clickedReactionIcon && actionType !== "removed") {
      clickedReactionIcon.classList.add("reaction-animation");

      // Create a floating emoji effect
      createFloatingEmoji(clickedReactionIcon, newReaction);

      setTimeout(() => {
        clickedReactionIcon.classList.remove("reaction-animation");
      }, 400);
    }

    // Update visual state of reaction icons
    const allReactionIcons = container.querySelectorAll(".reaction-icon");
    allReactionIcons.forEach((icon) => {
      const iconReaction = icon.getAttribute("data-reaction");
      if (iconReaction === reactionData.user_reaction) {
        icon.style.backgroundColor = "rgba(237, 90, 44, 0.2)";
        icon.style.transform = "scale(1.1)";
      } else {
        icon.style.backgroundColor = "";
        icon.style.transform = "";
      }
    });
  }

  // Create floating emoji animation effect
  function createFloatingEmoji(element, reactionType) {
    const emojiMap = {
      yummy: "🍔",
      delicious: "🍕",
      tasty: "🍰",
      love: "🍲",
      amazing: "🍗",
    };

    const emoji = emojiMap[reactionType];
    if (!emoji) return;

    const floatingEmoji = document.createElement("div");
    floatingEmoji.textContent = emoji;
    floatingEmoji.style.position = "absolute";
    floatingEmoji.style.fontSize = "20px";
    floatingEmoji.style.pointerEvents = "none";
    floatingEmoji.style.zIndex = "9999";
    floatingEmoji.style.animation = "floatUp 2s ease-out forwards";

    // Position relative to the clicked element
    const rect = element.getBoundingClientRect();
    floatingEmoji.style.left = rect.left + rect.width / 2 + "px";
    floatingEmoji.style.top = rect.top + window.scrollY + "px";

    document.body.appendChild(floatingEmoji);

    // Remove after animation
    setTimeout(() => {
      if (floatingEmoji.parentNode) {
        floatingEmoji.parentNode.removeChild(floatingEmoji);
      }
    }, 2000);
  }

  // Test function to debug modal
  window.testModal = function () {
    console.log("Testing modal...");
    const modal = document.getElementById("reactionUsersModal");
    const usersList = document.getElementById("reactionUsersList");

    console.log("Modal element:", modal);
    console.log("UsersList element:", usersList);

    if (modal && usersList) {
      usersList.innerHTML =
        '<p style="text-align: center; padding: 20px;">Test data loaded successfully!</p>';
      modal.style.display = "block";
      console.log("Modal should be visible now");
    } else {
      console.error("Modal elements not found");
    }
  };

  // FIXED: Enhanced function to show POST reaction users with correct modal
  window.showReactionUsers = function (postId) {
    console.log("🎯 showReactionUsers called with postId:", postId);

    const formData = new FormData();
    formData.append("action", "get_reaction_users");
    formData.append("post_id", postId);

    // FIXED: Use the correct modal ID for POST reactions
    const modal = document.getElementById("postReactionUsersModal");
    const usersList = document.getElementById("postReactionUsersList");

    console.log("Modal found:", !!modal, "UsersList found:", !!usersList);

    if (!modal) {
      console.error(
        "❌ postReactionUsersModal not found - Check your HTML structure",
      );
      // Fallback: try to create the modal dynamically
      createPostReactionModal();
      return showReactionUsers(postId); // Retry after creating modal
    }

    if (!usersList) {
      console.error("❌ postReactionUsersList not found");
      return;
    }

    function createPostReactionModal() {
      console.log("🔧 Creating missing post reaction modal...");

      const existingModal = document.getElementById("postReactionUsersModal");
      if (existingModal) return;

      const modalHTML = `
        <div id="postReactionUsersModal" class="modal" style="display: none;">
            <div class="modal-content" style="max-width: 500px; max-height: 70vh; overflow: hidden;">
                <span class="close-modal" style="cursor: pointer;">&times;</span>
                <h2 style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee;">People who reacted</h2>
                <div id="postReactionUsersList" class="reaction-users-list" style="max-height: 400px; overflow-y: auto;">
                    <!-- Post reaction users will be loaded here -->
                </div>
            </div>
        </div>
    `;

      document.body.insertAdjacentHTML("beforeend", modalHTML);

      // Add event listeners to the new modal
      const modal = document.getElementById("postReactionUsersModal");
      const closeBtn = modal.querySelector(".close-modal");

      closeBtn.addEventListener("click", function () {
        modal.style.display = "none";
      });

      modal.addEventListener("click", function (e) {
        if (e.target === modal) {
          modal.style.display = "none";
        }
      });

      console.log("✅ Post reaction modal created successfully");
    }

    // ENSURE this runs when DOM is loaded
    document.addEventListener("DOMContentLoaded", function () {
      // FIXED: Initialize post reaction modal handlers
      const postReactionModal = document.getElementById(
        "postReactionUsersModal",
      );
      const postReactionCloseBtn =
        postReactionModal?.querySelector(".close-modal");

      if (postReactionCloseBtn) {
        postReactionCloseBtn.addEventListener("click", function (e) {
          e.preventDefault();
          console.log("✅ Post reaction modal close button clicked");
          postReactionModal.style.display = "none";
        });
      }

      if (postReactionModal) {
        postReactionModal.addEventListener("click", function (event) {
          if (event.target === postReactionModal) {
            console.log("✅ Post reaction modal backdrop clicked");
            postReactionModal.style.display = "none";
          }
        });
      }

      // Check if modal exists, if not create it
      if (!postReactionModal) {
        console.log("⚠️ Post reaction modal not found, creating it...");
        createPostReactionModal();
      }

      console.log("✅ Post reaction system initialized");
    });
    // Show loading state
    usersList.innerHTML = `
        <div style="text-align: center; padding: 30px;">
            <div class="loading-spinner" style="margin: 0 auto 15px;"></div>
            <p style="color: #666;">Loading reactions...</p>
        </div>
    `;
    modal.style.display = "block";

    // Fetch reactions
    fetch("post_reaction.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => {
        console.log("Response status:", response.status);
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }
        return response.json();
      })
      .then((data) => {
        console.log("✅ Response data:", data);
        if (data.success && data.users) {
          displayPostReactionUsers(data.users);
        } else {
          throw new Error(data.message || "No reaction data found");
        }
      })
      .catch((error) => {
        console.error("❌ Error fetching reactions:", error);
        usersList.innerHTML = `
            <div style="text-align: center; padding: 30px; color: #f44336;">
                <p style="margin-bottom: 15px;">Failed to load reactions</p>
                <button onclick="showReactionUsers(${postId})" 
                        style="background: #ED5A2C; color: white; border: none; padding: 8px 16px; border-radius: 15px; cursor: pointer;">
                    Try Again
                </button>
            </div>
        `;
      });
  };
  // FIXED: Display function for POST reactions only
  function displayPostReactionUsers(users) {
    console.log(
      "🎨 displayPostReactionUsers called with",
      users?.length,
      "users",
    );

    const usersList = document.getElementById("postReactionUsersList");
    if (!usersList) {
      console.error("❌ postReactionUsersList element not found");
      return;
    }

    if (!users || users.length === 0) {
      usersList.innerHTML = `
            <div style="text-align: center; padding: 40px; color: #666;">
                <div style="font-size: 48px; margin-bottom: 15px;">😔</div>
                <p style="font-size: 16px;">No reactions yet</p>
                <p style="font-size: 14px; opacity: 0.8;">Be the first to react!</p>
            </div>
        `;
      return;
    }

    let html = "";
    users.forEach((user, index) => {
      const userImage =
        user.PROFILE_IMAGE ||
        "https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg";
      const reactionTime = formatReactionTime(user.CREATED_AT);
      const reactionType =
        user.REACTION_TYPE?.charAt(0).toUpperCase() +
          user.REACTION_TYPE?.slice(1) || "Like";
      const reactionEmoji = user.REACTION_EMOJI || "👍";

      html += `
            <div class="reaction-user-item" style="display: flex; align-items: center; padding: 15px; border-bottom: 1px solid #eee; transition: all 0.2s ease; cursor: pointer;">
                <img loading="lazy" decoding="async" src="${userImage}" 
                     alt="${escapeHtml(user.NAME || "User")}" 
                     class="reaction-user-avatar" 
                     style="width: 50px; height: 50px; border-radius: 50%; margin-right: 15px; object-fit: cover; border: 2px solid #f0f0f0; transition: all 0.2s ease;"
                     onerror="this.src='https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg'">
                <div class="reaction-user-info" style="flex: 1;">
                    <div class="reaction-user-name" style="font-weight: 600; margin-bottom: 5px; color: #333; font-size: 16px;">
                        ${escapeHtml(user.NAME || "Unknown User")}
                    </div>
                    <div class="reaction-user-time" style="font-size: 13px; color: #666;">
                        Reacted ${reactionTime}
                    </div>
                </div>
                <div class="reaction-user-emoji" style="font-size: 28px; margin-left: 15px; display: flex; flex-direction: column; align-items: center;">
                    <span style="margin-bottom: 2px;">${reactionEmoji}</span>
                    <small style="font-size: 11px; color: #888; text-transform: capitalize; font-weight: 500;">
                        ${reactionType}
                    </small>
                </div>
            </div>
        `;
    });

    usersList.innerHTML = html;

    console.log("✅ Post reaction users displayed successfully");
  }

  // Helper function to format reaction time
  function formatReactionTime(dateString) {
    const now = new Date();
    const reactionDate = new Date(dateString);
    const diffInSeconds = Math.floor((now - reactionDate) / 1000);

    if (diffInSeconds < 60) {
      return "just now";
    } else if (diffInSeconds < 3600) {
      const minutes = Math.floor(diffInSeconds / 60);
      return `${minutes} minute${minutes > 1 ? "s" : ""} ago`;
    } else if (diffInSeconds < 86400) {
      const hours = Math.floor(diffInSeconds / 3600);
      return `${hours} hour${hours > 1 ? "s" : ""} ago`;
    } else if (diffInSeconds < 604800) {
      const days = Math.floor(diffInSeconds / 86400);
      return `${days} day${days > 1 ? "s" : ""} ago`;
    } else {
      return reactionDate.toLocaleDateString();
    }
  }

  // Enhanced mobile touch support for reactions
  const likesContainers = document.querySelectorAll(".post-likes-container");

  likesContainers.forEach((container) => {
    let touchStartTime = 0;

    // Handle touch start
    container.addEventListener("touchstart", function (e) {
      touchStartTime = Date.now();
    });

    // Handle touch end with duration check
    container.addEventListener("touchend", function (e) {
      const touchDuration = Date.now() - touchStartTime;

      // Short tap - toggle reactions panel
      if (touchDuration < 200) {
        e.preventDefault();
        if (this.classList.contains("active")) {
          this.classList.remove("active");
        } else {
          // Remove active class from all containers
          likesContainers.forEach((c) => c.classList.remove("active"));
          // Add active class to the current container
          this.classList.add("active");
        }
      }
    });

    // Handle long press for direct like
    let longPressTimer;
    container.addEventListener("touchstart", function (e) {
      longPressTimer = setTimeout(() => {
        // Long press detected - directly like with default reaction
        const postId = this.getAttribute("data-post-id");
        if (postId) {
          handleReaction(postId, "yummy");
          this.classList.remove("active");

          // Provide haptic feedback if available
          if ("vibrate" in navigator) {
            navigator.vibrate(50);
          }
        }
      }, 500);
    });

    container.addEventListener("touchend", function () {
      clearTimeout(longPressTimer);
    });

    container.addEventListener("touchmove", function () {
      clearTimeout(longPressTimer);
    });
  });

  // Close reactions when tapping elsewhere
  document.addEventListener("touchstart", function (e) {
    const activeContainers = document.querySelectorAll(
      ".post-likes-container.active",
    );
    activeContainers.forEach((container) => {
      if (!container.contains(e.target)) {
        container.classList.remove("active");
      }
    });
  });

  // Close reaction users modal functionality
  const reactionModal = document.getElementById("reactionUsersModal");
  const reactionCloseBtn = reactionModal?.querySelector(".close-modal");

  if (reactionCloseBtn) {
    reactionCloseBtn.addEventListener("click", function (e) {
      e.preventDefault();
      console.log("Close button clicked"); // Debug log
      reactionModal.style.display = "none";
    });
  }

  if (reactionModal) {
    window.addEventListener("click", function (event) {
      if (event.target === reactionModal) {
        console.log("Modal backdrop clicked"); // Debug log
        reactionModal.style.display = "none";
      }
    });
  }

  // Add a global click handler for debugging
  document.addEventListener("click", function (e) {
    if (e.target.classList.contains("like-count")) {
      console.log("Like count clicked:", e.target);
      e.stopPropagation();
    }
  });

  // Image modal functionality
  const imageModal = document.getElementById("imageModal");
  const modalImage = document.getElementById("modalImage");
  const imageCloseBtn = document.querySelector(".image-close-btn");

  // Function to open image modal
  window.openImageModal = function (imageSrc) {
    modalImage.src = imageSrc;
    imageModal.style.display = "flex";
    document.body.style.overflow = "hidden";
  };

  // Close image modal when clicking the close button
  if (imageCloseBtn) {
    imageCloseBtn.addEventListener("click", function () {
      imageModal.style.display = "none";
      document.body.style.overflow = "auto";
    });
  }

  // Close image modal when clicking outside the image
  window.addEventListener("click", function (event) {
    if (event.target === imageModal) {
      imageModal.style.display = "none";
      document.body.style.overflow = "auto";
    }
  });

  // Post menu toggle functionality
  window.togglePostMenu = function (postId) {
    const menu = document.getElementById("post-menu-" + postId);
    if (menu) {
      // Close all other open menus first
      document
        .querySelectorAll(".post-menu-dropdown")
        .forEach(function (dropdown) {
          if (
            dropdown.id !== "post-menu-" + postId &&
            dropdown.style.display === "block"
          ) {
            dropdown.style.display = "none";
          }
        });

      // Toggle the clicked menu
      menu.style.display = menu.style.display === "block" ? "none" : "block";
    }
  };

  // Close menus when clicking outside
  document.addEventListener("click", function (event) {
    if (!event.target.closest(".post-actions")) {
      document
        .querySelectorAll(".post-menu-dropdown")
        .forEach(function (dropdown) {
          dropdown.style.display = "none";
        });
    }
  });

  // Edit post functionality
  const editModal = document.getElementById("editPostModal");
  const editForm = document.getElementById("editPostForm");

  window.editPost = function (postId, description, mediaUrl) {
    document.getElementById("edit_post_id").value = postId;
    document.getElementById("edit_description").value = description || "";
    document.getElementById("existing_image").value = mediaUrl || "";

    // Show media preview if exists
    const previewContainer = document.getElementById(
      "edit-image-preview-container",
    );

    if (mediaUrl) {
      // Check if it's a video or an image based on file extension
      const isVideo = mediaUrl.toLowerCase().endsWith(".mp4");

      if (isVideo) {
        // Create a video element for preview
        previewContainer.innerHTML = `
                            <video controls id="edit-video-preview" style="max-width: 100%; max-height: 300px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); display: block;">
                                <source src="${mediaUrl}" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                        `;
      } else {
        // Display image
        previewContainer.innerHTML = `
                            <img loading="lazy" decoding="async" id="edit-image-preview" src="${mediaUrl}" alt="Image Preview" style="display: block; max-width: 100%; max-height: 300px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);">
                        `;
      }
    } else {
      // Reset the container to show the default empty image element
      previewContainer.innerHTML = `
                        <img loading="lazy" decoding="async" id="edit-image-preview" src="/placeholder.svg" alt="Image Preview" style="display: none; max-width: 100%; max-height: 300px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);">
                    `;
    }

    // Show the modal
    editModal.style.display = "block";

    // Close any open menus
    document
      .querySelectorAll(".post-menu-dropdown")
      .forEach(function (dropdown) {
        dropdown.style.display = "none";
      });
  };

  // Close modal when clicking X
  const closeModalBtns = document.querySelectorAll(".close-modal");
  closeModalBtns.forEach((btn) => {
    btn.addEventListener("click", function () {
      const modal = this.closest(".modal");
      if (modal) {
        modal.style.display = "none";
      }
    });
  });

  // Close modal when clicking outside
  window.addEventListener("click", function (event) {
    const modals = document.querySelectorAll(".modal");
    modals.forEach((modal) => {
      if (event.target === modal) {
        modal.style.display = "none";
      }
    });
  });

  // Handle edit post media preview (image or video)
  const editImageInput = document.getElementById("edit_post_image");
  const editImagePreviewContainer = document.getElementById(
    "edit-image-preview-container",
  );

  if (editImageInput && editImagePreviewContainer) {
    editImageInput.addEventListener("change", function () {
      const file = this.files[0];
      if (file) {
        // Check if the file is a video
        if (file.type.startsWith("video/")) {
          // Create a video element for preview
          editImagePreviewContainer.innerHTML = `
                                <video controls id="edit-video-preview" style="max-width: 100%; max-height: 300px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); display: block;">
                                    <source src="${URL.createObjectURL(file)}" type="${file.type}">
                                    Your browser does not support the video tag.
                                </video>
                            `;
        } else {
          // Handle image files
          const reader = new FileReader();
          reader.onload = function (e) {
            // Reset the container with the image
            editImagePreviewContainer.innerHTML = `
                                    <img loading="lazy" decoding="async" id="edit-image-preview" src="${e.target.result}" alt="Image Preview" style="display: block; max-width: 100%; max-height: 300px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);">
                                `;
          };
          reader.readAsDataURL(file);
        }
      }
    });
  }

  // Handle edit form submission
  if (editForm) {
    editForm.addEventListener("submit", function (e) {
      e.preventDefault();
      const formData = new FormData(this);
      formData.append("action", "edit_post");

      // Show loading toast
      showToast("Updating post...", "success");

      fetch("dashboard.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            // Show success toast before reloading
            showToast("Post updated successfully!", "success");
            // Give the toast a moment to be seen before reload
            setTimeout(() => {
              window.location.reload();
            }, 1000);
          } else {
            // Show error toast
            showToast(data.message || "Error updating post", "error");
          }
        })
        .catch((error) => {
          // Show error toast instead of console error and alert
          showToast("An error occurred while updating the post.", "error");
        });
    });
  }

  // Create a toast notification function
  function showToast(message, type = "success") {
    // Create toast container if it doesn't exist
    let toastContainer = document.getElementById("toast-container");
    if (!toastContainer) {
      toastContainer = document.createElement("div");
      toastContainer.id = "toast-container";
      toastContainer.style.position = "fixed";
      toastContainer.style.bottom = "20px";
      toastContainer.style.right = "20px";
      toastContainer.style.zIndex = "9999";
      document.body.appendChild(toastContainer);
    }

    // Create toast element
    const toast = document.createElement("div");
    toast.className = `toast ${type}`;
    toast.style.minWidth = "250px";
    toast.style.margin = "10px";
    toast.style.padding = "15px 20px";
    toast.style.borderRadius = "4px";
    toast.style.boxShadow = "0 2px 10px rgba(0,0,0,0.2)";
    toast.style.backgroundColor = type === "success" ? "#4CAF50" : "#ED5A2C";
    toast.style.color = "white";
    toast.style.display = "flex";
    toast.style.alignItems = "center";
    toast.style.justifyContent = "space-between";
    toast.style.animation = "fadeIn 0.5s, fadeOut 0.5s 2.5s";
    toast.style.animationFillMode = "forwards";

    // Add styles for animations
    const style = document.createElement("style");
    style.textContent = `
                    @keyframes fadeIn {
                        from { opacity: 0; transform: translateY(20px); }
                        to { opacity: 1; transform: translateY(0); }
                    }
                    @keyframes fadeOut {
                        from { opacity: 1; transform: translateY(0); }
                        to { opacity: 0; transform: translateY(20px); }
                    }
                `;
    document.head.appendChild(style);

    // Add content
    const icon = document.createElement("span");
    icon.innerHTML =
      type === "success"
        ? '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>'
        : '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>';
    icon.style.marginRight = "10px";

    const messageSpan = document.createElement("span");
    messageSpan.textContent = message;
    messageSpan.style.flex = "1";

    const closeBtn = document.createElement("span");
    closeBtn.innerHTML = "&times;";
    closeBtn.style.marginLeft = "10px";
    closeBtn.style.cursor = "pointer";
    closeBtn.style.fontSize = "20px";
    closeBtn.onclick = function () {
      toast.remove();
    };

    toast.appendChild(icon);
    toast.appendChild(messageSpan);
    toast.appendChild(closeBtn);

    // Add to container
    toastContainer.appendChild(toast);

    // Auto remove after 3 seconds
    setTimeout(() => {
      toast.remove();
    }, 3000);
  }

  // Delete post functionality
  window.deletePost = function (postId) {
    if (
      confirm(
        "Are you sure you want to delete this post? This action cannot be undone.",
      )
    ) {
      const formData = new FormData();
      formData.append("action", "delete_post");
      formData.append("post_id", postId);

      fetch("dashboard.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            // Remove the post from the DOM
            const postElement = document.querySelector(
              `[data-post-id="${postId}"]`,
            );
            if (postElement) {
              postElement.remove();
              // Show success notification
              showToast("Post deleted successfully!", "success");
            } else {
              // Reload the page if we can't find the element
              window.location.reload();
            }
          } else {
            // Show error notification instead of alert
            showToast(data.message || "Error deleting post", "error");
          }
        })
        .catch((error) => {
          // Show error notification instead of console error and alert
          showToast("An error occurred while deleting the post.", "error");
        });
    }

    // Close any open menus
    document
      .querySelectorAll(".post-menu-dropdown")
      .forEach(function (dropdown) {
        dropdown.style.display = "none";
      });
  };

  // Handle media preview for new posts (image or video)
  const postImage = document.getElementById("postImage");
  const imagePreview = document.getElementById("imagePreview");
  const imagePreviewContainer = document.getElementById(
    "imagePreviewContainer",
  );
  const removeImage = document.getElementById("removeImage");
  const postDescription = document.getElementById("postDescription");
  const postButton = document.getElementById("postButton");

  if (postImage && imagePreviewContainer) {
    // Initially hide the preview container
    imagePreviewContainer.style.display = "none";

    postImage.addEventListener("change", function () {
      const file = this.files[0];
      if (file) {
        // Check if the file is a video
        if (file.type.startsWith("video/")) {
          // Create a video element instead of using the image
          imagePreviewContainer.innerHTML = `
                                <div style="position: relative; display: inline-block;">
                                    <video controls style="max-width: 100%; max-height: 300px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);">
                                        <source src="${URL.createObjectURL(file)}" type="${file.type}">
                                        Your browser does not support the video tag.
                                    </video>
                                    <button type="button" class="remove-image" id="removeVideoBtn">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <line x1="18" y1="6" x2="6" y2="18"></line>
                                            <line x1="6" y1="6" x2="18" y2="18"></line>
                                        </svg>
                                    </button>
                                </div>
                            `;

          // Show the preview container
          imagePreviewContainer.style.display = "block";

          // Add event listener to the new remove button
          document
            .getElementById("removeVideoBtn")
            .addEventListener("click", function () {
              imagePreviewContainer.innerHTML = `
                                    <div style="position: relative; display: inline-block;">
                                        <img loading="lazy" decoding="async" src="/placeholder.svg" alt="Preview" class="image-preview" id="imagePreview">
                                        <button type="button" class="remove-image" id="removeImage">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                                <line x1="6" y1="6" x2="18" y2="18"></line>
                                            </svg>
                                        </button>
                                    </div>
                                `;
              imagePreviewContainer.style.display = "none";
              postImage.value = "";
              updatePostButtonState();
            });
        } else {
          // Handle image files as before
          const reader = new FileReader();
          reader.onload = function (e) {
            // Reset the container to its original state with an image
            imagePreviewContainer.innerHTML = `
                                    <div style="position: relative; display: inline-block;">
                                        <img loading="lazy" decoding="async" src="${e.target.result}" alt="Preview" class="image-preview" id="imagePreview">
                                        <button type="button" class="remove-image" id="removeImage">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                                <line x1="6" y1="6" x2="18" y2="18"></line>
                                            </svg>
                                        </button>
                                    </div>
                                `;
            imagePreviewContainer.style.display = "block";

            // Add event listener to the new remove button
            document
              .getElementById("removeImage")
              .addEventListener("click", function () {
                imagePreviewContainer.style.display = "none";
                postImage.value = "";
                updatePostButtonState();
              });
          };
          reader.readAsDataURL(file);
        }
        updatePostButtonState();
      }
    });
  }

  // Update post button state
  function updatePostButtonState() {
    if (postDescription && postButton && postImage) {
      const hasDescription = postDescription.value.trim() !== "";
      const hasImage = postImage.files.length > 0;
      postButton.disabled = !hasDescription && !hasImage;
    }
  }

  // Update button state when description changes
  if (postDescription) {
    postDescription.addEventListener("input", updatePostButtonState);
    // Initial button state
    updatePostButtonState();
  }

  // Handle tab switching
  const tabs = document.querySelectorAll(".post-tab");
  tabs.forEach((tab) => {
    tab.addEventListener("click", function () {
      // If Make Recipe tab is clicked, redirect to recipes.php
      if (this.textContent === "Make Recipe") {
        window.location.href = "recipes.php?openRecipeForm=1";
      }
    });
  });
});

// REPLACE the comments JavaScript in your dashboard.php with this optimized version:

// ========================================
// ENHANCED COMMENTS SYSTEM - COMPLETE JAVASCRIPT
// ========================================

// Global variables for comment system
let currentPostId = null;
let commentsLoading = false;
let currentOffset = 0;
let hasMoreComments = false;
let totalCommentsCount = 0;
const commentsPerLoad = 10;

// FIXED: Enhanced function to show POST reaction users with separate modal
// REPLACE your existing showReactionUsers function with this FIXED version:

// FIXED: Enhanced function to show POST reaction users with separate modal
window.showReactionUsers = function (postId) {
  console.log("showReactionUsers called with postId:", postId);

  const formData = new FormData();
  formData.append("action", "get_reaction_users");
  formData.append("post_id", postId);

  // FIXED: Use the correct modal ID for POST reactions
  const modal = document.getElementById("postReactionUsersModal");
  const usersList = document.getElementById("postReactionUsersList");
  const modalTitle = modal?.querySelector("h2");

  if (!modal) {
    console.error("❌ postReactionUsersModal not found - Check your HTML");
    return;
  }

  if (!usersList) {
    console.error("❌ postReactionUsersList not found - Check your HTML");
    return;
  }

  // Set the correct title for POST reactions
  if (modalTitle) {
    modalTitle.textContent = "People who reacted";
  }

  usersList.innerHTML =
    '<p style="text-align: center; padding: 20px; color: #666;">Loading reactions...</p>';
  modal.style.display = "block";

  fetch("post_reaction.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => {
      console.log("Response status:", response.status);
      return response.json();
    })
    .then((data) => {
      console.log("Response data:", data);
      if (data.success) {
        displayPostReactionUsers(data.users);
      } else {
        usersList.innerHTML =
          '<p style="text-align: center; padding: 20px; color: #f44336;">Error: ' +
          (data.message || "Failed to load reactions") +
          "</p>";
        console.error("API Error:", data.message);
      }
    })
    .catch((error) => {
      console.error("Fetch Error:", error);
      usersList.innerHTML =
        '<p style="text-align: center; padding: 20px; color: #f44336;">Network error occurred</p>';
    });
};

// FIXED: Separate display function for POST reactions
function displayPostReactionUsers(users) {
  console.log("displayPostReactionUsers called with users:", users);

  const modal = document.getElementById("postReactionUsersModal");
  const usersList = document.getElementById("postReactionUsersList");

  if (!usersList) {
    console.error("postReactionUsersList element not found");
    return;
  }

  if (!users || users.length === 0) {
    usersList.innerHTML =
      '<p style="text-align: center; padding: 30px; color: #666; font-size: 16px;">No reactions yet</p>';
  } else {
    console.log("Displaying", users.length, "users");
    let usersHTML = "";
    users.forEach((user, index) => {
      console.log("Processing user", index, ":", user);
      const userImage =
        user.PROFILE_IMAGE ||
        "https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg";
      const reactionTime = formatReactionTime(user.CREATED_AT);
      const reactionTypeCapitalized = user.REACTION_TYPE
        ? user.REACTION_TYPE.charAt(0).toUpperCase() +
          user.REACTION_TYPE.slice(1)
        : "Unknown";
      const reactionEmoji = user.REACTION_EMOJI || "👍";

      usersHTML += `
                <div class="reaction-user-item" style="display: flex; align-items: center; padding: 15px; border-bottom: 1px solid #eee; transition: background-color 0.2s; cursor: pointer;">
                    <img loading="lazy" decoding="async" src="${userImage}" alt="${escapeHtml(user.NAME || "User")}" class="reaction-user-avatar" 
                         style="width: 50px; height: 50px; border-radius: 50%; margin-right: 15px; object-fit: cover; border: 2px solid #f0f0f0;"
                         onerror="this.src='https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg'">
                    <div class="reaction-user-info" style="flex: 1;">
                        <div class="reaction-user-name" style="font-weight: 600; margin-bottom: 5px; color: #333; font-size: 16px;">${escapeHtml(user.NAME || "Unknown User")}</div>
                        <div class="reaction-user-time" style="font-size: 13px; color: #666;">Reacted ${reactionTime}</div>
                    </div>
                    <div class="reaction-user-emoji" style="font-size: 28px; margin-left: 15px; display: flex; flex-direction: column; align-items: center;">
                        <span>${reactionEmoji}</span>
                        <small style="font-size: 11px; color: #888; margin-top: 3px; text-transform: capitalize;">${reactionTypeCapitalized}</small>
                    </div>
                </div>
            `;
    });
    usersList.innerHTML = usersHTML;
  }

  if (modal) {
    modal.style.display = "block";
    // Add backdrop click to close
    modal.onclick = function (e) {
      if (e.target === modal) {
        modal.style.display = "none";
      }
    };
  }
}

// FIXED: Separate display function for POST reactions
function displayPostReactionUsers(users) {
  console.log("displayPostReactionUsers called with users:", users);

  const modal = document.getElementById("postReactionUsersModal");
  const usersList = document.getElementById("postReactionUsersList");

  if (!usersList) {
    console.error("postReactionUsersList element not found");
    return;
  }

  if (!users || users.length === 0) {
    usersList.innerHTML =
      '<p style="text-align: center; padding: 30px; color: #666; font-size: 16px;">No reactions yet</p>';
  } else {
    console.log("Displaying", users.length, "users");
    let usersHTML = "";
    users.forEach((user, index) => {
      console.log("Processing user", index, ":", user);
      const userImage =
        user.PROFILE_IMAGE ||
        "https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg";
      const reactionTime = formatReactionTime(user.CREATED_AT);
      const reactionTypeCapitalized = user.REACTION_TYPE
        ? user.REACTION_TYPE.charAt(0).toUpperCase() +
          user.REACTION_TYPE.slice(1)
        : "Unknown";
      const reactionEmoji = user.REACTION_EMOJI || "👍";

      usersHTML += `
                <div class="reaction-user-item" style="display: flex; align-items: center; padding: 15px; border-bottom: 1px solid #eee; transition: background-color 0.2s; cursor: pointer;">
                    <img loading="lazy" decoding="async" src="${userImage}" alt="${escapeHtml(user.NAME || "User")}" class="reaction-user-avatar" 
                         style="width: 50px; height: 50px; border-radius: 50%; margin-right: 15px; object-fit: cover; border: 2px solid #f0f0f0;"
                         onerror="this.src='https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg'">
                    <div class="reaction-user-info" style="flex: 1;">
                        <div class="reaction-user-name" style="font-weight: 600; margin-bottom: 5px; color: #333; font-size: 16px;">${escapeHtml(user.NAME || "Unknown User")}</div>
                        <div class="reaction-user-time" style="font-size: 13px; color: #666;">Reacted ${reactionTime}</div>
                    </div>
                    <div class="reaction-user-emoji" style="font-size: 28px; margin-left: 15px; display: flex; flex-direction: column; align-items: center;">
                        <span>${reactionEmoji}</span>
                        <small style="font-size: 11px; color: #888; margin-top: 3px; text-transform: capitalize;">${reactionTypeCapitalized}</small>
                    </div>
                </div>
            `;
    });
    usersList.innerHTML = usersHTML;

    // Add hover effects
    usersList.querySelectorAll(".reaction-user-item").forEach((item) => {
      item.addEventListener("mouseenter", function () {
        this.style.backgroundColor = "#f8f9fa";
      });
      item.addEventListener("mouseleave", function () {
        this.style.backgroundColor = "transparent";
      });
    });
  }

  if (modal) {
    modal.style.display = "block";
    // Add backdrop click to close
    modal.onclick = function (e) {
      if (e.target === modal) {
        modal.style.display = "none";
      }
    };
  }
}

// Helper function to format reaction time
function formatReactionTime(dateString) {
  const now = new Date();
  const reactionDate = new Date(dateString);
  const diffInSeconds = Math.floor((now - reactionDate) / 1000);

  if (diffInSeconds < 60) {
    return "just now";
  } else if (diffInSeconds < 3600) {
    const minutes = Math.floor(diffInSeconds / 60);
    return `${minutes} minute${minutes > 1 ? "s" : ""} ago`;
  } else if (diffInSeconds < 86400) {
    const hours = Math.floor(diffInSeconds / 3600);
    return `${hours} hour${hours > 1 ? "s" : ""} ago`;
  } else if (diffInSeconds < 604800) {
    const days = Math.floor(diffInSeconds / 86400);
    return `${days} day${days > 1 ? "s" : ""} ago`;
  } else {
    return reactionDate.toLocaleDateString();
  }
}

// Initialize comments system when DOM is loaded
document.addEventListener("DOMContentLoaded", function () {
  initializeCommentsSystem();
});

// Main initialization function
function initializeCommentsSystem() {
  console.log("🚀 Initializing Enhanced Comments System...");

  // Initialize modal elements
  const commentsModal = document.getElementById("commentsModal");
  const closeButton = document.querySelector(".close-comments");
  const commentInput = document.getElementById("commentInput");
  const commentSubmit = document.getElementById("commentSubmit");

  // Close button event listener
  if (closeButton) {
    closeButton.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      closeCommentsModal();
    });
  }

  // Modal backdrop click to close
  if (commentsModal) {
    commentsModal.addEventListener("click", function (e) {
      if (e.target === commentsModal) {
        closeCommentsModal();
      }
    });

    // Prevent modal content clicks from closing modal
    const modalContent = commentsModal.querySelector(".comments-modal-content");
    if (modalContent) {
      modalContent.addEventListener("click", function (e) {
        e.stopPropagation();
      });
    }
  }

  // Comment input event listeners
  if (commentInput) {
    // Auto-resize textarea
    commentInput.addEventListener("input", function () {
      this.style.height = "auto";
      this.style.height = this.scrollHeight + "px";
      updateCommentSubmitButton();
    });

    // Enter key to submit (Shift+Enter for new line)
    commentInput.addEventListener("keydown", function (e) {
      if (e.key === "Enter" && !e.shiftKey) {
        e.preventDefault();
        if (this.value.trim() && !commentSubmit.disabled) {
          addComment();
        }
      }
    });

    // Focus event
    commentInput.addEventListener("focus", function () {
      this.parentElement.style.borderColor = "var(--primary-color)";
    });

    // Blur event
    commentInput.addEventListener("blur", function () {
      this.parentElement.style.borderColor = "#e0e0e0";
    });
  }

  // Comment submit button event listener
  if (commentSubmit) {
    commentSubmit.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      if (!this.disabled && commentInput && commentInput.value.trim()) {
        addComment();
      }
    });
  }

  // Initialize keyboard shortcuts
  document.addEventListener("keydown", function (e) {
    // Escape key to close modal
    if (
      e.key === "Escape" &&
      commentsModal &&
      commentsModal.style.display === "block"
    ) {
      closeCommentsModal();
    }
  });

  // Initialize comment reaction handlers
  initializeCommentReactionHandlers();

  // FIXED: Close POST reaction users modal functionality
  const postReactionModal = document.getElementById("postReactionUsersModal");
  const postReactionCloseBtn = postReactionModal?.querySelector(".close-modal");

  if (postReactionCloseBtn) {
    postReactionCloseBtn.addEventListener("click", function (e) {
      e.preventDefault();
      console.log("Post reaction modal close button clicked");
      postReactionModal.style.display = "none";
    });
  }

  if (postReactionModal) {
    window.addEventListener("click", function (event) {
      if (event.target === postReactionModal) {
        console.log("Post reaction modal backdrop clicked");
        postReactionModal.style.display = "none";
      }
    });
  }

  // FIXED: Close COMMENT reaction users modal functionality
  const commentReactionModal = document.getElementById(
    "commentReactionUsersModal",
  );
  const commentReactionCloseBtn =
    commentReactionModal?.querySelector(".close-modal");

  if (commentReactionCloseBtn) {
    commentReactionCloseBtn.addEventListener("click", function (e) {
      e.preventDefault();
      console.log("Comment reaction modal close button clicked");
      commentReactionModal.style.display = "none";
    });
  }

  if (commentReactionModal) {
    window.addEventListener("click", function (event) {
      if (event.target === commentReactionModal) {
        console.log("Comment reaction modal backdrop clicked");
        commentReactionModal.style.display = "none";
      }
    });
  }

  console.log("✅ Comments System Initialized Successfully!");
}

// Function to open comments modal
function openCommentsModal(postId) {
  console.log("📖 Opening comments modal for post:", postId);

  currentPostId = postId;
  currentOffset = 0;
  hasMoreComments = false;
  totalCommentsCount = 0;

  const modal = document.getElementById("commentsModal");
  const commentsBody = document.getElementById("commentsBody");
  const commentInput = document.getElementById("commentInput");

  if (!modal || !commentsBody) {
    console.error("❌ Comments modal elements not found");
    return;
  }

  // Show modal immediately
  modal.style.display = "block";
  document.body.style.overflow = "hidden";

  // Clear previous content and show loading
  commentsBody.innerHTML = `
        <div style="text-align: center; padding: 40px; color: #666;">
            <div class="loading-spinner" style="margin: 0 auto 15px;"></div>
            <p>Loading comments...</p>
        </div>
    `;

  // Reset comment input
  if (commentInput) {
    commentInput.value = "";
    commentInput.style.height = "auto";
  }

  updateCommentSubmitButton();

  // Load initial comments
  loadComments(postId, true);

  // Focus on comment input after a short delay
  setTimeout(() => {
    if (commentInput) {
      commentInput.focus();
    }
  }, 500);
}

// Function to close comments modal
function closeCommentsModal() {
  console.log("🔒 Closing comments modal");

  const modal = document.getElementById("commentsModal");
  if (modal) {
    modal.style.display = "none";
  }

  document.body.style.overflow = "auto";
  currentPostId = null;
  currentOffset = 0;
  hasMoreComments = false;

  // Close any open reaction panels
  closeAllCommentReactionPanels();
}

// Enhanced function to load comments with pagination
function loadComments(postId, isFirstLoad = false) {
  if (commentsLoading) {
    console.log("⏳ Comments already loading, skipping...");
    return;
  }

  console.log(
    `📥 Loading comments for post ${postId}, first load: ${isFirstLoad}`,
  );
  commentsLoading = true;

  const formData = new FormData();
  formData.append("action", "get_comments");
  formData.append("post_id", postId);
  formData.append("offset", isFirstLoad ? 0 : currentOffset);
  formData.append("limit", commentsPerLoad);

  // Update load more button if not first load
  if (!isFirstLoad) {
    const loadMoreBtn = document.getElementById("loadMoreCommentsBtn");
    if (loadMoreBtn) {
      loadMoreBtn.disabled = true;
      loadMoreBtn.innerHTML = '<div class="loading-spinner small"></div>';
    }
  }

  fetch("comments.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      // Check if response is actually JSON
      const contentType = response.headers.get("content-type");
      if (!contentType || !contentType.includes("application/json")) {
        throw new Error("Response is not JSON");
      }
      return response.json();
    })
    .then((data) => {
      console.log("📨 Comments response:", data);

      if (data.success) {
        // Update pagination variables
        currentOffset = data.next_offset || 0;
        hasMoreComments = data.has_more || false;
        totalCommentsCount = data.total_comments || 0;

        if (isFirstLoad) {
          displayComments(data.comments, data.reactions, true);
        } else {
          appendComments(data.comments);
        }
      } else {
        throw new Error(data.message || "Failed to load comments");
      }
    })
    .catch((error) => {
      console.error("❌ Error loading comments:", error);
      if (isFirstLoad) {
        showCommentsError("Failed to load comments. Please try again.");
      } else {
        showToast("Failed to load more comments. Please try again.", "error");
      }
    })
    .finally(() => {
      commentsLoading = false;

      // Reset load more button
      if (!isFirstLoad) {
        const loadMoreBtn = document.getElementById("loadMoreCommentsBtn");
        if (loadMoreBtn) {
          loadMoreBtn.disabled = false;
          loadMoreBtn.innerHTML = "Load More Comments";

          if (!hasMoreComments) {
            loadMoreBtn.style.display = "none";
          }
        }
      }
    });
}

// Function to load more comments
function loadMoreComments() {
  if (!currentPostId || !hasMoreComments || commentsLoading) {
    console.log("⚠️ Cannot load more comments:", {
      currentPostId,
      hasMoreComments,
      commentsLoading,
    });
    return;
  }
  loadComments(currentPostId, false);
}

// Enhanced function to display comments (first load)
function displayComments(comments, reactions, isFirstLoad = true) {
  console.log(
    `🎨 Displaying ${comments?.length || 0} comments, first load: ${isFirstLoad}`,
  );

  const commentsBody = document.getElementById("commentsBody");
  if (!commentsBody) return;

  let html = "";

  // Display comments
  if (comments && comments.length > 0) {
    comments.forEach((comment) => {
      html += generateCommentHTML(comment);
    });
  }

  // Display post reactions section (only on first load)
  if (isFirstLoad && reactions && reactions.length > 0) {
    html += `
            <div class="reactions-section" style="padding: 20px; border-top: 1px solid #eee; margin-top: 20px; background-color: #f9f9f9;">
                <div class="reactions-title" style="font-weight: 600; margin-bottom: 15px; color: #333;">Post Reactions:</div>
                <div class="reactions-list" style="display: flex; flex-wrap: wrap; gap: 10px;">
        `;

    reactions.forEach((reaction) => {
      const userImage =
        reaction.PROFILE_IMAGE ||
        "https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg";
      html += `
                <div class="reaction-item" style="display: flex; align-items: center; gap: 8px; padding: 8px 12px; background: white; border-radius: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <img loading="lazy" decoding="async" src="${userImage}" alt="${escapeHtml(reaction.NAME)}" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover;" onerror="this.src='https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg'">
                    <span style="font-size: 18px;">${reaction.REACTION_EMOJI}</span>
                    <span style="font-size: 12px; color: #666;">${escapeHtml(reaction.NAME)}</span>
                </div>
            `;
    });

    html += "</div></div>";
  }

  // Add Load More button if there are more comments
  if (hasMoreComments) {
    html += `
            <div style="text-align: center; padding: 20px;">
                <button id="loadMoreCommentsBtn" onclick="loadMoreComments()" 
                        style="background: linear-gradient(135deg, #ED5A2C 0%, #ff6b3d 100%); 
                               color: white; border: none; padding: 12px 24px; border-radius: 25px; 
                               font-weight: 500; cursor: pointer; transition: all 0.2s ease;
                               box-shadow: 0 2px 8px rgba(237, 90, 44, 0.3);">
                    Load More Comments
                </button>
            </div>
        `;
  }

  // Show empty state if no content
  if (!html && isFirstLoad) {
    html = `
            <div class="no-comments" style="text-align: center; padding: 50px 20px; color: #666;">
                <div style="font-size: 48px; margin-bottom: 15px;">💬</div>
                <p style="font-size: 16px; margin-bottom: 10px;">No comments yet</p>
                <p style="font-size: 14px; opacity: 0.8;">Be the first to share your thoughts!</p>
            </div>
        `;
  }

  commentsBody.innerHTML = html;

  // Initialize comment interactions after content is loaded
  setTimeout(() => {
    initializeCommentInteractions();
  }, 100);
}

// Function to append new comments (for load more)
function appendComments(comments) {
  if (!comments || comments.length === 0) return;

  console.log(`➕ Appending ${comments.length} more comments`);

  const commentsBody = document.getElementById("commentsBody");
  const loadMoreBtn = document.getElementById("loadMoreCommentsBtn");

  if (!commentsBody) return;

  // Generate HTML for new comments
  let newCommentsHTML = "";
  comments.forEach((comment) => {
    newCommentsHTML += generateCommentHTML(comment);
  });

  // Create temporary container
  const tempDiv = document.createElement("div");
  tempDiv.innerHTML = newCommentsHTML;

  // Insert new comments before the load more button
  if (loadMoreBtn && loadMoreBtn.parentElement) {
    const loadMoreContainer = loadMoreBtn.parentElement;
    while (tempDiv.firstChild) {
      commentsBody.insertBefore(tempDiv.firstChild, loadMoreContainer);
    }

    // Update or hide load more button
    if (!hasMoreComments) {
      loadMoreContainer.style.display = "none";
    }
  } else {
    // No load more button, just append
    while (tempDiv.firstChild) {
      commentsBody.appendChild(tempDiv.firstChild);
    }
  }

  // Initialize interactions for new comments
  setTimeout(() => {
    initializeCommentInteractions();
  }, 100);
}

// Helper function to generate comment HTML
function generateCommentHTML(comment) {
  const timeAgo = getTimeAgo(comment.CREATED_AT);
  const userImage =
    comment.PROFILE_IMAGE ||
    "https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg";
  const currentUserId = window.DashboardConfig
    ? window.DashboardConfig.currentUserId
    : null;
  const isOwner = comment.USER_ID == currentUserId;

  // Handle comment reactions
  const hasUserReaction =
    comment.has_user_reaction === 1 || comment.has_user_reaction === "1";
  const userReactionType = comment.user_reaction_type;
  const userReactionEmoji = comment.user_reaction_emoji;
  const totalReactions = parseInt(comment.total_reactions) || 0;

  return `
        <div class="comment-item ${hasUserReaction ? "has-reaction" : ""}" data-comment-id="${comment.COMMENT_ID}">
            <img loading="lazy" decoding="async" src="${userImage}" alt="${escapeHtml(comment.NAME)}" class="comment-avatar" onerror="this.src='https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg'">
            <div class="comment-content">
                <div class="comment-author">${escapeHtml(comment.NAME)}</div>
                <div class="comment-text">${escapeHtml(comment.COMMENT_TEXT)}</div>
                
                ${
                  hasUserReaction
                    ? `
                    <div class="comment-reaction-display" style="margin: 8px 0; display: flex; align-items: center; gap: 6px; font-size: 14px; color: var(--primary-color); font-weight: 500;">
                        <span class="reaction-emoji">${userReactionEmoji}</span>
                        <span style="text-transform: capitalize;">You reacted with ${userReactionType}</span>
                    </div>
                `
                    : ""
                }
                
                ${
                  totalReactions > 0
                    ? `
                    <div class="comment-reaction-count" onclick="showCommentReactionUsers(${comment.COMMENT_ID})" 
                         style="font-size: 12px; color: #666; cursor: pointer; margin: 8px 0; display: inline-flex; align-items: center; gap: 5px; padding: 4px 8px; border-radius: 12px; background-color: #f0f0f0; transition: all 0.2s ease;">
                        <span>👥</span>
                        <span>${totalReactions} reaction${totalReactions !== 1 ? "s" : ""}</span>
                    </div>
                `
                    : ""
                }
                
                <div class="comment-time" style="font-size: 12px; color: #888; margin: 8px 0;">${timeAgo}</div>
                
                <div class="comment-actions" style="display: flex; align-items: center; gap: 15px; margin-top: 10px;">
                    <div class="comment-like-container" data-comment-id="${comment.COMMENT_ID}">
                        <button class="comment-like-btn ${hasUserReaction ? "active" : ""}" 
                                onclick="toggleCommentReactions(${comment.COMMENT_ID})" 
                                data-user-reaction="${userReactionType || ""}"
                                style="background: none; border: none; color: ${hasUserReaction ? "var(--primary-color)" : "#666"}; cursor: pointer; font-size: 12px; display: flex; align-items: center; gap: 5px; padding: 5px 8px; border-radius: 15px; transition: all 0.2s ease; font-weight: ${hasUserReaction ? "600" : "500"};">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" 
                                 fill="${hasUserReaction ? "var(--primary-color)" : "none"}" 
                                 stroke="${hasUserReaction ? "var(--primary-color)" : "currentColor"}" 
                                 stroke-width="2">
                                <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
                            </svg>
                            ${hasUserReaction ? userReactionType.charAt(0).toUpperCase() + userReactionType.slice(1) : "React"}
                        </button>
                        
                        <!-- Comment Food Reactions Panel -->
                        <div class="comment-food-reactions" id="comment-reactions-${comment.COMMENT_ID}">
                            <div class="comment-reaction-icon" data-reaction="yummy" data-comment-id="${comment.COMMENT_ID}" title="Yummy!" onclick="handleCommentReaction(${comment.COMMENT_ID}, 'yummy')">
                                <span>🍔</span>
                            </div>
                            <div class="comment-reaction-icon" data-reaction="delicious" data-comment-id="${comment.COMMENT_ID}" title="Delicious!" onclick="handleCommentReaction(${comment.COMMENT_ID}, 'delicious')">
                                <span>🍕</span>
                            </div>
                            <div class="comment-reaction-icon" data-reaction="tasty" data-comment-id="${comment.COMMENT_ID}" title="Tasty!" onclick="handleCommentReaction(${comment.COMMENT_ID}, 'tasty')">
                                <span>🍰</span>
                            </div>
                            <div class="comment-reaction-icon" data-reaction="love" data-comment-id="${comment.COMMENT_ID}" title="Love it!" onclick="handleCommentReaction(${comment.COMMENT_ID}, 'love')">
                                <span>🍲</span>
                            </div>
                            <div class="comment-reaction-icon" data-reaction="amazing" data-comment-id="${comment.COMMENT_ID}" title="Amazing!" onclick="handleCommentReaction(${comment.COMMENT_ID}, 'amazing')">
                                <span>🍗</span>
                            </div>
                            <div class="comment-reaction-icon" data-reaction="like" data-comment-id="${comment.COMMENT_ID}" title="Like!" onclick="handleCommentReaction(${comment.COMMENT_ID}, 'like')">
                                <span>👍</span>
                            </div>
                        </div>
                    </div>
                    
                    ${
                      isOwner
                        ? `
                        <button class="comment-delete-btn" onclick="deleteComment(${comment.COMMENT_ID})" 
                                style="background: none; border: none; color: #f44336; cursor: pointer; font-size: 12px; padding: 5px 8px; border-radius: 15px; transition: all 0.2s ease; font-weight: 500;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="m19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                            Delete
                        </button>
                    `
                        : ""
                    }
                </div>
            </div>
        </div>
    `;
}

// Function to initialize comment interactions
function initializeCommentInteractions() {
  console.log("🔧 Initializing comment interactions...");

  // Remove any existing event listeners to prevent duplicates
  document.removeEventListener("click", handleDocumentClick);

  // Add document click listener for closing reaction panels
  document.addEventListener("click", handleDocumentClick);

  // Initialize mobile touch support
  initializeMobileCommentSupport();
}

// Document click handler for closing reaction panels
function handleDocumentClick(e) {
  // Don't close if clicking inside a reaction panel or its trigger
  if (
    !e.target.closest(".comment-like-container") &&
    !e.target.closest(".comment-food-reactions")
  ) {
    closeAllCommentReactionPanels();
  }
}

// Function to close all comment reaction panels
function closeAllCommentReactionPanels() {
  document
    .querySelectorAll(".comment-like-container.active")
    .forEach((container) => {
      container.classList.remove("active");
    });

  // Remove mobile backdrops
  document
    .querySelectorAll(".comment-reaction-backdrop")
    .forEach((backdrop) => {
      backdrop.remove();
    });
}

// Function to toggle comment reaction panel
function toggleCommentReactions(commentId) {
  console.log("🎭 Toggling comment reactions for:", commentId);

  // Close all other reaction panels first
  document
    .querySelectorAll(".comment-like-container.active")
    .forEach((container) => {
      if (container.getAttribute("data-comment-id") != commentId) {
        container.classList.remove("active");
      }
    });

  // Remove existing backdrops
  document
    .querySelectorAll(".comment-reaction-backdrop")
    .forEach((backdrop) => backdrop.remove());

  // Toggle current panel
  const container = document.querySelector(
    `[data-comment-id="${commentId}"].comment-like-container`,
  );
  if (!container) {
    console.error("❌ Comment reaction container not found for:", commentId);
    return;
  }

  const isActive = container.classList.contains("active");

  if (isActive) {
    container.classList.remove("active");
  } else {
    container.classList.add("active");

    // Add mobile backdrop if needed
    if (window.innerWidth <= 768) {
      const backdrop = document.createElement("div");
      backdrop.className = "comment-reaction-backdrop";
      backdrop.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.05);
                z-index: 999;
                backdrop-filter: blur(1px);
            `;
      backdrop.addEventListener("click", () => {
        container.classList.remove("active");
        backdrop.remove();
      });
      document.body.appendChild(backdrop);
    }
  }
}

// Enhanced function to handle comment reactions
function handleCommentReaction(commentId, reactionType) {
  console.log(
    `👍 Handling comment reaction: ${reactionType} for comment:`,
    commentId,
  );

  // Add loading state
  const clickedIcon = document.querySelector(
    `[data-comment-id="${commentId}"] [data-reaction="${reactionType}"]`,
  );
  if (clickedIcon) {
    clickedIcon.style.opacity = "0.6";
    clickedIcon.style.transform = "scale(1.1)";
    clickedIcon.style.pointerEvents = "none";
  }

  const formData = new FormData();
  formData.append("action", "add_comment_reaction");
  formData.append("comment_id", commentId);
  formData.append("reaction_type", reactionType);

  fetch("comments.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return response.json();
    })
    .then((data) => {
      console.log("📨 Comment reaction response:", data);

      if (data.success) {
        // Update UI
        updateCommentReactionUI(commentId, data, reactionType);

        // Close reaction panel
        const container = document.querySelector(
          `[data-comment-id="${commentId}"].comment-like-container`,
        );
        if (container) {
          container.classList.remove("active");
        }

        // Remove backdrop
        document
          .querySelectorAll(".comment-reaction-backdrop")
          .forEach((backdrop) => backdrop.remove());

        // Show success message
        const messages = {
          added: `You reacted with ${reactionType}! ✨`,
          updated: `Changed reaction to ${reactionType}! 🔄`,
          removed: `Reaction removed! 👋`,
        };
        showToast(messages[data.action_type] || "Reaction updated!", "success");
      } else {
        throw new Error(data.message || "Failed to update reaction");
      }
    })
    .catch((error) => {
      console.error("❌ Error handling comment reaction:", error);
      showToast("Failed to update reaction. Please try again.", "error");
    })
    .finally(() => {
      // Remove loading state
      if (clickedIcon) {
        clickedIcon.style.opacity = "1";
        clickedIcon.style.transform = "scale(1)";
        clickedIcon.style.pointerEvents = "auto";
      }
    });
}

// Function to update comment reaction UI
function updateCommentReactionUI(commentId, responseData, newReaction) {
  console.log("🎨 Updating comment reaction UI for:", commentId);

  const commentItem = document.querySelector(
    `[data-comment-id="${commentId}"]`,
  );
  const container = document.querySelector(
    `[data-comment-id="${commentId}"].comment-like-container`,
  );
  const likeButton = container?.querySelector(".comment-like-btn");

  if (!commentItem || !container || !likeButton) {
    console.error("❌ Comment UI elements not found");
    return;
  }

  if (responseData.has_reaction) {
    // User has a reaction
    likeButton.classList.add("active");
    likeButton.setAttribute("data-user-reaction", responseData.reaction_type);
    likeButton.style.color = "var(--primary-color)";
    likeButton.style.fontWeight = "600";

    // Update button content
    const reactionText =
      responseData.reaction_type.charAt(0).toUpperCase() +
      responseData.reaction_type.slice(1);
    likeButton.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" 
                 fill="var(--primary-color)" stroke="var(--primary-color)" stroke-width="2">
                <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
            </svg>
            ${reactionText}
        `;

    commentItem.classList.add("has-reaction");

    // Update or add reaction display
    let reactionDisplay = commentItem.querySelector(
      ".comment-reaction-display",
    );
    if (reactionDisplay) {
      reactionDisplay.innerHTML = `
                <span class="reaction-emoji">${responseData.reaction_emoji}</span>
                <span style="text-transform: capitalize;">You reacted with ${responseData.reaction_type}</span>
            `;
    } else {
      const commentText = commentItem.querySelector(".comment-text");
      if (commentText) {
        commentText.insertAdjacentHTML(
          "afterend",
          `
                    <div class="comment-reaction-display" style="margin: 8px 0; display: flex; align-items: center; gap: 6px; font-size: 14px; color: var(--primary-color); font-weight: 500;">
                        <span class="reaction-emoji">${responseData.reaction_emoji}</span>
                        <span style="text-transform: capitalize;">You reacted with ${responseData.reaction_type}</span>
                    </div>
                `,
        );
      }
    }
  } else {
    // User removed reaction
    likeButton.classList.remove("active");
    likeButton.setAttribute("data-user-reaction", "");
    likeButton.style.color = "#666";
    likeButton.style.fontWeight = "500";

    // Reset button content
    likeButton.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" 
                 fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
            </svg>
            React
        `;

    commentItem.classList.remove("has-reaction");

    // Remove reaction display
    const reactionDisplay = commentItem.querySelector(
      ".comment-reaction-display",
    );
    if (reactionDisplay) {
      reactionDisplay.remove();
    }
  }

  // Add animation to clicked reaction icon
  const clickedIcon = container.querySelector(
    `[data-reaction="${newReaction}"]`,
  );
  if (clickedIcon && responseData.has_reaction) {
    clickedIcon.style.transform = "scale(1.3)";
    setTimeout(() => {
      clickedIcon.style.transform = "scale(1)";
    }, 200);
  }
}

// Enhanced function to add comment
function addComment() {
  console.log("💬 Adding new comment...");

  const commentInput = document.getElementById("commentInput");
  const submitBtn = document.getElementById("commentSubmit");

  if (!commentInput || !submitBtn) {
    console.error("❌ Comment input elements not found");
    return;
  }

  const commentText = commentInput.value.trim();

  if (!commentText) {
    showToast("Please write something!", "error");
    commentInput.focus();
    return;
  }

  if (!currentPostId) {
    showToast("Error: No post selected", "error");
    return;
  }

  if (commentText.length > 500) {
    showToast("Comment is too long (max 500 characters)", "error");
    return;
  }

  // Show loading state
  submitBtn.disabled = true;
  const originalContent = submitBtn.innerHTML;
  submitBtn.innerHTML = '<div class="loading-spinner small"></div>';

  const formData = new FormData();
  formData.append("action", "add_comment");
  formData.append("post_id", currentPostId);
  formData.append("comment_text", commentText);

  fetch("comments.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return response.json();
    })
    .then((data) => {
      console.log("📨 Add comment response:", data);

      if (data.success) {
        // Clear input
        commentInput.value = "";
        commentInput.style.height = "auto";

        // Show success
        showToast("Comment added! 🎉", "success");

        // Reload comments to show the new one
        currentOffset = 0;
        loadComments(currentPostId, true);

        // Update comment count in post
        updatePostCommentCount(currentPostId);
      } else {
        throw new Error(data.message || "Failed to add comment");
      }
    })
    .catch((error) => {
      console.error("❌ Error adding comment:", error);
      showToast("Failed to add comment. Please try again.", "error");
    })
    .finally(() => {
      // Reset submit button
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalContent;
      updateCommentSubmitButton();
    });
}

// Function to delete comment
function deleteComment(commentId) {
  if (!confirm("Are you sure you want to delete this comment?")) {
    return;
  }

  console.log("🗑️ Deleting comment:", commentId);

  const formData = new FormData();
  formData.append("action", "delete_comment");
  formData.append("comment_id", commentId);

  fetch("comments.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        showToast("Comment deleted! 🗑️", "success");

        // Animate and remove comment
        const commentElement = document.querySelector(
          `[data-comment-id="${commentId}"]`,
        );
        if (commentElement) {
          commentElement.style.transition = "all 0.3s ease";
          commentElement.style.opacity = "0";
          commentElement.style.transform = "translateX(-20px)";

          setTimeout(() => {
            commentElement.remove();
            updatePostCommentCount(currentPostId);
            totalCommentsCount = Math.max(0, totalCommentsCount - 1);
          }, 300);
        }
      } else {
        throw new Error(data.message || "Failed to delete comment");
      }
    })
    .catch((error) => {
      console.error("❌ Error deleting comment:", error);
      showToast("Failed to delete comment. Please try again.", "error");
    });
}

// FIXED: Function to show COMMENT reaction users with separate modal
function showCommentReactionUsers(commentId) {
  console.log("👥 Showing comment reaction users for:", commentId);

  const formData = new FormData();
  formData.append("action", "get_comment_reaction_users");
  formData.append("comment_id", commentId);

  // Use SEPARATE modal for comment reactions
  const modal = document.getElementById("commentReactionUsersModal");
  const usersList = document.getElementById("commentReactionUsersList");
  const modalTitle = modal?.querySelector("h2");

  if (!modal || !usersList) {
    console.error("❌ Comment reaction users modal not found");
    return;
  }

  // Set the correct title for COMMENT reactions
  if (modalTitle) {
    modalTitle.textContent = "People who reacted to this comment";
  }

  usersList.innerHTML =
    '<div class="loading-spinner" style="margin: 20px auto;"></div><p style="text-align: center; color: #666;">Loading reactions...</p>';
  modal.style.display = "block";

  fetch("comments.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        displayCommentReactionUsers(data.users);
      } else {
        throw new Error(data.message || "Failed to load reactions");
      }
    })
    .catch((error) => {
      console.error("❌ Error loading comment reaction users:", error);
      usersList.innerHTML =
        '<p style="text-align: center; padding: 20px; color: #f44336;">Failed to load reactions</p>';
    });
}

// FIXED: Separate display function for COMMENT reactions
function displayCommentReactionUsers(users) {
  const usersList = document.getElementById("commentReactionUsersList");
  if (!usersList) return;

  if (!users || users.length === 0) {
    usersList.innerHTML =
      '<p style="text-align: center; padding: 30px; color: #666; font-size: 16px;">No reactions yet</p>';
    return;
  }

  let html = "";
  users.forEach((user) => {
    const userImage =
      user.PROFILE_IMAGE ||
      "https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg";
    const reactionTime = formatReactionTime(user.CREATED_AT);
    const reactionType =
      user.REACTION_TYPE?.charAt(0).toUpperCase() +
        user.REACTION_TYPE?.slice(1) || "Unknown";
    const reactionEmoji = user.REACTION_EMOJI || "👍";

    html += `
            <div class="reaction-user-item" style="display: flex; align-items: center; padding: 15px; border-bottom: 1px solid #eee; transition: background-color 0.2s; cursor: pointer;">
                <img loading="lazy" decoding="async" src="${userImage}" alt="${escapeHtml(user.NAME || "User")}" class="reaction-user-avatar" 
                     style="width: 50px; height: 50px; border-radius: 50%; margin-right: 15px; object-fit: cover; border: 2px solid #f0f0f0;"
                     onerror="this.src='https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg'">
                <div class="reaction-user-info" style="flex: 1;">
                    <div class="reaction-user-name" style="font-weight: 600; margin-bottom: 5px; color: #333; font-size: 16px;">${escapeHtml(user.NAME || "Unknown User")}</div>
                    <div class="reaction-user-time" style="font-size: 13px; color: #666;">Reacted ${reactionTime}</div>
                </div>
                <div class="reaction-user-emoji" style="font-size: 28px; margin-left: 15px; display: flex; flex-direction: column; align-items: center;">
                    <span>${reactionEmoji}</span>
                    <small style="font-size: 11px; color: #888; margin-top: 3px; text-transform: capitalize;">${reactionType}</small>
                </div>
            </div>
        `;
  });

  usersList.innerHTML = html;

  // Add hover effects
  usersList.querySelectorAll(".reaction-user-item").forEach((item) => {
    item.addEventListener("mouseenter", function () {
      this.style.backgroundColor = "#f8f9fa";
    });
    item.addEventListener("mouseleave", function () {
      this.style.backgroundColor = "transparent";
    });
  });
}

// Function to initialize mobile comment support
function initializeMobileCommentSupport() {
  const containers = document.querySelectorAll(".comment-like-container");

  containers.forEach((container) => {
    let touchStartTime = 0;

    container.addEventListener(
      "touchstart",
      function (e) {
        touchStartTime = Date.now();
      },
      {
        passive: true,
      },
    );

    container.addEventListener("touchend", function (e) {
      const touchDuration = Date.now() - touchStartTime;

      if (touchDuration < 300) {
        // Quick tap
        e.preventDefault();
        const commentId = this.getAttribute("data-comment-id");
        if (commentId) {
          toggleCommentReactions(commentId);
        }
      }
    });
  });
}

// Function to initialize comment reaction handlers
function initializeCommentReactionHandlers() {
  console.log("🎭 Initializing comment reaction handlers...");

  // This function is called during initialization
  // Most handlers are attached dynamically when comments are loaded
}

// Function to update comment submit button state
function updateCommentSubmitButton() {
  const commentInput = document.getElementById("commentInput");
  const submitBtn = document.getElementById("commentSubmit");

  if (!commentInput || !submitBtn) return;

  const hasText = commentInput.value.trim().length > 0;
  const isNotTooLong = commentInput.value.length <= 500;

  submitBtn.disabled = !hasText || !isNotTooLong || commentsLoading;

  // Update visual state
  if (hasText && isNotTooLong) {
    submitBtn.style.opacity = "1";
    submitBtn.style.transform = "scale(1)";
  } else {
    submitBtn.style.opacity = "0.6";
    submitBtn.style.transform = "scale(0.95)";
  }
}

// Function to update post comment count
function updatePostCommentCount(postId) {
  const formData = new FormData();
  formData.append("action", "get_comment_count");
  formData.append("post_id", postId);

  fetch("comments.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        const countElement = document.getElementById(`comment-count-${postId}`);
        if (countElement) {
          const count = data.count;
          const text =
            count === 0
              ? "0 comments"
              : count === 1
                ? "1 comment"
                : `${count} comments`;
          countElement.textContent = text;

          // Animate the update
          countElement.style.transform = "scale(1.1)";
          setTimeout(() => {
            countElement.style.transform = "scale(1)";
          }, 200);
        }
      }
    })
    .catch((error) => {
      console.error("Error updating comment count:", error);
    });
}

// Function to show comments error
function showCommentsError(message) {
  const commentsBody = document.getElementById("commentsBody");
  if (!commentsBody) return;

  commentsBody.innerHTML = `
        <div style="text-align: center; padding: 40px; color: #f44336;">
            <div style="font-size: 48px; margin-bottom: 15px;">⚠️</div>
            <p style="margin-bottom: 20px;">${message}</p>
            <button onclick="loadComments(currentPostId, true)" 
                    style="background: #ED5A2C; color: white; border: none; padding: 10px 20px; border-radius: 20px; cursor: pointer;">
                Try Again
            </button>
        </div>
    `;
}

// Utility function to format time
function getTimeAgo(dateString) {
  const now = new Date();
  const date = new Date(dateString);
  const diff = Math.floor((now - date) / 1000);

  if (diff < 30) return "Just now";
  if (diff < 60) return `${diff}s ago`;
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
  if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;

  return date.toLocaleDateString();
}

// Enhanced toast notification function
function showToast(message, type = "success") {
  // Remove existing toasts
  document.querySelectorAll(".toast").forEach((toast) => toast.remove());

  const toast = document.createElement("div");
  toast.className = `toast ${type}`;
  toast.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: ${type === "success" ? "#4CAF50" : "#f44336"};
        color: white;
        padding: 12px 20px;
        border-radius: 25px;
        font-size: 14px;
        font-weight: 500;
        z-index: 10000;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        animation: slideInUp 0.3s ease, slideOutDown 0.3s ease 2.7s forwards;
        display: flex;
        align-items: center;
        gap: 8px;
        max-width: 300px;
    `;

  const icon = type === "success" ? "✅" : "❌";
  toast.innerHTML = `<span>${icon}</span><span>${message}</span>`;

  // Add animation styles if not already present
  if (!document.getElementById("toast-animations")) {
    const style = document.createElement("style");
    style.id = "toast-animations";
    style.textContent = `
            @keyframes slideInUp {
                from { opacity: 0; transform: translateY(100px); }
                to { opacity: 1; transform: translateY(0); }
            }
            @keyframes slideOutDown {
                from { opacity: 1; transform: translateY(0); }
                to { opacity: 0; transform: translateY(100px); }
            }
        `;
    document.head.appendChild(style);
  }

  document.body.appendChild(toast);

  setTimeout(() => {
    if (toast.parentNode) {
      toast.remove();
    }
  }, 3000);
}

// Global functions for backward compatibility
window.openCommentsModal = openCommentsModal;
window.closeCommentsModal = closeCommentsModal;
window.loadMoreComments = loadMoreComments;
window.toggleCommentReactions = toggleCommentReactions;
window.handleCommentReaction = handleCommentReaction;
window.addComment = addComment;
window.deleteComment = deleteComment;
window.showCommentReactionUsers = showCommentReactionUsers;

console.log("✅ Enhanced Comments System JavaScript Loaded Successfully!");

// ========================================
// ENHANCED STORY UPLOAD SYSTEM - PREMIUM UI/UX WITH INTERACTIVE BOARD
// ========================================

// State management for story files
let selectedStoryFiles = [];
let selectedObjectUrls = [];

// Enhanced Story Upload Handler (Entry Point)
function handleStoryUpload(input) {
  console.log("📸 Story upload initiated");

  if (!input.files || input.files.length === 0) {
    console.log("❌ No files selected");
    return;
  }

  const files = Array.from(input.files);
  processFiles(files);
  
  // Reset the input value so the same files can be selected again if needed
  input.value = "";
}

// Helper to asynchronously get video duration using temporary HTML5 video element
function getVideoDuration(file) {
  return new Promise((resolve) => {
    const video = document.createElement("video");
    video.preload = "metadata";
    video.onloadedmetadata = function () {
      window.URL.revokeObjectURL(video.src);
      resolve(video.duration);
    };
    video.onerror = function () {
      window.URL.revokeObjectURL(video.src);
      resolve(0);
    };
    video.src = URL.createObjectURL(file);
  });
}

// Process and validate files
async function processFiles(files) {
  const maxFiles = 10;
  const maxSize = 50 * 1024 * 1024; // 50MB max per file
  const allowedTypes = [
    "image/jpeg",
    "image/jpg",
    "image/png",
    "image/gif",
    "image/webp",
    "video/mp4",
    "video/quicktime",
    "video/webm",
    "video/mov",
  ];

  const invalidFiles = [];
  const oversizedFiles = [];
  const tooLongVideos = [];
  const newFiles = [];

  for (const file of files) {
    // Check for duplicates (same name and size)
    const isDuplicate = selectedStoryFiles.some(
      (existingFile) => existingFile.name === file.name && existingFile.size === file.size
    );

    if (isDuplicate) {
      console.log(`ℹ️ Skipping duplicate file: ${file.name}`);
      continue;
    }

    // Check file type
    if (!allowedTypes.includes(file.type)) {
      invalidFiles.push(file.name);
      continue;
    }

    // Check file size
    if (file.size > maxSize) {
      oversizedFiles.push(file.name);
      continue;
    }

    // Check video duration (max 1 minute / 60 seconds)
    if (file.type.startsWith("video/")) {
      const duration = await (window.getVideoDuration || getVideoDuration)(file);
      if (duration > 60) {
        tooLongVideos.push(file.name);
        continue;
      }
    }

    newFiles.push(file);
  }

  // Display validation errors if any
  if (invalidFiles.length > 0) {
    showToast(
      `Unsupported formats: ${invalidFiles.slice(0, 3).join(", ")}${invalidFiles.length > 3 ? "..." : ""}. Only images/videos allowed.`,
      "error"
    );
    return;
  }

  if (oversizedFiles.length > 0) {
    showToast(
      `Files too large (Max 50MB): ${oversizedFiles.slice(0, 3).join(", ")}${oversizedFiles.length > 3 ? "..." : ""}.`,
      "error"
    );
    return;
  }

  if (tooLongVideos.length > 0) {
    showToast(
      `Videos too long (Max 1 min): ${tooLongVideos.slice(0, 3).join(", ")}${tooLongVideos.length > 3 ? "..." : ""}.`,
      "error"
    );
    return;
  }

  // Check overall limit
  if (selectedStoryFiles.length + newFiles.length > maxFiles) {
    showToast(`Maximum of ${maxFiles} stories can be uploaded at once.`, "error");
    return;
  }

  // Add validated files to list
  if (newFiles.length > 0) {
    selectedStoryFiles = [...selectedStoryFiles, ...newFiles];
    showStoryUploadPreview();
  }
}

// Show/Update Story Upload Preview Modal
function showStoryUploadPreview() {
  console.log("🖼️ Updating story upload preview board for", selectedStoryFiles.length, "files");

  if (selectedStoryFiles.length === 0) {
    closeStoryUploadModal();
    return;
  }

  let modal = document.getElementById("storyUploadModal");

  // Create modal markup if it does not exist
  if (!modal) {
    modal = document.createElement("div");
    modal.id = "storyUploadModal";
    modal.className = "story-upload-modal";
    document.body.appendChild(modal);
    
    // Add backdrop close support
    modal.addEventListener("click", function (e) {
      if (e.target === modal) {
        closeStoryUploadModal();
      }
    });
  }

  // HTML content of the modal
  modal.innerHTML = `
    <div class="story-upload-content">
      <!-- Modal Header -->
      <div class="story-upload-header">
        <div>
          <h3 style="margin: 0; font-size: 20px; font-weight: 700; font-family: 'DM Serif Display', serif;">Upload Stories</h3>
          <p style="margin: 4px 0 0 0; font-size: 13px; opacity: 0.95; font-weight: 500;">
            ${selectedStoryFiles.length} file${selectedStoryFiles.length !== 1 ? "s" : ""} selected for upload
          </p>
        </div>
        <button onclick="closeStoryUploadModal()" class="story-modal-close-btn" aria-label="Close modal">
          ×
        </button>
      </div>

      <!-- Preview Grid Board -->
      <div class="story-preview-grid" id="storyPreviewGrid">
        <!-- Rendered dynamically -->
      </div>

      <!-- Modal Footer -->
      <div class="story-upload-footer">
        <div class="story-visibility-selector">
          <label class="visibility-radio-label">
            <input type="radio" name="storyVisibility" value="public" checked>
            <span class="custom-radio-btn"></span>
            <span>Public</span>
          </label>
          <label class="visibility-radio-label">
            <input type="radio" name="storyVisibility" value="friends">
            <span class="custom-radio-btn"></span>
            <span>Friends</span>
          </label>
        </div>
        <div class="story-modal-actions">
          <button onclick="closeStoryUploadModal()" class="story-cancel-btn">
            Cancel
          </button>
          <button onclick="uploadStories()" id="uploadStoriesBtn" class="story-submit-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right: 4px;">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
              <polyline points="7 10l5-5 5 5"></polyline>
              <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
            Upload ${selectedStoryFiles.length} Stor${selectedStoryFiles.length !== 1 ? "ies" : "y"}
          </button>
        </div>
      </div>
    </div>
    
    <!-- Hidden input to add more files to the board -->
    <input type="file" id="storyMediaMore" class="story-media-input"
           accept="image/*,video/mp4,video/quicktime,video/webm"
           multiple
           onchange="handleAddMoreStories(this)">
  `;

  // Render the preview items inside the grid
  const gridContainer = document.getElementById("storyPreviewGrid");
  
  // Revoke previously created object URLs to prevent leaks
  selectedObjectUrls.forEach(url => URL.revokeObjectURL(url));
  selectedObjectUrls = [];

  selectedStoryFiles.forEach((file, index) => {
    const objectUrl = URL.createObjectURL(file);
    selectedObjectUrls.push(objectUrl);

    const card = document.createElement("div");
    card.className = "story-preview-item";

    const isVideo = file.type.startsWith("video/");
    const badgeText = isVideo ? "video" : "image";

    card.innerHTML = `
      ${
        isVideo
          ? `<video src="${objectUrl}" muted loop autoplay playsinline></video>`
          : `<img src="${objectUrl}" alt="Preview thumbnail">`
      }
      <div class="story-preview-badge">${badgeText}</div>
      <button onclick="removeStoryFile(${index})" class="story-preview-remove" title="Remove file">
        ×
      </button>
      <div class="story-preview-filename-tooltip">${escapeHtml(file.name)}</div>
    `;

    gridContainer.appendChild(card);
  });

  // Append the "+" interactive board/card at the end of the grid
  const addCard = document.createElement("div");
  addCard.className = "story-preview-add-card";
  addCard.onclick = triggerAddMoreStories;
  addCard.innerHTML = `
    <div class="preview-add-plus-icon">
      <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
        <line x1="12" y1="5" x2="12" y2="19"></line>
        <line x1="5" y1="12" x2="19" y2="12"></line>
      </svg>
    </div>
    <span>Add More</span>
  `;
  gridContainer.appendChild(addCard);

  // Setup modal CSS styles if not already present
  ensureModalStyles();
}

// Trigger click on hidden file input to append more files
function triggerAddMoreStories() {
  const moreInput = document.getElementById("storyMediaMore");
  if (moreInput) {
    moreInput.click();
  }
}

// Handle adding more files from inside the modal
function handleAddMoreStories(input) {
  if (input.files && input.files.length > 0) {
    processFiles(Array.from(input.files));
  }
  input.value = "";
}

// Remove specific file from the upload board
function removeStoryFile(index) {
  if (index >= 0 && index < selectedStoryFiles.length) {
    console.log("🗑️ Removing preview item at index:", index);
    selectedStoryFiles.splice(index, 1);
    showStoryUploadPreview();
  }
}

// Close Story Upload Modal and reset state
function closeStoryUploadModal() {
  const modal = document.getElementById("storyUploadModal");
  if (modal) {
    modal.style.animation = "storyModalFadeOut 0.25s ease-out forwards";
    const modalContent = modal.querySelector(".story-upload-content");
    if (modalContent) {
      modalContent.style.animation = "storyModalSlideDown 0.25s ease-out forwards";
    }

    setTimeout(() => {
      if (modal.parentNode) {
        modal.parentNode.removeChild(modal);
      }
    }, 250);
  }

  // Revoke object URLs to free memory
  selectedObjectUrls.forEach(url => URL.revokeObjectURL(url));
  selectedObjectUrls = [];
  selectedStoryFiles = [];
}

// Upload Stories via XMLHttpRequest with Real Upload Progress
async function uploadStories() {
  const uploadBtn = document.getElementById("uploadStoriesBtn");

  if (selectedStoryFiles.length === 0) {
    console.error("❌ No files to upload");
    return;
  }

  const visibilityElement = document.querySelector('input[name="storyVisibility"]:checked');
  const visibility = visibilityElement ? visibilityElement.value : "public";

  console.log(`📤 Uploading ${selectedStoryFiles.length} stories directly to Cloudinary (Visibility: ${visibility})`);

  // Show loading/progress state
  uploadBtn.disabled = true;
  uploadBtn.innerHTML = `
    <div class="upload-spinner" style="margin-right: 8px;"></div>
    <span>Uploading 0%</span>
  `;

  // Disable cancel buttons inside modal during upload
  const cancelBtn = document.querySelector(".story-cancel-btn");
  if (cancelBtn) cancelBtn.disabled = true;
  const closeBtn = document.querySelector(".story-modal-close-btn");
  if (closeBtn) closeBtn.style.display = "none";
  const removeButtons = document.querySelectorAll(".story-preview-remove");
  removeButtons.forEach(btn => btn.style.display = "none");
  const addCard = document.querySelector(".story-preview-add-card");
  if (addCard) addCard.style.pointerEvents = "none";

  try {
    const formData = new FormData();

    // Attach all files to the array
    selectedStoryFiles.forEach((file) => {
      formData.append("story_media[]", file);
    });

    formData.append("visibility", visibility);

    // Establish XHR request for upload progress callback
    const xhr = new XMLHttpRequest();
    xhr.open("POST", "story.php", true);

    // Track upload progress
    xhr.upload.onprogress = function (e) {
      if (e.lengthComputable) {
        const percent = Math.round((e.loaded / e.total) * 100);
        // Map 0-99% progress
        if (percent < 100) {
          uploadBtn.innerHTML = `
            <div class="upload-spinner" style="margin-right: 8px;"></div>
            <span>Uploading ${percent}%</span>
          `;
        } else {
          // 100% means upload is done; Cloudinary is processing/optimizing the files
          uploadBtn.innerHTML = `
            <div class="upload-spinner" style="margin-right: 8px;"></div>
            <span>Optimizing...</span>
          `;
        }
      }
    };

    // Callback on request complete
    xhr.onload = function () {
      if (xhr.status >= 200 && xhr.status < 300) {
        showToast("✨ Stories uploaded and compressed successfully!", "success");
        closeStoryUploadModal();
        
        // Refresh page to load stories from database
        setTimeout(() => {
          window.location.reload();
        }, 1200);
      } else {
        console.error("❌ Story upload failed with status:", xhr.status);
        showToast("Failed to upload stories. Please try again.", "error");
        resetUploadButton();
      }
    };

    // Callback on network error
    xhr.onerror = function () {
      console.error("❌ Story upload network error");
      showToast("Network error. Check connection and try again.", "error");
      resetUploadButton();
    };

    xhr.send(formData);

  } catch (error) {
    console.error("❌ Catch error during story upload:", error);
    showToast("An unexpected error occurred. Please try again.", "error");
    resetUploadButton();
  }
}

// Reset upload button UI state
function resetUploadButton() {
  const uploadBtn = document.getElementById("uploadStoriesBtn");
  if (!uploadBtn) return;

  const cancelBtn = document.querySelector(".story-cancel-btn");
  if (cancelBtn) cancelBtn.disabled = false;
  const closeBtn = document.querySelector(".story-modal-close-btn");
  if (closeBtn) closeBtn.style.display = "flex";
  const removeButtons = document.querySelectorAll(".story-preview-remove");
  removeButtons.forEach(btn => btn.style.display = "flex");
  const addCard = document.querySelector(".story-preview-add-card");
  if (addCard) addCard.style.pointerEvents = "auto";

  uploadBtn.disabled = false;
  uploadBtn.innerHTML = `
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right: 4px;">
      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
      <polyline points="7 10l5-5 5 5"></polyline>
      <line x1="12" y1="15" x2="12" y2="3"></line>
    </svg>
    Upload ${selectedStoryFiles.length} Stor${selectedStoryFiles.length !== 1 ? "ies" : "y"}
  `;
}

// Drag & drop handlers on dashboard story upload circle
function initializeStoryUpload() {
  console.log("🚀 Initializing premium story upload drag & drop handlers");

  const storyForm = document.getElementById("storyForm");
  const storyInput = document.getElementById("storyMedia");
  const addStoryLabel = document.querySelector(".add-story-label");

  if (!storyForm || !storyInput || !addStoryLabel) {
    console.warn("⚠️ Story upload elements not found in dashboard");
    return;
  }

  let dragCounter = 0;

  // Drag and drop event listeners
  addStoryLabel.addEventListener("dragenter", function (e) {
    e.preventDefault();
    e.stopPropagation();
    dragCounter++;
    this.style.transform = "scale(1.04)";
    this.style.backgroundColor = "rgba(237, 90, 44, 0.05)";
    const addIcon = this.querySelector(".story-add-icon");
    if (addIcon) {
      addIcon.style.borderColor = "#ED5A2C";
      addIcon.style.backgroundColor = "rgba(237, 90, 44, 0.08)";
    }
  });

  addStoryLabel.addEventListener("dragleave", function (e) {
    e.preventDefault();
    e.stopPropagation();
    dragCounter--;
    if (dragCounter === 0) {
      this.style.transform = "";
      this.style.backgroundColor = "";
      const addIcon = this.querySelector(".story-add-icon");
      if (addIcon) {
        addIcon.style.borderColor = "";
        addIcon.style.backgroundColor = "";
      }
    }
  });

  addStoryLabel.addEventListener("dragover", function (e) {
    e.preventDefault();
    e.stopPropagation();
  });

  addStoryLabel.addEventListener("drop", function (e) {
    e.preventDefault();
    e.stopPropagation();
    dragCounter = 0;

    // Reset circle hover states
    this.style.transform = "";
    this.style.backgroundColor = "";
    const addIcon = this.querySelector(".story-add-icon");
    if (addIcon) {
      addIcon.style.borderColor = "";
      addIcon.style.backgroundColor = "";
    }

    const files = e.dataTransfer.files;
    if (files && files.length > 0) {
      processFiles(Array.from(files));
    }
  });
}

// Add CSS styles for the preview modal board dynamically
function ensureModalStyles() {
  if (document.getElementById("storyUploadBoardStyles")) return;

  const styleTag = document.createElement("style");
  styleTag.id = "storyUploadBoardStyles";
  styleTag.textContent = `
    /* Modal Backdrop */
    .story-upload-modal {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.75);
      backdrop-filter: blur(10px);
      z-index: 10000;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    /* Modal Content Box */
    .story-upload-content {
      background: white;
      border-radius: 24px;
      width: 620px;
      max-width: 100%;
      max-height: 85vh;
      overflow: hidden;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
      display: flex;
      flex-direction: column;
      border: 1px solid rgba(0,0,0,0.05);
      animation: storyModalSlideUp 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    /* Modal Header */
    .story-upload-header {
      background: linear-gradient(135deg, #ED5A2C 0%, #ff6b3d 100%);
      color: white;
      padding: 20px 25px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .story-modal-close-btn {
      background: rgba(255, 255, 255, 0.2);
      border: none;
      color: white;
      width: 36px;
      height: 36px;
      border-radius: 50%;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      font-weight: 300;
      transition: all 0.2s ease;
    }

    .story-modal-close-btn:hover {
      background: rgba(255, 255, 255, 0.35);
      transform: scale(1.05) rotate(90deg);
    }

    /* Grid layout */
    .story-preview-grid {
      padding: 25px;
      overflow-y: auto;
      max-height: 52vh;
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
      gap: 15px;
      background-color: #fafafa;
    }

    /* Preview Item Card */
    .story-preview-item {
      position: relative;
      aspect-ratio: 9 / 16;
      background: #eee;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 4px 10px rgba(0,0,0,0.05);
      border: 2px solid white;
      transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
    }

    .story-preview-item:hover {
      transform: translateY(-4px);
      box-shadow: 0 10px 20px rgba(237, 90, 44, 0.15);
      border-color: rgba(237, 90, 44, 0.3);
    }

    .story-preview-item img, 
    .story-preview-item video {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    /* Close Button on Card */
    .story-preview-remove {
      position: absolute;
      top: 8px;
      right: 8px;
      width: 24px;
      height: 24px;
      border-radius: 50%;
      background: rgba(0, 0, 0, 0.5);
      backdrop-filter: blur(4px);
      border: 1px solid rgba(255, 255, 255, 0.25);
      color: white;
      font-size: 16px;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.2s ease;
      z-index: 5;
    }

    .story-preview-remove:hover {
      background: #f44336;
      border-color: #f44336;
      transform: scale(1.15) rotate(90deg);
    }

    /* Media badge label */
    .story-preview-badge {
      position: absolute;
      top: 8px;
      left: 8px;
      background: rgba(0, 0, 0, 0.6);
      color: white;
      padding: 2px 7px;
      border-radius: 12px;
      font-size: 9px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      backdrop-filter: blur(3px);
      border: 1px solid rgba(255, 255, 255, 0.15);
      z-index: 4;
    }

    /* Tooltip tooltip on hover showing actual filename */
    .story-preview-filename-tooltip {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      background: rgba(0, 0, 0, 0.7);
      color: white;
      padding: 4px 6px;
      font-size: 9px;
      text-align: center;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      opacity: 0;
      transition: opacity 0.2s ease;
      pointer-events: none;
      z-index: 4;
    }

    .story-preview-item:hover .story-preview-filename-tooltip {
      opacity: 1;
    }

    /* Add Card (+) */
    .story-preview-add-card {
      aspect-ratio: 9 / 16;
      border-radius: 16px;
      border: 2px dashed rgba(237, 90, 44, 0.35);
      background: rgba(237, 90, 44, 0.02);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      color: #ED5A2C;
      font-weight: 700;
      font-size: 12px;
      gap: 10px;
      transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
    }

    .story-preview-add-card:hover {
      background: rgba(237, 90, 44, 0.08);
      border-color: #ED5A2C;
      transform: translateY(-4px);
      box-shadow: 0 10px 20px rgba(237, 90, 44, 0.1);
    }

    .preview-add-plus-icon {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: linear-gradient(135deg, #ED5A2C 0%, #ff6b3d 100%);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 8px rgba(237, 90, 44, 0.3);
      transition: all 0.3s ease;
    }

    .story-preview-add-card:hover .preview-add-plus-icon {
      transform: scale(1.1) rotate(90deg);
      box-shadow: 0 6px 14px rgba(237, 90, 44, 0.4);
    }

    /* Footer */
    .story-upload-footer {
      padding: 20px 25px;
      background: white;
      border-top: 1px solid #f0f0f0;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    /* Radio buttons visibility style */
    .story-visibility-selector {
      display: flex;
      gap: 15px;
    }

    .visibility-radio-label {
      display: flex;
      align-items: center;
      gap: 6px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 600;
      color: #666;
      position: relative;
      user-select: none;
    }

    .visibility-radio-label input {
      position: absolute;
      opacity: 0;
      cursor: pointer;
    }

    .custom-radio-btn {
      width: 16px;
      height: 16px;
      border: 2px solid #ccc;
      border-radius: 50%;
      display: inline-block;
      position: relative;
      transition: all 0.2s ease;
    }

    .visibility-radio-label:hover input ~ .custom-radio-btn {
      border-color: #ED5A2C;
    }

    .visibility-radio-label input:checked ~ .custom-radio-btn {
      border-color: #ED5A2C;
      background-color: #ED5A2C;
    }

    .custom-radio-btn::after {
      content: "";
      position: absolute;
      display: none;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: white;
    }

    .visibility-radio-label input:checked ~ .custom-radio-btn::after {
      display: block;
    }

    .visibility-radio-label input:checked ~ span:not(.custom-radio-btn) {
      color: #ED5A2C;
    }

    /* Button actions */
    .story-modal-actions {
      display: flex;
      gap: 10px;
    }

    .story-cancel-btn {
      background: #f5f5f5;
      color: #666;
      border: none;
      padding: 10px 18px;
      border-radius: 30px;
      font-weight: 600;
      font-size: 13px;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .story-cancel-btn:hover:not(:disabled) {
      background: #e5e5e5;
      color: #333;
    }

    .story-submit-btn {
      background: linear-gradient(135deg, #ED5A2C 0%, #ff6b3d 100%);
      color: white;
      border: none;
      padding: 10px 22px;
      border-radius: 30px;
      font-weight: 600;
      font-size: 13px;
      cursor: pointer;
      box-shadow: 0 4px 10px rgba(237, 90, 44, 0.25);
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
    }

    .story-submit-btn:hover:not(:disabled) {
      transform: translateY(-1px);
      box-shadow: 0 6px 14px rgba(237, 90, 44, 0.35);
      filter: brightness(1.05);
    }

    .story-submit-btn:disabled,
    .story-cancel-btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none !important;
      box-shadow: none !important;
    }

    /* Scrollbar styles for the preview grid */
    .story-preview-grid::-webkit-scrollbar {
      width: 6px;
    }

    .story-preview-grid::-webkit-scrollbar-track {
      background: #f0f0f0;
      border-radius: 3px;
    }

    .story-preview-grid::-webkit-scrollbar-thumb {
      background: #ED5A2C;
      border-radius: 3px;
    }

    .story-preview-grid::-webkit-scrollbar-thumb:hover {
      background: #d94e22;
    }

    /* Keyframes animations */
    @keyframes storyModalSlideUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes storyModalSlideDown {
      from {
        opacity: 1;
        transform: translateY(0);
      }
      to {
        opacity: 0;
        transform: translateY(30px);
      }
    }

    @keyframes storyModalFadeIn {
      from {
        opacity: 0;
      }
      to {
        opacity: 1;
      }
    }

    @keyframes storyModalFadeOut {
      from {
        opacity: 1;
      }
      to {
        opacity: 0;
      }
    }

    /* Mobile media responsiveness overrides */
    @media (max-width: 576px) {
      .story-upload-content {
        max-height: 90vh;
        width: 100%;
        border-radius: 20px;
      }

      .story-preview-grid {
        grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
        gap: 10px;
        padding: 15px;
        max-height: 58vh;
      }

      .story-upload-header {
        padding: 15px;
      }

      .story-upload-footer {
        padding: 15px;
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
      }

      .story-visibility-selector {
        justify-content: center;
      }

      .story-modal-actions {
        justify-content: space-between;
      }

      .story-submit-btn,
      .story-cancel-btn {
        flex: 1;
        justify-content: center;
        padding: 12px;
      }
    }
  `;
  document.head.appendChild(styleTag);
}

// Initialise story upload systems
document.addEventListener("DOMContentLoaded", function () {
  setTimeout(() => {
    initializeStoryUpload();
  }, 500);
});

// Export handlers to window object for HTML inline attribute hooks
window.handleStoryUpload = handleStoryUpload;
window.showStoryUploadPreview = showStoryUploadPreview;
window.triggerAddMoreStories = triggerAddMoreStories;
window.handleAddMoreStories = handleAddMoreStories;
window.removeStoryFile = removeStoryFile;
window.closeStoryUploadModal = closeStoryUploadModal;
window.uploadStories = uploadStories;
window.initializeStoryUpload = initializeStoryUpload;

console.log("✅ Enhanced Story Upload System Board Loaded Successfully!");
