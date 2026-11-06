<?php
/**
 * EasyShop — Edit Category View
 */

$pageTitle   = 'Edit Category — EasyShop Admin';
$activeNav   = 'categories';
$depthPrefix = '../../';

require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';

$categoryId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($categoryId <= 0) {
    setFlash('error', 'Invalid category identifier provided.');
    header('Location: index.php');
    exit;
}

// Fetch current category
$stmt = mysqli_prepare($conn, "SELECT * FROM categories WHERE category_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $categoryId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$category = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$category) {
    setFlash('error', 'Category not found.');
    header('Location: index.php');
    exit;
}

$oldInput   = $_SESSION['old_category_input'] ?? $category;
$formErrors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['old_category_input'], $_SESSION['form_errors']);

$flashError = getFlash('error');
?>

<main class="admin-main">
  <div class="mb-4">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="index.php">Categories</a></li>
        <li class="breadcrumb-item active" aria-current="page">Edit: <?= e($category['category_name']) ?></li>
      </ol>
    </nav>
    <h1 class="h3 fw-bold text-dark mb-1">Edit Category</h1>
    <p class="text-muted small mb-0">Modify category details and publication status</p>
  </div>

  <?php if ($flashError): ?>
    <div class="alert alert-danger alert-dismissible fade show mb-4 small" role="alert">
      <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= e($flashError) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <div class="row">
    <div class="col-lg-8">
      <div class="admin-card p-4">
        <form action="../../actions/category-edit-action.php" method="POST" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="category_id" value="<?= (int)$category['category_id'] ?>">

          <!-- Category Name -->
          <div class="mb-3">
            <label for="category_name" class="form-label fw-semibold">Category Name <span class="text-danger">*</span></label>
            <input 
              type="text" 
              name="category_name" 
              id="category_name" 
              class="form-control <?= isset($formErrors['category_name']) ? 'is-invalid' : '' ?>" 
              value="<?= e($oldInput['category_name'] ?? '') ?>" 
              required
            >
            <?php if (isset($formErrors['category_name'])): ?>
              <div class="invalid-feedback"><?= e($formErrors['category_name']) ?></div>
            <?php endif; ?>
          </div>

          <!-- URL Slug -->
          <div class="mb-3">
            <label for="slug" class="form-label fw-semibold">URL Slug <span class="text-muted small fw-normal">(Must be unique)</span></label>
            <div class="input-group">
              <span class="input-group-text bg-light text-muted small">/category/</span>
              <input 
                type="text" 
                name="slug" 
                id="slug" 
                class="form-control <?= isset($formErrors['slug']) ? 'is-invalid' : '' ?>" 
                value="<?= e($oldInput['slug'] ?? '') ?>"
              >
            </div>
            <?php if (isset($formErrors['slug'])): ?>
              <div class="text-danger small mt-1"><?= e($formErrors['slug']) ?></div>
            <?php endif; ?>
          </div>

          <!-- Description -->
          <div class="mb-3">
            <label for="description" class="form-label fw-semibold">Description</label>
            <textarea 
              name="description" 
              id="description" 
              rows="3" 
              class="form-control"
            ><?= e($oldInput['description'] ?? '') ?></textarea>
          </div>

          <!-- Status -->
          <div class="mb-4">
            <label class="form-label fw-semibold d-block">Publication Status</label>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="status" id="statusActive" value="active" <?= ($oldInput['status'] ?? 'active') === 'active' ? 'checked' : '' ?>>
              <label class="form-check-label" for="statusActive">Active</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="status" id="statusInactive" value="inactive" <?= ($oldInput['status'] ?? '') === 'inactive' ? 'checked' : '' ?>>
              <label class="form-check-label" for="statusInactive">Inactive</label>
            </div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm px-3">
              <i class="bi bi-save me-1"></i> Update Category
            </button>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
