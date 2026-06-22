/* Extracted from view-recipe.php inline <script> (refactor). PHP values via window.ViewRecipeConfig. */

        document.addEventListener('DOMContentLoaded', function() {
            // Save ingredient checked state to localStorage
            const checkboxes = document.querySelectorAll('.ingredient-checkbox');
            const recipeId = window.ViewRecipeConfig.recipeId;
            
            // Load saved state
            checkboxes.forEach(checkbox => {
                const id = checkbox.id;
                const savedState = localStorage.getItem(`recipe_${recipeId}_${id}`);
                if (savedState === 'true') {
                    checkbox.checked = true;
                }
                
                // Save state on change
                checkbox.addEventListener('change', function() {
                    localStorage.setItem(`recipe_${recipeId}_${id}`, this.checked);
                });
            });
        });

        if (window.ViewRecipeConfig && window.ViewRecipeConfig.isOwner) {
        // Edit modal functions
        window.openEditModal = function openEditModal() {
            document.getElementById('editRecipeModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        window.closeEditModal = function closeEditModal() {
            document.getElementById('editRecipeModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Delete confirmation
        window.confirmDelete = function confirmDelete() {
            if (confirm('Are you sure you want to delete this recipe? This action cannot be undone and will remove the recipe from all collections.')) {
                document.getElementById('deleteForm').submit();
            }
        }

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('editRecipeModal');
            if (event.target === modal) {
                closeEditModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeEditModal();
            }
        });
        }
