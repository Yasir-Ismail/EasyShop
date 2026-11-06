<?php
/**
 * EasyShop — Administrative Analytics Dashboard
 * 
 * Protected Route: Requires administrator privileges.
 * Displays live store metrics: product & category totals, active counts,
 * customer registrations, order breakdown, and realized COD sales revenue.
 * Also provides recent order activity and newly registered customers.
 */

$pageTitle   = 'Dashboard — EasyShop Admin';
$activeNav   = 'dashboard';
$depthPrefix = '../';

require_once __DIR__ . '/../includes/admin-header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';

// 1. Initialize Default Aggregates
$prodStats = [
    'total_products'     => 0,
    'active_products'    => 0,
    'low_stock_products' => 0
];
$totalCategories = 0;
$totalCustomers  = 0;

$orderStats = [
    'total_orders'          => 0,
    'pending_orders'        => 0,
    'processing_orders'     => 0,
    'delivered_orders'      => 0,
    'cancelled_orders'      => 0,
    'collected_sales'       => 0.0,
    'delivered_sales_value' => 0.0
];

$recentOrders    = [];
$recentCustomers = [];

if ($conn) {
    // A. Product Catalog Metrics (Single Query)
    $prodQuery = "SELECT 
                    COUNT(*) AS total_products,
                    COALESCE(SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END), 0) AS active_products,
                    COALESCE(SUM(CASE WHEN stock <= 5 THEN 1 ELSE 0 END), 0) AS low_stock_products
                  FROM products";
    if ($res = mysqli_query($conn, $prodQuery)) {
        $prodStats = mysqli_fetch_assoc($res) ?: $prodStats;
        mysqli_free_result($res);
    }

    // B. Category Metric
    if ($res = mysqli_query($conn, "SELECT COUNT(*) FROM categories")) {
        $totalCategories = (int)mysqli_fetch_row($res)[0];
        mysqli_free_result($res);
    }

    // C. Customer Metric
    if ($res = mysqli_query($conn, "SELECT COUNT(*) FROM users WHERE role = 'customer'")) {
        $totalCustomers = (int)mysqli_fetch_row($res)[0];
        mysqli_free_result($res);
    }

    // D. Order & Sales Metrics (Single Query)
    // Sales Rule: Cancelled orders are strictly excluded.
    // Delivered sales reflect completed deliveries; collected sales reflect delivered & paid.
    $orderQuery = "SELECT 
                     COUNT(*) AS total_orders,
                     COALESCE(SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END), 0) AS pending_orders,
                     COALESCE(SUM(CASE WHEN order_status IN ('confirmed', 'processing') THEN 1 ELSE 0 END), 0) AS processing_orders,
                     COALESCE(SUM(CASE WHEN order_status = 'delivered' THEN 1 ELSE 0 END), 0) AS delivered_orders,
                     COALESCE(SUM(CASE WHEN order_status = 'cancelled' THEN 1 ELSE 0 END), 0) AS cancelled_orders,
                     COALESCE(SUM(CASE WHEN order_status = 'delivered' AND payment_status = 'paid' THEN total_amount ELSE 0 END), 0) AS collected_sales,
                     COALESCE(SUM(CASE WHEN order_status = 'delivered' THEN total_amount ELSE 0 END), 0) AS delivered_sales_value
                   FROM orders";
    if ($res = mysqli_query($conn, $orderQuery)) {
        $orderStats = mysqli_fetch_assoc($res) ?: $orderStats;
        mysqli_free_result($res);
    }

    // E. Recent 5 Orders
    $recentOrderQuery = "SELECT o.order_id, o.order_number, o.shipping_name, o.total_amount, 
                                o.order_status, o.payment_status, o.created_at,
                                u.name AS user_name
                         FROM orders o
                         LEFT JOIN users u ON o.user_id = u.user_id
                         ORDER BY o.order_id DESC
                         LIMIT 5";
    if ($res = mysqli_query($conn, $recentOrderQuery)) {
        while ($row = mysqli_fetch_assoc($res)) {
            $recentOrders[] = $row;
        }
        mysqli_free_result($res);
    }

    // F. Recent 5 Registered Customers
    $recentCustQuery = "SELECT u.user_id, u.name, u.email, u.phone, u.created_at,
                               (SELECT COUNT(*) FROM orders WHERE user_id = u.user_id) AS order_count
                        FROM users u
                        WHERE u.role = 'customer'
                        ORDER BY u.user_id DESC
                        LIMIT 5";
    if ($res = mysqli_query($conn, $recentCustQuery)) {
        while ($row = mysqli_fetch_assoc($res)) {
            $recentCustomers[] = $row;
        }
        mysqli_free_result($res);
    }
}

$flashSuccess = getFlash('success');
$flashWarning = getFlash('warning');
$flashError   = getFlash('error');
?>

<main class="admin-main">
  <!-- Flash Messages -->
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

  <!-- Title & Quick Actions -->
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <h1 class="h3 fw-bold text-dark mb-1">Administrative Overview</h1>
      <p class="text-muted small mb-0">Real-time store metrics, inventory tracking, and sales analytics</p>
    </div>
    <div class="d-flex gap-2">
      <a href="products/add.php" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i> Add Product
      </a>
      <a href="orders/index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-receipt me-1"></i> View Orders
      </a>
    </div>
  </div>

  <!-- Primary Financial & Order KPI Row -->
  <div class="row g-3 mb-4">
    <!-- Realized Delivered Sales -->
    <div class="col-sm-6 col-xl-3">
      <div class="admin-card p-3 h-100">
        <div class="d-flex align-items-center gap-3">
          <div class="stat-icon bg-success-subtle text-success p-3 rounded-3 fs-3">
            <i class="bi bi-cash-coin"></i>
          </div>
          <div>
            <div class="text-muted small text-uppercase fw-semibold" style="font-size: 0.72rem;">Delivered Sales</div>
            <div class="h4 fw-bold mb-0 text-success"><?= format_pkr($orderStats['delivered_sales_value']) ?></div>
            <div class="small text-muted" style="font-size: 0.7rem;">Excludes cancelled orders</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Total Orders Placed -->
    <div class="col-sm-6 col-xl-3">
      <div class="admin-card p-3 h-100">
        <div class="d-flex align-items-center gap-3">
          <div class="stat-icon bg-primary-subtle text-primary p-3 rounded-3 fs-3">
            <i class="bi bi-receipt"></i>
          </div>
          <div>
            <div class="text-muted small text-uppercase fw-semibold" style="font-size: 0.72rem;">Total Orders</div>
            <div class="h3 fw-bold mb-0 text-dark"><?= (int)$orderStats['total_orders'] ?></div>
          </div>
        </div>
        <div class="mt-2 pt-2 border-top">
          <a href="orders/index.php" class="small text-decoration-none text-primary fw-medium">
            Manage Orders <i class="bi bi-arrow-right"></i>
          </a>
        </div>
      </div>
    </div>

    <!-- Pending / Processing Orders -->
    <div class="col-sm-6 col-xl-3">
      <div class="admin-card p-3 h-100">
        <div class="d-flex align-items-center gap-3">
          <div class="stat-icon bg-warning-subtle text-warning p-3 rounded-3 fs-3">
            <i class="bi bi-hourglass-split"></i>
          </div>
          <div>
            <div class="text-muted small text-uppercase fw-semibold" style="font-size: 0.72rem;">Pending Fulfillment</div>
            <div class="h3 fw-bold mb-0 text-dark"><?= (int)$orderStats['pending_orders'] + (int)$orderStats['processing_orders'] ?></div>
          </div>
        </div>
        <div class="mt-2 pt-2 border-top">
          <a href="orders/index.php?status=pending" class="small text-decoration-none text-warning fw-medium">
            <?= (int)$orderStats['pending_orders'] ?> Pending Approval
          </a>
        </div>
      </div>
    </div>

    <!-- Registered Customers -->
    <div class="col-sm-6 col-xl-3">
      <div class="admin-card p-3 h-100">
        <div class="d-flex align-items-center gap-3">
          <div class="stat-icon bg-info-subtle text-info p-3 rounded-3 fs-3">
            <i class="bi bi-people"></i>
          </div>
          <div>
            <div class="text-muted small text-uppercase fw-semibold" style="font-size: 0.72rem;">Customers</div>
            <div class="h3 fw-bold mb-0 text-dark"><?= (int)$totalCustomers ?></div>
          </div>
        </div>
        <div class="mt-2 pt-2 border-top">
          <a href="customers/index.php" class="small text-decoration-none text-info fw-medium">
            Manage Customers <i class="bi bi-arrow-right"></i>
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Catalog & Inventory KPI Row -->
  <div class="row g-3 mb-4">
    <!-- Total Products -->
    <div class="col-sm-6 col-xl-3">
      <div class="admin-card p-3 h-100">
        <div class="d-flex align-items-center gap-3">
          <div class="stat-icon bg-secondary-subtle text-secondary p-3 rounded-3 fs-3">
            <i class="bi bi-box-seam"></i>
          </div>
          <div>
            <div class="text-muted small text-uppercase fw-semibold" style="font-size: 0.72rem;">Total Catalog</div>
            <div class="h4 fw-bold mb-0 text-dark"><?= (int)$prodStats['total_products'] ?> Products</div>
            <div class="small text-muted" style="font-size: 0.7rem;"><?= (int)$prodStats['active_products'] ?> Active Live</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Categories -->
    <div class="col-sm-6 col-xl-3">
      <div class="admin-card p-3 h-100">
        <div class="d-flex align-items-center gap-3">
          <div class="stat-icon bg-secondary-subtle text-secondary p-3 rounded-3 fs-3">
            <i class="bi bi-tags"></i>
          </div>
          <div>
            <div class="text-muted small text-uppercase fw-semibold" style="font-size: 0.72rem;">Categories</div>
            <div class="h4 fw-bold mb-0 text-dark"><?= (int)$totalCategories ?> Departments</div>
          </div>
        </div>
        <div class="mt-2 pt-2 border-top">
          <a href="categories/index.php" class="small text-decoration-none text-secondary fw-medium">
            Manage Categories <i class="bi bi-arrow-right"></i>
          </a>
        </div>
      </div>
    </div>

    <!-- Low Stock Alert -->
    <div class="col-sm-6 col-xl-3">
      <div class="admin-card p-3 h-100">
        <div class="d-flex align-items-center gap-3">
          <div class="stat-icon bg-danger-subtle text-danger p-3 rounded-3 fs-3">
            <i class="bi bi-exclamation-triangle"></i>
          </div>
          <div>
            <div class="text-muted small text-uppercase fw-semibold" style="font-size: 0.72rem;">Low Stock (&le; 5)</div>
            <div class="h4 fw-bold mb-0 text-danger"><?= (int)$prodStats['low_stock_products'] ?> Items</div>
          </div>
        </div>
        <div class="mt-2 pt-2 border-top">
          <a href="products/index.php?filter=low_stock" class="small text-decoration-none text-danger fw-medium">
            Inspect Low Inventory <i class="bi bi-arrow-right"></i>
          </a>
        </div>
      </div>
    </div>

    <!-- Delivered Orders Count -->
    <div class="col-sm-6 col-xl-3">
      <div class="admin-card p-3 h-100">
        <div class="d-flex align-items-center gap-3">
          <div class="stat-icon bg-success-subtle text-success p-3 rounded-3 fs-3">
            <i class="bi bi-check2-all"></i>
          </div>
          <div>
            <div class="text-muted small text-uppercase fw-semibold" style="font-size: 0.72rem;">Completed Orders</div>
            <div class="h4 fw-bold mb-0 text-dark"><?= (int)$orderStats['delivered_orders'] ?> Delivered</div>
          </div>
        </div>
        <div class="mt-2 pt-2 border-top">
          <span class="small text-muted"><?= (int)$orderStats['cancelled_orders'] ?> Cancelled</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Recent Orders & Recent Customers Grid -->
  <div class="row g-4 mb-4">
    <!-- Recent Orders Table -->
    <div class="col-lg-7">
      <div class="admin-card overflow-hidden h-100">
        <div class="p-3 bg-white border-bottom d-flex justify-content-between align-items-center">
          <h5 class="fw-bold mb-0 fs-6 text-dark">Recent Orders</h5>
          <a href="orders/index.php" class="btn btn-outline-secondary btn-sm">View All Orders</a>
        </div>

        <?php if (empty($recentOrders)): ?>
          <div class="p-4 text-center text-muted small">No orders recorded in database yet.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle mb-0 table-hover">
              <thead class="table-light small">
                <tr>
                  <th scope="col" class="ps-3">Order #</th>
                  <th scope="col">Customer</th>
                  <th scope="col">Total</th>
                  <th scope="col">Status</th>
                  <th scope="col" class="text-end pe-3">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentOrders as $ro): ?>
                  <?php
                    $statusBadge = 'bg-secondary';
                    switch ($ro['order_status']) {
                        case 'pending':    $statusBadge = 'bg-warning text-dark'; break;
                        case 'confirmed':  $statusBadge = 'bg-info text-dark'; break;
                        case 'processing': $statusBadge = 'bg-primary text-white'; break;
                        case 'shipped':    $statusBadge = 'bg-info text-white'; break;
                        case 'delivered':  $statusBadge = 'bg-success text-white'; break;
                        case 'cancelled':  $statusBadge = 'bg-danger text-white'; break;
                    }
                  ?>
                  <tr>
                    <td class="ps-3">
                      <a href="orders/details.php?id=<?= (int)$ro['order_id'] ?>" class="fw-bold font-monospace text-primary text-decoration-none small">
                        <?= e($ro['order_number']) ?>
                      </a>
                    </td>
                    <td class="small">
                      <div class="fw-semibold text-dark text-truncate" style="max-width: 140px;"><?= e($ro['shipping_name']) ?></div>
                    </td>
                    <td class="small fw-bold text-dark">
                      <?= format_pkr($ro['total_amount']) ?>
                    </td>
                    <td>
                      <span class="badge <?= $statusBadge ?> text-uppercase" style="font-size: 0.65rem;">
                        <?= e($ro['order_status']) ?>
                      </span>
                    </td>
                    <td class="text-end pe-3">
                      <a href="orders/details.php?id=<?= (int)$ro['order_id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.75rem;">
                        View
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Recent Customers Table -->
    <div class="col-lg-5">
      <div class="admin-card overflow-hidden h-100">
        <div class="p-3 bg-white border-bottom d-flex justify-content-between align-items-center">
          <h5 class="fw-bold mb-0 fs-6 text-dark">Recent Customers</h5>
          <a href="customers/index.php" class="btn btn-outline-secondary btn-sm">View All</a>
        </div>

        <?php if (empty($recentCustomers)): ?>
          <div class="p-4 text-center text-muted small">No customers registered yet.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle mb-0 table-hover">
              <thead class="table-light small">
                <tr>
                  <th scope="col" class="ps-3">Customer</th>
                  <th scope="col" class="text-center">Orders</th>
                  <th scope="col" class="text-end pe-3">Joined</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentCustomers as $rc): ?>
                  <tr>
                    <td class="ps-3">
                      <a href="customers/details.php?id=<?= (int)$rc['user_id'] ?>" class="fw-semibold text-dark text-decoration-none small d-block">
                        <?= e($rc['name']) ?>
                      </a>
                      <span class="text-muted" style="font-size: 0.72rem;"><?= e($rc['email']) ?></span>
                    </td>
                    <td class="text-center small">
                      <span class="badge bg-light text-dark border">
                        <?= (int)$rc['order_count'] ?>
                      </span>
                    </td>
                    <td class="text-end pe-3 text-muted" style="font-size: 0.75rem;">
                      <?= date('M j, Y', strtotime($rc['created_at'])) ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
