<?php
/**
 * EasyShop — Admin Header Layout Component
 * 
 * Guards route with requireAdmin(), loads styles, and renders admin topbar.
 */

// Calculate depth back to project root
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

require_once ROOT_PATH . '/includes/admin-check.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/includes/csrf.php';

$admin = currentUser();
$pageTitle = $pageTitle ?? 'Admin Portal — EasyShop';
$activeNav = $activeNav ?? 'dashboard';

// Compute relative path prefix to root for links (e.g. '../' or '../../')
$depthPrefix = $depthPrefix ?? '../';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?></title>

  <!-- Google Fonts & Bootstrap 5 -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= $depthPrefix ?>assets/css/style.css">

  <style>
    body {
      background-color: var(--color-bg-secondary);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }
    .admin-navbar {
      background-color: var(--color-primary);
      color: var(--color-white);
      border-bottom: 1px solid rgba(255,255,255,0.08);
      z-index: 1030;
    }
    .admin-layout {
      flex: 1;
      display: flex;
    }
    .admin-sidebar {
      width: 250px;
      background-color: #FFFFFF;
      border-right: 1px solid var(--color-border);
      flex-shrink: 0;
      padding: 1.5rem 0;
    }
    .admin-nav-link {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.75rem 1.5rem;
      color: var(--color-text-muted);
      font-weight: 500;
      font-size: 0.92rem;
      transition: all var(--transition-fast);
      border-left: 3px solid transparent;
      text-decoration: none;
    }
    .admin-nav-link:hover {
      background-color: var(--color-bg-secondary);
      color: var(--color-primary);
    }
    .admin-nav-link.active {
      background-color: var(--color-accent-light);
      color: var(--color-accent);
      border-left-color: var(--color-accent);
      font-weight: 600;
    }
    .admin-nav-link i {
      font-size: 1.15rem;
    }
    .admin-main {
      flex: 1;
      padding: 2rem 2.5rem;
      overflow-x: auto;
    }
    .admin-card {
      background: #FFFFFF;
      border-radius: var(--radius-lg);
      border: 1px solid var(--color-border);
      box-shadow: var(--shadow-sm);
    }
    .badge-status-active {
      background-color: #D1FAE5;
      color: #065F46;
      font-weight: 600;
      padding: 0.35rem 0.65rem;
      border-radius: 20px;
    }
    .badge-status-inactive {
      background-color: #FEE2E2;
      color: #991B1B;
      font-weight: 600;
      padding: 0.35rem 0.65rem;
      border-radius: 20px;
    }
    .product-thumb-sm {
      width: 48px;
      height: 48px;
      object-fit: contain;
      border-radius: var(--radius-sm);
      background-color: var(--color-bg-secondary);
      border: 1px solid var(--color-border);
      padding: 2px;
    }
    @media (max-width: 991.98px) {
      .admin-layout {
        flex-direction: column;
      }
      .admin-sidebar {
        width: 100%;
        border-right: none;
        border-bottom: 1px solid var(--color-border);
        padding: 0.5rem 0;
      }
      .admin-sidebar .nav {
        flex-direction: row;
        overflow-x: auto;
        padding: 0 1rem;
      }
      .admin-nav-link {
        padding: 0.5rem 0.85rem;
        border-left: none;
        border-bottom: 2px solid transparent;
        white-space: nowrap;
      }
      .admin-nav-link.active {
        border-left-color: transparent;
        border-bottom-color: var(--color-accent);
      }
      .admin-main {
        padding: 1.5rem 1rem;
      }
    }
  </style>
</head>
<body>

  <!-- Top Navigation -->
  <nav class="navbar navbar-expand-lg admin-navbar sticky-top">
    <div class="container-fluid px-3 px-lg-4">
      <a class="navbar-brand text-white d-flex align-items-center" href="<?= $depthPrefix ?>admin/index.php">
        <span class="text-white fw-bold fs-5">Easy</span><span class="text-info fw-bold fs-5">Shop</span>
        <span class="badge bg-danger ms-2 text-uppercase font-monospace" style="font-size: 0.65rem;">Admin</span>
      </a>

      <div class="d-flex align-items-center gap-3">
        <a href="<?= $depthPrefix ?>index.html" target="_blank" class="btn btn-outline-light btn-sm d-none d-sm-inline-flex align-items-center">
          <i class="bi bi-box-arrow-up-right me-1"></i> Public Store
        </a>
        <div class="text-white-50 small d-none d-md-inline">
          Hello, <strong class="text-white"><?= e($admin['name']) ?></strong>
        </div>
        <a href="<?= $depthPrefix ?>auth/logout.php" class="btn btn-outline-danger btn-sm text-white border-danger-subtle">
          <i class="bi bi-box-arrow-right me-1"></i> Logout
        </a>
      </div>
    </div>
  </nav>

  <!-- Admin Body Layout with Sidebar -->
  <div class="admin-layout">
