<?php
// Include the database connection script
require_once 'config/config.php';
// Cloudinary uploader (with graceful local-disk fallback)
require_once 'includes/CloudinaryService.php';
// Start the session to manage user data
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to the login page if not logged in
    header("Location: sign-in.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';
$userProfileImage = $_SESSION['profile_image'] ?? 'https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg';

// Check if recipe ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: recipes.php");
    exit();
}

$recipeId = $_GET['id'];

// Handle recipe deletion
if (isset($_POST['delete_recipe'])) {
    try {
        // Check if the current user owns this recipe
        $ownerCheck = $pdo->prepare("SELECT USER_ID, PHOTO_URL FROM RECIPES WHERE RECIPES_ID = ?");
        $ownerCheck->execute([$recipeId]);
        $recipeOwner = $ownerCheck->fetch(PDO::FETCH_ASSOC);
        
        if ($recipeOwner && $recipeOwner['USER_ID'] == $userId) {
            $pdo->beginTransaction();
            
            // Delete recipe ingredients relationships
            $deleteIngredientRelations = $pdo->prepare("DELETE FROM RECIPE_INGREDIENTS WHERE RECIPES_ID = ?");
            $deleteIngredientRelations->execute([$recipeId]);
            
            // Delete difficulty relationships
            $deleteDifficultyRelations = $pdo->prepare("DELETE FROM DIFFICULTY_RECIPES WHERE RECIPES_ID = ?");
            $deleteDifficultyRelations->execute([$recipeId]);
            
            // Delete from collections
            $deleteCollections = $pdo->prepare("DELETE FROM COLLECT WHERE RECIPES_ID = ?");
            $deleteCollections->execute([$recipeId]);
            
            // Delete the recipe photo if it exists
            if (!empty($recipeOwner['PHOTO_URL'])) {
                if (strpos($recipeOwner['PHOTO_URL'], 'http') === 0) {
                    CloudinaryService::destroyByUrl($recipeOwner['PHOTO_URL']);
                } elseif (file_exists($recipeOwner['PHOTO_URL'])) {
                    unlink($recipeOwner['PHOTO_URL']);
                }
            }
            
            // Delete the recipe itself
            $deleteRecipe = $pdo->prepare("DELETE FROM RECIPES WHERE RECIPES_ID = ? AND USER_ID = ?");
            $deleteRecipe->execute([$recipeId, $userId]);
            
            $pdo->commit();
            
            // Redirect to recipes page with success message
            header("Location: recipes.php?deleted=1");
            exit();
        } else {
            throw new Exception("You don't have permission to delete this recipe.");
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $deleteError = $e->getMessage();
    }
}

// Handle recipe editing
if (isset($_POST['edit_recipe'])) {
    try {
        // Check if the current user owns this recipe
        $ownerCheck = $pdo->prepare("SELECT USER_ID FROM RECIPES WHERE RECIPES_ID = ?");
        $ownerCheck->execute([$recipeId]);
        $recipeOwner = $ownerCheck->fetch(PDO::FETCH_ASSOC);
        
        if ($recipeOwner && $recipeOwner['USER_ID'] == $userId) {
            $pdo->beginTransaction();
            
            // Handle photo upload if new photo is provided
            $photoUrl = $_POST['existing_photo_url'];
            if (isset($_FILES['edit_recipe_photo']) && $_FILES['edit_recipe_photo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/recipes/';
                
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $fileName = uniqid() . '_' . basename($_FILES['edit_recipe_photo']['name']);
                $uploadFile = $uploadDir . $fileName;

                // Try Cloudinary first; fall back to local disk to guarantee uptime.
                $oldPhoto = $photoUrl;
                $cloud = CloudinaryService::upload($_FILES['edit_recipe_photo']['tmp_name'], 'feedora/recipes');
                if ($cloud['success']) {
                    $photoUrl = $cloud['url'];
                } elseif (move_uploaded_file($_FILES['edit_recipe_photo']['tmp_name'], $uploadFile)) {
                    $photoUrl = $uploadFile;
                }

                // Delete the old photo if it changed.
                if ($photoUrl !== $oldPhoto && !empty($oldPhoto)) {
                    if (strpos($oldPhoto, 'http') === 0) {
                        CloudinaryService::destroyByUrl($oldPhoto);
                    } elseif (file_exists($oldPhoto)) {
                        unlink($oldPhoto);
                    }
                }
            }
            
            // Update recipe basic information
            $updateRecipe = $pdo->prepare("
                UPDATE RECIPES 
                SET TITLE = ?, INSTRUCTIONS = ?, PREP_TIME_MINUTES = ?, 
                    COOK_TIME_MINUTES = ?, SERVINGS = ?, PHOTO_URL = ?, ID_CATEGORIE = ?
                WHERE RECIPES_ID = ? AND USER_ID = ?
            ");
            $updateRecipe->execute([
                $_POST['edit_title'],
                $_POST['edit_instructions'],
                $_POST['edit_prep_time'],
                $_POST['edit_cook_time'],
                $_POST['edit_servings'],
                $photoUrl,
                $_POST['edit_category'],
                $recipeId,
                $userId
            ]);
            
            // Update difficulty
            $deleteDifficulty = $pdo->prepare("DELETE FROM DIFFICULTY_RECIPES WHERE RECIPES_ID = ?");
            $deleteDifficulty->execute([$recipeId]);
            
            if ($_POST['edit_difficulty'] !== 'all') {
                $insertDifficulty = $pdo->prepare("INSERT INTO DIFFICULTY_RECIPES (DIFFICULTY_ID, RECIPES_ID) VALUES (?, ?)");
                $insertDifficulty->execute([$_POST['edit_difficulty'], $recipeId]);
            }
            
            $pdo->commit();
            
            // Redirect to avoid form resubmission
            header("Location: view-recipe.php?id=$recipeId&updated=1");
            exit();
        } else {
            throw new Exception("You don't have permission to edit this recipe.");
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $editError = $e->getMessage();
    }
}

// Get recipe details
$recipeStmt = $pdo->prepare("
    SELECT r.*, c.NAME_CATEGORIE, d.DIFFICULTY_NAME, u.NAME as AUTHOR_NAME, u.PROFILE_IMAGE as AUTHOR_IMAGE 
    FROM RECIPES r 
    LEFT JOIN CATEGORIE c ON r.ID_CATEGORIE = c.ID_CATEGORIE 
    LEFT JOIN DIFFICULTY_RECIPES dr ON r.RECIPES_ID = dr.RECIPES_ID 
    LEFT JOIN DIFFICULTY d ON dr.DIFFICULTY_ID = d.DIFFICULTY_ID 
    LEFT JOIN USERS u ON r.USER_ID = u.USER_ID 
    WHERE r.RECIPES_ID = ?
");
$recipeStmt->execute([$recipeId]);
$recipe = $recipeStmt->fetch(PDO::FETCH_ASSOC);

// If recipe doesn't exist, redirect
if (!$recipe) {
    header("Location: recipes.php");
    exit();
}

// Check if current user is the recipe owner
$isOwner = ($recipe['USER_ID'] == $userId);

// Get recipe ingredients
$ingredientsStmt = $pdo->prepare("
    SELECT i.INGREDIENTS_ID, i.NAME, iu.QUANTITY, u.UNIT_NAME 
    FROM RECIPE_INGREDIENTS ri 
    JOIN INGREDIENTS i ON ri.INGREDIENTS_ID = i.INGREDIENTS_ID 
    LEFT JOIN INGREDIENTS_UNIT iu ON i.INGREDIENTS_ID = iu.INGREDIENTS_ID 
    LEFT JOIN UNIT u ON iu.UNIT_ID = u.UNIT_ID 
    WHERE ri.RECIPES_ID = ?
");
$ingredientsStmt->execute([$recipeId]);
$ingredients = $ingredientsStmt->fetchAll(PDO::FETCH_ASSOC);

// Check if the current user has collected/saved this recipe
$isCollectedStmt = $pdo->prepare("SELECT * FROM COLLECT WHERE USER_ID = ? AND RECIPES_ID = ?");
$isCollectedStmt->execute([$userId, $recipeId]);
$isCollected = $isCollectedStmt->rowCount() > 0;

// Process save/unsave recipe action
if (isset($_POST['toggle_collect'])) {
    try {
        if ($isCollected) {
            // Remove from collection
            $removeStmt = $pdo->prepare("DELETE FROM COLLECT WHERE USER_ID = ? AND RECIPES_ID = ?");
            $success = $removeStmt->execute([$userId, $recipeId]);
            $isCollected = false;
        } else {
            // Add to collection
            $addStmt = $pdo->prepare("INSERT INTO COLLECT (USER_ID, RECIPES_ID, DATE_COLLECT) VALUES (?, ?, CURRENT_TIMESTAMP)");
            $success = $addStmt->execute([$userId, $recipeId]);
            $isCollected = true;
        }
        
        if ($success) {
            // Redirect to avoid form resubmission
            header("Location: view-recipe.php?id=$recipeId");
            exit();
        } else {
            throw new PDOException("Failed to update collection");
        }
    } catch (PDOException $e) {
        error_log("Collection update error: " . $e->getMessage());
        // You might want to show an error message to the user here
    }
}

// Get categories and difficulties for edit form
$categoriesStmt = $pdo->prepare("SELECT * FROM CATEGORIE ORDER BY NAME_CATEGORIE");
$categoriesStmt->execute();
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

$difficultiesStmt = $pdo->prepare("SELECT * FROM DIFFICULTY ORDER BY DIFFICULTY_ID");
$difficultiesStmt->execute();
$difficulties = $difficultiesStmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------------------------------------------------------------------------
 * Phase 4 SEO: build JSON-LD Recipe schema + meta/OpenGraph values.
 * Handles both Cloudinary (absolute https) and local upload paths.
 * ------------------------------------------------------------------------- */
$seoBaseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

$feedoraAbsUrl = function ($path) use ($seoBaseUrl) {
    if (!$path) return '';
    if (preg_match('#^https?://#i', $path)) return $path; // already absolute (Cloudinary)
    return $seoBaseUrl . '/' . ltrim($path, '/');
};

$seoImage = !empty($recipe['PHOTO_URL'])
    ? $feedoraAbsUrl($recipe['PHOTO_URL'])
    : $feedoraAbsUrl('images/default-recipe.jpg');
$seoUrl   = $seoBaseUrl . '/view-recipe.php?id=' . (int)$recipeId;
$seoTitle = $recipe['TITLE'] ?? 'Recipe';

$seoDescRaw = trim(strip_tags($recipe['INSTRUCTIONS'] ?? ''));
if ($seoDescRaw === '') {
    $seoDescRaw = 'A delicious ' . ($recipe['NAME_CATEGORIE'] ?? '') . ' recipe shared on Feedora.';
}
$seoDescRaw = preg_replace('/\s+/', ' ', $seoDescRaw);
$seoDescription = mb_substr($seoDescRaw, 0, 157) . (mb_strlen($seoDescRaw) > 157 ? '…' : '');

$feedoraIsoMinutes = function ($m) {
    $m = (int)$m;
    return $m > 0 ? ('PT' . $m . 'M') : null;
};
$seoPrep = $feedoraIsoMinutes($recipe['PREP_TIME_MINUTES'] ?? 0);
$seoCook = $feedoraIsoMinutes($recipe['COOK_TIME_MINUTES'] ?? 0);

$seoIngredients = [];
foreach ($ingredients as $ing) {
    $line = trim(preg_replace('/\s+/', ' ',
        ($ing['QUANTITY'] ?? '') . ' ' . ($ing['UNIT_NAME'] ?? '') . ' ' . ($ing['NAME'] ?? '')));
    if ($line !== '') $seoIngredients[] = $line;
}

$seoSteps = array_values(array_filter(
    array_map('trim', preg_split('/\r\n|\r|\n/', $recipe['INSTRUCTIONS'] ?? '')),
    function ($s) { return $s !== ''; }
));

$seoJsonLd = [
    '@context'       => 'https://schema.org',
    '@type'          => 'Recipe',
    'name'           => $seoTitle,
    'description'    => $seoDescription,
    'image'          => [$seoImage],
    'author'         => ['@type' => 'Person', 'name' => $recipe['AUTHOR_NAME'] ?? 'Feedora Chef'],
    'datePublished'  => !empty($recipe['CREATED_AT']) ? date('c', strtotime($recipe['CREATED_AT'])) : null,
    'recipeCategory' => $recipe['NAME_CATEGORIE'] ?? null,
    'recipeYield'    => !empty($recipe['SERVINGS']) ? ((string)$recipe['SERVINGS'] . ' servings') : null,
    'prepTime'       => $seoPrep,
    'cookTime'       => $seoCook,
    'totalTime'      => ($seoPrep && $seoCook)
        ? ('PT' . ((int)$recipe['PREP_TIME_MINUTES'] + (int)$recipe['COOK_TIME_MINUTES']) . 'M')
        : null,
    'url'            => $seoUrl,
];
if (!empty($seoIngredients)) {
    $seoJsonLd['recipeIngredient'] = $seoIngredients;
}
if (!empty($seoSteps)) {
    $seoJsonLd['recipeInstructions'] = array_map(function ($s) {
        return ['@type' => 'HowToStep', 'text' => $s];
    }, $seoSteps);
}
$seoJsonLd = array_filter($seoJsonLd, function ($v) { return $v !== null && $v !== ''; });
// JSON_HEX_TAG prevents any "</script>" inside content from breaking out of the block.
$seoJsonLdJson = json_encode($seoJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_PRETTY_PRINT);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
    <script src="<?php echo asset('assets/js/csrf.js'); ?>"></script>
    <link rel="preconnect" href="https://res.cloudinary.com" crossorigin>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo htmlspecialchars($seoDescription, ENT_QUOTES); ?>">
    <meta name="theme-color" content="#ED5A2C">
    <link rel="canonical" href="<?php echo htmlspecialchars($seoUrl, ENT_QUOTES); ?>">
    <title><?php echo htmlspecialchars($seoTitle); ?> - Feedora</title>

    <!-- Open Graph / social share cards -->
    <meta property="og:type" content="article">
    <meta property="og:title" content="<?php echo htmlspecialchars($seoTitle, ENT_QUOTES); ?> - Feedora">
    <meta property="og:description" content="<?php echo htmlspecialchars($seoDescription, ENT_QUOTES); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($seoImage, ENT_QUOTES); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($seoUrl, ENT_QUOTES); ?>">
    <meta property="og:site_name" content="Feedora">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($seoTitle, ENT_QUOTES); ?> - Feedora">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($seoDescription, ENT_QUOTES); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($seoImage, ENT_QUOTES); ?>">

    <!-- JSON-LD structured data: Google Recipe rich result -->
    <script type="application/ld+json">
<?php echo $seoJsonLdJson; ?>
    </script>

    <link rel="stylesheet" href="<?php echo asset('Home.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('fonts.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('assets/css/view-recipe.css'); ?>">
    <?php if ($isCollected): ?>
    <!-- Dynamic value kept inline; the rest of the rule lives in view-recipe.css -->
    <style>.save-recipe-btn { --save-recipe-bg: #4CAF50; }</style>
    <?php endif; ?>
</head>

<body>
    <?php include('sidebar.php'); ?>

    <main class="main-content">
        <?php include('header.php'); ?>

        <?php if (isset($_GET['updated']) && $_GET['updated'] == 1): ?>
            <div class="success-message">
                Recipe updated successfully!
            </div>
        <?php endif; ?>

        <?php if (isset($editError)): ?>
            <div class="error-message">
                Error updating recipe: <?php echo htmlspecialchars($editError); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($deleteError)): ?>
            <div class="error-message">
                Error deleting recipe: <?php echo htmlspecialchars($deleteError); ?>
            </div>
        <?php endif; ?>

        <a href="recipes.php" class="back-to-recipes">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            Back to Recipes
        </a>

        <div class="recipe-container" data-recipe-id="<?php echo htmlspecialchars($recipe['TITLE']); ?> | Feedora">
            <div class="recipe-header">
                <?php if (!empty($recipe['PHOTO_URL'])): ?>
                    <img src="<?php echo htmlspecialchars($recipe['PHOTO_URL']); ?>" alt="<?php echo htmlspecialchars($recipe['TITLE']); ?>" class="recipe-header-image">
                <?php else: ?>
                    <img src="images/default-recipe.jpg" alt="Default Recipe Image" class="recipe-header-image">
                <?php endif; ?>
                
                <div class="recipe-header-overlay">
                    <h1 class="recipe-title"><?php echo htmlspecialchars($recipe['TITLE']); ?></h1>
                    
                    <div class="recipe-meta">
                        <span class="recipe-meta-item">
                            <span class="recipe-meta-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 2v7c0 1.1.9 2 2 2h14a2 2 0 0 0 2-2V2"></path>
                                    <path d="M7 2v20"></path>
                                    <path d="M17 2v20"></path>
                                    <path d="M9 2v4"></path>
                                    <path d="M15 2v4"></path>
                                    <path d="M12 14v3"></path>
                                    <path d="M10 17h4"></path>
                                </svg>
                            </span>
                            <?php echo htmlspecialchars($recipe['NAME_CATEGORIE'] ?? 'Uncategorized'); ?>
                        </span>
                        
                        <span class="recipe-meta-item">
                            <span class="recipe-meta-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 20v-6M6 20V10M18 20V4"></path>
                                </svg>
                            </span>
                            <?php echo htmlspecialchars($recipe['DIFFICULTY_NAME'] ?? 'Not specified'); ?>
                        </span>
                    </div>
                    
                    <div class="recipe-author">
                        <?php if (!empty($recipe['AUTHOR_IMAGE'])): ?>
                            <img src="<?php echo htmlspecialchars($recipe['AUTHOR_IMAGE']); ?>" alt="<?php echo htmlspecialchars($recipe['AUTHOR_NAME']); ?>" class="recipe-author-image">
                        <?php else: ?>
                            <img src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg" alt="Default Profile" class="recipe-author-image">
                        <?php endif; ?>
                        <span class="recipe-author-name">By <?php echo htmlspecialchars($recipe['AUTHOR_NAME']); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="recipe-content">
                <div class="recipe-instructions">
                    <h2 class="recipe-section-title">Instructions</h2>
                    <div class="recipe-instructions-text">
                        <?php echo nl2br(htmlspecialchars($recipe['INSTRUCTIONS'])); ?>
                    </div>
                </div>
                
                <div class="recipe-sidebar">
                    <div class="recipe-details">
                        <div class="recipe-detail-item">
                            <div class="detail-value"><?php echo htmlspecialchars($recipe['PREP_TIME_MINUTES'] ?? '0'); ?></div>
                            <div class="detail-label">Prep Time (min)</div>
                        </div>
                        
                        <div class="recipe-detail-item">
                            <div class="detail-value"><?php echo htmlspecialchars($recipe['COOK_TIME_MINUTES'] ?? '0'); ?></div>
                            <div class="detail-label">Cook Time (min)</div>
                        </div>
                        
                        <div class="recipe-detail-item">
                            <div class="detail-value"><?php echo htmlspecialchars($recipe['SERVINGS'] ?? '1'); ?></div>
                            <div class="detail-label">Servings</div>
                        </div>
                    </div>
                    
                    <h2 class="recipe-section-title">Ingredients</h2>
                    <?php if (count($ingredients) > 0): ?>
                        <ul class="recipe-ingredients-list">
                            <?php foreach ($ingredients as $ingredient): ?>
                                <li class="recipe-ingredient-item">
                                    <input type="checkbox" class="ingredient-checkbox" id="ingredient-<?php echo $ingredient['INGREDIENTS_ID']; ?>">
                                    <label class="ingredient-text" for="ingredient-<?php echo $ingredient['INGREDIENTS_ID']; ?>">
                                        <?php 
                                            $quantity = !empty($ingredient['QUANTITY']) ? $ingredient['QUANTITY'] : '';
                                            $unit = !empty($ingredient['UNIT_NAME']) ? $ingredient['UNIT_NAME'] : '';
                                            if (!empty($quantity) && !empty($unit)) {
                                                echo htmlspecialchars("$quantity $unit of {$ingredient['NAME']}");
                                            } else {
                                                echo htmlspecialchars($ingredient['NAME']);
                                            }
                                        ?>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>No ingredients listed for this recipe.</p>
                    <?php endif; ?>
                    
                    <div class="recipe-actions">
                        <?php if (!$isOwner): ?>
                            <!-- Save Recipe Button for Non-Owners -->
                             
                            <form method="post" action="">
          <?php echo csrf_field(); ?>
                                <input type="hidden" name="recipe_id" value="<?php echo $recipeId; ?>">
                                <button type="submit" name="toggle_collect" class="recipe-action-btn save-recipe-btn" id="saveBtn">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="<?php echo $isCollected ? 'white' : 'none'; ?>" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                                    </svg>
                                    <?php echo $isCollected ? 'Saved to Collection' : 'Save to Collection'; ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <!-- Edit and Delete Buttons for Recipe Owner -->
                            <button class="recipe-action-btn edit-recipe-btn" onclick="openEditModal()">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                                Edit Recipe
                            </button>
                            
                            <button class="recipe-action-btn delete-recipe-btn" onclick="confirmDelete()">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="3 6 5 6 21 6"></polyline>
                                    <path d="m19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                </svg>
                                Delete Recipe
                            </button>
                        <?php endif; ?>
                        
                        <!-- Print Button for Everyone -->
                        <button class="recipe-action-btn print-recipe-btn" onclick="window.print()">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                                <rect x="6" y="14" width="12" height="8"></rect>
                            </svg>
                            Print Recipe
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php if ($isOwner): ?>
    <!-- Edit Recipe Modal -->
    <div id="editRecipeModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeEditModal()">&times;</span>
            <h2>Edit Recipe</h2>
            
            <form method="post" action="" enctype="multipart/form-data">
          <?php echo csrf_field(); ?>
                <input type="hidden" name="existing_photo_url" value="<?php echo htmlspecialchars($recipe['PHOTO_URL'] ?? ''); ?>">
                
                <div class="form-group">
                    <label for="edit_title">Recipe Title</label>
                    <input type="text" id="edit_title" name="edit_title" value="<?php echo htmlspecialchars($recipe['TITLE']); ?>" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group half">
                        <label for="edit_category">Category</label>
                        <select id="edit_category" name="edit_category" required>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['ID_CATEGORIE']; ?>" 
                                    <?php echo ($recipe['ID_CATEGORIE'] == $category['ID_CATEGORIE']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['NAME_CATEGORIE']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group half">
                        <label for="edit_difficulty">Difficulty</label>
                        <select id="edit_difficulty" name="edit_difficulty">
                            <option value="all" <?php echo empty($recipe['DIFFICULTY_NAME']) ? 'selected' : ''; ?>>All Difficulties</option>
                            <?php foreach ($difficulties as $difficulty): ?>
                                <option value="<?php echo $difficulty['DIFFICULTY_ID']; ?>" 
                                    <?php echo ($recipe['DIFFICULTY_NAME'] == $difficulty['DIFFICULTY_NAME']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($difficulty['DIFFICULTY_NAME']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_recipe_photo">Recipe Photo</label>
                    <?php if (!empty($recipe['PHOTO_URL'])): ?>
                        <div style="margin-bottom: 10px;">
                            <img src="<?php echo htmlspecialchars($recipe['PHOTO_URL']); ?>" alt="Current photo" style="max-width: 200px; height: auto; border-radius: 8px;">
                            <p style="font-size: 12px; color: #666; margin-top: 5px;">Current photo (leave empty to keep current)</p>
                        </div>
                    <?php endif; ?>
                    <input type="file" id="edit_recipe_photo" name="edit_recipe_photo" accept="image/*">
                </div>
                
                <div class="form-group">
                    <label for="edit_instructions">Instructions</label>
                    <textarea id="edit_instructions" name="edit_instructions" rows="6" required><?php echo htmlspecialchars($recipe['INSTRUCTIONS']); ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group third">
                        <label for="edit_prep_time">Prep Time (minutes)</label>
                        <input type="number" id="edit_prep_time" name="edit_prep_time" min="0" value="<?php echo htmlspecialchars($recipe['PREP_TIME_MINUTES'] ?? '0'); ?>" required>
                    </div>
                    
                    <div class="form-group third">
                        <label for="edit_cook_time">Cook Time (minutes)</label>
                        <input type="number" id="edit_cook_time" name="edit_cook_time" min="0" value="<?php echo htmlspecialchars($recipe['COOK_TIME_MINUTES'] ?? '0'); ?>" required>
                    </div>
                    
                    <div class="form-group third">
                        <label for="edit_servings">Servings</label>
                        <input type="number" id="edit_servings" name="edit_servings" min="1" value="<?php echo htmlspecialchars($recipe['SERVINGS'] ?? '1'); ?>" required>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" onclick="closeEditModal()" class="btn-secondary">Cancel</button>
                    <button type="submit" name="edit_recipe" class="btn-primary">Update Recipe</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Form -->
    <form id="deleteForm" method="post" action="" style="display: none;">
          <?php echo csrf_field(); ?>
        <input type="hidden" name="delete_recipe" value="1">
    </form>
    <?php endif; ?>

    <script>
        window.ViewRecipeConfig = {
            recipeId: <?php echo json_encode($recipeId); ?>,
            isOwner: <?php echo json_encode((bool)$isOwner); ?>
        };
    </script>
    <script src="<?php echo asset('assets/js/view-recipe.js'); ?>" defer></script>
</body>

</html>
