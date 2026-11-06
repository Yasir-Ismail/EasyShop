<?php
/**
 * EasyShop — Customer Storefront Header
 * 
 * Provides HTML5 doctype, <head> tags, fonts, styles, and announcement bar.
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/csrf.php';

$pageTitle = $pageTitle ?? 'EasyShop — Everything You Need, All in One Place';
$pageDesc  = $pageDesc  ?? 'EasyShop — Premium e-commerce shopping in Pakistan. Electronics, fashion, home & living, beauty and more.';
$depthPrefix = $depthPrefix ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= e($pageDesc) ?>">
  <meta name="csrf-token" content="<?= e(get_csrf_token()) ?>">
  <meta name="base-depth" content="<?= e($depthPrefix) ?>">
  <title><?= e($pageTitle) ?></title>

  <!-- Google Fonts: Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

  <!-- Bootstrap 5.3 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

  <!-- EasyShop Custom CSS -->
  <link rel="stylesheet" href="<?= $depthPrefix ?>assets/css/style.css">
</head>
<body>

  <!-- ===================== A. ANNOUNCEMENT BAR ===================== -->
  <div id="announcementBar" class="announcement-bar">
    <div class="container">
      <p class="announcement-text">
        <i class="bi bi-truck"></i>
        FLAT RS. 200 DELIVERY ACROSS PAKISTAN • CASH ON DELIVERY AVAILABLE
      </p>
      <button class="announcement-close" aria-label="Close announcement">
        <i class="bi bi-x"></i>
      </button>
    </div>
  </div>
