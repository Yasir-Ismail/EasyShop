<?php
/**
 * EasyShop — Customer Portal & Account Dashboard
 * 
 * Protected Route: Requires active customer session.
 * Displays real-time database metrics: total orders, pending orders, completed orders,
 * recent order activity, customer profile card, and quick action shortcuts.
 */

$pageTitle   = 'Customer Dashboard — EasyShop';
$pageDesc    = 'Manage your EasyShop account, view recent order activity, and update profile settings.';
$activePage  = 'account';
$depthPrefix = '../';

require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';

$user = currentUser();
$userId = (int)$user['user_id'];

// 1. Fetch live customer metrics from database
$totalOrders     = 0;
$pendingOrders   = 0;
$completedOrders = 0;
$wishlistCount   = 0;
$recentOrders    = [];
$customerProfile = $user;

if ($conn) {
    // Refresh user record from DB to ensure latest info and registration date
    $uStmt = mysqli_prepare($conn, "SELECT user_id, name, email, phone, role, created_at FROM users WHERE user_id = ? LIMIT 1");
    if ($uStmt) {
        mysqli_stmt_bind_param($uStmt, 'i', $userId);
        mysqli_stmt_execute($uStmt);
        $uRes = mysqli_stmt_get_result($uStmt);
        if ($freshUser = mysqli_fetch_assoc($uRes)) {
            $customerProfile = $freshUser;
            // Update session cache if profile was updated
            $_SESSION['user']['name']  = $freshUser['name'];
            $_SESSION['user']['email'] = $freshUser['email'];
            $_SESSION['user']['phone'] = $freshUser['phone'];
        }
        mysqli_stmt_close($uStmt);
    }

    // Total orders count
    $oStmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM orders WHERE user_id = ?");
    if ($oStmt) {
        mysqli_stmt_bind_param($oStmt, 'i', $userId);
        mysqli_stmt_execute($oStmt);
        mysqli_stmt_bind_result($oStmt, $totalOrders);
        mysqli_stmt_fetch($oStmt);
        mysqli_stmt_close($oStmt);
    }

    // Pending orders count
    $pStmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM orders WHERE user_id = ? AND order_status IN ('pending', 'confirmed', 'processing')");
    if ($pStmt) {
        mysqli_stmt_bind_param($pStmt, 'i', $userId);
        mysqli_stmt_execute($pStmt);
        mysqli_stmt_bind_result($pStmt, $pendingOrders);
        mysqli_stmt_fetch($pStmt);
        mysqli_stmt_close($pStmt);
    }

    // Completed / Delivered orders count
    $cStmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM orders WHERE user_id = ? AND order_status = 'delivered'");
    if ($cStmt) {
        mysqli_stmt_bind_param($cStmt, 'i', $userId);
        mysqli_stmt_execute($cStmt);
        mysqli_stmt_bind_result($cStmt, $completedOrders);
        mysqli_stmt_fetch($cStmt);
        mysqli_stmt_close($cStmt);
    }

    // Wishlist count
    $wishlistCount = get_wishlist_count($userId);

    // Recent 5 orders
    $recentSql = "SELECT o.order_id, o.order_number, o.subtotal, o.shipping_fee, o.total_amount,
                         o.payment_method, o.payment_status, o.order_status, o.created_at,
                         COUNT(oi.order_item_id) AS item_count
                  FROM orders o
                  LEFT JOIN order_items oi ON o.order_id = oi.order_id
                  WHERE o.user_id = ?
                  GROUP BY o.order_id
                  ORDER BY o.order_id DESC
                  LIMIT 5";
    $rStmt = mysqli_prepare($conn, $recentSql);
    if ($rStmt) {
        mysqli_stmt_bind_param($rStmt, 'i', $userId);
        mysqli_stmt_execute($rStmt);
        $rRes = mysqli_stmt_get_result($rStmt);
        while ($row = mysqli_fetch_assoc($rRes)) {
            $recentOrders[] = $row;
        }
        mysqli_stmt_close($rStmt);
    }
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
          <li class="breadcrumb-item active" aria-current="page">My Account</li>
        </ol>
      </nav>
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
          <h1 class="h3 fw-bold text-dark mb-0">Customer Dashboard</h1>
          <p class="text-muted small mb-0">Welcome back, <?= e($customerProfile['name']) ?>!</p>
        </div>
        <div class="d-flex gap-2">
          <a href="profile.php" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-person-gear me-1"></i> Edit Profile
          </a>
          <a href="../auth/logout.php" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-box-arrow-right me-1"></i> Log Out
          </a>
        </div>
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

      <!-- Profile Header Summary Card -->
      <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="bg-primary text-white p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
          <div class="d-flex align-items-center gap-3">
            <div class="user-avatar bg-white text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold fs-3" style="width: 64px; height: 64px;">
              <?= strtoupper(substr($customerProfile['name'], 0, 1)) ?>
            </div>
            <div>
              <h4 class="fw-bold mb-1 text-white"><?= e($customerProfile['name']) ?></h4>
              <div class="small text-white-50">
                <i class="bi bi-envelope me-1"></i> <?= e($customerProfile['email']) ?>
                <?php if (!empty($customerProfile['phone'])): ?>
                  <span class="mx-2">•</span>
                  <i class="bi bi-telephone me-1"></i> <?= e($customerProfile['phone']) ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <div class="text-end">
            <span class="badge bg-light text-dark px-3 py-2 small">
              <i class="bi bi-calendar-check me-1"></i> Member Since: <?= date('M Y', strtotime($customerProfile['created_at'] ?? 'now')) ?>
            </span>
          </div>
        </div>
      </div>

      <!-- Live Order Metric Cards -->
      <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
          <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
            <div class="d-flex align-items-center gap-3">
              <div class="bg-primary-subtle text-primary p-3 rounded-3 fs-3">
                <i class="bi bi-receipt"></i>
              </div>
              <div>
                <div class="text-muted small text-uppercase fw-semibold">Total Orders</div>
                <div class="h3 fw-bold mb-0 text-dark"><?= (int)$totalOrders ?></div>
              </div>
            </div>
            <div class="mt-3 pt-2 border-top">
              <a href="orders.php" class="small text-decoration-none text-primary fw-medium">
                View All Orders <i class="bi bi-arrow-right"></i>
              </a>
            </div>
          </div>
        </div>

        <div class="col-sm-6 col-lg-3">
          <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
            <div class="d-flex align-items-center gap-3">
              <div class="bg-warning-subtle text-warning p-3 rounded-3 fs-3">
                <i class="bi bi-clock-history"></i>
              </div>
              <div>
                <div class="text-muted small text-uppercase fw-semibold">Pending / In-Transit</div>
                <div class="h3 fw-bold mb-0 text-dark"><?= (int)$pendingOrders ?></div>
              </div>
            </div>
            <div class="mt-3 pt-2 border-top">
              <span class="small text-muted">Awaiting delivery</span>
            </div>
          </div>
        </div>

        <div class="col-sm-6 col-lg-3">
          <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
            <div class="d-flex align-items-center gap-3">
              <div class="bg-success-subtle text-success p-3 rounded-3 fs-3">
                <i class="bi bi-check-circle"></i>
              </div>
              <div>
                <div class="text-muted small text-uppercase fw-semibold">Delivered Orders</div>
                <div class="h3 fw-bold mb-0 text-dark"><?= (int)$completedOrders ?></div>
              </div>
            </div>
            <div class="mt-3 pt-2 border-top">
              <span class="small text-muted">Successfully received</span>
            </div>
          </div>
        </div>

        <div class="col-sm-6 col-lg-3">
          <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
            <div class="d-flex align-items-center gap-3">
              <div class="bg-danger-subtle text-danger p-3 rounded-3 fs-3">
                <i class="bi bi-heart"></i>
              </div>
              <div>
                <div class="text-muted small text-uppercase fw-semibold">Saved Items</div>
                <div class="h3 fw-bold mb-0 text-dark"><?= (int)$wishlistCount ?></div>
              </div>
            </div>
            <div class="mt-3 pt-2 border-top">
              <a href="wishlist.php" class="small text-decoration-none text-danger fw-medium">
                View Wishlist <i class="bi bi-arrow-right"></i>
              </a>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Action Modules -->
      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <div class="card border-0 shadow-sm rounded-4 p-4 text-center h-100">
            <i class="bi bi-person-gear display-5 text-primary mb-2"></i>
            <h5 class="fw-bold mb-1 text-dark">Profile Settings</h5>
            <p class="text-muted small mb-3">Update your name, contact phone number, email or password.</p>
            <a href="profile.php" class="btn btn-outline-primary btn-sm mt-auto">Manage Profile</a>
          </div>
        </div>

        <div class="col-md-4">
          <div class="card border-0 shadow-sm rounded-4 p-4 text-center h-100">
            <i class="bi bi-bag-check display-5 text-primary mb-2"></i>
            <h5 class="fw-bold mb-1 text-dark">Order History</h5>
            <p class="text-muted small mb-3">Track packages, view receipts, and review line-item snapshots.</p>
            <a href="orders.php" class="btn btn-outline-primary btn-sm mt-auto">View My Orders</a>
          </div>
        </div>

        <div class="col-md-4">
          <div class="card border-0 shadow-sm rounded-4 p-4 text-center h-100">
            <i class="bi bi-shop display-5 text-primary mb-2"></i>
            <h5 class="fw-bold mb-1 text-dark">Browse Catalog</h5>
            <p class="text-muted small mb-3">Explore new arrivals, featured deals, and electronics.</p>
            <a href="../products.php" class="btn btn-outline-primary btn-sm mt-auto">Go Shopping</a>
          </div>
        </div>
      </div>

      <!-- Recent Orders Section -->
      <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="p-3 bg-white border-bottom d-flex justify-content-between align-items-center">
          <h5 class="fw-bold text-dark mb-0 fs-6">Recent Orders Activity</h5>
          <a href="orders.php" class="btn btn-outline-secondary btn-sm">View All Orders</a>
        </div>

        <?php if (empty($recentOrders)): ?>
          <div class="p-5 text-center text-muted">
            <i class="bi bi-bag-x display-6 d-block mb-2 text-muted"></i>
            <p class="mb-3 small">You haven't placed any orders yet.</p>
            <a href="../products.php" class="btn btn-primary-custom btn-sm px-4">Start Shopping</a>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle mb-0 table-hover">
              <thead class="table-light small">
                <tr>
                  <th scope="col" class="ps-4">Order #</th>
                  <th scope="col">Date</th>
                  <th scope="col" class="text-center">Items</th>
                  <th scope="col">Total</th>
                  <th scope="col">Status</th>
                  <th scope="col" class="text-end pe-4">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentOrders as $ord): ?>
                  <?php
                    $statusClass = 'bg-secondary';
                    switch ($ord['order_status']) {
                        case 'pending':    $statusClass = 'bg-warning text-dark'; break;
                        case 'confirmed':  $statusClass = 'bg-info text-dark'; break;
                        case 'processing': $statusClass = 'bg-primary text-white'; break;
                        case 'shipped':    $statusClass = 'bg-info text-white'; break;
                        case 'delivered':  $statusClass = 'bg-success text-white'; break;
                        case 'cancelled':  $statusClass = 'bg-danger text-white'; break;
                    }
                  ?>
                  <tr>
                    <td class="ps-4">
                      <a href="order-details.php?order=<?= urlencode($ord['order_number']) ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                        <?= e($ord['order_number']) ?>
                      </a>
                    </td>
                    <td class="small text-muted">
                      <?= date('M j, Y', strtotime($ord['created_at'])) ?>
                    </td>
                    <td class="text-center small">
                      <span class="badge bg-light text-dark border">
                        <?= (int)$ord['item_count'] ?> item<?= (int)$ord['item_count'] === 1 ? '' : 's' ?>
                      </span>
                    </td>
                    <td class="fw-bold text-dark small">
                      <?= format_pkr($ord['total_amount']) ?>
                    </td>
                    <td>
                      <span class="badge <?= $statusClass ?> small text-uppercase">
                        <?= e($ord['order_status']) ?>
                      </span>
                    </td>
                    <td class="text-end pe-4">
                      <a href="order-details.php?order=<?= urlencode($ord['order_number']) ?>" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-eye me-1"></i> Details
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
  </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
