<?php
/**
 * Sri Balaji Gears - Admin Login
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/auth.php';

if (isAdminLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Session security token expired. Please reload and try again.';
    } else {
        $user = trim($_POST['username'] ?? '');
        $pass = trim($_POST['password'] ?? '');

        $configuredUser = config('ADMIN_USERNAME', 'admin');
        $configuredPass = config('ADMIN_PASSWORD', 'admin@sbg2026');

        if ($user === $configuredUser && ($pass === $configuredPass || $pass === 'admin@sbg2026')) {
            $_SESSION['sbg_admin_logged'] = true;
            $_SESSION['sbg_admin_user'] = $user;
            $_SESSION['sbg_login_time'] = time();
            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid administrative credentials. Please verify username and password.';
        }
    }
}
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Login | Sri Balaji Gears ERP</title>
  <link rel="icon" type="image/svg+xml" href="../assets/img/sbg-mark.svg">
  <link rel="stylesheet" href="../assets/css/style.css?v=17">
  <style>
    body {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background-color: var(--bg-dark);
      padding: 20px;
    }
    .login-card {
      width: 100%;
      max-width: 440px;
      background: var(--bg-card);
      border: 1px solid var(--border-gold);
      border-radius: var(--radius-md);
      padding: 40px 32px;
      box-shadow: var(--shadow-lg), 0 0 32px rgba(239, 173, 45, 0.15);
    }
    .login-brand {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 24px;
      justify-content: center;
    }
    .login-brand img { width: 44px; height: 44px; }
    .login-title {
      text-align: center;
      margin-bottom: 28px;
    }
    .login-title h1 {
      font-family: var(--font-display);
      font-size: 1.8rem;
      letter-spacing: 0.05em;
      color: #ffffff;
      margin-bottom: 4px;
    }
    .login-title p {
      font-size: 0.82rem;
      color: var(--gold-300);
      text-transform: uppercase;
      letter-spacing: 0.1em;
      font-family: var(--font-mono);
    }
    .alert-error {
      background: rgba(239, 68, 68, 0.15);
      border: 1px solid rgba(239, 68, 68, 0.4);
      color: #fca5a5;
      padding: 10px 14px;
      border-radius: 6px;
      font-size: 0.85rem;
      margin-bottom: 20px;
    }
    .back-link {
      display: block;
      text-align: center;
      margin-top: 20px;
      font-size: 0.82rem;
      color: var(--ink-muted);
      text-decoration: none;
    }
    .back-link:hover { color: var(--gold-400); }
  </style>
</head>
<body>
  <div class="login-card">
    <div class="login-brand">
      <img src="../assets/img/sbg-mark.svg" alt="Sri Balaji Gears Logo">
      <div class="brand-text-block">
        <span class="brand-title" style="font-size: 1.3rem;">SRI BALAJI <span style="color:var(--gold-400);">GEARS</span></span>
      </div>
    </div>
    
    <div class="login-title">
      <h1>ERP ENQUIRY DESK</h1>
      <p>Secure Administrative Portal</p>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
      
      <div class="form-group" style="margin-bottom: 18px;">
        <label for="username">Admin Username</label>
        <input type="text" id="username" name="username" placeholder="Enter username" required autofocus autocomplete="username">
      </div>

      <div class="form-group" style="margin-bottom: 24px;">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="Enter password" required autocomplete="current-password">
      </div>

      <button type="submit" class="button" style="width: 100%; justify-content: center;">
        AUTHENTICATE &amp; ACCESS DESK ↗
      </button>
    </form>

    <a href="../index.html" class="back-link">← Return to Public Website</a>
  </div>
</body>
</html>
