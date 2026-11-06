<?php
/**
 * EasyShop — Customer Profile & Security Settings Page
 * 
 * Protected Route: Requires active customer session.
 * Displays customer account information and provides secure forms
 * to update personal details (Name, Email, Phone) and change passwords.
 */

$pageTitle   = 'Profile Settings — EasyShop';
$pageDesc    = 'Manage your EasyShop profile details, update contact information and change your password.';
$activePage  = 'profile';
$depthPrefix = '../';

require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';

$userId = (int)$_SESSION['user']['user_id'];
$customer = null;

if ($conn) {
    $stmt = mysqli_prepare($conn, "SELECT user_id, name, email, phone, role, status, created_at FROM users WHERE user_id = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $customer = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
    }
}

if (!$customer) {
    setFlash('error', 'User account not found.');
    header('Location: ../auth/login.php');
    exit;
}

$flashSuccess = getFlash('success');
$flashWarning = getFlash('warning');
$flashError   = getFlash('error');
$flashInfo    = getFlash('info');

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

  <!-- Breadcrumbs & Heading -->
  <div class="py-4 bg-light-custom border-bottom">
    <div class="container">
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1 small">
          <li class="breadcrumb-item"><a href="../index.php" class="text-decoration-none">Home</a></li>
          <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">My Account</a></li>
          <li class="breadcrumb-item active" aria-current="page">Profile Settings</li>
        </ol>
      </nav>
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
          <h1 class="h3 fw-bold text-dark mb-1">Account &amp; Security Settings</h1>
          <p class="text-muted small mb-0">Update your personal information and manage account security</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
        </a>
      </div>
    </div>
  </div>

  <main class="section-padding py-5">
    <div class="container">

      <!-- Flash Notifications -->
      <?php if ($flashSuccess): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4 small" role="alert">
          <i class="bi bi-check-circle-fill me-1"></i> <?= e($flashSuccess) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <?php if ($flashWarning): ?>
        <div class="alert alert-warning alert-dismissible fade show mb-4 small" role="alert">
          <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= e($flashWarning) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <?php if ($flashError): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4 small" role="alert">
          <i class="bi bi-exclamation-octagon-fill me-1"></i> <?= e($flashError) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <?php if ($flashInfo): ?>
        <div class="alert alert-info alert-dismissible fade show mb-4 small" role="alert">
          <i class="bi bi-info-circle-fill me-1"></i> <?= e($flashInfo) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <div class="row g-4">
        <!-- Left Column: Personal Information Form -->
        <div class="col-lg-7">
          <div class="card border-0 shadow-sm rounded-4 p-4">
            <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
              <div class="bg-primary-subtle text-primary p-2 rounded-3 fs-5">
                <i class="bi bi-person-badge"></i>
              </div>
              <div>
                <h5 class="fw-bold text-dark mb-0">Personal Profile</h5>
                <p class="text-muted small mb-0">Editable contact information</p>
              </div>
            </div>

            <form action="../actions/update-profile.php" method="POST">
              <?= csrf_field() ?>

              <!-- Full Name -->
              <div class="mb-3">
                <label for="name" class="form-label small fw-semibold text-dark">
                  Full Name <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                  <span class="input-group-text bg-light text-muted"><i class="bi bi-person"></i></span>
                  <input type="text" class="form-control" id="name" name="name" 
                         value="<?= e($customer['name']) ?>" required maxlength="100">
                </div>
              </div>

              <!-- Email Address -->
              <div class="mb-3">
                <label for="email" class="form-label small fw-semibold text-dark">
                  Email Address <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                  <span class="input-group-text bg-light text-muted"><i class="bi bi-envelope"></i></span>
                  <input type="email" class="form-control" id="email" name="email" 
                         value="<?= e($customer['email']) ?>" required maxlength="190">
                </div>
                <div class="form-text small text-muted">Used for login and order notifications. Must be unique.</div>
              </div>

              <!-- Contact Phone Number -->
              <div class="mb-3">
                <label for="phone" class="form-label small fw-semibold text-dark">
                  Contact Phone Number
                </label>
                <div class="input-group">
                  <span class="input-group-text bg-light text-muted"><i class="bi bi-telephone"></i></span>
                  <input type="tel" class="form-control" id="phone" name="phone" 
                         value="<?= e($customer['phone'] ?? '') ?>" maxlength="20"
                         placeholder="e.g. 03001234567">
                </div>
                <div class="form-text small text-muted">Pre-fills during checkout for courier delivery.</div>
              </div>

              <!-- Read-Only Account Metadata -->
              <div class="row g-2 mb-4 pt-2 border-top">
                <div class="col-sm-6">
                  <span class="text-muted small d-block">Account Type:</span>
                  <span class="badge bg-secondary-subtle text-secondary text-uppercase fw-semibold">
                    <?= e($customer['role']) ?>
                  </span>
                </div>
                <div class="col-sm-6">
                  <span class="text-muted small d-block">Member Since:</span>
                  <span class="small fw-semibold text-dark">
                    <?= date('F j, Y', strtotime($customer['created_at'])) ?>
                  </span>
                </div>
              </div>

              <button type="submit" class="btn btn-primary-custom px-4">
                <i class="bi bi-save me-1"></i> Save Profile Changes
              </button>
            </form>
          </div>
        </div>

        <!-- Right Column: Security & Password Management Form -->
        <div class="col-lg-5">
          <div class="card border-0 shadow-sm rounded-4 p-4">
            <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
              <div class="bg-warning-subtle text-warning-emphasis p-2 rounded-3 fs-5">
                <i class="bi bi-shield-lock"></i>
              </div>
              <div>
                <h5 class="fw-bold text-dark mb-0">Change Password</h5>
                <p class="text-muted small mb-0">Keep your account safe</p>
              </div>
            </div>

            <form action="../actions/change-password.php" method="POST">
              <?= csrf_field() ?>

              <!-- Current Password -->
              <div class="mb-3">
                <label for="current_password" class="form-label small fw-semibold text-dark">
                  Current Password <span class="text-danger">*</span>
                </label>
                <input type="password" class="form-control" id="current_password" name="current_password" 
                       required placeholder="Enter your current password">
              </div>

              <!-- New Password -->
              <div class="mb-3">
                <label for="new_password" class="form-label small fw-semibold text-dark">
                  New Password <span class="text-danger">*</span>
                </label>
                <input type="password" class="form-control" id="new_password" name="new_password" 
                       required minlength="8" placeholder="Minimum 8 characters">
                <div class="form-text small text-muted">Must be at least 8 characters long.</div>
              </div>

              <!-- Confirm Password -->
              <div class="mb-4">
                <label for="confirm_password" class="form-label small fw-semibold text-dark">
                  Confirm New Password <span class="text-danger">*</span>
                </label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                       required minlength="8" placeholder="Re-enter new password">
              </div>

              <button type="submit" class="btn btn-outline-primary w-100">
                <i class="bi bi-key me-1"></i> Update Password
              </button>
            </form>
          </div>
        </div>
      </div>

    </div>
  </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
