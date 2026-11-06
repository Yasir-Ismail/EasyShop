<?php
/**
 * EasyShop — Admin Orders Management (List & Filter)
 * 
 * Protected Route: Requires administrator privileges.
 * Lists customer sales orders, supports searching by order number,
 * filtering by order status, and provides links to detail management.
 */

$pageTitle   = 'Orders — EasyShop Admin';
$activeNav   = 'orders';
$depthPrefix = '../../';

require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';

// 1. Filter & Search Parameters
$statusFilter = trim($_GET['status'] ?? 'all');
$searchQuery  = trim($_GET['search'] ?? '');

$validStatuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
if (!in_array($statusFilter, $validStatuses, true)) {
    $statusFilter = 'all';
}

// 2. Aggregate Metrics
$stats = [
    'total'      => 0,
    'pending'    => 0,
    'processing' => 0,
    'delivered'  => 0,
];

if ($conn) {
    if ($res = mysqli_query($conn, "SELECT COUNT(*) FROM orders")) {
        $stats['total'] = (int)mysqli_fetch_row($res)[0];
        mysqli_free_result($res);
    }
    if ($res = mysqli_query($conn, "SELECT COUNT(*) FROM orders WHERE order_status = 'pending'")) {
        $stats['pending'] = (int)mysqli_fetch_row($res)[0];
        mysqli_free_result($res);
    }
    if ($res = mysqli_query($conn, "SELECT COUNT(*) FROM orders WHERE order_status IN ('confirmed', 'processing')")) {
        $stats['processing'] = (int)mysqli_fetch_row($res)[0];
        mysqli_free_result($res);
    }
    if ($res = mysqli_query($conn, "SELECT COUNT(*) FROM orders WHERE order_status = 'delivered'")) {
        $stats['delivered'] = (int)mysqli_fetch_row($res)[0];
        mysqli_free_result($res);
    }
}

// 3. Build dynamic filtered query
$orders = [];
if ($conn) {
    $sql = "SELECT o.order_id, o.order_number, o.subtotal, o.shipping_fee, o.total_amount,
                   o.payment_method, o.payment_status, o.order_status, o.is_stock_restored,
                   o.shipping_name, o.shipping_phone, o.created_at,
                   u.name AS customer_account_name, u.email AS customer_account_email,
                   COUNT(oi.order_item_id) AS items_count
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.user_id
            LEFT JOIN order_items oi ON o.order_id = oi.order_id
            WHERE 1=1 ";

    $types = '';
    $params = [];

    if ($statusFilter !== 'all') {
        $sql .= "AND o.order_status = ? ";
        $types .= 's';
        $params[] = $statusFilter;
    }

    if (!empty($searchQuery)) {
        $sql .= "AND (o.order_number LIKE ? OR o.shipping_name LIKE ? OR o.shipping_phone LIKE ?) ";
        $types .= 'sss';
        $like = '%' . $searchQuery . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= "GROUP BY o.order_id ORDER BY o.order_id DESC";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $orders[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
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

  <!-- Title & Actions -->
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <h1 class="h3 fw-bold text-dark mb-1">Order Management</h1>
      <p class="text-muted small mb-0">Track customer sales, review deliveries, and update fulfillment statuses</p>
    </div>
  </div>

  <!-- Metric Statistics Cards -->
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
      <div class="admin-card p-3">
        <div class="d-flex align-items-center gap-3">
          <div class="stat-icon bg-primary-subtle text-primary p-3 rounded-3 fs-3">
            <i class="bi bi-receipt"></i>
          </div>
          <div>
            <div class="text-muted small text-uppercase fw-semibold">Total Orders</div>
            <div class="h3 fw-bold mb-0 text-dark"><?= $stats['total'] ?></div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-xl-3">
      <div class="admin-card p-3">
        <div class="d-flex align-items-center gap-3">
          <div class="stat-icon bg-warning-subtle text-warning p-3 rounded-3 fs-3">
            <i class="bi bi-clock-history"></i>
          </div>
          <div>
            <div class="text-muted small text-uppercase fw-semibold">Pending Orders</div>
            <div class="h3 fw-bold mb-0 text-dark"><?= $stats['pending'] ?></div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-xl-3">
      <div class="admin-card p-3">
        <div class="d-flex align-items-center gap-3">
          <div class="stat-icon bg-info-subtle text-info p-3 rounded-3 fs-3">
            <i class="bi bi-box-seam"></i>
          </div>
          <div>
            <div class="text-muted small text-uppercase fw-semibold">Processing / Confirmed</div>
            <div class="h3 fw-bold mb-0 text-dark"><?= $stats['processing'] ?></div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-xl-3">
      <div class="admin-card p-3">
        <div class="d-flex align-items-center gap-3">
          <div class="stat-icon bg-success-subtle text-success p-3 rounded-3 fs-3">
            <i class="bi bi-check-all"></i>
          </div>
          <div>
            <div class="text-muted small text-uppercase fw-semibold">Delivered Orders</div>
            <div class="h3 fw-bold mb-0 text-dark"><?= $stats['delivered'] ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filters & Search Toolbar -->
  <div class="admin-card p-3 mb-4">
    <form method="GET" action="index.php" class="row g-2 align-items-center">
      <div class="col-md-5">
        <div class="input-group">
          <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
          <input type="text" name="search" class="form-control" placeholder="Search by Order #, Customer, or Phone..." value="<?= e($searchQuery) ?>">
        </div>
      </div>

      <div class="col-md-4">
        <div class="input-group">
          <span class="input-group-text bg-light text-muted"><i class="bi bi-funnel"></i></span>
          <select name="status" class="form-select" onchange="this.form.submit()">
            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="confirmed" <?= $statusFilter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
            <option value="processing" <?= $statusFilter === 'processing' ? 'selected' : '' ?>>Processing</option>
            <option value="shipped" <?= $statusFilter === 'shipped' ? 'selected' : '' ?>>Shipped</option>
            <option value="delivered" <?= $statusFilter === 'delivered' ? 'selected' : '' ?>>Delivered</option>
            <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
          </select>
        </div>
      </div>

      <div class="col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary w-100">Filter</button>
        <?php if (!empty($searchQuery) || $statusFilter !== 'all'): ?>
          <a href="index.php" class="btn btn-outline-secondary" title="Reset Filters"><i class="bi bi-arrow-counterclockwise"></i></a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Orders Table -->
  <div class="admin-card overflow-hidden">
    <div class="table-responsive">
      <table class="table align-middle mb-0 table-hover">
        <thead class="table-light small">
          <tr>
            <th scope="col" class="ps-3">Order #</th>
            <th scope="col">Date</th>
            <th scope="col">Customer / Recipient</th>
            <th scope="col">Contact</th>
            <th scope="col" class="text-center">Items</th>
            <th scope="col">Total</th>
            <th scope="col">Payment</th>
            <th scope="col">Status</th>
            <th scope="col" class="text-end pe-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($orders)): ?>
            <tr>
              <td colspan="9" class="text-center py-5 text-muted">
                <i class="bi bi-inbox display-6 d-block mb-2 text-muted"></i>
                No orders found matching the selected criteria.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($orders as $ord): ?>
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

                $payBadge = ($ord['payment_status'] === 'paid') ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning-emphasis';
              ?>
              <tr>
                <td class="ps-3">
                  <a href="details.php?id=<?= (int)$ord['order_id'] ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                    <?= e($ord['order_number']) ?>
                  </a>
                </td>
                <td class="small text-muted">
                  <?= date('M j, Y', strtotime($ord['created_at'])) ?><br>
                  <span style="font-size: 0.72rem;"><?= date('g:i A', strtotime($ord['created_at'])) ?></span>
                </td>
                <td>
                  <div class="fw-semibold text-dark small"><?= e($ord['shipping_name']) ?></div>
                  <?php if (!empty($ord['customer_account_name'])): ?>
                    <span class="text-muted small" style="font-size: 0.72rem;">User: <?= e($ord['customer_account_name']) ?></span>
                  <?php else: ?>
                    <span class="text-muted small" style="font-size: 0.72rem;">Guest/Deleted Account</span>
                  <?php endif; ?>
                </td>
                <td class="small text-muted">
                  <i class="bi bi-telephone me-1"></i><?= e($ord['shipping_phone']) ?>
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
                  <span class="badge <?= $payBadge ?> small">
                    <?= strtoupper(e($ord['payment_method'])) ?> • <?= ucfirst(e($ord['payment_status'])) ?>
                  </span>
                </td>
                <td>
                  <span class="badge <?= $statusBadge ?> small text-uppercase">
                    <?= e($ord['order_status']) ?>
                  </span>
                  <?php if ($ord['order_status'] === 'cancelled' && (int)$ord['is_stock_restored'] === 1): ?>
                    <span class="badge bg-secondary-subtle text-secondary small d-block mt-1" style="font-size: 0.65rem;">
                      Stock Restored
                    </span>
                  <?php endif; ?>
                </td>
                <td class="text-end pe-3">
                  <a href="details.php?id=<?= (int)$ord['order_id'] ?>" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-eye me-1"></i> Manage
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
