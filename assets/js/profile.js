/* Extracted from profile.php inline <script> (refactor). */

    function updateFileName(input) {
      const fileName = input.files[0] ? input.files[0].name : 'No file chosen';
      document.getElementById('file-name').textContent = fileName;
      
      // Preview image if selected
      if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
          const profileContainer = document.querySelector('.profile-image-container');
          
          // Check if there's already an image or placeholder
          if (profileContainer.querySelector('.profile-image')) {
            profileContainer.querySelector('.profile-image').src = e.target.result;
          } else if (profileContainer.querySelector('.profile-placeholder')) {
            // Remove placeholder and add image
            profileContainer.innerHTML = '<img src="' + e.target.result + '" alt="Profile Image" class="profile-image">';
          }
        };
        reader.readAsDataURL(input.files[0]);
      }
    }
  
