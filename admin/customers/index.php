<?php
/**
 * EasyShop — Admin Customer Management (Listing, Search & Pagination)
 * 
 * Protected Route: Requires administrator privileges.
 * Lists registered customers with their order history counts, delivered lifetime
 * spending totals, search filtering, and pagination.
 */

$pageTitle   = 'Customers — EasyShop Admin';
$activeNav   = 'customers';
$depthPrefix = '../../';

require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';

// 1. Filter, Search & Pagination Setup
$searchQuery  = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');
$validStatuses = ['all', 'active', 'inactive'];
if (!in_array($statusFilter, $validStatuses, true)) {
    $statusFilter = 'all';
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$totalCustomers = 0;
$customers = [];

if ($conn) {
    // Count Query for Pagination
    $countSql = "SELECT COUNT(*) FROM users u WHERE u.role = 'customer' ";
    $countTypes = '';
    $countParams = [];

    if (!empty($searchQuery)) {
        $countSql .= "AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?) ";
        $countTypes .= 'sss';
        $like = '%' . $searchQuery . '%';
        $countParams[] = $like;
        $countParams[] = $like;
        $countParams[] = $like;
    }

    if ($statusFilter !== 'all') {
        $countSql .= "AND u.status = ? ";
        $countTypes .= 's';
        $countParams[] = $statusFilter;
    }

    $cStmt = mysqli_prepare($conn, $countSql);
    if ($cStmt) {
        if (!empty($countParams)) {
            mysqli_stmt_bind_param($cStmt, $countTypes, ...$countParams);
        }
        mysqli_stmt_execute($cStmt);
        mysqli_stmt_bind_result($cStmt, $totalCustomers);
        mysqli_stmt_fetch($cStmt);
        mysqli_stmt_close($cStmt);
    }

    $totalPages = max(1, (int)ceil($totalCustomers / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $perPage;
    }

    // Customer Records Query with Lifetime Spending & Order Totals
    $sql = "SELECT u.user_id, u.name, u.email, u.phone, u.role, u.status, u.created_at,
                   COUNT(o.order_id) AS total_orders,
                   COALESCE(SUM(CASE WHEN o.order_status = 'delivered' THEN o.total_amount ELSE 0 END), 0) AS total_spent
            FROM users u
            LEFT JOIN orders o ON u.user_id = o.user_id
            WHERE u.role = 'customer' ";

    $types = '';
    $params = [];

    if (!empty($searchQuery)) {
        $sql .= "AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?) ";
        $types .= 'sss';
        $like = '%' . $searchQuery . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if ($statusFilter !== 'all') {
        $sql .= "AND u.status = ? ";
        $types .= 's';
        $params[] = $statusFilter;
    }

    $sql .= "GROUP BY u.user_id ORDER BY u.user_id DESC LIMIT ? OFFSET ?";
    $types .= 'ii';
    $params[] = $perPage;
    $params[] = $offset;

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $customers[] = $row;
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

  <!-- Title & Count -->
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <h1 class="h3 fw-bold text-dark mb-1">Customer Management</h1>
      <p class="text-muted small mb-0">Browse registered shoppers, inspect order volume, and review lifetime spending</p>
    </div>
    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
      Total Customers: <?= (int)$totalCustomers ?>
    </span>
  </div>

  <!-- Search & Filter Bar -->
  <div class="admin-card p-3 mb-4">
    <form method="GET" action="index.php" class="row g-2 align-items-center">
      <div class="col-md-6">
        <div class="input-group">
          <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
          <input type="text" name="search" class="form-control" placeholder="Search by customer name, email or phone..." value="<?= e($searchQuery) ?>">
        </div>
      </div>

      <div class="col-md-3">
        <select name="status" class="form-select" onchange="this.form.submit()">
          <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Account Statuses</option>
          <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active Only</option>
          <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
        </select>
      </div>

      <div class="col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary w-100">Apply Filter</button>
        <?php if (!empty($searchQuery) || $statusFilter !== 'all'): ?>
          <a href="index.php" class="btn btn-outline-secondary" title="Reset Search"><i class="bi bi-arrow-counterclockwise"></i></a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Customer List Card -->
  <div class="admin-card overflow-hidden mb-4">
    <div class="table-responsive">
      <table class="table align-middle mb-0 table-hover">
        <thead class="table-light small">
          <tr>
            <th scope="col" class="ps-3">ID</th>
            <th scope="col">Customer</th>
            <th scope="col">Contact</th>
            <th scope="col">Status</th>
            <th scope="col">Joined</th>
            <th scope="col" class="text-center">Orders</th>
            <th scope="col">Delivered Spend</th>
            <th scope="col" class="text-end pe-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($customers)): ?>
            <tr>
              <td colspan="8" class="text-center py-5 text-muted">
                <i class="bi bi-people display-6 d-block mb-2 text-muted"></i>
                No customers found matching your criteria.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($customers as $cust): ?>
              <tr>
                <td class="ps-3 text-muted small font-monospace">
                  #<?= (int)$cust['user_id'] ?>
                </td>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 34px; height: 34px; font-size: 0.85rem;">
                      <?= strtoupper(substr($cust['name'], 0, 1)) ?>
                    </div>
                    <div>
                      <a href="details.php?id=<?= (int)$cust['user_id'] ?>" class="fw-semibold text-dark text-decoration-none small d-block">
                        <?= e($cust['name']) ?>
                      </a>
                      <span class="text-muted" style="font-size: 0.72rem;"><?= e($cust['email']) ?></span>
                    </div>
                  </div>
                </td>
                <td class="small text-muted">
                  <?php if (!empty($cust['phone'])): ?>
                    <i class="bi bi-telephone me-1"></i><?= e($cust['phone']) ?>
                  <?php else: ?>
                    <span class="text-muted-subtle">&mdash;</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if (($cust['status'] ?? 'active') === 'active'): ?>
                    <span class="badge bg-success-subtle text-success small">Active</span>
                  <?php else: ?>
                    <span class="badge bg-danger-subtle text-danger small">Inactive</span>
                  <?php endif; ?>
                </td>
                <td class="small text-muted">
                  <?= date('M j, Y', strtotime($cust['created_at'])) ?>
                </td>
                <td class="text-center small">
                  <span class="badge bg-light text-dark border">
                    <?= (int)$cust['total_orders'] ?>
                  </span>
                </td>
                <td class="fw-bold text-dark small">
                  <?= format_pkr($cust['total_spent']) ?>
                </td>
                <td class="text-end pe-3">
                  <a href="details.php?id=<?= (int)$cust['user_id'] ?>" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-person-lines-fill me-1"></i> View Details
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination Controls -->
    <?php if ($totalPages > 1): ?>
      <div class="p-3 bg-light border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="text-muted small">
          Showing Page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong> (<?= (int)$totalCustomers ?> customers)
        </span>
        <nav aria-label="Customer pagination">
          <ul class="pagination pagination-sm mb-0">
            <?php if ($page > 1): ?>
              <li class="page-item">
                <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($searchQuery) ?>&status=<?= urlencode($statusFilter) ?>">Previous</a>
              </li>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
              <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($searchQuery) ?>&status=<?= urlencode($statusFilter) ?>"><?= $i ?></a>
              </li>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
              <li class="page-item">
                <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($searchQuery) ?>&status=<?= urlencode($statusFilter) ?>">Next</a>
              </li>
            <?php endif; ?>
          </ul>
        </nav>
      </div>
    <?php endif; ?>
  </div>
</main>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
