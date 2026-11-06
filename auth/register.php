<?php
/**
 * EasyShop — Customer Registration View
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

// Redirect logged-in users away from registration
if (isLoggedIn()) {
    header('Location: ../customer/dashboard.php');
    exit;
}

$oldInput   = $_SESSION['old_input'] ?? [];
$formErrors = $_SESSION['form_errors'] ?? [];

// Clear flash sessions for form data
unset($_SESSION['old_input'], $_SESSION['form_errors']);

$flashSuccess = getFlash('success');
$flashError   = getFlash('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Account — EasyShop</title>

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
      max-width: 500px;
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
    .form-control.is-invalid {
      border-color: var(--color-danger);
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

  <!-- Minimal Header -->
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

  <!-- Register Form Wrapper -->
  <div class="auth-wrapper">
    <div class="auth-card">

      <div class="auth-header">
        <div class="d-inline-flex p-3 rounded-circle bg-light-custom text-primary mb-2">
          <i class="bi bi-person-plus fs-2 text-primary"></i>
        </div>
        <h1 class="auth-title">Create an Account</h1>
        <p class="auth-subtitle">Join EasyShop for fast checkout, order tracking, and deals</p>
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

      <!-- Register Form -->
      <form action="../actions/register-action.php" method="POST" novalidate>
        <!-- CSRF Token -->
        <?= csrf_field() ?>

        <!-- Full Name -->
        <div class="mb-3">
          <label for="name" class="form-label">Full Name</label>
          <div class="input-group has-validation">
            <span class="input-group-text bg-light text-muted"><i class="bi bi-person"></i></span>
            <input 
              type="text" 
              name="name" 
              id="name" 
              class="form-control <?= isset($formErrors['name']) ? 'is-invalid' : '' ?>" 
              placeholder="e.g. Muhammad Usman" 
              value="<?= e($oldInput['name'] ?? '') ?>" 
              required
              autofocus
            >
            <?php if (isset($formErrors['name'])): ?>
              <div class="invalid-feedback"><?= e($formErrors['name']) ?></div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Email Address -->
        <div class="mb-3">
          <label for="email" class="form-label">Email Address</label>
          <div class="input-group has-validation">
            <span class="input-group-text bg-light text-muted"><i class="bi bi-envelope"></i></span>
            <input 
              type="email" 
              name="email" 
              id="email" 
              class="form-control <?= isset($formErrors['email']) ? 'is-invalid' : '' ?>" 
              placeholder="name@example.pk" 
              value="<?= e($oldInput['email'] ?? '') ?>" 
              required
            >
            <?php if (isset($formErrors['email'])): ?>
              <div class="invalid-feedback"><?= e($formErrors['email']) ?></div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Phone Number -->
        <div class="mb-3">
          <label for="phone" class="form-label">Phone Number</label>
          <div class="input-group has-validation">
            <span class="input-group-text bg-light text-muted"><i class="bi bi-telephone"></i></span>
            <input 
              type="tel" 
              name="phone" 
              id="phone" 
              class="form-control <?= isset($formErrors['phone']) ? 'is-invalid' : '' ?>" 
              placeholder="0300 1234567" 
              value="<?= e($oldInput['phone'] ?? '') ?>" 
              required
            >
            <?php if (isset($formErrors['phone'])): ?>
              <div class="invalid-feedback"><?= e($formErrors['phone']) ?></div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Password -->
        <div class="mb-3">
          <label for="password" class="form-label">Password</label>
          <div class="input-group has-validation">
            <span class="input-group-text bg-light text-muted"><i class="bi bi-lock"></i></span>
            <input 
              type="password" 
              name="password" 
              id="password" 
              class="form-control <?= isset($formErrors['password']) ? 'is-invalid' : '' ?>" 
              placeholder="Minimum 6 characters" 
              required
            >
            <?php if (isset($formErrors['password'])): ?>
              <div class="invalid-feedback"><?= e($formErrors['password']) ?></div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Confirm Password -->
        <div class="mb-4">
          <label for="confirm_password" class="form-label">Confirm Password</label>
          <div class="input-group has-validation">
            <span class="input-group-text bg-light text-muted"><i class="bi bi-shield-check"></i></span>
            <input 
              type="password" 
              name="confirm_password" 
              id="confirm_password" 
              class="form-control <?= isset($formErrors['confirm_password']) ? 'is-invalid' : '' ?>" 
              placeholder="Re-enter your password" 
              required
            >
            <?php if (isset($formErrors['confirm_password'])): ?>
              <div class="invalid-feedback"><?= e($formErrors['confirm_password']) ?></div>
            <?php endif; ?>
          </div>
        </div>

        <button type="submit" class="btn btn-primary-custom w-100 py-2">
          <i class="bi bi-check2-circle me-1"></i> Register Account
        </button>
      </form>

      <div class="auth-footer">
        Already have an account? 
        <a href="login.php">Log In</a>
      </div>

    </div>
  </div>

  <footer class="py-3 text-center text-muted small bg-white border-top">
    &copy; <?= date('Y') ?> EasyShop. All rights reserved.
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
