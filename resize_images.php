<?php
$file = 'Home.php';
$content = file_get_contents($file);

// Replace all Cloudinary URLs that have f_auto,q_auto or f_webp,q_auto
// with f_auto,q_auto,w_800 (for general images)
$content = preg_replace('/(image\/upload\/)(f_(?:auto|webp),q_auto)(\/v[0-9]+\/)/', '$1$2,w_800$3', $content);

// For avatars (Chef icons), which are smaller, let's use w_200.
// They usually contain "Cooking_" or "vf_" or "Choumicha" or "images-webp"
$content = preg_replace('/(image\/upload\/f_(?:auto|webp),q_auto),w_800(\/v[0-9]+\/feedora\/assets\/(?:Cooking_|vf_|Choumicha|images-webp))/', '$1,w_200$2', $content);

file_put_contents($file, $content);
echo "Done replacing URLs\n";
