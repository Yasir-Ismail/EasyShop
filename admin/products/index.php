<?php
/**
 * EasyShop — Admin Product Catalog Management
 * 
 * Features:
 * - Product list with image thumbnails
 * - Pagination (10 per page)
 * - Filtering by category, low-stock, and keyword search
 * - Direct stock indicators and status badges
 */

$pageTitle   = 'Products — EasyShop Admin';
$activeNav   = 'products';
$depthPrefix = '../../';

require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';

// Pagination setup
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

// Filtering parameters
$filterCategory = (int)($_GET['category_id'] ?? 0);
$filterStock    = trim($_GET['filter'] ?? '');
$searchKeyword  = trim($_GET['search'] ?? '');

// Build dynamic WHERE clause
$whereConditions = ["1=1"];
$params = [];
$types  = "";

if ($filterCategory > 0) {
    $whereConditions[] = "p.category_id = ?";
    $params[] = $filterCategory;
    $types   .= "i";
}

if ($filterStock === 'low_stock') {
    $whereConditions[] = "p.stock <= 5";
} elseif ($filterStock === 'out_of_stock') {
    $whereConditions[] = "p.stock = 0";
}

if (!empty($searchKeyword)) {
    $whereConditions[] = "(p.product_name LIKE ? OR p.sku LIKE ?)";
    $likeParam = '%' . $searchKeyword . '%';
    $params[] = $likeParam;
    $params[] = $likeParam;
    $types   .= "ss";
}

$whereSql = implode(" AND ", $whereConditions);

// Total matching count
$countSql = "SELECT COUNT(*) FROM products p WHERE $whereSql";
$countStmt = mysqli_prepare($conn, $countSql);
if (!empty($types)) {
    mysqli_stmt_bind_param($countStmt, $types, ...$params);
}
mysqli_stmt_execute($countStmt);
mysqli_stmt_bind_result($countStmt, $totalProducts);
mysqli_stmt_fetch($countStmt);
mysqli_stmt_close($countStmt);

$totalPages = max(1, ceil($totalProducts / $perPage));

// Fetch paginated products with category names
$querySql = "SELECT p.*, c.category_name 
             FROM products p 
             LEFT JOIN categories c ON p.category_id = c.category_id 
             WHERE $whereSql 
             ORDER BY p.product_id DESC 
             LIMIT ? OFFSET ?";

$paramsWithLimit = $params;
$paramsWithLimit[] = $perPage;
$paramsWithLimit[] = $offset;
$typesWithLimit = $types . "ii";

$stmt = mysqli_prepare($conn, $querySql);
mysqli_stmt_bind_param($stmt, $typesWithLimit, ...$paramsWithLimit);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$products = [];
while ($row = mysqli_fetch_assoc($res)) {
    $products[] = $row;
}
mysqli_stmt_close($stmt);

// Fetch categories for the filter dropdown
$allCategories = [];
if ($catRes = mysqli_query($conn, "SELECT category_id, category_name FROM categories ORDER BY category_name ASC")) {
    while ($c = mysqli_fetch_assoc($catRes)) {
        $allCategories[] = $c;
    }
    mysqli_free_result($catRes);
}

$flashSuccess = getFlash('success');
$flashError   = getFlash('error');
?>

<main class="admin-main">
  <!-- Flash Alerts -->
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

  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <h1 class="h3 fw-bold text-dark mb-1">Product Catalog</h1>
      <p class="text-muted small mb-0">Total of <?= $totalProducts ?> product(s) registered in catalog</p>
    </div>
    <a href="add.php" class="btn btn-primary btn-sm">
      <i class="bi bi-plus-lg me-1"></i> Add New Product
    </a>
  </div>

  <!-- Search & Filter Filter-Bar -->
  <div class="admin-card p-3 mb-4">
    <form method="GET" action="index.php" class="row g-2 align-items-center">
      <!-- Search Input -->
      <div class="col-md-4">
        <div class="input-group input-group-sm">
          <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
          <input 
            type="text" 
            name="search" 
            class="form-control" 
            placeholder="Search by product name or SKU..." 
            value="<?= e($searchKeyword) ?>"
          >
        </div>
      </div>

      <!-- Category Filter -->
      <div class="col-md-3">
        <select name="category_id" class="form-select form-select-sm">
          <option value="0">All Categories</option>
          <?php foreach ($allCategories as $catOption): ?>
            <option value="<?= (int)$catOption['category_id'] ?>" <?= $filterCategory === (int)$catOption['category_id'] ? 'selected' : '' ?>>
              <?= e($catOption['category_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Stock Status Filter -->
      <div class="col-md-3">
        <select name="filter" class="form-select form-select-sm">
          <option value="">All Stock Levels</option>
          <option value="low_stock" <?= $filterStock === 'low_stock' ? 'selected' : '' ?>>Low Stock (&le; 5 units)</option>
          <option value="out_of_stock" <?= $filterStock === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock (0 units)</option>
        </select>
      </div>

      <!-- Submit & Reset Buttons -->
      <div class="col-md-2 d-flex gap-1">
        <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
        <?php if (!empty($searchKeyword) || $filterCategory > 0 || !empty($filterStock)): ?>
          <a href="index.php" class="btn btn-outline-secondary btn-sm" title="Clear Filters">
            <i class="bi bi-x-lg"></i>
          </a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Products Data Table -->
  <div class="admin-card overflow-hidden mb-4">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width: 70px;">Image</th>
            <th>Product Name &amp; SKU</th>
            <th>Category</th>
            <th>Price (PKR)</th>
            <th class="text-center" style="width: 100px;">Stock</th>
            <th style="width: 110px;">Status</th>
            <th class="text-end" style="width: 150px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($products)): ?>
            <tr>
              <td colspan="7" class="text-center py-5 text-muted">
                <i class="bi bi-box-seam fs-2 d-block mb-2 text-secondary"></i>
                No matching products found. Try adjusting your search filters.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($products as $prod): ?>
              <tr>
                <td>
                  <img 
                    src="<?= e(get_product_image_url($prod['image'], $depthPrefix)) ?>" 
                    alt="<?= e($prod['product_name']) ?>" 
                    class="product-thumb-sm"
                  >
                </td>
                <td>
                  <div class="fw-semibold text-dark"><?= e($prod['product_name']) ?></div>
                  <div class="text-muted small font-monospace">SKU: <?= e($prod['sku']) ?></div>
                </td>
                <td>
                  <span class="badge bg-light text-dark border">
                    <?= e($prod['category_name'] ?? 'Uncategorized') ?>
                  </span>
                </td>
                <td>
                  <span class="fw-bold text-dark"><?= format_pkr($prod['price']) ?></span>
                  <?php if (!empty($prod['original_price'])): ?>
                    <div class="text-muted small text-decoration-line-through">
                      <?= format_pkr($prod['original_price']) ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td class="text-center">
                  <?php if ((int)$prod['stock'] === 0): ?>
                    <span class="badge bg-danger">Out of stock</span>
                  <?php elseif ((int)$prod['stock'] <= 5): ?>
                    <span class="badge bg-warning text-dark"><?= (int)$prod['stock'] ?> left</span>
                  <?php else: ?>
                    <span class="badge bg-success-subtle text-success"><?= (int)$prod['stock'] ?> in stock</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($prod['status'] === 'active'): ?>
                    <span class="badge-status-active"><i class="bi bi-check2 me-1"></i>Active</span>
                  <?php else: ?>
                    <span class="badge-status-inactive"><i class="bi bi-x me-1"></i>Inactive</span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <div class="btn-group btn-group-sm">
                    <a href="view.php?id=<?= (int)$prod['product_id'] ?>" class="btn btn-outline-info" title="View Details">
                      <i class="bi bi-eye"></i>
                    </a>
                    <a href="edit.php?id=<?= (int)$prod['product_id'] ?>" class="btn btn-outline-secondary" title="Edit Product">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <button type="button" class="btn btn-outline-danger" title="Delete Product" data-bs-toggle="modal" data-bs-target="#delModal<?= (int)$prod['product_id'] ?>">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>

                  <!-- Delete Modal -->
                  <div class="modal fade" id="delModal<?= (int)$prod['product_id'] ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                      <div class="modal-content text-start">
                        <div class="modal-header">
                          <h5 class="modal-title fw-bold">Delete Product</h5>
                          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                          <p class="mb-2">Are you sure you want to delete product <strong>"<?= e($prod['product_name']) ?>"</strong> (SKU: <?= e($prod['sku']) ?>)?</p>
                          <p class="text-muted small mb-0">This operation will permanently remove the product from the catalog.</p>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                          <form action="../../actions/product-delete-action.php" method="POST" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="product_id" value="<?= (int)$prod['product_id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">
                              <i class="bi bi-trash me-1"></i> Delete Product
                            </button>
                          </form>
                        </div>
                      </div>
                    </div>
                  </div>

                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Pagination Controls -->
  <?php if ($totalPages > 1): ?>
    <nav aria-label="Product pagination" class="d-flex justify-content-center">
      <ul class="pagination pagination-sm">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <?php
            $queryArr = $_GET;
            $queryArr['page'] = $p;
            $pageUrl = 'index.php?' . http_build_query($queryArr);
          ?>
          <li class="page-item <?= $p === $page ? 'active' : '' ?>">
            <a class="page-link" href="<?= e($pageUrl) ?>"><?= $p ?></a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>
  <?php endif; ?>

</main>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
