<?php
/**
 * EasyShop — Admin Sidebar Navigation Component
 */
$depthPrefix = $depthPrefix ?? '../';
$activeNav   = $activeNav ?? 'dashboard';
?>
<aside class="admin-sidebar">
  <div class="px-3 mb-3 d-none d-lg-block">
    <div class="small text-uppercase fw-bold text-muted" style="font-size: 0.72rem; letter-spacing: 0.08em;">
      Store Management
    </div>
  </div>

  <nav class="nav flex-column">
    <a href="<?= $depthPrefix ?>admin/index.php" class="admin-nav-link <?= $activeNav === 'dashboard' ? 'active' : '' ?>">
      <i class="bi bi-speedometer2"></i> Dashboard
    </a>
    <a href="<?= $depthPrefix ?>admin/categories/index.php" class="admin-nav-link <?= $activeNav === 'categories' ? 'active' : '' ?>">
      <i class="bi bi-tags"></i> Categories
    </a>
    <a href="<?= $depthPrefix ?>admin/products/index.php" class="admin-nav-link <?= $activeNav === 'products' ? 'active' : '' ?>">
      <i class="bi bi-box-seam"></i> Products
    </a>
    <a href="<?= $depthPrefix ?>admin/orders/index.php" class="admin-nav-link <?= $activeNav === 'orders' ? 'active' : '' ?>">
      <i class="bi bi-receipt"></i> Orders
    </a>
    <a href="<?= $depthPrefix ?>admin/customers/index.php" class="admin-nav-link <?= $activeNav === 'customers' ? 'active' : '' ?>">
      <i class="bi bi-people"></i> Customers
    </a>
  </nav>

  <hr class="mx-3 my-4 d-none d-lg-block">

  <div class="px-3 mb-2 d-none d-lg-block">
    <div class="small text-uppercase fw-bold text-muted" style="font-size: 0.72rem; letter-spacing: 0.08em;">
      Quick Links
    </div>
  </div>

  <nav class="nav flex-column d-none d-lg-flex">
    <a href="<?= $depthPrefix ?>index.html" target="_blank" class="admin-nav-link">
      <i class="bi bi-shop"></i> View Storefront
    </a>
    <a href="<?= $depthPrefix ?>auth/logout.php" class="admin-nav-link text-danger">
      <i class="bi bi-box-arrow-right"></i> Sign Out
    </a>
  </nav>
</aside>
