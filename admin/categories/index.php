<?php
/**
 * EasyShop — Admin Category Management (List & Overview)
 */

$pageTitle   = 'Categories — EasyShop Admin';
$activeNav   = 'categories';
$depthPrefix = '../../';

require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';

// Fetch all categories with assigned product count
$categories = [];
if ($conn) {
    $sql = "SELECT c.*, COUNT(p.product_id) AS product_count 
            FROM categories c 
            LEFT JOIN products p ON c.category_id = p.category_id 
            GROUP BY c.category_id 
            ORDER BY c.category_id ASC";
    if ($res = mysqli_query($conn, $sql)) {
        while ($row = mysqli_fetch_assoc($res)) {
            $categories[] = $row;
        }
        mysqli_free_result($res);
    }
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
      <h1 class="h3 fw-bold text-dark mb-1">Category Management</h1>
      <p class="text-muted small mb-0">Organize and manage store departments and navigation</p>
    </div>
    <a href="add.php" class="btn btn-primary btn-sm">
      <i class="bi bi-plus-lg me-1"></i> Add New Category
    </a>
  </div>

  <div class="admin-card overflow-hidden">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width: 70px;">ID</th>
            <th>Category Name</th>
            <th>Slug</th>
            <th>Description</th>
            <th class="text-center" style="width: 120px;">Products</th>
            <th style="width: 110px;">Status</th>
            <th class="text-end" style="width: 140px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($categories)): ?>
            <tr>
              <td colspan="7" class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                No categories found. Click "Add New Category" to create one.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($categories as $cat): ?>
              <tr>
                <td class="text-muted fw-bold">#<?= (int)$cat['category_id'] ?></td>
                <td>
                  <span class="fw-semibold text-dark"><?= e($cat['category_name']) ?></span>
                </td>
                <td>
                  <code class="text-muted small"><?= e($cat['slug']) ?></code>
                </td>
                <td class="text-muted small">
                  <?= !empty($cat['description']) ? e(mb_strimwidth($cat['description'], 0, 50, '...')) : '<em class="text-black-50">None</em>' ?>
                </td>
                <td class="text-center">
                  <span class="badge bg-light text-dark border">
                    <?= (int)$cat['product_count'] ?> item(s)
                  </span>
                </td>
                <td>
                  <?php if ($cat['status'] === 'active'): ?>
                    <span class="badge-status-active"><i class="bi bi-check2 me-1"></i>Active</span>
                  <?php else: ?>
                    <span class="badge-status-inactive"><i class="bi bi-x me-1"></i>Inactive</span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <div class="btn-group btn-group-sm">
                    <a href="edit.php?id=<?= (int)$cat['category_id'] ?>" class="btn btn-outline-secondary" title="Edit Category">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <button type="button" class="btn btn-outline-danger" title="Delete Category" data-bs-toggle="modal" data-bs-target="#deleteModal<?= (int)$cat['category_id'] ?>">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>

                  <!-- Safe Delete Modal (POST with CSRF) -->
                  <div class="modal fade" id="deleteModal<?= (int)$cat['category_id'] ?>" tabindex="-1" aria-labelledby="modalLabel<?= (int)$cat['category_id'] ?>" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                      <div class="modal-content text-start">
                        <div class="modal-header">
                          <h5 class="modal-title fw-bold" id="modalLabel<?= (int)$cat['category_id'] ?>">Confirm Category Deletion</h5>
                          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                          <p class="mb-2">Are you sure you want to delete category <strong>"<?= e($cat['category_name']) ?>"</strong>?</p>
                          <?php if ((int)$cat['product_count'] > 0): ?>
                            <div class="alert alert-warning small mb-0">
                              <i class="bi bi-exclamation-triangle-fill me-1"></i>
                              <strong>Warning:</strong> This category currently contains <strong><?= (int)$cat['product_count'] ?> product(s)</strong>. The system will prevent deletion until all assigned products are safely reassigned or removed.
                            </div>
                          <?php else: ?>
                            <p class="text-muted small mb-0">This category has no assigned products and can be safely removed.</p>
                          <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                          <form action="../../actions/category-delete-action.php" method="POST" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="category_id" value="<?= (int)$cat['category_id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">
                              <i class="bi bi-trash me-1"></i> Delete Category
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
</main>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
