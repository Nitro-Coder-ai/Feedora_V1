        // PHP values are injected via window.RecipesConfig in recipes.php
        // (external .js files cannot run server-side PHP). Units originally
        // came from a server-side foreach; we now render them from that config.
        function escapeHtml(value) {
            return String(value).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c];
            });
        }
        var RECIPE_UNITS = (window.RecipesConfig && window.RecipesConfig.units) || [];
        var unitOptionsHtml = RECIPE_UNITS.map(function (unit) {
            return '<option value="' + escapeHtml(unit.UNIT_ID) + '">' + escapeHtml(unit.UNIT_NAME) + '</option>';
        }).join('');

        // JavaScript to enhance the filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Check if we need to open the recipe form modal automatically
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('openRecipeForm') === '1') {
                const modal = document.getElementById('recipeFormModal');
                if (modal) {
                    modal.style.display = 'block';
                    document.body.classList.add('modal-open');
                }
            }
            
            // Make filter options clickable
            document.querySelectorAll('.filter-option').forEach(function(option) {
                option.addEventListener('click', function() {
                    window.location.href = this.getAttribute('href');
                });
            });
            // Modal functionality
            const modal = document.getElementById('recipeFormModal');
            const openBtns = [document.getElementById('openRecipeFormBtn'), document.getElementById('openRecipeFormBtn2')];
            const closeBtn = document.querySelector('.close-modal');
            
            openBtns.forEach(btn => {
                if (btn) {
                    btn.addEventListener('click', function() {
                        modal.style.display = 'block';
                        document.body.classList.add('modal-open');
                    });
                }
            });
            
            closeBtn.addEventListener('click', function() {
                modal.style.display = 'none';
                document.body.classList.remove('modal-open');
            });
            
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                    document.body.classList.remove('modal-open');
                }
            });
            
            // Photo upload preview
            const photoInput = document.getElementById('recipePhotoInput');
            const browseTrigger = document.getElementById('browseTrigger');
            const uploadPlaceholder = document.getElementById('uploadPlaceholder');
            const photoPreview = document.getElementById('photoPreview');
            const previewImage = document.getElementById('previewImage');
            const removePhotoBtn = document.getElementById('removePhoto');
            const photoContainer = document.getElementById('photoUploadContainer');
            
            browseTrigger.addEventListener('click', function() {
                photoInput.click();
            });
            
            photoInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        previewImage.src = e.target.result;
                        uploadPlaceholder.style.display = 'none';
                        photoPreview.style.display = 'block';
                    };
                    
                    reader.readAsDataURL(this.files[0]);
                }
            });
            
            removePhotoBtn.addEventListener('click', function() {
                photoInput.value = '';
                previewImage.src = '';
                photoPreview.style.display = 'none';
                uploadPlaceholder.style.display = 'flex';
            });
            
            // Drag and drop functionality
            photoContainer.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });
            
            photoContainer.addEventListener('dragleave', function() {
                this.classList.remove('dragover');
            });
            
            photoContainer.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
                
                if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                    photoInput.files = e.dataTransfer.files;
                    
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewImage.src = e.target.result;
                        uploadPlaceholder.style.display = 'none';
                        photoPreview.style.display = 'block';
                    };
                    
                    reader.readAsDataURL(e.dataTransfer.files[0]);
                }
            });
            
            // Add/remove ingredients
            const ingredientsContainer = document.getElementById('ingredientsContainer');
            const addIngredientBtn = document.getElementById('addIngredient');
            
            addIngredientBtn.addEventListener('click', function() {
                const newRow = document.createElement('div');
                newRow.className = 'ingredient-row';
                newRow.innerHTML = `
                    <input type="text" name="ingredient_name[]" placeholder="Ingredient name" required>
                    <input type="number" name="ingredient_quantity[]" placeholder="Qty" min="0" step="0.01" required>
                    <select name="ingredient_unit[]" required>
                        <option value="" disabled selected>Unit</option>
                        ${unitOptionsHtml}
                    </select>
                    <button type="button" class="remove-ingredient">✕</button>
                `;
                
                ingredientsContainer.appendChild(newRow);
                
                // Add event listener to the new remove button
                const removeBtn = newRow.querySelector('.remove-ingredient');
                removeBtn.addEventListener('click', function() {
                    ingredientsContainer.removeChild(newRow);
                });
            });
            
            // Add event listener to the initial remove button
            document.querySelector('.remove-ingredient').addEventListener('click', function() {
                if (ingredientsContainer.children.length > 1) {
                    this.parentElement.remove();
                }
            });
        });
