<?php
/**
 * EasyShop — Customer Orders History Page
 * 
 * Protected Route: Requires active customer session.
 * Displays list of all past orders placed by the currently authenticated customer.
 */

$pageTitle   = 'My Orders — EasyShop';
$pageDesc    = 'View your previous purchases, track order statuses and inspect receipts.';
$activePage  = 'orders';
$depthPrefix = '../';

require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';

$userId = (int)$_SESSION['user']['user_id'];
$orders = [];

if ($conn) {
    $sql = "SELECT o.order_id, o.order_number, o.subtotal, o.shipping_fee, o.total_amount,
                   o.payment_method, o.payment_status, o.order_status, o.created_at,
                   COUNT(oi.order_item_id) AS item_count
            FROM orders o
            LEFT JOIN order_items oi ON o.order_id = oi.order_id
            WHERE o.user_id = ?
            GROUP BY o.order_id
            ORDER BY o.order_id DESC";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $orders[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
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
          <li class="breadcrumb-item active" aria-current="page">My Orders</li>
        </ol>
      </nav>
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h1 class="h3 fw-bold text-dark mb-0">Order History</h1>
        <a href="../products.php" class="btn btn-outline-primary btn-sm">
          <i class="bi bi-cart-plus me-1"></i> Continue Shopping
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
          <i class="bi bi-exclamation-octagon-fill me-1"></i> <?= e($flashError) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <?php if (empty($orders)): ?>
        <!-- Empty State -->
        <div class="card border-0 shadow-sm rounded-4 p-5 text-center my-4">
          <div class="mb-3">
            <i class="bi bi-bag-x display-3 text-muted"></i>
          </div>
          <h3 class="fw-bold text-dark mb-2">No Orders Yet</h3>
          <p class="text-muted small mb-4 mx-auto" style="max-width: 460px;">
            You have not placed any orders with EasyShop yet. Browse our product catalog to discover trending electronics, fashion, and home essentials.
          </p>
          <div>
            <a href="../products.php" class="btn btn-primary-custom px-4">
              <i class="bi bi-shop me-1"></i> Explore Products
            </a>
          </div>
        </div>

      <?php else: ?>

        <!-- Orders Table Card -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
          <div class="p-3 bg-white border-bottom d-flex justify-content-between align-items-center">
            <span class="fw-bold text-dark">
              Total Placed Orders: <strong><?= count($orders) ?></strong>
            </span>
          </div>

          <div class="table-responsive">
            <table class="table align-middle mb-0 table-hover">
              <thead class="table-light small">
                <tr>
                  <th scope="col" class="ps-4">Order #</th>
                  <th scope="col">Date Placed</th>
                  <th scope="col" class="text-center">Items</th>
                  <th scope="col">Total Amount</th>
                  <th scope="col">Payment</th>
                  <th scope="col">Status</th>
                  <th scope="col" class="text-end pe-4">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($orders as $ord): ?>
                  <?php
                    // Status Badge Styling
                    $statusClass = 'bg-secondary';
                    switch ($ord['order_status']) {
                        case 'pending':    $statusClass = 'bg-warning text-dark'; break;
                        case 'confirmed':  $statusClass = 'bg-info text-dark'; break;
                        case 'processing': $statusClass = 'bg-primary text-white'; break;
                        case 'shipped':    $statusClass = 'bg-info text-white'; break;
                        case 'delivered':  $statusClass = 'bg-success text-white'; break;
                        case 'cancelled':  $statusClass = 'bg-danger text-white'; break;
                    }

                    $payClass = ($ord['payment_status'] === 'paid') ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning-emphasis';
                  ?>
                  <tr>
                    <td class="ps-4">
                      <a href="order-details.php?order=<?= urlencode($ord['order_number']) ?>" class="fw-bold text-primary font-monospace text-decoration-none">
                        <?= e($ord['order_number']) ?>
                      </a>
                    </td>
                    <td class="small text-muted">
                      <?= date('M j, Y, g:i A', strtotime($ord['created_at'])) ?>
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
                      <span class="badge <?= $payClass ?> small">
                        <?= strtoupper(e($ord['payment_method'])) ?> • <?= ucfirst(e($ord['payment_status'])) ?>
                      </span>
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
        </div>

      <?php endif; ?>

    </div>
  </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
