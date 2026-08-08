<?php
/**
 * Email verification landing page. The link emailed at signup points here:
 *   verify-email.php?token=<hex>
 * A valid, unexpired token flips EMAIL_VERIFIED, logs the user in, and forwards
 * them into onboarding / the dashboard.
 */
require_once 'config/config.php';
session_start();

$token = trim($_GET['token'] ?? '');
$ok = false;
$title = 'Verification failed';
$message = 'This verification link is invalid or has expired. Please sign in to request a new one.';

if ($token !== '') {
    try {
        // We stored only the SHA-256 of the token, so match on the hash.
        $stmt = $pdo->prepare(
            "SELECT USER_ID, NAME, EMAIL, PROFILE_IMAGE, ONBOARDED
             FROM USERS
             WHERE VERIFICATION_TOKEN = ? AND VERIFICATION_EXPIRES > NOW() AND EMAIL_VERIFIED = 0
             LIMIT 1"
        );
        $stmt->execute([hash_verification_token($token)]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $pdo->prepare("UPDATE USERS SET EMAIL_VERIFIED = 1, VERIFICATION_TOKEN = NULL, VERIFICATION_EXPIRES = NULL WHERE USER_ID = ?")
                ->execute([$user['USER_ID']]);

            // Log them in (fresh session id) and clear any pending-verify markers.
            session_regenerate_id(true);
            $_SESSION['user_id']    = (int) $user['USER_ID'];
            $_SESSION['user_name']  = $user['NAME'];
            $_SESSION['user_email'] = $user['EMAIL'];
            if (!empty($user['PROFILE_IMAGE'])) $_SESSION['profile_image'] = $user['PROFILE_IMAGE'];
            unset($_SESSION['pending_verify_email'], $_SESSION['pending_verify_uid'], $_SESSION['pending_verify_link']);

            header('Location: ' . ((int) ($user['ONBOARDED'] ?? 0) === 0 ? 'onboarding.php' : 'dashboard.php'));
            exit;
        }
    } catch (PDOException $e) {
        error_log('[verify-email] ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($title); ?> · Feedora</title>
  <meta name="theme-color" content="#ED5A2C">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo asset('fonts.css'); ?>">
  <link rel="icon" href="https://res.cloudinary.com/dio1bvxbv/image/upload/f_auto,q_auto/v1782090013/feedora/assets/Frame-1171277973-svg.svg" type="image/svg+xml">
  <style>
    body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:radial-gradient(900px 420px at 100% -8%,rgba(237,90,44,.10),transparent 60%),#fbfbfd;font-family:'Qurova',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#4b5262;padding:20px}
    .card{max-width:460px;width:100%;background:#fff;border:1px solid #ececf1;border-radius:20px;padding:40px 34px;text-align:center;box-shadow:0 18px 50px -24px rgba(31,36,48,.28)}
    .ico{width:72px;height:72px;border-radius:50%;background:#fff2ec;color:#ED5A2C;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:34px}
    h1{font-family:'DM Serif Display',serif;font-size:26px;color:#1f2430;margin:0 0 12px}
    p{font-size:15.5px;line-height:1.6;margin:0 0 24px}
    .btn{display:inline-block;background:#ED5A2C;color:#fff;text-decoration:none;padding:13px 28px;border-radius:999px;font-weight:700;font-size:15px}
    .btn:hover{background:#d24a20}
  </style>
</head>
<body>
  <div class="card">
    <div class="ico">✕</div>
    <h1><?php echo htmlspecialchars($title); ?></h1>
    <p><?php echo htmlspecialchars($message); ?></p>
    <a class="btn" href="sign-in.php">Back to sign in</a>
  </div>
</body>
</html>
