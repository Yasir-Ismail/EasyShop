<?php
/**
 * EasyShop — Customer Navigation Bar & Search Overlay Component
 * 
 * Dynamically queries active categories for the dropdown menu,
 * checks session authentication state for account links,
 * and renders the interactive search modal overlay.
 */

$depthPrefix = $depthPrefix ?? '';
$activePage  = $activePage ?? 'home';

// Fetch active categories for the dropdown menu
$navCategories = [];
if (isset($conn) && $conn) {
    $catQuery = "SELECT category_id, category_name, slug FROM categories WHERE status = 'active' ORDER BY category_name ASC";
    if ($catRes = mysqli_query($conn, $catQuery)) {
        while ($c = mysqli_fetch_assoc($catRes)) {
            $navCategories[] = $c;
        }
        mysqli_free_result($catRes);
    }
}

// Account Link Target
$accountLink = $depthPrefix . 'auth/login.php';
$accountTitle = 'Login / Register';
if (isLoggedIn()) {
    $accountLink = isAdmin() ? ($depthPrefix . 'admin/index.php') : ($depthPrefix . 'customer/dashboard.php');
    $accountTitle = 'My Account (' . e($_SESSION['user']['name']) . ')';
}

// Live Cart & Wishlist counts
$navCartCount     = get_cart_count();
$navWishlistCount = get_wishlist_count();
?>

<!-- ===================== B. NAVIGATION BAR ===================== -->
<nav id="mainNavbar" class="navbar navbar-expand-lg sticky-top">
  <div class="container">
    <!-- Brand -->
    <a class="navbar-brand" href="<?= $depthPrefix ?>index.php">
      <span class="brand-easy">Easy</span><span class="brand-shop">Shop</span>
    </a>

    <!-- Mobile Icons & Hamburger Toggle -->
    <div class="navbar-mobile-icons d-lg-none">
      <button class="nav-icon-btn search-toggle" aria-label="Search">
        <i class="bi bi-search"></i>
      </button>
      <a href="<?= e($accountLink) ?>" class="nav-icon-btn" aria-label="Account" title="<?= e($accountTitle) ?>">
        <i class="bi bi-person<?= isLoggedIn() ? '-fill text-primary' : '' ?>"></i>
      </a>
      <a href="<?= $depthPrefix ?>cart.php" class="nav-icon-btn position-relative" aria-label="Cart">
        <i class="bi bi-cart3"></i>
        <span id="cartCountMobile" class="icon-badge <?= $navCartCount === 0 ? 'hidden' : '' ?>"><?= $navCartCount ?></span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
        <i class="bi bi-list"></i>
      </button>
    </div>

    <!-- Collapsible Navigation -->
    <div class="collapse navbar-collapse" id="navbarContent">
      <ul class="navbar-nav mx-auto">
        <li class="nav-item">
          <a class="nav-link <?= $activePage === 'home' ? 'active' : '' ?>" href="<?= $depthPrefix ?>index.php">Home</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $activePage === 'shop' ? 'active' : '' ?>" href="<?= $depthPrefix ?>products.php">Shop</a>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= $activePage === 'categories' ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            Categories
          </a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item fw-semibold" href="<?= $depthPrefix ?>products.php"><i class="bi bi-grid me-2 text-primary"></i>All Products</a></li>
            <li><hr class="dropdown-divider"></li>
            <?php if (!empty($navCategories)): ?>
              <?php foreach ($navCategories as $navCat): ?>
                <li>
                  <a class="dropdown-item" href="<?= $depthPrefix ?>products.php?category_id=<?= (int)$navCat['category_id'] ?>">
                    <i class="bi bi-chevron-right me-2 small text-muted"></i><?= e($navCat['category_name']) ?>
                  </a>
                </li>
              <?php endforeach; ?>
            <?php else: ?>
              <li><span class="dropdown-item text-muted small">No categories available</span></li>
            <?php endif; ?>
          </ul>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?= $depthPrefix ?>index.php#whyChoose">About Us</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?= $depthPrefix ?>index.php#footer">Contact</a>
        </li>
      </ul>

      <!-- Desktop Nav Icons -->
      <div class="navbar-icons d-none d-lg-flex">
        <button class="nav-icon-btn search-toggle" aria-label="Search" title="Search Products">
          <i class="bi bi-search"></i>
        </button>
        <a href="<?= e($accountLink) ?>" class="nav-icon-btn" aria-label="Account" title="<?= e($accountTitle) ?>">
          <i class="bi bi-person<?= isLoggedIn() ? '-fill text-primary' : '' ?>"></i>
        </a>
        <a href="<?= $depthPrefix ?>customer/wishlist.php" class="nav-icon-btn position-relative" aria-label="Wishlist" title="My Wishlist">
          <i class="bi bi-heart"></i>
          <span id="wishlistCount" class="icon-badge <?= $navWishlistCount === 0 ? 'hidden' : '' ?>"><?= $navWishlistCount ?></span>
        </a>
        <a href="<?= $depthPrefix ?>cart.php" class="nav-icon-btn position-relative" aria-label="Cart" title="Shopping Cart">
          <i class="bi bi-cart3"></i>
          <span id="cartCount" class="icon-badge <?= $navCartCount === 0 ? 'hidden' : '' ?>"><?= $navCartCount ?></span>
        </a>
      </div>
    </div>
  </div>
</nav>

<!-- ===================== SEARCH OVERLAY MODAL ===================== -->
<div id="searchOverlay" class="search-overlay">
  <div class="search-overlay-content">
    <button id="searchClose" class="search-close-btn" aria-label="Close search">
      <i class="bi bi-x-lg"></i>
    </button>
    <h4 class="search-heading">What are you looking for?</h4>
    <form action="<?= $depthPrefix ?>products.php" method="GET" class="search-input-wrapper">
      <i class="bi bi-search"></i>
      <input type="text" name="search" id="searchInput" class="search-input" placeholder="Search by product name, SKU or keyword..." autocomplete="off" required>
    </form>
    <div class="search-suggestions">
      <a href="<?= $depthPrefix ?>products.php?search=Earbuds" class="search-tag text-decoration-none">Earbuds</a>
      <a href="<?= $depthPrefix ?>products.php?search=Watch" class="search-tag text-decoration-none">Watch</a>
      <a href="<?= $depthPrefix ?>products.php?search=Shoes" class="search-tag text-decoration-none">Shoes</a>
      <a href="<?= $depthPrefix ?>products.php?search=Backpack" class="search-tag text-decoration-none">Backpack</a>
      <a href="<?= $depthPrefix ?>products.php?search=Jacket" class="search-tag text-decoration-none">Jacket</a>
    </div>
  </div>
</div>
