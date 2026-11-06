<?php
/**
 * EasyShop — Customer Order Details Page
 * 
 * Protected Route: Requires active customer session.
 * Displays granular breakdown for a single order belonging to the authenticated customer.
 */

$pageTitle   = 'Order Details — EasyShop';
$pageDesc    = 'View details, line items, and delivery status of your order.';
$activePage  = 'orders';
$depthPrefix = '../';

require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';

$userId = (int)$_SESSION['user']['user_id'];
$orderNumber = trim($_GET['order'] ?? '');
$orderIdParam = (int)($_GET['id'] ?? 0);

if (empty($orderNumber) && $orderIdParam <= 0) {
    setFlash('error', 'Please specify an order to view.');
    header('Location: orders.php');
    exit;
}

$order = null;
$orderItems = [];

if ($conn) {
    if (!empty($orderNumber)) {
        $stmt = mysqli_prepare($conn, "SELECT * FROM orders WHERE order_number = ? AND user_id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'si', $orderNumber, $userId);
    } else {
        $stmt = mysqli_prepare($conn, "SELECT * FROM orders WHERE order_id = ? AND user_id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'ii', $orderIdParam, $userId);
    }

    if ($stmt) {
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $order = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
    }

    if ($order) {
        $orderId = (int)$order['order_id'];
        $itemStmt = mysqli_prepare($conn, "SELECT oi.*, p.image, p.slug 
                                           FROM order_items oi 
                                           LEFT JOIN products p ON oi.product_id = p.product_id 
                                           WHERE oi.order_id = ? 
                                           ORDER BY oi.order_item_id ASC");
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
    setFlash('error', 'Order not found or you do not have permission to view this order.');
    header('Location: orders.php');
    exit;
}

$flashSuccess = getFlash('success');
$flashError   = getFlash('error');

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

  <!-- Print Stylesheet -->
  <style>
    @media print {
      #announcementBar, #mainNavbar, #footer, .no-print {
        display: none !important;
      }
      body {
        background: #fff !important;
        font-size: 12pt;
      }
      .card {
        border: 1px solid #ccc !important;
        box-shadow: none !important;
      }
    }
  </style>

  <!-- Breadcrumbs & Heading -->
  <div class="py-4 bg-light-custom border-bottom no-print">
    <div class="container">
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1 small">
          <li class="breadcrumb-item"><a href="../index.php" class="text-decoration-none">Home</a></li>
          <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">My Account</a></li>
          <li class="breadcrumb-item"><a href="orders.php" class="text-decoration-none">My Orders</a></li>
          <li class="breadcrumb-item active" aria-current="page"><?= e($order['order_number']) ?></li>
        </ol>
      </nav>
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
          <h1 class="h3 fw-bold text-dark mb-1">Order #<?= e($order['order_number']) ?></h1>
          <span class="text-muted small">Placed on <?= date('F j, Y \a\t g:i A', strtotime($order['created_at'])) ?></span>
        </div>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-outline-dark btn-sm" onclick="window.print();">
            <i class="bi bi-printer me-1"></i> Print Invoice
          </button>
          <a href="orders.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Orders
          </a>
        </div>
      </div>
    </div>
  </div>

  <main class="section-padding py-5">
    <div class="container" style="max-width: 960px;">

      <!-- Status Header Alert -->
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

      <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
        <div class="row g-4 align-items-center">
          <div class="col-md-6 border-end">
            <div class="small text-muted text-uppercase fw-bold mb-1">Current Order Status</div>
            <div class="d-flex align-items-center gap-2">
              <span class="badge <?= $statusBadge ?> fs-6 px-3 py-2 text-uppercase">
                <?= e($order['order_status']) ?>
              </span>
              <?php if ($order['order_status'] === 'cancelled'): ?>
                <span class="badge bg-secondary-subtle text-secondary small">Order Cancelled</span>
              <?php endif; ?>
            </div>
            <div class="small text-muted mt-2">
              Payment Method: <strong><?= strtoupper(e($order['payment_method'])) ?></strong> (<?= ucfirst(e($order['payment_status'])) ?>)
            </div>
          </div>

          <div class="col-md-6">
            <div class="small text-muted text-uppercase fw-bold mb-1">Delivery Address</div>
            <div class="fw-semibold text-dark"><?= e($order['shipping_name']) ?></div>
            <div class="small text-muted"><i class="bi bi-telephone me-1"></i><?= e($order['shipping_phone']) ?></div>
            <div class="small text-muted"><i class="bi bi-geo-alt me-1"></i><?= nl2br(e($order['shipping_address'])) ?></div>
          </div>
        </div>
      </div>

      <!-- Line Items Card -->
      <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="p-3 bg-light border-bottom">
          <h5 class="fw-bold text-dark mb-0 fs-6">Order Items</h5>
        </div>

        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead class="table-light small">
              <tr>
                <th scope="col" class="ps-4">Item Details</th>
                <th scope="col" class="text-center">Unit Price</th>
                <th scope="col" class="text-center">Quantity</th>
                <th scope="col" class="text-end pe-4">Line Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($orderItems as $item): ?>
                <tr>
                  <td class="ps-4">
                    <div class="d-flex align-items-center gap-3">
                      <img src="<?= e(get_product_image_url($item['image'], '../')) ?>" 
                           alt="<?= e($item['product_name']) ?>" 
                           class="rounded-3 border object-fit-cover" 
                           style="width: 50px; height: 50px;">
                      <div>
                        <div class="fw-semibold text-dark small"><?= e($item['product_name']) ?></div>
                        <?php if (!empty($item['product_id'])): ?>
                          <span class="text-muted small" style="font-size: 0.72rem;">SKU Ref: #<?= (int)$item['product_id'] ?></span>
                        <?php endif; ?>
                      </div>
                    </div>
                  </td>
                  <td class="text-center text-muted small">
                    <?= format_pkr($item['price']) ?>
                  </td>
                  <td class="text-center fw-bold text-dark small">
                    <?= (int)$item['quantity'] ?>
                  </td>
                  <td class="text-end pe-4 fw-bold text-dark small">
                    <?= format_pkr($item['subtotal']) ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Order Financial Summary -->
        <div class="p-4 bg-light border-top">
          <div class="row justify-content-end">
            <div class="col-md-5">
              <div class="d-flex justify-content-between mb-2 small">
                <span class="text-muted">Items Subtotal:</span>
                <span class="fw-semibold text-dark"><?= format_pkr($order['subtotal']) ?></span>
              </div>
              <div class="d-flex justify-content-between mb-2 small">
                <span class="text-muted">Standard Delivery (Pakistan):</span>
                <span class="fw-semibold text-dark"><?= format_pkr($order['shipping_fee']) ?></span>
              </div>
              <hr class="my-2">
              <div class="d-flex justify-content-between align-items-baseline">
                <span class="h6 fw-bold text-dark mb-0">Grand Total:</span>
                <span class="h4 fw-bold text-primary mb-0"><?= format_pkr($order['total_amount']) ?></span>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
