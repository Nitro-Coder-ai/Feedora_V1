/* Extracted from sidebar.php inline <script> (refactor). */

    // Function to update user online status
    function updateOnlineStatus() {
        const formData = new FormData();
        formData.append('action', 'update_status');
        
        fetch('chat.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Status updated successfully
            }
        })
        .catch(error => {
            console.error('Error updating online status:', error);
        });
    }

    // Update online status immediately when page loads
    updateOnlineStatus();

    // Update online status every 30 seconds
    setInterval(updateOnlineStatus, 30000);
