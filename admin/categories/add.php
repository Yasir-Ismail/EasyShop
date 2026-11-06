<?php
/**
 * EasyShop — Add New Category View
 */

$pageTitle   = 'Add Category — EasyShop Admin';
$activeNav   = 'categories';
$depthPrefix = '../../';

require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';

$oldInput   = $_SESSION['old_category_input'] ?? [];
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
        <li class="breadcrumb-item active" aria-current="page">Add Category</li>
      </ol>
    </nav>
    <h1 class="h3 fw-bold text-dark mb-1">Create New Category</h1>
    <p class="text-muted small mb-0">Define a new department for categorizing products</p>
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
        <form action="../../actions/category-add-action.php" method="POST" novalidate>
          <?= csrf_field() ?>

          <!-- Category Name -->
          <div class="mb-3">
            <label for="category_name" class="form-label fw-semibold">Category Name <span class="text-danger">*</span></label>
            <input 
              type="text" 
              name="category_name" 
              id="category_name" 
              class="form-control <?= isset($formErrors['category_name']) ? 'is-invalid' : '' ?>" 
              placeholder="e.g. Home & Living" 
              value="<?= e($oldInput['category_name'] ?? '') ?>" 
              required
              autofocus
            >
            <?php if (isset($formErrors['category_name'])): ?>
              <div class="invalid-feedback"><?= e($formErrors['category_name']) ?></div>
            <?php endif; ?>
          </div>

          <!-- URL Slug -->
          <div class="mb-3">
            <label for="slug" class="form-label fw-semibold">URL Slug <span class="text-muted small fw-normal">(Optional, generated automatically if left empty)</span></label>
            <div class="input-group">
              <span class="input-group-text bg-light text-muted small">/category/</span>
              <input 
                type="text" 
                name="slug" 
                id="slug" 
                class="form-control <?= isset($formErrors['slug']) ? 'is-invalid' : '' ?>" 
                placeholder="home-living" 
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
              placeholder="Brief description of products featured in this category..."
            ><?= e($oldInput['description'] ?? '') ?></textarea>
          </div>

          <!-- Status -->
          <div class="mb-4">
            <label class="form-label fw-semibold d-block">Publication Status</label>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="status" id="statusActive" value="active" <?= ($oldInput['status'] ?? 'active') === 'active' ? 'checked' : '' ?>>
              <label class="form-check-label" for="statusActive">Active (Visible in store)</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="status" id="statusInactive" value="inactive" <?= ($oldInput['status'] ?? '') === 'inactive' ? 'checked' : '' ?>>
              <label class="form-check-label" for="statusInactive">Inactive (Hidden)</label>
            </div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm px-3">
              <i class="bi bi-check2-circle me-1"></i> Save Category
            </button>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
