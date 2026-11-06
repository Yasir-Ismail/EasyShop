<?php


$pageTitle   = 'Order Details — EasyShop Admin';
$activeNav   = 'orders';
$depthPrefix = '../../';

require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
require_once __DIR__ . '/../../includes/csrf.php';

$orderId = (int)($_GET['id'] ?? 0);

if ($orderId <= 0) {
    setFlash('error', 'Invalid order ID specified.');
    header('Location: index.php');
    exit;
}

$order = null;
$orderItems = [];

if ($conn) {
    $sql = "SELECT o.*, u.name AS user_name, u.email AS user_email, u.phone AS user_phone, u.created_at AS user_registered_at
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.user_id
            WHERE o.order_id = ?
            LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $orderId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $order = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
    }

    if ($order) {
        $itemSql = "SELECT oi.*, p.stock AS current_live_stock, p.sku AS current_sku, p.image, p.status AS product_status
                    FROM order_items oi
                    LEFT JOIN products p ON oi.product_id = p.product_id
                    WHERE oi.order_id = ?
                    ORDER BY oi.order_item_id ASC";
        $itemStmt = mysqli_prepare($conn, $itemSql);
        if ($itemStmt) {
            mysqli_stmt_bind_param($itemStmt, 'i', $orderId);
            mysqli_stmt_execute($itemStmt);
            $itemRes = mysqli_stmt_get_result($itemStmt);
            while ($row = mysqli_fetch_assoc($itemRes)) {
                $orderItems[] = $row;
            }
            mysqli_stmt_close($itemStmt);
        }
    }
}

if (!$order) {
    setFlash('error', 'Order not found.');
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
        <a href="index.php" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i> Back to Orders</a>
      </div>
      <h1 class="h3 fw-bold text-dark mb-0">
        Order #<?= e($order['order_number']) ?>
      </h1>
      <span class="text-muted small">Placed on <?= date('F j, Y \a\t g:i A', strtotime($order['created_at'])) ?></span>
    </div>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print();">
        <i class="bi bi-printer me-1"></i> Print Order
      </button>
    </div>
  </div>

  <div class="row g-4">
    <!-- Left Column: Order Line Items and Addresses -->
    <div class="col-lg-8">
      
      <!-- Meta Information Card -->
      <div class="admin-card p-4 mb-4">
        <div class="row g-3">
          <div class="col-sm-6 border-end">
            <h6 class="text-muted text-uppercase small fw-bold mb-2">Delivery &amp; Contact Details</h6>
            <div class="fw-bold text-dark"><?= e($order['shipping_name']) ?></div>
            <div class="small text-muted"><i class="bi bi-telephone me-1"></i><?= e($order['shipping_phone']) ?></div>
            <div class="small text-muted mt-1"><i class="bi bi-geo-alt me-1"></i><?= nl2br(e($order['shipping_address'])) ?></div>
          </div>
          <div class="col-sm-6">
            <h6 class="text-muted text-uppercase small fw-bold mb-2">Customer Account</h6>
            <?php if (!empty($order['user_id'])): ?>
              <div class="fw-bold text-dark"><?= e($order['user_name']) ?></div>
              <div class="small text-muted"><i class="bi bi-envelope me-1"></i><?= e($order['user_email']) ?></div>
              <?php if (!empty($order['user_phone'])): ?>
                <div class="small text-muted"><i class="bi bi-telephone me-1"></i><?= e($order['user_phone']) ?></div>
              <?php endif; ?>
              <div class="small text-muted mt-1" style="font-size: 0.72rem;">Customer ID: #<?= (int)$order['user_id'] ?></div>
            <?php else: ?>
              <span class="text-muted small">Guest checkout / Account deleted</span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Line Items Card -->
      <div class="admin-card overflow-hidden mb-4">
        <div class="p-3 bg-light border-bottom">
          <h5 class="fw-bold text-dark mb-0 fs-6">Purchased Items Snapshot</h5>
        </div>

        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead class="table-light small">
              <tr>
                <th scope="col" class="ps-3">Product Name &amp; Snapshot</th>
                <th scope="col" class="text-center">Live Stock</th>
                <th scope="col" class="text-center">Order Price</th>
                <th scope="col" class="text-center">Qty</th>
                <th scope="col" class="text-end pe-3">Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($orderItems as $item): ?>
                <tr>
                  <td class="ps-3">
                    <div class="d-flex align-items-center gap-3">
                      <img src="<?= e(get_product_image_url($item['image'], '../../')) ?>" 
                           alt="<?= e($item['product_name']) ?>" 
                           class="rounded-3 border object-fit-cover" 
                           style="width: 44px; height: 44px;">
                      <div>
                        <div class="fw-semibold text-dark small"><?= e($item['product_name']) ?></div>
                        <div class="text-muted small" style="font-size: 0.72rem;">
                          Product ID: #<?= (int)$item['product_id'] ?> 
                          <?php if (!empty($item['current_sku'])): ?>
                            | SKU: <?= e($item['current_sku']) ?>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  </td>
                  <td class="text-center small">
                    <?php if ($item['product_id'] !== null): ?>
                      <span class="badge <?= (int)$item['current_live_stock'] > 5 ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning-emphasis' ?>">
                        <?= (int)$item['current_live_stock'] ?> in stock
                      </span>
                    <?php else: ?>
                      <span class="badge bg-secondary-subtle text-secondary">Deleted</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-center text-muted small">
                    <?= format_pkr($item['price']) ?>
                  </td>
                  <td class="text-center fw-bold text-dark small">
                    <?= (int)$item['quantity'] ?>
                  </td>
                  <td class="text-end pe-3 fw-bold text-dark small">
                    <?= format_pkr($item['subtotal']) ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Financial Summary -->
        <div class="p-4 bg-light border-top">
          <div class="row justify-content-end">
            <div class="col-md-5">
              <div class="d-flex justify-content-between mb-2 small">
                <span class="text-muted">Subtotal:</span>
                <span class="fw-semibold text-dark"><?= format_pkr($order['subtotal']) ?></span>
              </div>
              <div class="d-flex justify-content-between mb-2 small">
                <span class="text-muted">Shipping Fee:</span>
                <span class="fw-semibold text-dark"><?= format_pkr($order['shipping_fee']) ?></span>
              </div>
              <hr class="my-2">
              <div class="d-flex justify-content-between align-items-baseline">
                <span class="h6 fw-bold text-dark mb-0">Total Amount:</span>
                <span class="h4 fw-bold text-primary mb-0"><?= format_pkr($order['total_amount']) ?></span>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- Right Column: Status & Fulfillment Actions -->
    <div class="col-lg-4">
      <div class="admin-card p-4 sticky-top" style="top: 20px;">
        <h5 class="fw-bold text-dark mb-3">Order Status Management</h5>

        <!-- Current Status Badges -->
        <div class="mb-4 pb-3 border-bottom">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-muted small">Order Status:</span>
            <?php
              $statusBadge = 'bg-secondary';
              switch ($order['order_status']) {
                  case 'pending':    $statusBadge = 'bg-warning text-dark'; break;
                  case 'confirmed':  $statusBadge = 'bg-info text-dark'; break;
                  case 'processing': $statusBadge = 'bg-primary text-white'; break;
                  case 'shipped':    $statusBadge = 'bg-info text-white'; break;
                  case 'delivered':  $statusBadge = 'bg-success text-white'; break;
                  case 'cancelled':  $statusBadge = 'bg-danger text-white'; break;
              }
            ?>
            <span class="badge <?= $statusBadge ?> text-uppercase px-2 py-1">
              <?= e($order['order_status']) ?>
            </span>
          </div>

          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-muted small">Payment Status:</span>
            <span class="badge <?= $order['payment_status'] === 'paid' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning-emphasis' ?>">
              <?= ucfirst(e($order['payment_status'])) ?> (<?= strtoupper(e($order['payment_method'])) ?>)
            </span>
          </div>

          <div class="d-flex justify-content-between align-items-center">
            <span class="text-muted small">Stock Restored:</span>
            <span class="badge <?= (int)$order['is_stock_restored'] === 1 ? 'bg-success-subtle text-success' : 'bg-light text-muted border' ?>">
              <?= (int)$order['is_stock_restored'] === 1 ? 'Yes (Restored)' : 'No' ?>
            </span>
          </div>
        </div>

        <!-- Update Status Form -->
        <form action="update-status.php" method="POST" onsubmit="return confirm('Confirm update of order status?');">
          <?= csrf_field() ?>
          <input type="hidden" name="order_id" value="<?= (int)$order['order_id'] ?>">

          <div class="mb-3">
            <label for="order_status" class="form-label small fw-semibold text-dark">Change Order Status</label>
            <select name="order_status" id="order_status" class="form-select" <?= $order['order_status'] === 'cancelled' ? 'disabled' : '' ?>>
              <option value="pending" <?= $order['order_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
              <option value="confirmed" <?= $order['order_status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
              <option value="processing" <?= $order['order_status'] === 'processing' ? 'selected' : '' ?>>Processing</option>
              <option value="shipped" <?= $order['order_status'] === 'shipped' ? 'selected' : '' ?>>Shipped</option>
              <option value="delivered" <?= $order['order_status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
              <option value="cancelled" <?= $order['order_status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
          </div>

          <div class="mb-3">
            <label for="payment_status" class="form-label small fw-semibold text-dark">Change Payment Status</label>
            <select name="payment_status" id="payment_status" class="form-select">
              <option value="pending" <?= $order['payment_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
              <option value="paid" <?= $order['payment_status'] === 'paid' ? 'selected' : '' ?>>Paid (Cash Received)</option>
              <option value="failed" <?= $order['payment_status'] === 'failed' ? 'selected' : '' ?>>Failed</option>
            </select>
          </div>

          <!-- Cancellation Stock Rule Callout -->
          <?php if (in_array($order['order_status'], ['pending', 'confirmed', 'processing'], true)): ?>
            <div class="alert alert-info py-2 px-3 small mb-3">
              <i class="bi bi-info-circle me-1"></i> <strong>Cancellation Rule:</strong> Selecting <em>Cancelled</em> will automatically restore stock to all associated products safely once.
            </div>
          <?php elseif (in_array($order['order_status'], ['shipped', 'delivered'], true)): ?>
            <div class="alert alert-warning py-2 px-3 small mb-3">
              <i class="bi bi-exclamation-triangle me-1"></i> Order is in or past transit. Cancellation is locked to preserve inventory balance.
            </div>
          <?php elseif ($order['order_status'] === 'cancelled'): ?>
            <div class="alert alert-secondary py-2 px-3 small mb-3">
              <i class="bi bi-lock-fill me-1"></i> Order is permanently cancelled. Inventory was restored.
            </div>
          <?php endif; ?>

          <?php if ($order['order_status'] !== 'cancelled'): ?>
            <button type="submit" class="btn btn-primary w-100 py-2">
              <i class="bi bi-check2-circle me-1"></i> Save Changes
            </button>
          <?php endif; ?>
        </form>

      </div>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
