<?php
/**
 * EasyShop — Customer & Administrator Login View
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

// If user is already logged in, redirect them to their respective portal
if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: ../admin/index.php');
    } else {
        header('Location: ../customer/dashboard.php');
    }
    exit;
}

$oldEmail = $_SESSION['old_login_email'] ?? '';
unset($_SESSION['old_login_email']);

$flashSuccess = getFlash('success');
$flashError   = getFlash('error');
$flashInfo    = getFlash('info');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — EasyShop</title>

  <!-- Google Fonts & Bootstrap 5 -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css">

  <style>
    body {
      background-color: var(--color-bg-secondary);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }
    .auth-wrapper {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 3rem 1rem;
    }
    .auth-card {
      background: var(--color-white);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-lg);
      border: 1px solid var(--color-border);
      width: 100%;
      max-width: 440px;
      padding: 2.5rem 2rem;
    }
    .auth-header {
      text-align: center;
      margin-bottom: 2rem;
    }
    .auth-title {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--color-primary);
      margin-top: 0.75rem;
      margin-bottom: 0.25rem;
    }
    .auth-subtitle {
      color: var(--color-text-muted);
      font-size: 0.88rem;
    }
    .form-label {
      font-weight: 600;
      font-size: 0.85rem;
      color: var(--color-primary);
      margin-bottom: 0.4rem;
    }
    .form-control {
      padding: 0.75rem 1rem;
      border: 1.5px solid var(--color-border);
      border-radius: var(--radius-sm);
      font-size: 0.95rem;
      transition: border-color var(--transition-fast);
    }
    .form-control:focus {
      border-color: var(--color-accent);
      box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
    }
    .auth-footer {
      text-align: center;
      margin-top: 1.5rem;
      font-size: 0.88rem;
      color: var(--color-text-muted);
    }
    .auth-footer a {
      color: var(--color-accent);
      font-weight: 600;
    }
    .auth-footer a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>

  <!-- Minimal Brand Header -->
  <header class="py-3 bg-white border-bottom">
    <div class="container d-flex justify-content-between align-items-center">
      <a href="../index.html" class="navbar-brand">
        <span class="brand-easy">Easy</span><span class="brand-shop">Shop</span>
      </a>
      <a href="../index.html" class="text-muted small">
        <i class="bi bi-arrow-left me-1"></i> Return to Store
      </a>
    </div>
  </header>

  <!-- Login Form Wrapper -->
  <div class="auth-wrapper">
    <div class="auth-card">

      <div class="auth-header">
        <div class="d-inline-flex p-3 rounded-circle bg-light-custom text-primary mb-2">
          <i class="bi bi-person-lock fs-2 text-primary"></i>
        </div>
        <h1 class="auth-title">Welcome Back</h1>
        <p class="auth-subtitle">Log in to manage your orders, wishlist, and profile</p>
      </div>

      <!-- Flash Messages -->
      <?php if ($flashSuccess): ?>
        <div class="alert alert-success alert-dismissible fade show small" role="alert">
          <i class="bi bi-check-circle-fill me-1"></i> <?= e($flashSuccess) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <?php if ($flashError): ?>
        <div class="alert alert-danger alert-dismissible fade show small" role="alert">
          <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= e($flashError) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <?php if ($flashInfo): ?>
        <div class="alert alert-info alert-dismissible fade show small" role="alert">
          <i class="bi bi-info-circle-fill me-1"></i> <?= e($flashInfo) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <!-- Form Post to Action Processor -->
      <form action="../actions/login-action.php" method="POST" novalidate>
        <!-- CSRF Token -->
        <?= csrf_field() ?>

        <div class="mb-3">
          <label for="email" class="form-label">Email Address</label>
          <div class="input-group">
            <span class="input-group-text bg-light text-muted"><i class="bi bi-envelope"></i></span>
            <input 
              type="email" 
              name="email" 
              id="email" 
              class="form-control" 
              placeholder="name@example.pk" 
              value="<?= e($oldEmail) ?>" 
              required 
              autocomplete="email"
              autofocus
            >
          </div>
        </div>

        <div class="mb-4">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <label for="password" class="form-label mb-0">Password</label>
          </div>
          <div class="input-group">
            <span class="input-group-text bg-light text-muted"><i class="bi bi-lock"></i></span>
            <input 
              type="password" 
              name="password" 
              id="password" 
              class="form-control" 
              placeholder="••••••••" 
              required 
              autocomplete="current-password"
            >
          </div>
        </div>

        <button type="submit" class="btn btn-primary-custom w-100 py-2">
          <i class="bi bi-box-arrow-in-right me-1"></i> Log In
        </button>
      </form>

      <div class="auth-footer">
        Don't have an account yet? 
        <a href="register.php">Create Account</a>
      </div>

    </div>
  </div>

  <footer class="py-3 text-center text-muted small bg-white border-top">
    &copy; <?= date('Y') ?> EasyShop. All rights reserved.
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
