<?php
/**
 * EasyShop — Customer Saved Wishlist Page
 * 
 * Protected Route: Requires active customer session.
 * Displays saved products, live inventory availability, and options to
 * move items to cart or remove them.
 */

$pageTitle   = 'My Wishlist — EasyShop';
$pageDesc    = 'View and manage your saved products on EasyShop.';
$activePage  = 'wishlist';
$depthPrefix = '../';

require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';

$userId = (int)$_SESSION['user']['user_id'];

// Fetch all wishlist items for this customer
$wishlistItems = [];
if ($conn) {
    $sql = "SELECT w.wishlist_id, w.product_id, w.created_at,
                   p.product_name, p.slug, p.sku, p.price, p.original_price, p.stock, p.image, p.status,
                   c.category_name
            FROM wishlist_items w
            INNER JOIN products p ON w.product_id = p.product_id
            LEFT JOIN categories c ON p.category_id = c.category_id
            WHERE w.user_id = ?
            ORDER BY w.wishlist_id DESC";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $wishlistItems[] = $row;
    }
    mysqli_stmt_close($stmt);
}

$flashSuccess = getFlash('success');
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
          <li class="breadcrumb-item active" aria-current="page">Wishlist</li>
        </ol>
      </nav>
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
          <h1 class="h3 fw-bold text-dark mb-0">My Wishlist</h1>
          <p class="text-muted small mb-0"><?= count($wishlistItems) ?> saved product(s)</p>
        </div>
        <a href="../products.php" class="btn btn-outline-primary btn-sm">
          <i class="bi bi-bag-plus me-1"></i> Continue Shopping
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

      <?php if ($flashError): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4 small" role="alert">
          <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= e($flashError) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <?php if ($flashInfo): ?>
        <div class="alert alert-info alert-dismissible fade show mb-4 small" role="alert">
          <i class="bi bi-info-circle-fill me-1"></i> <?= e($flashInfo) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <?php if (empty($wishlistItems)): ?>
        <!-- Empty State -->
        <div class="card border-0 shadow-sm rounded-4 p-5 text-center my-4">
          <div class="mb-3">
            <i class="bi bi-heart display-3 text-secondary"></i>
          </div>
          <h3 class="fw-bold text-dark mb-2">Your Wishlist is Empty</h3>
          <p class="text-muted small mb-4 mx-auto" style="max-width: 450px;">
            Save items you love by clicking the heart icon on any product card, and easily access them here anytime.
          </p>
          <div>
            <a href="../products.php" class="btn btn-primary-custom px-4 py-2">
              <i class="bi bi-shop me-2"></i>Explore Catalog
            </a>
          </div>
        </div>

      <?php else: ?>
        <!-- Wishlist Items Grid -->
        <div class="row g-4">
          <?php foreach ($wishlistItems as $item): ?>
            <?php
              $isAvailable = ($item['status'] === 'active' && (int)$item['stock'] > 0);
              $discountPercent = 0;
              if (!empty($item['original_price']) && (float)$item['original_price'] > (float)$item['price']) {
                  $discountPercent = round((((float)$item['original_price'] - (float)$item['price']) / (float)$item['original_price']) * 100);
              }
            ?>
            <div class="col-md-6 col-lg-4 col-xl-3">
              <div class="product-card h-100 d-flex flex-column">
                <div class="product-card-img position-relative">
                  <a href="../product-details.php?id=<?= (int)$item['product_id'] ?>" class="d-block w-100 h-100 text-center">
                    <img src="<?= e(get_product_image_url($item['image'], $depthPrefix)) ?>" alt="<?= e($item['product_name']) ?>">
                  </a>

                  <?php if ($discountPercent > 0): ?>
                    <span class="discount-badge">-<?= $discountPercent ?>%</span>
                  <?php endif; ?>

                  <!-- Remove from Wishlist Form -->
                  <form action="../actions/remove-from-wishlist.php" method="POST" class="position-absolute top-0 end-0 m-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="wishlist_id" value="<?= (int)$item['wishlist_id'] ?>">
                    <button type="submit" class="btn btn-sm btn-light rounded-circle shadow-sm p-2 text-danger" title="Remove from wishlist">
                      <i class="bi bi-x-lg"></i>
                    </button>
                  </form>
                </div>

                <div class="product-card-body d-flex flex-column flex-grow-1">
                  <span class="product-category"><?= e($item['category_name'] ?? 'General') ?></span>
                  <h5 class="product-name">
                    <a href="../product-details.php?id=<?= (int)$item['product_id'] ?>" class="text-decoration-none text-dark">
                      <?= e($item['product_name']) ?>
                    </a>
                  </h5>

                  <div class="product-price mt-auto mb-2">
                    <span class="current-price"><?= format_pkr($item['price']) ?></span>
                    <?php if (!empty($item['original_price'])): ?>
                      <span class="original-price"><?= format_pkr($item['original_price']) ?></span>
                    <?php endif; ?>
                  </div>

                  <!-- Availability Indicator -->
                  <div class="mb-3 small">
                    <?php if (!$isAvailable): ?>
                      <span class="text-danger fw-semibold"><i class="bi bi-x-circle me-1"></i>Unavailable</span>
                    <?php else: ?>
                      <span class="text-success fw-semibold"><i class="bi bi-check-circle me-1"></i>In Stock</span>
                    <?php endif; ?>
                  </div>

                  <!-- Move to Cart Form -->
                  <?php if ($isAvailable): ?>
                    <form action="../actions/add-to-cart.php" method="POST" class="mt-auto">
                      <?= csrf_field() ?>
                      <input type="hidden" name="product_id" value="<?= (int)$item['product_id'] ?>">
                      <input type="hidden" name="quantity" value="1">
                      <button type="submit" class="btn btn-primary-custom w-100 btn-sm py-2">
                        <i class="bi bi-cart-plus me-1"></i> Move to Cart
                      </button>
                    </form>
                  <?php else: ?>
                    <button class="btn btn-secondary w-100 btn-sm py-2 mt-auto" disabled>
                      Out of Stock
                    </button>
                  <?php endif; ?>

                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </div>
  </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
