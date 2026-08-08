<?php
/**
 * "Check your inbox" page shown after signup / when an unverified user tries to
 * sign in. Offers a throttled "resend email" action. When SMTP isn't configured
 * (dev), it surfaces the verification link directly so testing isn't blocked.
 */
require_once 'config/config.php';
session_start();

$email = $_SESSION['pending_verify_email'] ?? '';
$uid   = (int) ($_SESSION['pending_verify_uid'] ?? 0);

// Nothing pending → nothing to show.
if ($email === '' || $uid === 0) {
    header('Location: sign-in.php');
    exit;
}

$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resend') {
    csrf_verify();
    $rlKey = 'resend_' . ($_SERVER['REMOTE_ADDR'] ?? 'cli');
    if (rate_limit_exceeded($rlKey, 3, 900)) {
        $notice = 'Please wait a few minutes before requesting another email.';
    } else {
        rate_limit_record($rlKey, 900);
        try {
            $token = make_verification_token();
            $pdo->prepare("UPDATE USERS SET VERIFICATION_TOKEN = ?, VERIFICATION_EXPIRES = ? WHERE USER_ID = ? AND EMAIL_VERIFIED = 0")
                ->execute([hash_verification_token($token), date('Y-m-d H:i:s', time() + 86400), $uid]);
            [$sent] = send_verification_email($email, $_SESSION['user_name'] ?? 'there', $token);
            $notice = $sent ? 'A fresh verification email is on its way.' : 'Verification link refreshed.';
            if (!mailer_configured()) $_SESSION['pending_verify_link'] = verification_link($token);
        } catch (PDOException $e) {
            error_log('[verify-notice] ' . $e->getMessage());
            $notice = 'Something went wrong. Please try again.';
        }
    }
}
$devLink = $_SESSION['pending_verify_link'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Confirm your email · Feedora</title>
  <meta name="theme-color" content="#ED5A2C">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo asset('fonts.css'); ?>">
  <link rel="icon" href="https://res.cloudinary.com/dio1bvxbv/image/upload/f_auto,q_auto/v1782090013/feedora/assets/Frame-1171277973-svg.svg" type="image/svg+xml">
  <style>
    body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:radial-gradient(900px 420px at 100% -8%,rgba(237,90,44,.10),transparent 60%),#fbfbfd;font-family:'Qurova',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#4b5262;padding:20px}
    .card{max-width:480px;width:100%;background:#fff;border:1px solid #ececf1;border-radius:20px;padding:40px 34px;text-align:center;box-shadow:0 18px 50px -24px rgba(31,36,48,.28)}
    .ico{width:76px;height:76px;border-radius:50%;background:#fff2ec;color:#ED5A2C;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:36px}
    h1{font-family:'DM Serif Display',serif;font-size:27px;color:#1f2430;margin:0 0 12px}
    p{font-size:15.5px;line-height:1.6;margin:0 0 18px}
    .email{font-weight:700;color:#1f2430}
    .btn{display:inline-block;background:#ED5A2C;color:#fff;border:none;cursor:pointer;padding:13px 28px;border-radius:999px;font-weight:700;font-size:15px;font-family:inherit}
    .btn:hover{background:#d24a20}
    .link{display:inline-block;margin-top:16px;color:#65676b;text-decoration:none;font-size:14px}
    .link:hover{color:#ED5A2C}
    .notice{background:#eafaf0;border:1px solid #b8ecc9;color:#1c8a4b;padding:10px 14px;border-radius:12px;font-size:14px;margin-bottom:18px}
    .devbox{margin-top:22px;padding:14px;border:1px dashed #ED5A2C;border-radius:12px;background:#fff8f5;font-size:12.5px;color:#8a5a44;word-break:break-all;text-align:left}
    .devbox a{color:#ED5A2C}
  </style>
</head>
<body>
  <div class="card">
    <div class="ico">✉️</div>
    <h1>Confirm your email</h1>
    <?php if ($notice): ?><div class="notice"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
    <p>We sent a verification link to <span class="email"><?php echo htmlspecialchars($email); ?></span>.
       Click it to activate your account — then you're ready to cook!</p>
    <form method="POST" action="verify-notice.php">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="resend">
      <button type="submit" class="btn">Resend email</button>
    </form>
    <br>
    <a class="link" href="sign-in.php">Back to sign in</a>

    <?php if ($devLink): ?>
      <div class="devbox">
        <strong>Dev mode (SMTP not configured):</strong><br>
        Verify directly → <a href="<?php echo htmlspecialchars($devLink, ENT_QUOTES); ?>"><?php echo htmlspecialchars($devLink); ?></a>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
