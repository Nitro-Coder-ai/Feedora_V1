<?php
// Include the database connection script
require_once 'config/config.php';
// Include FIXED notification utilities
require_once 'notification_utils.php';
// Cloudinary uploader (with graceful local-disk fallback)
require_once 'includes/CloudinaryService.php';
// Start the session to manage user data
session_start();
csrf_verify(); // CSRF: reject forged state-changing POSTs

// Logged-out visitors get an attractive public preview of the Recipes section
// (instead of a bare redirect to sign-in).
if (!isset($_SESSION['user_id'])) {
    include __DIR__ . '/includes/preview_recipes.php';
    exit();
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';
$userProfileImage = $_SESSION['profile_image'] ?? 'https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg';

// Get all categories from database
$categoriesStmt = $pdo->prepare("SELECT * FROM CATEGORIE ORDER BY NAME_CATEGORIE");
$categoriesStmt->execute();
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

// Get all difficulty levels from database
$difficultyStmt = $pdo->prepare("SELECT * FROM DIFFICULTY ORDER BY DIFFICULTY_ID");
$difficultyStmt->execute();
$difficulties = $difficultyStmt->fetchAll(PDO::FETCH_ASSOC);

// Get all units from database
$unitsStmt = $pdo->prepare("SELECT * FROM UNIT ORDER BY UNIT_NAME");
$unitsStmt->execute();
$units = $unitsStmt->fetchAll(PDO::FETCH_ASSOC);

// Process form submission for new recipe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_recipe'])) {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Handle file upload for recipe photo
        $photoUrl = '';
        if (isset($_FILES['recipe_photo']) && $_FILES['recipe_photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/recipes/';
            
            // Create directory if it doesn't exist
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            // Validate real image content; use a server-derived safe extension
            // (never the user's filename) to prevent disguised-script uploads.
            $safeExt = safe_image_ext($_FILES['recipe_photo']['tmp_name']);
            if ($safeExt === null) {
                throw new Exception('Invalid image file. Please upload a JPG, PNG, GIF, or WEBP.');
            }
            $fileName = uniqid('recipe_') . '.' . $safeExt;
            $uploadFile = $uploadDir . $fileName;

            // Try Cloudinary first; fall back to local disk to guarantee uptime.
            $cloud = CloudinaryService::upload($_FILES['recipe_photo']['tmp_name'], 'feedora/recipes');
            if ($cloud['success']) {
                $photoUrl = $cloud['url'];
            } elseif (move_uploaded_file($_FILES['recipe_photo']['tmp_name'], $uploadFile)) {
                $photoUrl = $uploadFile;
            }
        }
        
        // Insert recipe
        $recipeStmt = $pdo->prepare("INSERT INTO RECIPES (USER_ID, ID_CATEGORIE, TITLE, PHOTO_URL, INSTRUCTIONS, PREP_TIME_MINUTES, COOK_TIME_MINUTES, SERVINGS) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $recipeStmt->execute([
            $userId,
            $_POST['category'],
            $_POST['recipe_title'],
            $photoUrl,
            $_POST['instructions'],
            $_POST['prep_time'],
            $_POST['cook_time'],
            $_POST['servings']
        ]);
        
        $recipeId = $pdo->lastInsertId();
        
        // Insert difficulty relation if not 'all'
        if ($_POST['difficulty'] !== 'all') {
            $difficultyRecipeStmt = $pdo->prepare("INSERT INTO DIFFICULTY_RECIPES (DIFFICULTY_ID, RECIPES_ID) VALUES (?, ?)");
            $difficultyRecipeStmt->execute([$_POST['difficulty'], $recipeId]);
        }
        
        // Process ingredients
        if (isset($_POST['ingredient_name']) && is_array($_POST['ingredient_name'])) {
            for ($i = 0; $i < count($_POST['ingredient_name']); $i++) {
                if (!empty($_POST['ingredient_name'][$i])) {
                    // Insert ingredient
                    $ingredientStmt = $pdo->prepare("INSERT INTO INGREDIENTS (NAME) VALUES (?)");
                    $ingredientStmt->execute([$_POST['ingredient_name'][$i]]);
                    $ingredientId = $pdo->lastInsertId();
                    
                    // Link ingredient to recipe
                    $recipeIngredientStmt = $pdo->prepare("INSERT INTO RECIPE_INGREDIENTS (RECIPES_ID, INGREDIENTS_ID) VALUES (?, ?)");
                    $recipeIngredientStmt->execute([$recipeId, $ingredientId]);
                    
                    // Add quantity and unit
                    if (!empty($_POST['ingredient_quantity'][$i]) && !empty($_POST['ingredient_unit'][$i])) {
                        $unitStmt = $pdo->prepare("INSERT INTO INGREDIENTS_UNIT (INGREDIENTS_ID, UNIT_ID, QUANTITY) VALUES (?, ?, ?)");
                        $unitStmt->execute([
                            $ingredientId,
                            $_POST['ingredient_unit'][$i],
                            $_POST['ingredient_quantity'][$i]
                        ]);
                    }
                }
            }
        }
        
        // Notify followers of new recipe
        notifyFollowersNewRecipe($pdo, $recipeId, $userId);
        
        // Commit transaction
        $pdo->commit();
        
        // Redirect to avoid form resubmission
        header("Location: recipes.php?success=1");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        $error = "Error creating recipe: " . $e->getMessage();
    }
}

// Set default filter values
$selectedCategory = isset($_GET['category']) ? $_GET['category'] : 'All Categories';
$selectedDifficulty = isset($_GET['difficulty']) ? $_GET['difficulty'] : 'All Difficulties';

// Build the query based on filters
$query = "SELECT r.*, c.NAME_CATEGORIE, d.DIFFICULTY_NAME, u.NAME as AUTHOR_NAME 
         FROM RECIPES r 
         LEFT JOIN CATEGORIE c ON r.ID_CATEGORIE = c.ID_CATEGORIE 
         LEFT JOIN DIFFICULTY_RECIPES dr ON r.RECIPES_ID = dr.RECIPES_ID 
         LEFT JOIN DIFFICULTY d ON dr.DIFFICULTY_ID = d.DIFFICULTY_ID 
         LEFT JOIN USERS u ON r.USER_ID = u.USER_ID 
         WHERE 1=1";
$params = [];

// Apply category filter if not 'All Categories'
if ($selectedCategory !== 'All Categories') {
    $query .= " AND c.NAME_CATEGORIE = ?";
    $params[] = $selectedCategory;
}

// Apply difficulty filter if specified
if ($selectedDifficulty !== 'All Difficulties') {
    $query .= " AND d.DIFFICULTY_NAME = ?";
    $params[] = $selectedDifficulty;
}

// Order by newest first
$query .= " ORDER BY r.RECIPES_ID DESC";

// Execute the query
$recipesStmt = $pdo->prepare($query);
$recipesStmt->execute($params);
$recipes = $recipesStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
    <script src="<?php echo asset('assets/js/csrf.js'); ?>"></script>
    <link rel="preconnect" href="https://res.cloudinary.com" crossorigin>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Feedora - Your Recipe Collection">
    <meta name="theme-color" content="#ED5A2C">
    <title>Feedora - Recipes</title>
    <link rel="stylesheet" href="<?php echo asset('Home.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('fonts.css'); ?>">
        <!-- Favicon -->
        <link rel="icon" href="https://res.cloudinary.com/dio1bvxbv/image/upload/f_auto,q_auto/v1782090013/feedora/assets/Frame-1171277973-svg.svg" type="image/svg+xml">
    <link rel="stylesheet" href="<?php echo asset('assets/css/recipes.css'); ?>">
</head>

<body>
    <?php include('sidebar.php'); ?>

    <main class="main-content">
        <?php include('header.php'); ?>

        <!-- Recipes Content -->
        <div class="recipes-container">
            <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
                <div class="success-message">
                    <p>Recipe created successfully!</p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="error-message">
                    <p><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php endif; ?>

            <div class="recipes-content">
                <div class="recipes-main">
                    <div class="filter-header">
                        <div class="filter-form-container">
                            <form action="" method="get" id="filterForm" style="display: flex; align-items: center; gap: 10px;">
                                <select name="category" class="filter-dropdown" onchange="this.form.submit()">
                                    <option value="All Categories" <?php echo $selectedCategory === 'All Categories' ? 'selected' : ''; ?>>All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo htmlspecialchars($category['NAME_CATEGORIE']); ?>" <?php echo $selectedCategory === $category['NAME_CATEGORIE'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['NAME_CATEGORIE']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <select name="difficulty" class="filter-dropdown" onchange="this.form.submit()">
                                    <option value="All Difficulties" <?php echo $selectedDifficulty === 'All Difficulties' ? 'selected' : ''; ?>>All Difficulties</option>
                                    <?php foreach ($difficulties as $difficulty): ?>
                                        <option value="<?php echo htmlspecialchars($difficulty['DIFFICULTY_NAME']); ?>" <?php echo $selectedDifficulty === $difficulty['DIFFICULTY_NAME'] ? 'selected' : ''; ?>>
                                            Difficulty: <?php echo htmlspecialchars($difficulty['DIFFICULTY_NAME']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                            <button id="openRecipeFormBtn" class="make-recipe-btn" style="width: auto; margin-top: 0;">Make Recipe</button>
                        </div>
                    </div>

                    <?php if (count($recipes) > 0): ?>
                        <?php foreach ($recipes as $recipe): ?>
                            <div class="recipe-card">
                                <?php if (!empty($recipe['PHOTO_URL'])): ?>
                                    <img src="<?php echo htmlspecialchars($recipe['PHOTO_URL']); ?>" alt="<?php echo htmlspecialchars($recipe['TITLE']); ?>" class="recipe-image" loading="lazy" decoding="async">
                                <?php else: ?>
                                    <img src="images/default-recipe.jpg" alt="Default Recipe Image" class="recipe-image" loading="lazy" decoding="async">
                                <?php endif; ?>

                                <div class="recipe-content">
                                    <h3 class="recipe-title"><?php echo htmlspecialchars($recipe['TITLE']); ?></h3>

                                    <div class="recipe-meta">
                                        <span><?php echo htmlspecialchars($recipe['NAME_CATEGORIE'] ?? 'Uncategorized'); ?></span>
                                        <span>Difficulty: <?php echo htmlspecialchars($recipe['DIFFICULTY_NAME'] ?? 'Not specified'); ?></span>
                                    </div>

                                    <div class="recipe-meta">
                                        <span>Prep: <?php echo htmlspecialchars($recipe['PREP_TIME_MINUTES'] ?? '0'); ?> min</span>
                                        <span>Cook: <?php echo htmlspecialchars($recipe['COOK_TIME_MINUTES'] ?? '0'); ?> min</span>
                                        <span>Servings: <?php echo htmlspecialchars($recipe['SERVINGS'] ?? '1'); ?></span>
                                    </div>

                                    <div class="recipe-footer">
                                        <span class="recipe-author">By <?php echo htmlspecialchars($recipe['AUTHOR_NAME']); ?></span>
                                        <a href="view-recipe.php?id=<?php echo $recipe['RECIPES_ID']; ?>" class="view-recipe-btn">View Recipe</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-recipes">
                            <h3>No recipes found matching your criteria</h3>
                            <p>Try adjusting your filters or create a new recipe!</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="recipes-sidebar">
                    <h2>Filter Recipes</h2>

                    <div class="filter-section">
                        <h3>Categories</h3>
                        <div class="filter-options">
                            <a href="?category=All Categories&difficulty=<?php echo urlencode($selectedDifficulty); ?>" class="filter-option <?php echo $selectedCategory === 'All Categories' ? 'active' : ''; ?>">All</a>

                            <?php foreach ($categories as $category): ?>
                                <a href="?category=<?php echo urlencode($category['NAME_CATEGORIE']); ?>&difficulty=<?php echo urlencode($selectedDifficulty); ?>"
                                    class="filter-option <?php echo $selectedCategory === $category['NAME_CATEGORIE'] ? 'active' : ''; ?>">
                                    <?php echo htmlspecialchars($category['NAME_CATEGORIE']); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="filter-section">
                        <h3>Difficulty</h3>
                        <div class="filter-options">
                            <a href="?category=<?php echo urlencode($selectedCategory); ?>&difficulty=All Difficulties"
                                class="filter-option <?php echo $selectedDifficulty === 'All Difficulties' ? 'active' : ''; ?>">All</a>

                            <?php foreach ($difficulties as $difficulty): ?>
                                <a href="?category=<?php echo urlencode($selectedCategory); ?>&difficulty=<?php echo urlencode($difficulty['DIFFICULTY_NAME']); ?>"
                                    class="filter-option <?php echo $selectedDifficulty === $difficulty['DIFFICULTY_NAME'] ? 'active' : ''; ?>">
                                    <?php echo htmlspecialchars($difficulty['DIFFICULTY_NAME']); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <button id="openRecipeFormBtn2" class="make-recipe-btn">Make Recipe</button>
                </div>
            </div>
        </div>
    </main>

    <!-- Recipe Form Modal -->
    <div id="recipeFormModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            
            <div class="recipe-form-container">
                <h2>Share Your Recipe</h2>
                <p class="form-subtitle">Guide fellow cooks through each step</p>
                
                <form action="" method="post" enctype="multipart/form-data" id="recipeForm">
          <?php echo csrf_field(); ?>
                    <div class="form-group">
                        <label for="recipe_title">Recipe Title</label>
                        <input type="text" id="recipe_title" name="recipe_title" placeholder="Enter your recipe name..." required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group half">
                            <label for="category"><i class="icon-category"></i> Category</label>
                            <select id="category" name="category" required>
                                <option value="" disabled selected>Select category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['ID_CATEGORIE']; ?>">
                                        <?php echo htmlspecialchars($category['NAME_CATEGORIE']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group half">
                            <label for="difficulty"><i class="icon-difficulty"></i> Difficulty</label>
                            <select id="difficulty" name="difficulty" required>
                                <option value="" disabled>Select difficulty</option>
                                <option value="all" selected>All Difficulties</option>
                                <?php foreach ($difficulties as $difficulty): ?>
                                    <option value="<?php echo $difficulty['DIFFICULTY_ID']; ?>">
                                        <?php echo htmlspecialchars($difficulty['DIFFICULTY_NAME']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="recipe_photo">Recipe Photo</label>
                        <div class="photo-upload-container" id="photoUploadContainer">
                            <div class="upload-placeholder" id="uploadPlaceholder">
                                <i class="upload-icon">⬆️</i>
                                <p>Drag & drop your image here</p>
                                <button type="button" class="browse-btn" id="browseTrigger">Browse Files</button>
                            </div>
                            <div class="photo-preview" id="photoPreview" style="display: none;">
                                <img id="previewImage" src="" alt="Recipe preview">
                                <button type="button" class="remove-photo" id="removePhoto">Remove</button>
                            </div>
                            <input type="file" name="recipe_photo" id="recipePhotoInput" accept="image/*" style="display: none;">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Ingredients</label>
                        <div id="ingredientsContainer">
                            <div class="ingredient-row">
                                <input type="text" name="ingredient_name[]" placeholder="Ingredient name" required>
                                <input type="number" name="ingredient_quantity[]" placeholder="Qty" min="0" step="0.01" required>
                                <select name="ingredient_unit[]" required>
                                    <option value="" disabled selected>Unit</option>
                                    <?php foreach ($units as $unit): ?>
                                        <option value="<?php echo $unit['UNIT_ID']; ?>">
                                            <?php echo htmlspecialchars($unit['UNIT_NAME']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="remove-ingredient">✕</button>
                            </div>
                        </div>
                        <button type="button" id="addIngredient" class="add-ingredient-btn">+ Add Ingredient</button>
                    </div>
                    
                    <div class="form-group">
                        <label for="instructions">Instructions</label>
                        <textarea id="instructions" name="instructions" placeholder="Describe each step in detail..." rows="6" required></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group third">
                            <label for="prep_time"><i class="icon-time"></i> Prep Time</label>
                            <div class="time-input">
                                <input type="number" id="prep_time" name="prep_time" min="0" value="30" required>
                                <span class="time-unit">min</span>
                            </div>
                        </div>
                        
                        <div class="form-group third">
                            <label for="cook_time"><i class="icon-time"></i> Cook Time</label>
                            <div class="time-input">
                                <input type="number" id="cook_time" name="cook_time" min="0" value="45" required>
                                <span class="time-unit">min</span>
                            </div>
                        </div>
                        
                        <div class="form-group third">
                            <label for="servings"><i class="icon-servings"></i> Servings</label>
                            <input type="number" id="servings" name="servings" min="1" value="4" required>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="submit_recipe" class="submit-recipe-btn">Make Recipe</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        window.RecipesConfig = {
            units: <?php echo json_encode(array_map(function ($u) { return ['UNIT_ID' => $u['UNIT_ID'], 'UNIT_NAME' => $u['UNIT_NAME']]; }, $units)); ?>
        };
    </script>
    <script src="<?php echo asset('assets/js/recipes.js'); ?>" defer></script>
    
</body>

</html>
