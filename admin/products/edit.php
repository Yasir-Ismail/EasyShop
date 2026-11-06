<?php
/**
 * EasyShop — Edit Product View
 */

$pageTitle   = 'Edit Product — EasyShop Admin';
$activeNav   = 'products';
$depthPrefix = '../../';

require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';

$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($productId <= 0) {
    setFlash('error', 'Invalid product identifier.');
    header('Location: index.php');
    exit;
}

// Fetch current product
$stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE product_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $productId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$product) {
    setFlash('error', 'Product not found.');
    header('Location: index.php');
    exit;
}

// Fetch categories
$categories = [];
if ($catRes = mysqli_query($conn, "SELECT category_id, category_name FROM categories ORDER BY category_name ASC")) {
    while ($c = mysqli_fetch_assoc($catRes)) {
        $categories[] = $c;
    }
    mysqli_free_result($catRes);
}

$oldInput   = $_SESSION['old_product_input'] ?? $product;
$formErrors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['old_product_input'], $_SESSION['form_errors']);

$flashError = getFlash('error');
?>

<main class="admin-main">
  <div class="mb-4">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="index.php">Products</a></li>
        <li class="breadcrumb-item active" aria-current="page">Edit: <?= e($product['product_name']) ?></li>
      </ol>
    </nav>
    <h1 class="h3 fw-bold text-dark mb-1">Edit Product</h1>
    <p class="text-muted small mb-0">Update pricing, inventory, specs, or product photography</p>
  </div>

  <?php if ($flashError): ?>
    <div class="alert alert-danger alert-dismissible fade show mb-4 small" role="alert">
      <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= e($flashError) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <div class="row">
    <div class="col-lg-10">
      <div class="admin-card p-4">
        <form action="../../actions/product-edit-action.php" method="POST" enctype="multipart/form-data" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="product_id" value="<?= (int)$product['product_id'] ?>">

          <div class="row g-3">
            <!-- Product Name -->
            <div class="col-md-8">
              <label for="product_name" class="form-label fw-semibold">Product Title <span class="text-danger">*</span></label>
              <input 
                type="text" 
                name="product_name" 
                id="product_name" 
                class="form-control <?= isset($formErrors['product_name']) ? 'is-invalid' : '' ?>" 
                value="<?= e($oldInput['product_name'] ?? '') ?>" 
                required
              >
              <?php if (isset($formErrors['product_name'])): ?>
                <div class="invalid-feedback"><?= e($formErrors['product_name']) ?></div>
              <?php endif; ?>
            </div>

            <!-- Category -->
            <div class="col-md-4">
              <label for="category_id" class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
              <select name="category_id" id="category_id" class="form-select <?= isset($formErrors['category_id']) ? 'is-invalid' : '' ?>" required>
                <option value="">Select Category...</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= (int)$cat['category_id'] ?>" <?= ((int)($oldInput['category_id'] ?? 0) === (int)$cat['category_id']) ? 'selected' : '' ?>>
                    <?= e($cat['category_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if (isset($formErrors['category_id'])): ?>
                <div class="invalid-feedback"><?= e($formErrors['category_id']) ?></div>
              <?php endif; ?>
            </div>

            <!-- SKU -->
            <div class="col-md-6">
              <label for="sku" class="form-label fw-semibold">SKU (Stock Keeping Unit) <span class="text-danger">*</span></label>
              <input 
                type="text" 
                name="sku" 
                id="sku" 
                class="form-control text-uppercase font-monospace <?= isset($formErrors['sku']) ? 'is-invalid' : '' ?>" 
                value="<?= e($oldInput['sku'] ?? '') ?>" 
                required
              >
              <?php if (isset($formErrors['sku'])): ?>
                <div class="invalid-feedback"><?= e($formErrors['sku']) ?></div>
              <?php endif; ?>
            </div>

            <!-- Slug -->
            <div class="col-md-6">
              <label for="slug" class="form-label fw-semibold">URL Slug <span class="text-muted small fw-normal">(Must be unique)</span></label>
              <input 
                type="text" 
                name="slug" 
                id="slug" 
                class="form-control <?= isset($formErrors['slug']) ? 'is-invalid' : '' ?>" 
                value="<?= e($oldInput['slug'] ?? '') ?>"
              >
              <?php if (isset($formErrors['slug'])): ?>
                <div class="invalid-feedback"><?= e($formErrors['slug']) ?></div>
              <?php endif; ?>
            </div>

            <!-- Price -->
            <div class="col-md-4">
              <label for="price" class="form-label fw-semibold">Selling Price (PKR) <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text bg-light text-muted">Rs.</span>
                <input 
                  type="number" 
                  step="0.01" 
                  min="0.01" 
                  name="price" 
                  id="price" 
                  class="form-control <?= isset($formErrors['price']) ? 'is-invalid' : '' ?>" 
                  value="<?= e($oldInput['price'] ?? '') ?>" 
                  required
                >
                <?php if (isset($formErrors['price'])): ?>
                  <div class="invalid-feedback"><?= e($formErrors['price']) ?></div>
                <?php endif; ?>
              </div>
            </div>

            <!-- Original Price -->
            <div class="col-md-4">
              <label for="original_price" class="form-label fw-semibold">Original Price (PKR) <span class="text-muted small fw-normal">(Optional)</span></label>
              <div class="input-group">
                <span class="input-group-text bg-light text-muted">Rs.</span>
                <input 
                  type="number" 
                  step="0.01" 
                  min="0.01" 
                  name="original_price" 
                  id="original_price" 
                  class="form-control <?= isset($formErrors['original_price']) ? 'is-invalid' : '' ?>" 
                  value="<?= e($oldInput['original_price'] ?? '') ?>"
                >
                <?php if (isset($formErrors['original_price'])): ?>
                  <div class="invalid-feedback"><?= e($formErrors['original_price']) ?></div>
                <?php endif; ?>
              </div>
            </div>

            <!-- Stock Quantity -->
            <div class="col-md-4">
              <label for="stock" class="form-label fw-semibold">Stock Quantity <span class="text-danger">*</span></label>
              <input 
                type="number" 
                min="0" 
                step="1" 
                name="stock" 
                id="stock" 
                class="form-control <?= isset($formErrors['stock']) ? 'is-invalid' : '' ?>" 
                value="<?= e((string)($oldInput['stock'] ?? '0')) ?>" 
                required
              >
              <?php if (isset($formErrors['stock'])): ?>
                <div class="invalid-feedback"><?= e($formErrors['stock']) ?></div>
              <?php endif; ?>
            </div>

            <!-- Product Description -->
            <div class="col-12">
              <label for="description" class="form-label fw-semibold">Product Description</label>
              <textarea 
                name="description" 
                id="description" 
                rows="4" 
                class="form-control"
              ><?= e($oldInput['description'] ?? '') ?></textarea>
            </div>

            <!-- Product Image Section -->
            <div class="col-md-8">
              <label for="image" class="form-label fw-semibold">Replace Product Image <span class="text-muted small fw-normal">(Optional)</span></label>
              <input 
                type="file" 
                name="image" 
                id="image" 
                class="form-control <?= isset($formErrors['image']) ? 'is-invalid' : '' ?>" 
                accept="image/jpeg,image/png,image/webp"
              >
              <?php if (isset($formErrors['image'])): ?>
                <div class="invalid-feedback"><?= e($formErrors['image']) ?></div>
              <?php endif; ?>
              <div class="form-text small">Leave empty to keep current image. Accepts JPG, PNG, WebP up to 2MB.</div>
            </div>

            <!-- Current Image Thumbnail -->
            <div class="col-md-4">
              <label class="form-label fw-semibold d-block">Current Image</label>
              <div class="d-flex align-items-center gap-2 p-2 border rounded bg-light">
                <img 
                  src="<?= e(get_product_image_url($product['image'], $depthPrefix)) ?>" 
                  alt="Current preview" 
                  style="width: 50px; height: 50px; object-fit: contain;"
                  class="bg-white rounded border p-1"
                >
                <div class="small text-muted text-break">
                  <?= e(basename($product['image'])) ?>
                </div>
              </div>
            </div>

            <!-- Status -->
            <div class="col-md-12">
              <label class="form-label fw-semibold d-block">Publication Status</label>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="status" id="pActive" value="active" <?= ($oldInput['status'] ?? 'active') === 'active' ? 'checked' : '' ?>>
                <label class="form-check-label" for="pActive">Active (In Catalog)</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="status" id="pInactive" value="inactive" <?= ($oldInput['status'] ?? '') === 'inactive' ? 'checked' : '' ?>>
                <label class="form-check-label" for="pInactive">Inactive (Hidden)</label>
              </div>
            </div>

          </div>

          <hr class="my-4">

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm px-4">
              <i class="bi bi-save me-1"></i> Update Product
            </button>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
