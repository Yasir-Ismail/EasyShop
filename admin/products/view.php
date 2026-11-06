<?php
/**
 * EasyShop — View Product Details
 */

$pageTitle   = 'Product Details — EasyShop Admin';
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

$stmt = mysqli_prepare($conn, "SELECT p.*, c.category_name 
                               FROM products p 
                               LEFT JOIN categories c ON p.category_id = c.category_id 
                               WHERE p.product_id = ? LIMIT 1");
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
?>

<main class="admin-main">
  <div class="mb-4">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="index.php">Products</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e($product['product_name']) ?></li>
      </ol>
    </nav>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h1 class="h3 fw-bold text-dark mb-0"><?= e($product['product_name']) ?></h1>
      <div class="d-flex gap-2">
        <a href="edit.php?id=<?= (int)$product['product_id'] ?>" class="btn btn-outline-primary btn-sm">
          <i class="bi bi-pencil me-1"></i> Edit Product
        </a>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-arrow-left me-1"></i> Back to Catalog
        </a>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <!-- Image Card -->
    <div class="col-lg-4">
      <div class="admin-card p-4 text-center">
        <div class="p-3 bg-light rounded-3 mb-3 d-flex align-items-center justify-content-center" style="min-height: 280px;">
          <img 
            src="<?= e(get_product_image_url($product['image'], $depthPrefix)) ?>" 
            alt="<?= e($product['product_name']) ?>" 
            style="max-width: 100%; max-height: 250px; object-fit: contain;"
          >
        </div>
        <div class="text-muted small">
          Path: <code><?= e($product['image']) ?></code>
        </div>
      </div>
    </div>

    <!-- Product Metadata Card -->
    <div class="col-lg-8">
      <div class="admin-card p-4">
        <h5 class="fw-bold mb-3 border-bottom pb-2">Catalog Specifications</h5>

        <div class="row g-3 mb-4">
          <div class="col-sm-6">
            <div class="text-muted small">Category</div>
            <div class="fw-semibold text-dark fs-6"><?= e($product['category_name'] ?? 'Uncategorized') ?></div>
          </div>

          <div class="col-sm-6">
            <div class="text-muted small">SKU Code</div>
            <div class="fw-semibold font-monospace text-dark fs-6"><?= e($product['sku']) ?></div>
          </div>

          <div class="col-sm-6">
            <div class="text-muted small">URL Slug</div>
            <div class="text-muted small font-monospace">/product/<?= e($product['slug']) ?></div>
          </div>

          <div class="col-sm-6">
            <div class="text-muted small">Status</div>
            <div>
              <?php if ($product['status'] === 'active'): ?>
                <span class="badge-status-active"><i class="bi bi-check2 me-1"></i>Active</span>
              <?php else: ?>
                <span class="badge-status-inactive"><i class="bi bi-x me-1"></i>Inactive</span>
              <?php endif; ?>
            </div>
          </div>

          <div class="col-sm-6">
            <div class="text-muted small">Selling Price</div>
            <div class="h4 fw-bold text-primary mb-0"><?= format_pkr($product['price'], true) ?></div>
            <?php if (!empty($product['original_price'])): ?>
              <div class="text-muted small text-decoration-line-through">
                Original: <?= format_pkr($product['original_price'], true) ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="col-sm-6">
            <div class="text-muted small">Inventory Stock</div>
            <div class="h5 fw-bold mb-0">
              <?php if ((int)$product['stock'] === 0): ?>
                <span class="text-danger"><i class="bi bi-x-circle me-1"></i>Out of Stock (0 units)</span>
              <?php elseif ((int)$product['stock'] <= 5): ?>
                <span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>Low Stock (<?= (int)$product['stock'] ?> units)</span>
              <?php else: ?>
                <span class="text-success"><i class="bi bi-check-circle me-1"></i>In Stock (<?= (int)$product['stock'] ?> units)</span>
              <?php endif; ?>
            </div>
          </div>

          <div class="col-12">
            <div class="text-muted small mb-1">Description</div>
            <div class="p-3 bg-light rounded text-dark small" style="white-space: pre-wrap; line-height: 1.6;">
              <?= !empty($product['description']) ? e($product['description']) : '<em>No description provided.</em>' ?>
            </div>
          </div>

          <div class="col-12 text-muted small pt-2 border-top">
            Created on <?= date('F j, Y, g:i a', strtotime($product['created_at'])) ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
