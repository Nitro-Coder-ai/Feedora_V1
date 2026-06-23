<?php
$file = 'Home.php';
$content = file_get_contents($file);
$content = str_replace('w_200', 'w_600', $content);
file_put_contents($file, $content);
echo "Quality restored!";
