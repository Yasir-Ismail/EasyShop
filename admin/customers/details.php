<?php
/**
 * EasyShop — Admin Customer Profile & Order History Details
 * 
 * Protected Route: Requires administrator privileges.
 * Displays customer account details, aggregated metrics, full order history,
 * and account status management (Active / Inactive toggle).
 */

$pageTitle   = 'Customer Details — EasyShop Admin';
$activeNav   = 'customers';
$depthPrefix = '../../';

require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
require_once __DIR__ . '/../../includes/csrf.php';

$customerId = (int)($_GET['id'] ?? 0);

if ($customerId <= 0) {
    setFlash('error', 'Invalid customer ID specified.');
    header('Location: index.php');
    exit;
}

$customer = null;
$customerOrders = [];
$stats = [
    'total_orders'     => 0,
    'delivered_orders' => 0,
    'pending_orders'   => 0,
    'lifetime_spend'   => 0.0
];

if ($conn) {
    // 1. Fetch Customer Record
    $stmt = mysqli_prepare($conn, "SELECT user_id, name, email, phone, role, status, created_at FROM users WHERE user_id = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $customerId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $customer = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
    }

    if ($customer) {
        // 2. Fetch Aggregated Metrics for this customer
        $statStmt = mysqli_prepare($conn, "SELECT 
                                             COUNT(*) AS total_orders,
                                             COALESCE(SUM(CASE WHEN order_status = 'delivered' THEN 1 ELSE 0 END), 0) AS delivered_orders,
                                             COALESCE(SUM(CASE WHEN order_status IN ('pending', 'confirmed', 'processing') THEN 1 ELSE 0 END), 0) AS pending_orders,
                                             COALESCE(SUM(CASE WHEN order_status = 'delivered' THEN total_amount ELSE 0 END), 0) AS lifetime_spend
                                           FROM orders 
                                           WHERE user_id = ?");
        if ($statStmt) {
            mysqli_stmt_bind_param($statStmt, 'i', $customerId);
            mysqli_stmt_execute($statStmt);
            $statRes = mysqli_stmt_get_result($statStmt);
            if ($row = mysqli_fetch_assoc($statRes)) {
                $stats = $row;
            }
            mysqli_stmt_close($statStmt);
        }

        // 3. Fetch Full Order History
        $orderStmt = mysqli_prepare($conn, "SELECT o.*, COUNT(oi.order_item_id) AS items_count
                                            FROM orders o
                                            LEFT JOIN order_items oi ON o.order_id = oi.order_id
                                            WHERE o.user_id = ?
                                            GROUP BY o.order_id
                                            ORDER BY o.order_id DESC");
        if ($orderStmt) {
            mysqli_stmt_bind_param($orderStmt, 'i', $customerId);
            mysqli_stmt_execute($orderStmt);
            $orderRes = mysqli_stmt_get_result($orderStmt);
            while ($o = mysqli_fetch_assoc($orderRes)) {
                $customerOrders[] = $o;
            }
            mysqli_stmt_close($orderStmt);
        }
    }
}

if (!$customer) {
    setFlash('error', 'Customer account not found.');
    header('Location: index.php');
    exit;
}

$flashSuccess = getFlash('success');
$flashWarning = getFlash('warning');
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

  <!-- Title & Navigation -->
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <div class="small text-muted mb-1">
        <a href="index.php" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i> Back to Customer Directory</a>
      </div>
      <h1 class="h3 fw-bold text-dark mb-0">Customer Profile: <?= e($customer['name']) ?></h1>
      <span class="text-muted small">Registered on <?= date('F j, Y \a\t g:i A', strtotime($customer['created_at'])) ?></span>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <!-- Customer Profile Summary Card -->
    <div class="col-lg-4">
      <div class="admin-card p-4 h-100">
        <div class="text-center pb-3 border-bottom mb-3">
          <div class="user-avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold fs-2 mx-auto mb-2" style="width: 72px; height: 72px;">
            <?= strtoupper(substr($customer['name'], 0, 1)) ?>
          </div>
          <h5 class="fw-bold text-dark mb-0"><?= e($customer['name']) ?></h5>
          <span class="badge bg-secondary-subtle text-secondary small text-uppercase">
            <?= e($customer['role']) ?>
          </span>
        </div>

        <div class="mb-3">
          <div class="small text-muted text-uppercase fw-bold mb-1">Email Address</div>
          <div class="small text-dark fw-medium">
            <i class="bi bi-envelope me-1 text-primary"></i> <?= e($customer['email']) ?>
          </div>
        </div>

        <div class="mb-3">
          <div class="small text-muted text-uppercase fw-bold mb-1">Phone Number</div>
          <div class="small text-dark">
            <?php if (!empty($customer['phone'])): ?>
              <i class="bi bi-telephone me-1 text-primary"></i> <?= e($customer['phone']) ?>
            <?php else: ?>
              <span class="text-muted">Not provided</span>
            <?php endif; ?>
          </div>
        </div>

        <div class="mb-4">
          <div class="small text-muted text-uppercase fw-bold mb-1">Account Status</div>
          <div class="d-flex align-items-center gap-2">
            <?php if (($customer['status'] ?? 'active') === 'active'): ?>
              <span class="badge bg-success-subtle text-success fs-6">Active</span>
            <?php else: ?>
              <span class="badge bg-danger-subtle text-danger fs-6">Inactive / Suspended</span>
            <?php endif; ?>
          </div>
        </div>

        <!-- Account Status Toggle Form -->
        <div class="pt-3 border-top">
          <form action="toggle-status.php" method="POST" onsubmit="return confirm('Change account status for this user?');">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" value="<?= (int)$customer['user_id'] ?>">
            <?php if (($customer['status'] ?? 'active') === 'active'): ?>
              <input type="hidden" name="new_status" value="inactive">
              <button type="submit" class="btn btn-outline-danger w-100 btn-sm">
                <i class="bi bi-person-x me-1"></i> Deactivate Account
              </button>
            <?php else: ?>
              <input type="hidden" name="new_status" value="active">
              <button type="submit" class="btn btn-outline-success w-100 btn-sm">
                <i class="bi bi-person-check me-1"></i> Reactivate Account
              </button>
            <?php endif; ?>
          </form>
        </div>
      </div>
    </div>

    <!-- Customer Statistics Cards -->
    <div class="col-lg-8">
      <div class="row g-3 mb-4">
        <div class="col-sm-6">
          <div class="admin-card p-3">
            <div class="text-muted small text-uppercase fw-semibold">Delivered Spending</div>
            <div class="h3 fw-bold mb-0 text-success"><?= format_pkr($stats['lifetime_spend']) ?></div>
            <div class="small text-muted" style="font-size: 0.72rem;">Completed sales value</div>
          </div>
        </div>

        <div class="col-sm-6">
          <div class="admin-card p-3">
            <div class="text-muted small text-uppercase fw-semibold">Total Orders Placed</div>
            <div class="h3 fw-bold mb-0 text-dark"><?= (int)$stats['total_orders'] ?></div>
            <div class="small text-muted" style="font-size: 0.72rem;">Lifetime transactions</div>
          </div>
        </div>

        <div class="col-sm-6">
          <div class="admin-card p-3">
            <div class="text-muted small text-uppercase fw-semibold">Delivered Orders</div>
            <div class="h4 fw-bold mb-0 text-dark"><?= (int)$stats['delivered_orders'] ?> Orders</div>
          </div>
        </div>

        <div class="col-sm-6">
          <div class="admin-card p-3">
            <div class="text-muted small text-uppercase fw-semibold">Pending / In-Transit</div>
            <div class="h4 fw-bold mb-0 text-warning"><?= (int)$stats['pending_orders'] ?> Orders</div>
          </div>
        </div>
      </div>

      <!-- Customer Orders Table -->
      <div class="admin-card overflow-hidden">
        <div class="p-3 bg-white border-bottom d-flex justify-content-between align-items-center">
          <h5 class="fw-bold mb-0 fs-6 text-dark">Order History (<?= count($customerOrders) ?>)</h5>
        </div>

        <?php if (empty($customerOrders)): ?>
          <div class="p-4 text-center text-muted small">No orders recorded for this customer.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle mb-0 table-hover">
              <thead class="table-light small">
                <tr>
                  <th scope="col" class="ps-3">Order #</th>
                  <th scope="col">Date</th>
                  <th scope="col" class="text-center">Items</th>
                  <th scope="col">Total</th>
                  <th scope="col">Status</th>
                  <th scope="col" class="text-end pe-3">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($customerOrders as $ord): ?>
                  <?php
                    $statusBadge = 'bg-secondary';
                    switch ($ord['order_status']) {
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
                      <a href="../orders/details.php?id=<?= (int)$ord['order_id'] ?>" class="fw-bold font-monospace text-primary text-decoration-none small">
                        <?= e($ord['order_number']) ?>
                      </a>
                    </td>
                    <td class="small text-muted">
                      <?= date('M j, Y', strtotime($ord['created_at'])) ?>
                    </td>
                    <td class="text-center small">
                      <span class="badge bg-light text-dark border">
                        <?= (int)$ord['items_count'] ?> item<?= (int)$ord['items_count'] === 1 ? '' : 's' ?>
                      </span>
                    </td>
                    <td class="fw-bold text-dark small">
                      <?= format_pkr($ord['total_amount']) ?>
                    </td>
                    <td>
                      <span class="badge <?= $statusBadge ?> text-uppercase" style="font-size: 0.65rem;">
                        <?= e($ord['order_status']) ?>
                      </span>
                    </td>
                    <td class="text-end pe-3">
                      <a href="../orders/details.php?id=<?= (int)$ord['order_id'] ?>" class="btn btn-outline-primary btn-sm py-0 px-2" style="font-size: 0.75rem;">
                        <i class="bi bi-eye"></i> View Order
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
  </div>
</main>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
