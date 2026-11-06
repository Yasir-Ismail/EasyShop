<?php
/**
 * EasyShop — Customer Storefront Footer Component
 */

$depthPrefix = $depthPrefix ?? '';

// Fetch a few active categories for quick footer links
$footerCategories = [];
if (isset($conn) && $conn) {
    if ($fRes = mysqli_query($conn, "SELECT category_id, category_name FROM categories WHERE status = 'active' LIMIT 5")) {
        while ($fc = mysqli_fetch_assoc($fRes)) {
            $footerCategories[] = $fc;
        }
        mysqli_free_result($fRes);
    }
}
?>

  <!-- ===================== J. FOOTER ===================== -->
  <footer id="footer" class="footer-section">
    <div class="container">
      <div class="row g-4 g-lg-5">
        <!-- Column 1: Brand Info -->
        <div class="col-lg-3 col-md-6">
          <a href="<?= $depthPrefix ?>index.php" class="footer-brand">
            <span class="brand-easy">Easy</span><span class="brand-shop">Shop</span>
          </a>
          <p class="footer-desc">
            Your one-stop shop for quality products in Pakistan. We bring you authentic deals, secure shopping, and fast nationwide delivery.
          </p>
          <div class="footer-social">
            <a href="#" class="social-link" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
            <a href="#" class="social-link" aria-label="Twitter"><i class="bi bi-twitter-x"></i></a>
            <a href="#" class="social-link" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
            <a href="#" class="social-link" aria-label="YouTube"><i class="bi bi-youtube"></i></a>
          </div>
        </div>

        <!-- Column 2: Quick Links -->
        <div class="col-lg-3 col-md-6">
          <h5 class="footer-heading">Quick Links</h5>
          <ul class="footer-links">
            <li><a href="<?= $depthPrefix ?>index.php">Home</a></li>
            <li><a href="<?= $depthPrefix ?>products.php">Shop All Products</a></li>
            <?php foreach ($footerCategories as $fCat): ?>
              <li>
                <a href="<?= $depthPrefix ?>products.php?category_id=<?= (int)$fCat['category_id'] ?>">
                  <?= e($fCat['category_name']) ?>
                </a>
              </li>
            <?php endforeach; ?>
            <li><a href="<?= $depthPrefix ?>index.php#whyChoose">About EasyShop</a></li>
          </ul>
        </div>

        <!-- Column 3: Customer Service -->
        <div class="col-lg-3 col-md-6">
          <h5 class="footer-heading">Customer Service</h5>
          <ul class="footer-links">
            <li><a href="<?= $depthPrefix ?>auth/login.php">My Account</a></li>
            <li><a href="<?= $depthPrefix ?>customer/dashboard.php">Order Tracking</a></li>
            <li><a href="#">Returns &amp; Refunds</a></li>
            <li><a href="#">Shipping Policy</a></li>
            <li><a href="#">Privacy &amp; Terms</a></li>
          </ul>
        </div>

        <!-- Column 4: Contact Info (Pakistan) -->
        <div class="col-lg-3 col-md-6">
          <h5 class="footer-heading">Contact Us</h5>
          <ul class="footer-contact">
            <li>
              <i class="bi bi-geo-alt"></i>
              <span>123 Main Boulevard, Gulberg III, Lahore, Pakistan</span>
            </li>
            <li>
              <i class="bi bi-telephone"></i>
              <span>+92 300 1234567</span>
            </li>
            <li>
              <i class="bi bi-envelope"></i>
              <span>support@easyshop.pk</span>
            </li>
            <li>
              <i class="bi bi-clock"></i>
              <span>Mon – Sat: 9:00 AM – 9:00 PM</span>
            </li>
          </ul>
        </div>
      </div>

      <!-- Footer Bottom Bar -->
      <div class="footer-bottom">
        <div class="row align-items-center">
          <div class="col-md-6">
            <p class="footer-copyright">&copy; <?= date('Y') ?> EasyShop. All rights reserved. Pakistan's Premium Store.</p>
          </div>
          <div class="col-md-6 text-md-end">
            <div class="footer-payment">
              <span>Payment Methods:</span>
              <span class="badge bg-light text-dark border ms-1">Cash on Delivery (COD)</span>
              <i class="bi bi-cash-stack ms-2"></i>
            </div>
          </div>
        </div>
      </div>
    </div>
  </footer>

  <!-- ===================== BACK TO TOP BUTTON ===================== -->
  <button id="backToTop" class="back-to-top" aria-label="Back to top">
    <i class="bi bi-arrow-up"></i>
  </button>

  <!-- Toast Notification Container -->
  <div id="toastContainer" class="toast-container"></div>

  <!-- Bootstrap 5 Bundle JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <!-- EasyShop Main JavaScript -->
  <script src="<?= $depthPrefix ?>assets/js/main.js"></script>
</body>
</html>
