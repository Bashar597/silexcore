/* =============================================================
   SILEXCORE TECHNOLOGIES — Main JavaScript
   Cart logic · Header behavior · Mobile nav · Animations
   ============================================================= */

(function () {
  'use strict';

  /* ----- Product catalog (single source of truth) ----- */
  const PRODUCTS = {
    'conductive-paint': {
      id: 'conductive-paint',
      sku: 'SC-650CP',
      name: 'Conductive Paint',
      tagline: 'Draw circuits. Repair PCBs.',
      image: 'assets/img/products/conductive-paint-main.webp',
      basePrice: 14.49,
      spec: '0.1–0.5 Ω/sq',
      variants: [
        { id: '20ml', label: 'Pack of 2 (10 ml each)', price: 14.99, image: 'assets/img/products/conductive-paint-pen.jpg' },
        { id: '1oz',  label: '1 oz Jar (30 ml)',       price: 14.49 },
        { id: '2oz',  label: '2 oz Jar (60 ml)',       price: 24.99, image: 'assets/img/products/conductive-paint-2oz.png' },
        { id: '4oz',  label: '4 oz Jar (120 ml)',      price: 44.98, image: 'assets/img/products/conductive-paint-4oz.png' }
      ]
    },
    'premium-conductive-paint': {
      id: 'premium-conductive-paint',
      sku: 'SC-700PCP',
      name: 'Premium Conductive Paint',
      tagline: 'Water-resistant. Indoor / outdoor.',
      image: 'assets/img/products/premium-paint-main.webp',
      basePrice: 16.99,
      spec: '54 dB shielding',
      variants: [
        { id: '1oz', label: '1 oz Jar (30 ml)', price: 16.99 }
      ]
    },
    'conductive-silicone': {
      id: 'conductive-silicone',
      sku: 'SC-110CS',
      name: 'Conductive Silicone',
      tagline: 'Flexible. Waterproof. Solder-free.',
      image: 'assets/img/products/Conductive-Silicone-1.jpg',
      basePrice: 14.78,
      spec: '5–35 Ω/sq',
      variants: [
        { id: '1oz', label: '1 oz Tube (30 ml)', price: 14.78 }
      ]
    },
    'conductive-glue': {
      id: 'conductive-glue',
      sku: 'SC-301CG',
      name: 'Conductive Glue',
      tagline: 'Repair electronics without soldering.',
      image: 'assets/img/products/Conductive-Glue-1.jpg',
      basePrice: 14.49,
      spec: '0.1–0.8 Ω through-glue',
      variants: [
        { id: '1oz', label: '1 oz Bottle (30 ml)', price: 14.49 }
      ]
    }
  };

  // Expose for product page scripts
  window.SILEXCORE_PRODUCTS = PRODUCTS;

  /* ----- Cart state (localStorage + server sync when logged in) ----- */
  const CART_KEY = 'silexcore_cart_v1';
  let _cartLoggedIn = false; // set after session check

  function loadCart() {
    try {
      const raw = localStorage.getItem(CART_KEY);
      return raw ? JSON.parse(raw) : [];
    } catch (e) {
      return [];
    }
  }

  function saveCart(cart) {
    try { localStorage.setItem(CART_KEY, JSON.stringify(cart)); }
    catch (e) { /* no-op */ }
    if (_cartLoggedIn) syncCartToServer(cart);
  }

  function syncCartToServer(cart) {
    const body = new FormData();
    body.append('action', 'sync');
    body.append('items', JSON.stringify(cart));
    fetch('api/cart.php', { method: 'POST', body }).catch(() => {});
  }

  // Called on login: load server cart, merge with localStorage, save merged version.
  function loadAndMergeServerCart() {
    fetch('api/cart.php')
      .then(r => r.json())
      .then(d => {
        if (!d.items || !d.items.length) return;
        const local = loadCart();
        d.items.forEach(si => {
          if (!local.find(i => i.key === si.product_key)) {
            // Reconstruct from server row
            const parts = si.product_key.split('|');
            local.push({
              key:          si.product_key,
              productId:    parts[0] || '',
              variantId:    parts[1] || '',
              name:         si.product_name,
              variantLabel: si.variant,
              price:        parseFloat(si.price),
              image:        si.image,
              qty:          parseInt(si.qty, 10)
            });
          }
        });
        saveCart(local);
        renderCart();
      })
      .catch(() => {});
  }

  function getCart() { return loadCart(); }

  function cartCount() {
    return loadCart().reduce((sum, item) => sum + item.qty, 0);
  }
  function cartTotal() {
    return loadCart().reduce((sum, item) => sum + item.qty * item.price, 0);
  }

  function addToCart(productId, variantId, qty = 1) {
    const product = PRODUCTS[productId];
    if (!product) return;
    const variant = product.variants.find(v => v.id === variantId) || product.variants[0];
    const cart = loadCart();
    const key = `${productId}|${variant.id}`;
    const existing = cart.find(i => i.key === key);
    if (existing) {
      existing.qty += qty;
    } else {
      cart.push({
        key,
        productId,
        variantId: variant.id,
        name: product.name,
        variantLabel: variant.label,
        price: variant.price,
        image: variant.image || product.image,
        qty
      });
    }
    saveCart(cart);
    renderCart();
    openCart();
    pulseCartButton();
  }

  function updateQty(key, delta) {
    const cart = loadCart();
    const item = cart.find(i => i.key === key);
    if (!item) return;
    item.qty += delta;
    if (item.qty <= 0) {
      saveCart(cart.filter(i => i.key !== key));
    } else {
      saveCart(cart);
    }
    renderCart();
  }
  function removeItem(key) {
    saveCart(loadCart().filter(i => i.key !== key));
    renderCart();
  }

  // Expose cart actions for inline handlers and product pages
  window.SilexCart = { addToCart, getCart, cartCount, cartTotal, updateQty, removeItem };

  function pulseCartButton() {
    const btn = document.querySelector('.cart-button');
    if (!btn) return;
    btn.animate(
      [
        { transform: 'scale(1)' },
        { transform: 'scale(1.15)', offset: 0.5 },
        { transform: 'scale(1)' }
      ],
      { duration: 400, easing: 'cubic-bezier(0.2, 0.8, 0.2, 1)' }
    );
  }

  function renderCart() {
    const cart = loadCart();
    const count = cartCount();
    // Header count
    document.querySelectorAll('.cart-count').forEach(el => el.textContent = count);
    document.querySelectorAll('.cart-button').forEach(btn => {
      btn.classList.toggle('has-items', count > 0);
    });

    const body = document.querySelector('.cart-body');
    const footer = document.querySelector('.cart-footer');
    if (!body) return;

    if (cart.length === 0) {
      body.innerHTML = `
        <div class="cart-empty">
          <p>Your cart is empty</p>
          <a href="shop.html" class="btn btn-ghost">Browse Products <span class="arrow">→</span></a>
        </div>`;
      if (footer) footer.style.display = 'none';
      return;
    }

    if (footer) footer.style.display = 'block';

    body.innerHTML = cart.map(item => `
      <div class="cart-item" data-key="${item.key}">
        <img class="cart-item-img" src="${item.image}" alt="${item.name}" onerror="this.style.background='#1c1c1c';this.src='data:image/svg+xml;utf8,<svg xmlns=&quot;http://www.w3.org/2000/svg&quot; viewBox=&quot;0 0 80 80&quot;><rect width=&quot;80&quot; height=&quot;80&quot; fill=&quot;%231c1c1c&quot;/></svg>'">
        <div class="cart-item-info">
          <h5>${item.name}</h5>
          <div class="variant">${item.variantLabel}</div>
          <div class="cart-qty">
            <button onclick="SilexCart.updateQty('${item.key}', -1)" aria-label="Decrease quantity">−</button>
            <span class="qty-val">${item.qty}</span>
            <button onclick="SilexCart.updateQty('${item.key}', 1)" aria-label="Increase quantity">+</button>
          </div>
        </div>
        <div>
          <div class="cart-item-price">$${(item.price * item.qty).toFixed(2)}</div>
          <a class="cart-item-remove" href="#" onclick="event.preventDefault();SilexCart.removeItem('${item.key}')">Remove</a>
        </div>
      </div>
    `).join('');

    const totalEl = document.querySelector('.cart-total .amount');
    if (totalEl) totalEl.textContent = '$' + cartTotal().toFixed(2);
  }

  function openCart() {
    document.querySelector('.cart-overlay')?.classList.add('open');
    document.querySelector('.cart-drawer')?.classList.add('open');
    document.body.style.overflow = 'hidden';
  }
  function closeCart() {
    document.querySelector('.cart-overlay')?.classList.remove('open');
    document.querySelector('.cart-drawer')?.classList.remove('open');
    document.body.style.overflow = '';
  }
  window.SilexCart.openCart = openCart;
  window.SilexCart.closeCart = closeCart;

  /* ----- Header scroll behavior (hide on scroll down, show on up) ----- */
  let lastScroll = 0;
  function onScroll() {
    const header = document.querySelector('.site-header');
    if (!header) return;
    const cur = window.scrollY;
    if (cur > 200 && cur > lastScroll) {
      header.classList.add('hidden');
    } else {
      header.classList.remove('hidden');
    }
    lastScroll = cur;

    // Back to top
    const btt = document.querySelector('.back-to-top');
    if (btt) btt.classList.toggle('show', cur > 600);
  }

  /* ----- Mobile nav ----- */
  function setupMobileNav() {
    const toggle = document.querySelector('.mobile-toggle');
    const nav = document.querySelector('.mobile-nav');
    if (!toggle || !nav) return;
    toggle.addEventListener('click', () => {
      toggle.classList.toggle('open');
      nav.classList.toggle('open');
      document.body.style.overflow = nav.classList.contains('open') ? 'hidden' : '';
    });
    nav.querySelectorAll('a').forEach(a => {
      a.addEventListener('click', () => {
        toggle.classList.remove('open');
        nav.classList.remove('open');
        document.body.style.overflow = '';
      });
    });
  }

  /* ----- Cart drawer wiring ----- */
  function setupCart() {
    document.querySelectorAll('.cart-button').forEach(btn => {
      btn.addEventListener('click', e => {
        e.preventDefault();
        openCart();
      });
    });
    document.querySelector('.cart-overlay')?.addEventListener('click', closeCart);
    document.querySelector('.cart-close')?.addEventListener('click', closeCart);
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') closeCart();
    });
    renderCart();
  }

  /* ----- Fade up on scroll ----- */
  function setupFadeUp() {
    if (!('IntersectionObserver' in window)) {
      document.querySelectorAll('.fade-up').forEach(el => el.classList.add('in'));
      return;
    }
    const io = new IntersectionObserver(entries => {
      entries.forEach(e => {
        if (e.isIntersecting) {
          e.target.classList.add('in');
          io.unobserve(e.target);
        }
      });
    }, { threshold: 0.1, rootMargin: '0px 0px -60px 0px' });
    document.querySelectorAll('.fade-up').forEach(el => io.observe(el));
  }

  /* ----- Newsletter form (stub) ----- */
  function setupNewsletter() {
    document.querySelectorAll('.newsletter-form').forEach(form => {
      form.addEventListener('submit', e => {
        e.preventDefault();
        const input = form.querySelector('input[type="email"]');
        if (!input || !input.value) return;
        const button = form.querySelector('button');
        const original = button.textContent;
        button.textContent = 'Subscribed ✓';
        button.style.background = 'var(--gold-warm)';
        button.style.color = 'var(--black)';
        input.value = '';
        setTimeout(() => {
          button.textContent = original;
          button.style.background = '';
          button.style.color = '';
        }, 3000);
      });
    });
  }

  /* ----- Back to top ----- */
  function setupBackToTop() {
    document.querySelector('.back-to-top')?.addEventListener('click', e => {
      e.preventDefault();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  /* ----- Gallery zoom / lightbox ----- */
  function setupGalleryZoom() {
    const mainImgs = document.querySelectorAll('.gallery-main img');
    if (!mainImgs.length) return;

    const lb = document.createElement('div');
    lb.id = 'gallery-lightbox';
    lb.innerHTML = '<button class="lb-close" aria-label="Close">&times;</button><img alt="" />';
    document.body.appendChild(lb);

    const lbImg = lb.querySelector('img');

    function openLb(src) {
      lbImg.src = src;
      lb.classList.add('open');
      document.body.style.overflow = 'hidden';
    }
    function closeLb() {
      lb.classList.remove('open');
      document.body.style.overflow = '';
    }

    mainImgs.forEach(function (img) {
      img.addEventListener('click', function () { openLb(img.src); });
    });

    lb.addEventListener('click', function (e) {
      if (e.target === lb || e.target.classList.contains('lb-close')) closeLb();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeLb();
    });
  }

  /* ----- Account header dropdown ----- */
  function setupAccountNav() {
    const btn = document.querySelector('.account-nav-btn');

    fetch('api/session.php')
      .then(r => r.json())
      .then(s => {
        if (!s.logged_in) return;

        // Enable server-side cart sync and merge any saved items
        _cartLoggedIn = true;
        loadAndMergeServerCart();

        if (!btn) return;

        const first = s.first_name || '';
        const last  = s.last_name  || '';
        const initials = (first.charAt(0) + last.charAt(0)).toUpperCase();

        btn.innerHTML = `
          <span class="account-initials" aria-hidden="true">${initials}</span>
          <div class="account-dropdown" role="menu">
            <div class="account-dropdown-header">
              <div class="account-dropdown-name">${first}</div>
              <div class="account-dropdown-email">${s.email || ''}</div>
            </div>
            <a href="account.html" role="menuitem">My Account</a>
            <a href="orders.html" role="menuitem">Order History</a>
            ${s.is_admin ? '<a href="admin.php" role="menuitem" style="color:var(--gold-warm);font-weight:600;">Dashboard</a>' : ''}
            <button class="account-dropdown-link dropdown-signout" role="menuitem" id="header-signout">Sign Out</button>
          </div>`;

        btn.removeAttribute('href');
        btn.setAttribute('role', 'button');
        btn.setAttribute('tabindex', '0');
        // Inject once — kills the sliding underline on the account button
        if (!document.getElementById('acct-btn-fix')) {
          const s = document.createElement('style');
          s.id = 'acct-btn-fix';
          s.textContent = '.account-nav-btn::after{display:none!important}';
          document.head.appendChild(s);
        }

        // Strip leftover CSS background shadow the server still has cached
        btn.style.setProperty('background', 'none', 'important');
        btn.style.setProperty('padding', '0', 'important');
        btn.style.setProperty('gap', '0', 'important');

        const bubble = btn.querySelector('.account-initials');
        if (bubble) {
          bubble.style.transition = 'box-shadow 0.3s ease';
          bubble.style.boxShadow = '0 0 0 2px #fff, 0 0 0 5px var(--gold-warm)';
        }
        btn.addEventListener('mouseenter', function () {
          if (bubble) bubble.style.boxShadow = '0 0 0 6px var(--gold-warm)';
        });
        btn.addEventListener('mouseleave', function () {
          if (bubble) bubble.style.boxShadow = '0 0 0 2px #fff, 0 0 0 5px var(--gold-warm)';
        });

        // Toggle dropdown on click
        btn.addEventListener('click', function (e) {
          if (e.target.closest('.account-dropdown')) return;
          btn.classList.toggle('open');
        });
        btn.addEventListener('keydown', function (e) {
          if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); btn.classList.toggle('open'); }
          if (e.key === 'Escape') btn.classList.remove('open');
        });
        document.addEventListener('click', function (e) {
          if (!btn.contains(e.target)) btn.classList.remove('open');
        });

        document.getElementById('header-signout')?.addEventListener('click', function () {
          fetch('api/logout.php', { method: 'POST' }).finally(function () {
            window.location.href = 'account.html';
          });
        });
      })
      .catch(() => {});
  }

  /* ----- Password visibility toggle (eye icon) ----- */
  function setupPasswordToggles() {
    // Inject shared CSS once
    if (!document.getElementById('pw-toggle-style')) {
      var s = document.createElement('style');
      s.id = 'pw-toggle-style';
      s.textContent = '.pw-wrap{position:relative;}.pw-wrap input{padding-right:44px!important;width:100%;box-sizing:border-box;}.pw-eye{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:2px;color:var(--t-text-muted,#aaa);line-height:0;}.pw-eye:hover{color:var(--gold-warm,#d4a017);}';
      document.head.appendChild(s);
    }

    document.querySelectorAll('input[type="password"]').forEach(function(input) {
      if (input.closest('.pw-wrap')) return; // already wrapped
      var parent = input.parentNode;
      var wrap = document.createElement('div');
      wrap.className = 'pw-wrap';
      parent.insertBefore(wrap, input);
      wrap.appendChild(input);

      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'pw-eye';
      btn.setAttribute('aria-label', 'Show/hide password');
      btn.innerHTML = '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
      wrap.appendChild(btn);

      btn.addEventListener('click', function(e) {
        e.preventDefault();
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.style.color = show ? 'var(--gold-warm,#d4a017)' : '';
      });

      // Fix Chrome autofill: briefly make readonly then release
      input.setAttribute('readonly', true);
      input.addEventListener('focus', function() { this.removeAttribute('readonly'); });
    });
  }

  /* ----- Init ----- */
  document.addEventListener('DOMContentLoaded', () => {
    setupMobileNav();
    setupCart();
    setupFadeUp();
    setupNewsletter();
    setupBackToTop();
    setupGalleryZoom();
    setupAccountNav();
    setupPasswordToggles();
    window.addEventListener('scroll', onScroll, { passive: true });
  });
})();
