/* ============================================================
   EasyShop — Main JavaScript
   Handles all frontend interactions: cart, wishlist, search,
   filtering, newsletter, animations, and UI state management.
   Uses localStorage for temporary state persistence.
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
  'use strict';

  // ==================== STATE MANAGEMENT ====================
  let cart = JSON.parse(localStorage.getItem('easyshop_cart')) || [];
  let wishlist = JSON.parse(localStorage.getItem('easyshop_wishlist')) || [];

  // ==================== DOM REFERENCES ====================
  const announcementBar = document.getElementById('announcementBar');
  const mainNavbar = document.getElementById('mainNavbar');
  const searchOverlay = document.getElementById('searchOverlay');
  const searchInput = document.getElementById('searchInput');
  const searchClose = document.getElementById('searchClose');
  const cartCountEl = document.getElementById('cartCount');
  const cartCountMobileEl = document.getElementById('cartCountMobile');
  const wishlistCountEl = document.getElementById('wishlistCount');
  const backToTopBtn = document.getElementById('backToTop');
  const newsletterForm = document.getElementById('newsletterForm');
  const newsletterEmail = document.getElementById('newsletterEmail');
  const newsletterSuccess = document.getElementById('newsletterSuccess');
  const toastContainer = document.getElementById('toastContainer');
  const featuredProductGrid = document.getElementById('featuredProductGrid');

  // ==================== A. ANNOUNCEMENT BAR ====================
  const initAnnouncementBar = () => {
    if (!announcementBar) return;

    // Check if previously dismissed this session
    if (sessionStorage.getItem('easyshop_announcement_dismissed')) {
      announcementBar.classList.add('dismissed');
      return;
    }

    const closeBtn = announcementBar.querySelector('.announcement-close');
    if (closeBtn) {
      closeBtn.addEventListener('click', () => {
        announcementBar.classList.add('dismissed');
        sessionStorage.setItem('easyshop_announcement_dismissed', 'true');
      });
    }
  };

  // ==================== B. STICKY NAVBAR SCROLL ====================
  const initStickyNavbar = () => {
    if (!mainNavbar) return;

    const handleScroll = () => {
      if (window.scrollY > 50) {
        mainNavbar.classList.add('scrolled');
      } else {
        mainNavbar.classList.remove('scrolled');
      }
    };

    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll(); // Initial check
  };

  // ==================== C. SEARCH OVERLAY ====================
  const initSearchOverlay = () => {
    if (!searchOverlay) return;

    const searchToggles = document.querySelectorAll('.search-toggle');

    const openSearch = () => {
      searchOverlay.classList.add('active');
      document.body.style.overflow = 'hidden';
      // Focus input after transition
      setTimeout(() => {
        if (searchInput) searchInput.focus();
      }, 350);
    };

    const closeSearch = () => {
      searchOverlay.classList.remove('active');
      document.body.style.overflow = '';
      if (searchInput) searchInput.value = '';
    };

    searchToggles.forEach(btn => btn.addEventListener('click', openSearch));

    if (searchClose) {
      searchClose.addEventListener('click', closeSearch);
    }

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && searchOverlay.classList.contains('active')) {
        closeSearch();
      }
    });

    // Close when clicking outside the search content
    searchOverlay.addEventListener('click', (e) => {
      if (e.target === searchOverlay) {
        closeSearch();
      }
    });

    // Search tag clicks populate the input
    const searchTags = searchOverlay.querySelectorAll('.search-tag');
    searchTags.forEach(tag => {
      tag.addEventListener('click', () => {
        if (searchInput) {
          searchInput.value = tag.textContent;
          searchInput.focus();
        }
      });
    });
  };

  // ==================== D. TOAST NOTIFICATION SYSTEM ====================
  const showToast = (message, type = 'info') => {
    if (!toastContainer) return;

    const iconMap = {
      success: 'bi-check-circle-fill',
      info: 'bi-info-circle-fill',
    };

    const toast = document.createElement('div');
    toast.className = `toast-item ${type}`;
    toast.innerHTML = `<i class="bi ${iconMap[type] || iconMap.info}"></i><span>${message}</span>`;

    toastContainer.appendChild(toast);

    // Auto-remove after 2.5 seconds
    setTimeout(() => {
      toast.classList.add('removing');
      setTimeout(() => toast.remove(), 300);
    }, 2500);
  };

  // ==================== E. CART FUNCTIONALITY ====================
  const updateCartCount = () => {
    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);

    [cartCountEl, cartCountMobileEl].forEach(el => {
      if (el) {
        el.textContent = totalItems;
        el.classList.toggle('hidden', totalItems === 0);
      }
    });
  };

  // ==================== E. CART FUNCTIONALITY ====================
  const getCsrfToken = () => {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  };

  const getBaseDepth = () => {
    const meta = document.querySelector('meta[name="base-depth"]');
    return meta ? meta.getAttribute('content') : '';
  };

  const updateCartCount = (count = null) => {
    const totalItems = (count !== null) ? count : cart.reduce((sum, item) => sum + item.quantity, 0);

    [cartCountEl, cartCountMobileEl].forEach(el => {
      if (el) {
        el.textContent = totalItems;
        el.classList.toggle('hidden', totalItems === 0);
      }
    });
  };

  const addToCart = (productId, productName, productPrice, qty = 1, btn = null) => {
    const csrfToken = getCsrfToken();
    const baseDepth = getBaseDepth();

    // If backend CSRF token is available, dispatch to Core PHP action handler
    if (csrfToken && productId) {
      const formData = new FormData();
      formData.append('product_id', productId);
      formData.append('quantity', qty);
      formData.append('csrf_token', csrfToken);
      formData.append('ajax', '1');

      fetch(baseDepth + 'actions/add-to-cart.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.login_required) {
          showToast(data.message, 'error');
          setTimeout(() => {
            window.location.href = baseDepth + 'auth/login.php';
          }, 1200);
          return;
        }

        if (data.success) {
          updateCartCount(data.cart_count);
          showToast(data.message, 'success');

          if (btn) {
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Added!';
            btn.classList.add('added');
            btn.disabled = true;

            setTimeout(() => {
              btn.innerHTML = originalHTML;
              btn.classList.remove('added');
              btn.disabled = false;
            }, 1500);
          }
        } else {
          showToast(data.message, 'error');
        }
      })
      .catch(err => {
        console.error('Cart request error:', err);
        showToast('Unable to add item to cart. Please try again.', 'error');
      });
      return;
    }

    // Fallback for static demo environments
    const existingIndex = cart.findIndex(item => item.id === productId);
    if (existingIndex > -1) {
      cart[existingIndex].quantity += qty;
    } else {
      cart.push({
        id: productId,
        name: productName,
        price: parseInt(productPrice, 10),
        quantity: qty,
      });
    }

    localStorage.setItem('easyshop_cart', JSON.stringify(cart));
    updateCartCount();
    showToast(`${productName} added to cart!`, 'success');
  };

  const initCartButtons = () => {
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('.add-to-cart-btn');
      if (!btn) return;

      // If button is inside an explicit form that submits directly without ajax, allow or enhance
      e.preventDefault();

      const productId = btn.dataset.productId;
      const productName = btn.dataset.productName || 'Product';
      const productPrice = btn.dataset.productPrice || '0';

      const qtyInput = document.getElementById('quantityInput');
      const qty = qtyInput ? (parseInt(qtyInput.value, 10) || 1) : 1;

      addToCart(productId, productName, productPrice, qty, btn);
    });
  };

  // ==================== F. WISHLIST FUNCTIONALITY ====================
  const updateWishlistCount = (count = null) => {
    if (wishlistCountEl) {
      const total = (count !== null) ? count : wishlist.length;
      wishlistCountEl.textContent = total;
      wishlistCountEl.classList.toggle('hidden', total === 0);
    }
  };

  const applyWishlistState = () => {
    document.querySelectorAll('.wishlist-btn').forEach(btn => {
      const productId = btn.dataset.productId;
      const icon = btn.querySelector('i');

      if (wishlist.includes(productId)) {
        btn.classList.add('active');
        if (icon) {
          icon.classList.remove('bi-heart');
          icon.classList.add('bi-heart-fill');
        }
      }
    });
  };

  const initWishlistButtons = () => {
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('.wishlist-btn');
      if (!btn) return;

      // If button has an explicit parent form for removal on the wishlist page, let it submit
      if (btn.closest('form') && btn.closest('form').getAttribute('action')?.includes('remove-from-wishlist.php')) {
        return;
      }

      e.preventDefault();

      const productId = btn.dataset.productId;
      const icon = btn.querySelector('i');
      const csrfToken = getCsrfToken();
      const baseDepth = getBaseDepth();

      // If backend CSRF token is present, toggle via backend endpoint
      if (csrfToken && productId) {
        const isCurrentlyActive = btn.classList.contains('active');
        const endpoint = isCurrentlyActive 
          ? (baseDepth + 'actions/remove-from-wishlist.php') 
          : (baseDepth + 'actions/add-to-wishlist.php');

        const formData = new FormData();
        formData.append('product_id', productId);
        formData.append('csrf_token', csrfToken);
        formData.append('ajax', '1');

        fetch(endpoint, {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: formData
        })
        .then(res => res.json())
        .then(data => {
          if (data.login_required) {
            showToast(data.message, 'error');
            setTimeout(() => {
              window.location.href = baseDepth + 'auth/login.php';
            }, 1200);
            return;
          }

          if (data.success) {
            if (isCurrentlyActive) {
              btn.classList.remove('active');
              if (icon) {
                icon.classList.remove('bi-heart-fill');
                icon.classList.add('bi-heart');
              }
            } else {
              btn.classList.add('active');
              if (icon) {
                icon.classList.remove('bi-heart');
                icon.classList.add('bi-heart-fill');
              }
            }

            if (data.wishlist_count !== undefined) {
              updateWishlistCount(data.wishlist_count);
            }
            showToast(data.message, isCurrentlyActive ? 'info' : 'success');
          } else {
            showToast(data.message, 'error');
          }
        })
        .catch(err => {
          console.error('Wishlist request error:', err);
          showToast('Unable to update wishlist. Please try again.', 'error');
        });
        return;
      }

      // Fallback for static environments
      const wishlistIndex = wishlist.indexOf(productId);
      if (wishlistIndex > -1) {
        wishlist.splice(wishlistIndex, 1);
        btn.classList.remove('active');
        if (icon) {
          icon.classList.remove('bi-heart-fill');
          icon.classList.add('bi-heart');
        }
        showToast('Removed from wishlist', 'info');
      } else {
        wishlist.push(productId);
        btn.classList.add('active');
        if (icon) {
          icon.classList.remove('bi-heart');
          icon.classList.add('bi-heart-fill');
        }
        showToast('Added to wishlist!', 'success');
      }

      localStorage.setItem('easyshop_wishlist', JSON.stringify(wishlist));
      updateWishlistCount();
    });
  };

  // ==================== G. PRODUCT CATEGORY FILTERING ====================
  const initProductFiltering = () => {
    const filterBtns = document.querySelectorAll('.filter-btn');
    if (!filterBtns.length || !featuredProductGrid) return;

    const productItems = featuredProductGrid.querySelectorAll('.product-item');

    filterBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        // Update active button
        filterBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        const category = btn.dataset.category;

        productItems.forEach(item => {
          const itemCategory = item.dataset.category;
          const shouldShow = category === 'all' || itemCategory === category;

          if (shouldShow) {
            item.classList.remove('hidden');
            item.classList.add('visible');
            item.style.display = '';
          } else {
            item.classList.add('hidden');
            item.classList.remove('visible');
            // Delay display:none to allow transition
            setTimeout(() => {
              if (item.classList.contains('hidden')) {
                item.style.display = 'none';
              }
            }, 400);
          }
        });
      });
    });
  };

  // ==================== H. CATEGORY CARD NAVIGATION ====================
  const initCategoryCards = () => {
    const categoryCards = document.querySelectorAll('.category-card');

    // Map category card names to filter values
    const categoryMap = {
      electronics: 'electronics',
      fashion: 'fashion',
      home: 'home',
      beauty: 'beauty',
      sports: 'sports',
      accessories: 'accessories',
    };

    categoryCards.forEach(card => {
      card.addEventListener('click', () => {
        const category = card.dataset.category;
        if (!category) return;

        // Scroll to featured products section
        const featuredSection = document.getElementById('featuredProducts');
        if (featuredSection) {
          featuredSection.scrollIntoView({ behavior: 'smooth' });

          // After scroll completes, activate the corresponding filter
          setTimeout(() => {
            const filterValue = categoryMap[category] || 'all';
            const targetFilter = document.querySelector(`.filter-btn[data-category="${filterValue}"]`);
            if (targetFilter) {
              targetFilter.click();
            }
          }, 600);
        }
      });
    });
  };

  // ==================== I. NEWSLETTER FORM ====================
  const initNewsletter = () => {
    if (!newsletterForm) return;

    // Check if already subscribed
    const existingEmail = localStorage.getItem('easyshop_newsletter');
    if (existingEmail && newsletterSuccess) {
      newsletterForm.style.display = 'none';
      newsletterSuccess.style.display = 'block';
      return;
    }

    newsletterForm.addEventListener('submit', (e) => {
      e.preventDefault();

      const email = newsletterEmail ? newsletterEmail.value.trim() : '';
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

      if (!emailRegex.test(email)) {
        if (newsletterEmail) {
          newsletterEmail.classList.add('is-invalid');
        }
        const errorEl = document.getElementById('emailError');
        if (errorEl) errorEl.style.display = 'block';
        return;
      }

      // Valid email: save and show success
      if (newsletterEmail) {
        newsletterEmail.classList.remove('is-invalid');
      }

      localStorage.setItem('easyshop_newsletter', email);
      newsletterForm.style.display = 'none';
      if (newsletterSuccess) {
        newsletterSuccess.style.display = 'block';
      }
      showToast('Successfully subscribed!', 'success');
    });

    // Remove invalid state on input
    if (newsletterEmail) {
      newsletterEmail.addEventListener('input', () => {
        newsletterEmail.classList.remove('is-invalid');
        const errorEl = document.getElementById('emailError');
        if (errorEl) errorEl.style.display = 'none';
      });
    }
  };

  // ==================== J. BACK TO TOP BUTTON ====================
  const initBackToTop = () => {
    if (!backToTopBtn) return;

    const handleScroll = () => {
      if (window.scrollY > 500) {
        backToTopBtn.classList.add('visible');
      } else {
        backToTopBtn.classList.remove('visible');
      }
    };

    window.addEventListener('scroll', handleScroll, { passive: true });

    backToTopBtn.addEventListener('click', () => {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  };

  // ==================== K. SMOOTH SCROLL FOR ANCHOR LINKS ====================
  const initSmoothScroll = () => {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', (e) => {
        const targetId = anchor.getAttribute('href');
        if (targetId === '#') return;

        const targetEl = document.querySelector(targetId);
        if (targetEl) {
          e.preventDefault();

          // Close mobile nav if open
          const navbarCollapse = document.getElementById('navbarContent');
          if (navbarCollapse && navbarCollapse.classList.contains('show')) {
            const bsCollapse = bootstrap.Collapse.getInstance(navbarCollapse);
            if (bsCollapse) bsCollapse.hide();
          }

          const navHeight = mainNavbar ? mainNavbar.offsetHeight : 0;
          const targetPosition = targetEl.getBoundingClientRect().top + window.scrollY - navHeight - 10;

          window.scrollTo({
            top: targetPosition,
            behavior: 'smooth',
          });
        }
      });
    });
  };

  // ==================== L. INTERSECTION OBSERVER ANIMATIONS ====================
  const initScrollAnimations = () => {
    // Add fade-in-up class to elements that should animate
    const animateTargets = document.querySelectorAll(
      '.category-card, .product-card, .benefit-card, .section-header'
    );

    animateTargets.forEach(el => el.classList.add('fade-in-up'));

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.classList.add('visible');
            observer.unobserve(entry.target);
          }
        });
      },
      {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px',
      }
    );

    animateTargets.forEach(el => observer.observe(el));
  };

  // ==================== M. DROPDOWN CATEGORY NAVIGATION ====================
  const initDropdownCategories = () => {
    const dropdownItems = document.querySelectorAll('.dropdown-item[data-category]');

    dropdownItems.forEach(item => {
      item.addEventListener('click', (e) => {
        e.preventDefault();
        const category = item.dataset.category;

        // Scroll to featured products
        const featuredSection = document.getElementById('featuredProducts');
        if (featuredSection) {
          featuredSection.scrollIntoView({ behavior: 'smooth' });

          setTimeout(() => {
            const targetFilter = document.querySelector(`.filter-btn[data-category="${category}"]`);
            if (targetFilter) {
              targetFilter.click();
            }
          }, 600);
        }
      });
    });
  };

  // ==================== INITIALIZE EVERYTHING ====================
  const init = () => {
    initAnnouncementBar();
    initStickyNavbar();
    initSearchOverlay();
    initCartButtons();
    initWishlistButtons();
    initProductFiltering();
    initCategoryCards();
    initNewsletter();
    initBackToTop();
    initSmoothScroll();
    initScrollAnimations();
    initDropdownCategories();

    // Apply saved state
    updateCartCount();
    updateWishlistCount();
    applyWishlistState();
  };

  init();
});
