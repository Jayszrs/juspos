<?php
// public/index.php - Final refined POS UI
if (session_status() === PHP_SESSION_NONE) session_start();

// redirect if not authenticated
if (empty($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}

// display-safe user info
$currentUser = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : null;
$currentRole = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
$displayName = $currentUser ? htmlspecialchars($currentUser, ENT_QUOTES, 'UTF-8') : '';
$displayRole = $currentRole ? htmlspecialchars($currentRole, ENT_QUOTES, 'UTF-8') : '';
?>

<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>POS Toko Jus — Kasir</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    /* ========== Theme vars (keuntungan: mudah disesuaikan) ========== */
    :root {
      --accent-900: #0f172a;
      /* slate-900-ish (primary) */
      --accent-800: #0b1220;
      --muted-600: rgba(15, 23, 42, 0.06);
      --soft-shadow: 0 6px 18px rgba(2, 6, 23, 0.06);
      --card-shadow: 0 4px 14px rgba(2, 6, 23, 0.04);
      --glass-bg: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(255, 255, 255, 0.96));
      --focus-ring: 0 0 0 4px rgba(15, 23, 42, 0.06);
      --transition-fast: 120ms;
      --transition-medium: 180ms;
    }

    /* ========== Small layout tweaks kept from original ========== */
    .modal-backdrop {
      background: rgba(2, 6, 23, 0.45);
    }

    .modal-panel {
      transform: translateY(12px);
      transition: transform var(--transition-medium) ease, opacity var(--transition-medium) ease;
      opacity: 0;
      z-index: 999;
      outline: none;
      will-change: transform, opacity;
    }

    .modal-show .modal-panel {
      transform: translateY(0);
      opacity: 1;
    }

    .glass {
      background: var(--glass-bg);
    }

    .thin-scroll::-webkit-scrollbar {
      height: 8px;
      width: 8px;
    }

    .thin-scroll::-webkit-scrollbar-thumb {
      background: rgba(0, 0, 0, 0.12);
      border-radius: 999px;
    }

    /* ========== Buttons: global behaviour (gentle interaction) ========== */
    button {
      transition: transform var(--transition-fast) ease, box-shadow var(--transition-fast) ease, background-color var(--transition-fast) ease, border-color var(--transition-fast) ease;
      cursor: pointer;
      -webkit-tap-highlight-color: transparent;
    }

    button:active {
      transform: translateY(1px) scale(.998);
    }

    /* keyboard focus (accessible) */
    button:focus-visible,
    a:focus-visible,
    input:focus-visible,
    textarea:focus-visible {
      box-shadow: var(--focus-ring);
      outline: none;
      border-radius: 0.5rem;
    }

    /* primary accent buttons (keeps your existing .bg-slate-900 text-white but improves hover) */
    .bg-slate-900 {
      background-color: var(--accent-900) !important;
    }

    .bg-slate-900:hover {
      background-color: var(--accent-800) !important;
      box-shadow: 0 8px 24px rgba(8, 12, 20, 0.12);
      transform: translateY(-2px);
    }

    .bg-slate-900:active {
      transform: translateY(0) scale(.997);
      box-shadow: 0 4px 12px rgba(8, 12, 20, 0.08);
    }

    /* border buttons (outline style) */
    .border.rounded-lg,
    .border.rounded {
      transition: background-color var(--transition-fast), border-color var(--transition-fast), box-shadow var(--transition-fast), transform var(--transition-fast);
    }

    .border.rounded-lg:hover,
    .border.rounded:hover {
      background-color: rgba(15, 23, 42, 0.02);
      border-color: rgba(15, 23, 42, 0.08);
      transform: translateY(-1px);
      box-shadow: var(--soft-shadow);
    }

    .border.rounded-lg:active,
    .border.rounded:active {
      transform: translateY(0);
      box-shadow: none;
    }

    /* subtle elevation for cards */
    .card-shadow {
      box-shadow: var(--card-shadow);
      transition: transform var(--transition-medium), box-shadow var(--transition-medium);
    }

    .card-shadow:hover {
      transform: translateY(-6px);
      box-shadow: 0 12px 40px rgba(2, 6, 23, 0.08);
    }

    /* products card specific selector (matches your product items: .bg-white.p-4.rounded-lg.border.card-shadow) */
    #products>div.bg-white.p-4.rounded-lg.border.card-shadow {
      cursor: pointer;
      will-change: transform, box-shadow;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      min-height: 140px;
      transition: transform var(--transition-medium), box-shadow var(--transition-medium), border-color var(--transition-medium);
    }

    #products>div.bg-white.p-4.rounded-lg.border.card-shadow:hover {
      transform: translateY(-8px);
      box-shadow: 0 18px 50px rgba(2, 6, 23, 0.09);
      border-color: rgba(15, 23, 42, 0.06);
    }

    /* plus / action buttons inside product card */
    #products button.w-9.h-9.rounded-full {
      transition: transform var(--transition-fast), box-shadow var(--transition-fast), background-color var(--transition-fast);
      box-shadow: 0 6px 18px rgba(2, 6, 23, 0.04);
    }

    #products button.w-9.h-9.rounded-full:hover {
      transform: translateY(-3px) scale(1.03);
      box-shadow: 0 12px 30px rgba(2, 6, 23, 0.08);
      filter: saturate(1.03);
    }

    #products button.w-9.h-9.rounded-full:active {
      transform: scale(.98);
      box-shadow: 0 6px 14px rgba(2, 6, 23, 0.06);
    }

    /* small detail button (Detail) */
    #products button[data-detail] {
      transition: background-color var(--transition-fast), color var(--transition-fast), transform var(--transition-fast);
    }

    #products button[data-detail]:hover {
      background-color: rgba(15, 23, 42, 0.02);
      transform: translateY(-2px);
    }

    /* category pills */
    .cat-pill {
      transition: transform var(--transition-fast), box-shadow var(--transition-fast), background-color var(--transition-fast), color var(--transition-fast);
      will-change: transform, box-shadow;
    }

    .cat-pill:hover {
      transform: translateY(-4px);
      box-shadow: 0 10px 30px rgba(2, 6, 23, 0.06);
      border-color: rgba(15, 23, 42, 0.06);
      background: linear-gradient(180deg, #ffffff, #fbfbfd);
    }

    .cat-pill:active {
      transform: translateY(-1px);
    }

    .cat-pill-active {
      background: var(--accent-900) !important;
      color: #fff !important;
      box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
    }

    /* cart item buttons (inc/dec/rm) - make them clearer to press */
    #cartList button,
    #checkoutItems button {
      transition: background-color var(--transition-fast), transform var(--transition-fast);
      padding: .35rem .5rem;
    }

    #cartList button:hover {
      transform: translateY(-2px);
    }

    /* checkout modal: payment method toggle */
    #checkoutModal .p-4.border.rounded-lg {
      transition: box-shadow var(--transition-fast), transform var(--transition-fast), border-color var(--transition-fast);
    }

    #checkoutModal .p-4.border.rounded-lg:hover {
      box-shadow: var(--soft-shadow);
      transform: translateY(-4px);
    }

    #checkoutModal button[onclick] {
      /* ensure action buttons are noticeable */
      border-radius: 0.5rem;
    }

    /* member/promo modal primary buttons */
    .modal-panel .bg-slate-900:hover {
      box-shadow: 0 10px 30px rgba(8, 12, 20, 0.1);
      transform: translateY(-3px);
    }

    /* subtle text-transform for small labels */
    .cat-pill,
    #products .text-xs,
    #cartList .text-xs {
      text-transform: uppercase;
      letter-spacing: .02em;
    }

    /* make inputs slightly friendlier */
    input[type="search"],
    input[type="text"],
    input[type="number"],
    textarea {
      transition: box-shadow var(--transition-fast), border-color var(--transition-fast);
    }

    input[type="search"]:focus,
    input[type="text"]:focus,
    input[type="number"]:focus,
    textarea:focus {
      box-shadow: var(--focus-ring);
      border-color: rgba(15, 23, 42, 0.12);
    }

    /* Status bar */
    #posStatus {
      min-height: 20px;
      transition: opacity var(--transition-fast);
    }

    /* small responsive tweaks to keep appearance tidy on narrow screens */
    @media (max-width: 768px) {
      #products>div.bg-white.p-4.rounded-lg.border.card-shadow {
        min-height: 120px;
      }

      .cat-pill {
        padding: .4rem .8rem;
        font-size: .82rem;
      }
    }
  </style>
</head>
<div id="posStatus" class="max-w-7xl mx-auto px-6 mt-2 text-sm text-gray-600" style="min-height:20px"></div>


<body class="bg-gray-50 text-gray-800 antialiased">
  <div class="max-w-7xl mx-auto p-6">

    <!-- Header -->
    <header class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-4">
        <div class="w-12 h-12 bg-slate-900 text-white rounded-lg flex items-center justify-center text-xl font-bold">JP</div>
        <div>
          <div class="flex items-center gap-3">
            <div class="font-bold text-lg">POS Toko Jus</div>
            <?php if ($displayRole): ?>
              <div class="text-xs bg-slate-100 border rounded-full px-2 py-0.5 text-slate-700"><?php echo $displayRole; ?></div>
            <?php endif; ?>
          </div>
          <div class="text-sm text-gray-500"><?php echo $displayName ? $displayName : '—'; ?></div>
        </div>
      </div>

      <div class="flex items-center gap-3">
        <button id="memberBtn" class="flex items-center gap-2 px-3 py-2 bg-white rounded-lg border">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-700" viewBox="0 0 20 20" fill="currentColor">
            <path d="M10 4a3 3 0 100 6 3 3 0 000-6z" />
            <path fill-rule="evenodd" d="M3 14a7 7 0 1114 0v1a1 1 0 01-1 1H4a1 1 0 01-1-1v-1z" clip-rule="evenodd" />
          </svg>
          <span class="text-sm">Member</span>
        </button>

        <button id="promoBtn" class="flex items-center gap-2 px-3 py-2 bg-white rounded-lg border">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-amber-600" viewBox="0 0 20 20" fill="currentColor">
            <path d="M4 3a2 2 0 00-2 2v2h16V5a2 2 0 00-2-2H4z" />
            <path fill-rule="evenodd" d="M18 9H2v6a2 2 0 002 2h12a2 2 0 002-2V9z" clip-rule="evenodd" />
          </svg>
          <span class="text-sm">Promo</span>
        </button>

        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'LEADER'): ?>
          <button id="rekapBtn" class="flex items-center gap-2 px-3 py-2 bg-white rounded-lg border" onclick="location.href='rekap.php'">
            <!-- icon (chart) -->
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-700" viewBox="0 0 20 20" fill="currentColor">
              <path d="M3 3h2v12H3V3zM8 7h2v8H8V7zM13 1h2v14h-2V1z" />
            </svg>
            <span class="text-sm">Rekap</span>
          </button>
        <?php endif; ?>

        <button id="logoutBtn" class="flex items-center gap-2 px-3 py-2 bg-white rounded-lg border text-red-600">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h6a1 1 0 110 2H5v10h5a1 1 0 110 2H4a1 1 0 01-1-1V4z" clip-rule="evenodd" />
            <path d="M12.293 9.293a1 1 0 011.414 0L16 11.586V7a1 1 0 112 0v6a1 1 0 01-1 1h-6a1 1 0 110-2h4.586l-2.293-2.293a1 1 0 010-1.414z" />
          </svg>
          <span class="text-sm">Logout</span>
        </button>
      </div>
    </header>

    <!-- Controls -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4 items-center">
      <div class="col-span-2">
        <input id="searchInput" type="search" placeholder="Cari menu atau kode..." class="w-full p-3 rounded-lg border" oninput="onSearch()">
      </div>

      <div class="flex items-center justify-center gap-4">
        <div class="flex rounded-lg bg-white border p-1 items-center">
          <button id="btnDine" class="px-5 py-2 bg-slate-900 text-white rounded">Dine In</button>
          <button id="btnTakeaway" class="px-5 py-2 rounded">Take Away</button>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <main class="lg:col-span-2">
        <div id="categoryBar" class="mb-4 flex items-center"></div>
        <div id="products" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4"></div>
      </main>

      <aside class="lg:col-span-1">

        <!-- MEMBER info (placed above Cart) -->
        <div id="memberInfoContainer"></div>

        <div class="bg-white rounded-2xl p-4 shadow">
          <div class="flex items-center justify-between mb-3">
            <div><strong>Cart</strong></div>
            <div id="cartQty" class="text-sm text-gray-500">0 items</div>
          </div>
          <ul id="cartList" class="space-y-3 max-h-[55vh] overflow-auto thin-scroll mb-3"></ul>

          <div id="cartFooter" class="border-t pt-3 text-sm text-gray-600"></div>

          <div class="mt-4 flex gap-3">
            <button id="btnClear" class="flex-1 border rounded-lg px-3 py-2" onclick="clearCart()">Hapus</button>
            <button id="btnSave" class="flex-1 bg-slate-900 text-white rounded-lg px-3 py-2" onclick="openCheckoutModal()">Save Order</button>
          </div>
        </div>
      </aside>
    </div>
  </div>

  <!-- Member Modal -->
  <div id="memberModal" class="fixed inset-0 hidden items-center justify-center z-40 modal-backdrop" aria-hidden>
    <div class="modal-panel glass rounded-2xl w-full max-w-2xl p-6 mx-4 shadow-lg">
      <div class="flex items-start justify-between mb-4">
        <h3 class="text-lg font-semibold">Member</h3>
        <div class="flex gap-2"><button class="text-sm px-2 py-1 border rounded" onclick="closeMemberModal()">Tutup</button></div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs text-gray-500">Cari (kode / nama / telp)</label>
          <div class="mt-2 flex gap-2">
            <input id="memberSearch" class="flex-1 p-3 border rounded" placeholder="Masukkan kata kunci" />
            <button id="memberSearchBtn" class="px-4 py-2 bg-slate-900 text-white rounded">Cari</button>
          </div>
          <div id="memberResults" class="mt-4 max-h-52 overflow-auto thin-scroll"></div>
        </div>
        <div>
          <div class="text-sm text-gray-500 mb-2">Buat member baru</div>
          <input id="newMemberName" class="w-full p-3 border rounded mb-2" placeholder="Nama lengkap" />
          <input id="newMemberPhone" class="w-full p-3 border rounded mb-2" placeholder="No HP" />
          <div class="flex gap-2"><button class="flex-1 px-4 py-2 bg-slate-900 text-white rounded" onclick="createMember()">Buat</button><button class="flex-1 px-4 py-2 border rounded" onclick="document.getElementById('newMemberName').value='';document.getElementById('newMemberPhone').value=''">Reset</button></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Promo Modal -->
  <div id="promoModal" class="fixed inset-0 hidden items-center justify-center z-40 modal-backdrop" aria-hidden>
    <div class="modal-panel glass rounded-2xl w-full max-w-md p-6 mx-4 shadow-lg">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold">Promo & Diskon</h3>
        <button class="text-sm px-2 py-1 border rounded" onclick="closePromoModal()">✕</button>
      </div>
      <div class="mb-3">
        <input id="promoCodeInput" class="w-full p-3 border rounded" placeholder="Masukkan kode promo" onkeydown="if(event.key==='Enter') applyPromo()">
      </div>
      <div class="flex gap-2 mb-3"><button class="flex-1 px-4 py-2 bg-slate-900 text-white rounded" onclick="applyPromo()">Terapkan</button><button class="flex-1 px-4 py-2 border rounded" onclick="closePromoModal()">Batal</button></div>
      <div id="promoList" class="text-sm text-gray-600"></div>
    </div>
  </div>

  <!-- Checkout Modal (unchanged) -->
  <div id="checkoutModal" class="fixed inset-0 hidden items-center justify-center z-50 modal-backdrop" aria-hidden>
    <div class="modal-panel glass rounded-2xl w-full max-w-2xl p-6 mx-4 shadow-lg">
      <div class="flex items-start justify-between mb-4">
        <div>
          <h3 class="text-lg font-semibold">Pembayaran</h3>
          <div class="text-sm text-gray-500">Periksa ringkasan lalu bayar (CASH wajib tepat)</div>
        </div>
        <button class="text-sm px-2 py-1 border rounded" onclick="closeCheckoutModal()">✕</button>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <div class="bg-white p-4 rounded-lg border mb-4">
            <div class="flex justify-between">
              <div class="text-sm text-gray-500">Subtotal</div>
              <div id="rcSubtotal" class="font-medium">Rp 0</div>
            </div>
            <div class="flex justify-between mt-2">
              <div class="text-sm text-green-600">Diskon</div>
              <div id="rcDiscount" class="font-medium">-Rp 0</div>
            </div>
            <div class="flex justify-between mt-2">
              <div class="text-sm">PPN (11%)</div>
              <div id="rcTax">Rp 0</div>
            </div>
            <div class="flex justify-between mt-2">
              <div class="text-sm">Pembulatan</div>
              <div id="rcRounding">Rp 0</div>
            </div>
            <hr class="my-3">
            <div class="flex justify-between text-xl font-bold">
              <div>Total</div>
              <div id="rcTotal">Rp 0</div>
            </div>
          </div>
          <div class="mb-3">
            <label class="block text-sm mb-2">Metode Pembayaran</label>
            <div class="flex gap-3"><button id="payCashBtn" class="flex-1 p-4 border rounded-lg bg-white">CASH</button><button id="payCardBtn" class="flex-1 p-4 border rounded-lg bg-white">CARD (dummy)</button></div>
          </div>
          <div>
            <label class="block text-sm mb-2">Jumlah Pembayaran (harus pas untuk CASH)</label>
            <input id="payAmountInput" type="number" class="w-full p-3 border rounded" />
          </div>
        </div>
        <div>
          <div class="bg-white p-4 rounded-lg border max-h-[48vh] overflow-auto thin-scroll">
            <div class="text-sm text-gray-500 mb-2">Ringkasan Item</div>
            <ul id="checkoutItems" class="space-y-2"></ul>
          </div>
          <div class="mt-4 flex gap-3 justify-end"><button class="px-4 py-2 border rounded" onclick="closeCheckoutModal()">Batal</button><button class="px-4 py-2 bg-slate-900 text-white rounded" onclick="submitPayment()">Bayar</button></div>
        </div>
      </div>
    </div>
  </div>

  <script>
    /* POS main client script — updated: auto-open receipt tab & auto-print support */
    (() => {
      const API_BASE = '../api';
      const TAX_RATE = 0.11;
      const ROUND_BASE = 100;
      let products = [];
      let cart = [];
      let currentPromo = null;
      let currentMember = null;
      let selectedPayMethod = 'CASH';

      // small UI status helper (optional element posStatus)
      function setStatus(msg, isError = false, timeoutMs = 3500) {
        const el = document.getElementById('posStatus');
        if (!el) {
          // fallback to console
          if (isError) console.error(msg);
          else console.log(msg);
          return;
        }
        el.textContent = msg || '';
        el.className = isError ? 'max-w-7xl mx-auto px-6 mt-2 text-sm text-red-600' : 'max-w-7xl mx-auto px-6 mt-2 text-sm text-gray-600';
        if (timeoutMs > 0) {
          setTimeout(() => {
            // only clear if still same message
            if (el && el.textContent === msg) el.textContent = '';
          }, timeoutMs);
        }
      }

      const fmt = n => new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0
      }).format(Math.round(n));
      const roundNearest = n => Math.round(n / ROUND_BASE) * ROUND_BASE;

      function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
      }

      // --- Attach logout (top-right) ---
      (function attachLogout() {
        const el = document.getElementById('logoutBtn');
        if (!el) return;
        el.addEventListener('click', async (ev) => {
          ev.preventDefault();
          try {
            await fetch(`${API_BASE}/auth.php?action=logout`, {
              method: 'GET',
              credentials: 'same-origin'
            });
          } catch (e) {
            console.warn('Logout request failed', e);
          }
          window.location.href = 'login.php';
        });
      })();

      // --- PRODUCTS ---
      async function fetchProducts(category) {
        try {
          let url = `${API_BASE}/products.php`;
          if (category && category !== 'ALL') url += '?category=' + encodeURIComponent(category);
          const r = await fetch(url, {
            credentials: 'same-origin'
          });
          if (!r.ok) throw new Error('products fetch failed: ' + r.status);
          products = await r.json();
          renderProducts(products);
        } catch (e) {
          console.error(e);
          const root = document.getElementById('products');
          if (root) root.innerHTML = '<div class="text-red-600">Gagal memuat produk</div>';
        }
      }

      function renderProducts(list) {
        const root = document.getElementById('products');
        if (!root) return;
        root.innerHTML = '';
        (list || []).forEach(p => {
          const el = document.createElement('div');
          el.className = 'bg-white p-4 rounded-lg border card-shadow';
          el.innerHTML = `
        <div class="text-xs text-gray-400">${escapeHtml(p.code||'')}</div>
        <div class="font-semibold mt-1">${escapeHtml(p.name||'')}</div>
        <div class="text-sm mt-2">${fmt(p.price||0)}</div>
        <div class="text-xs text-gray-500 mt-1">Stok: ${p.stock||0}</div>
        <div class="mt-3 flex justify-between items-center">
          <button class="w-9 h-9 rounded-full bg-slate-900 text-white" data-add="${p.id}">+</button>
          <button class="px-2 py-1 text-xs rounded border" data-detail="${p.id}">Detail</button>
        </div>`;
          root.appendChild(el);
        });
        root.querySelectorAll('[data-add]').forEach(btn => btn.onclick = () => addToCart(Number(btn.dataset.add)));
        root.querySelectorAll('[data-detail]').forEach(btn => btn.onclick = () => viewProduct(Number(btn.dataset.detail)));
      }

      function viewProduct(id) {
        const p = products.find(x => x.id == id);
        if (!p) return alert('Produk tidak ditemukan');
        alert(`${p.name}\n${fmt(p.price)}`);
      }

      // --- SEARCH ---
      (function attachSearch() {
        const el = document.getElementById('searchInput');
        if (el) el.addEventListener('input', onSearch);
      })();

      function onSearch() {
        const q = (document.getElementById('searchInput').value || '').toLowerCase();
        const filtered = products.filter(p => (p.name || '').toLowerCase().includes(q) || (p.code || '').toLowerCase().includes(q));
        renderProducts(filtered);
      }

      // --- CART helpers ---
      function findIdx(id) {
        return cart.findIndex(x => x.menu_id == id);
      }

      function addToCart(menu_id) {
        const p = products.find(x => x.id == menu_id);
        if (!p) return alert('Produk tidak ditemukan');
        const idx = findIdx(menu_id);
        if (idx >= 0) {
          if (cart[idx].qty + 1 > p.stock) return alert('Stok tidak cukup');
          cart[idx].qty++;
        } else {
          if (p.stock <= 0) return alert('Stok kosong');
          cart.push({
            menu_id: p.id,
            name: p.name,
            price: Number(p.price),
            qty: 1
          });
        }
        renderCart();
      }

      function incQty(id) {
        const i = findIdx(id);
        if (i < 0) return;
        const p = products.find(x => x.id == id);
        if (cart[i].qty + 1 > p.stock) return alert('Stok tidak cukup');
        cart[i].qty++;
        renderCart();
      }

      function decQty(id) {
        const i = findIdx(id);
        if (i < 0) return;
        cart[i].qty = Math.max(1, cart[i].qty - 1);
        renderCart();
      }

      function removeItem(id) {
        cart = cart.filter(x => x.menu_id != id);
        renderCart();
      }

      function moveUp(id) {
        const i = findIdx(id);
        if (i > 0) {
          [cart[i - 1], cart[i]] = [cart[i], cart[i - 1]];
          renderCart();
        }
      }

      function moveDown(id) {
        const i = findIdx(id);
        if (i >= 0 && i < cart.length - 1) {
          [cart[i + 1], cart[i]] = [cart[i], cart[i + 1]];
          renderCart();
        }
      }

      function clearCart() {
        if (!confirm('Hapus seluruh cart?')) return;
        cart = [];
        renderCart();
      }

      function calcSubtotal() {
        return cart.reduce((s, it) => s + it.price * it.qty, 0);
      }

      async function computePromoDiscount(subtotal) {
        if (!currentPromo) return 0;
        if (currentPromo.type === 'PERCENT') return subtotal * (currentPromo.value / 100);
        if (currentPromo.type === 'AMOUNT') return Number(currentPromo.value);
        return 0;
      }

      async function calcTotals() {
        const subtotal = calcSubtotal();
        const discount = await computePromoDiscount(subtotal);
        const after = Math.max(0, subtotal - discount);
        const tax = after * TAX_RATE;
        const raw = after + tax;
        const rounded = roundNearest(raw);
        const rounding = raw - rounded;
        return {
          subtotal,
          discount,
          tax,
          rounding,
          total: Math.round(rounded)
        };
      }

      async function renderCart() {
        const ul = document.getElementById('cartList');
        if (!ul) return;
        ul.innerHTML = '';
        cart.forEach(it => {
          const li = document.createElement('li');
          li.className = 'flex justify-between items-start';
          li.innerHTML = `
        <div class="flex-1">
          <div class="font-medium">${escapeHtml(it.name)}</div>
          <div class="text-xs text-gray-500">${fmt(it.price)} × ${it.qty}</div>
          <div class="mt-2 flex gap-2">
            <button class="px-2 py-1 border rounded" data-dec="${it.menu_id}">-</button>
            <div class="px-3 py-1 border rounded">${it.qty}</div>
            <button class="px-2 py-1 border rounded" data-inc="${it.menu_id}">+</button>
            <button class="px-3 text-sm text-gray-600" data-up="${it.menu_id}">↑</button>
            <button class="px-3 text-sm text-gray-600" data-down="${it.menu_id}">↓</button>
            <button class="px-3 text-sm text-red-600" data-rm="${it.menu_id}">🗑</button>
          </div>
        </div>
        <div class="ml-4 font-medium">${fmt(it.price*it.qty)}</div>`;
          ul.appendChild(li);
        });

        ul.querySelectorAll('[data-dec]').forEach(b => b.onclick = () => decQty(Number(b.dataset.dec)));
        ul.querySelectorAll('[data-inc]').forEach(b => b.onclick = () => incQty(Number(b.dataset.inc)));
        ul.querySelectorAll('[data-rm]').forEach(b => b.onclick = () => removeItem(Number(b.dataset.rm)));
        ul.querySelectorAll('[data-up]').forEach(b => b.onclick = () => moveUp(Number(b.dataset.up)));
        ul.querySelectorAll('[data-down]').forEach(b => b.onclick = () => moveDown(Number(b.dataset.down)));

        const totals = await calcTotals();
        const footer = document.getElementById('cartFooter');
        document.getElementById('cartQty').innerText = cart.length + ' items';
        if (footer) footer.innerHTML = `
      <div class='text-sm'>Subtotal: ${fmt(totals.subtotal)}</div>
      <div class='text-sm text-green-600'>Diskon: -${fmt(totals.discount)}</div>
      <div class='text-sm'>PPN (11%): ${fmt(totals.tax)}</div>
      <div class='text-sm'>Pembulatan: ${fmt(totals.rounding)}</div>
      <div class='text-lg font-bold mt-2'>Total: ${fmt(totals.total)}</div>`;
      }

      // --- CATEGORIES (dynamic) ---
      let activeCategory = 'ALL';
      async function renderCategoryBar() {
        const bar = document.getElementById('categoryBar');
        if (!bar) return;
        bar.innerHTML = '';
        try {
          const res = await fetch(`${API_BASE}/categories.php`, {
            credentials: 'same-origin'
          });
          if (!res.ok) throw new Error('Failed to load categories');
          const cats = await res.json();
          const btnAll = document.createElement('button');
          btnAll.className = 'px-4 py-2 rounded-lg bg-slate-900 text-white cat-pill-active';
          btnAll.dataset.cat = 'ALL';
          btnAll.textContent = 'SEMUA';
          btnAll.addEventListener('click', () => setActiveCategory('ALL', btnAll));
          bar.appendChild(btnAll);
          (cats || []).forEach(c => {
            const b = document.createElement('button');
            b.className = 'px-4 py-2 rounded-lg border ml-2 cat-pill';
            b.dataset.cat = c.code;
            b.textContent = (c.name || c.code).toUpperCase();
            b.addEventListener('click', () => setActiveCategory(c.code, b));
            bar.appendChild(b);
          });
          setActiveCategory('ALL', btnAll);
        } catch (e) {
          console.error('renderCategoryBar', e);
          if (bar) bar.innerHTML = '<div class="text-sm text-red-600">Gagal memuat kategori</div>';
        }
      }

      function setActiveCategory(key, btnEl) {
        activeCategory = key || 'ALL';
        document.querySelectorAll('#categoryBar button').forEach(b => {
          b.classList.remove('cat-pill-active', 'bg-slate-900', 'text-white');
          b.classList.add('border');
        });
        if (btnEl) {
          btnEl.classList.add('cat-pill-active', 'bg-slate-900', 'text-white');
          btnEl.classList.remove('border');
        }
        fetchProducts(key);
      }

      // --- MEMBER ---
      const memberSearchInput = document.getElementById('memberSearch');
      const memberSearchBtn = document.getElementById('memberSearchBtn');
      const memberResultsRoot = document.getElementById('memberResults');
      const memberModalEl = document.getElementById('memberModal');
      const memberInfoContainer = document.getElementById('memberInfoContainer');

      let memberSearchAbort = null;
      if (memberSearchBtn) memberSearchBtn.addEventListener('click', doMemberSearch);
      if (memberSearchInput) memberSearchInput.addEventListener('keydown', (ev) => {
        if (ev.key === 'Enter') {
          ev.preventDefault();
          doMemberSearch();
        }
      });

      async function doMemberSearch() {
        const q = (memberSearchInput && memberSearchInput.value || '').trim();
        if (!q) {
          renderMemberResults([]);
          return;
        }
        memberSearchBtn.disabled = true;
        const orig = memberSearchBtn.textContent;
        memberSearchBtn.textContent = 'Mencari...';
        try {
          if (memberSearchAbort) memberSearchAbort.abort();
          memberSearchAbort = new AbortController();
          const res = await fetch(`${API_BASE}/members.php?search=${encodeURIComponent(q)}`, {
            method: 'GET',
            credentials: 'same-origin',
            signal: memberSearchAbort.signal
          });
          if (!res.ok) {
            const txt = await res.text();
            console.error('members.php', res.status, txt);
            renderMemberResults([]);
            return;
          }
          const data = await res.json();
          renderMemberResults(Array.isArray(data) ? data : []);
        } catch (e) {
          if (e.name === 'AbortError') return;
          console.error('Member search failed', e);
          alert('Gagal mencari member. Periksa koneksi.');
          renderMemberResults([]);
        } finally {
          memberSearchBtn.disabled = false;
          memberSearchBtn.textContent = orig;
        }
      }

      function renderMemberResults(list) {
        if (!memberResultsRoot) return;
        memberResultsRoot.innerHTML = '';
        if (!list || list.length === 0) {
          memberResultsRoot.innerHTML = '<div class="text-sm text-gray-500">Tidak ada member</div>';
          return;
        }
        list.forEach(m => {
          const row = document.createElement('div');
          row.className = 'p-3 border rounded mb-2 flex justify-between items-center';
          row.innerHTML = `<div><div class="font-medium">${escapeHtml(m.name)}</div><div class="text-xs text-gray-500">${escapeHtml(m.phone||'')}</div></div>`;
          const btn = document.createElement('button');
          btn.className = 'px-3 py-1 bg-slate-900 text-white rounded';
          btn.type = 'button';
          btn.textContent = 'Pilih';
          btn.addEventListener('click', () => chooseMember(m.id, m.name, m.phone));
          row.appendChild(btn);
          memberResultsRoot.appendChild(row);
        });
      }

      function chooseMember(id, name, phone) {
        currentMember = {
          id,
          name,
          phone
        };
        updateMemberDisplay();
        if (typeof window.closeMemberModal === 'function') window.closeMemberModal();
      }

      async function createMember() {
        const name = (document.getElementById('newMemberName')?.value || '').trim();
        const phone = (document.getElementById('newMemberPhone')?.value || '').trim();
        if (!name) return alert('Nama diperlukan');
        try {
          const r = await fetch(`${API_BASE}/members.php`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              name,
              phone
            })
          });
          const j = await r.json();
          if (j.success) {
            currentMember = {
              id: j.member_id,
              name,
              phone
            };
            updateMemberDisplay();
            alert('Member dibuat: ' + name);
            if (typeof window.closeMemberModal === 'function') window.closeMemberModal();
          } else alert('Gagal membuat member: ' + (j.error || 'unknown'));
        } catch (e) {
          console.error(e);
          alert('Error membuat member');
        }
      }

      function updateMemberDisplay() {
        if (!memberInfoContainer) return;
        memberInfoContainer.innerHTML = '';
        if (!currentMember) return;
        const wrapper = document.createElement('div');
        wrapper.className = 'mb-4';
        wrapper.innerHTML = `
      <div class="bg-white rounded-lg p-3 border">
        <div class="flex items-center justify-between">
          <div><div class="text-xs text-gray-400">Member</div><div class="font-medium text-sm text-slate-800">${escapeHtml(currentMember.name||'')}</div><div class="text-xs text-gray-500">${escapeHtml(currentMember.phone||'')}</div></div>
          <div><button id="clearMemberBtn" class="px-3 py-1 border rounded text-sm text-red-600">Hapus</button></div>
        </div>
      </div>`;
        memberInfoContainer.appendChild(wrapper);
        const clearBtn = document.getElementById('clearMemberBtn');
        if (clearBtn) clearBtn.addEventListener('click', () => {
          if (!confirm('Hapus member dari transaksi?')) return;
          currentMember = null;
          updateMemberDisplay();
        });
      }

      // --- PROMO ---
      async function loadPromos() {
        try {
          const r = await fetch(`${API_BASE}/promotions.php`, {
            credentials: 'same-origin'
          });
          if (!r.ok) throw new Error('promotions fetch failed');
          const j = await r.json();
          const root = document.getElementById('promoList');
          if (!root) return;
          if (!j || j.length === 0) root.innerHTML = '<div class="text-sm text-gray-500">Tidak ada promo aktif</div>';
          else root.innerHTML = j.map(p => `<div class="p-2 border rounded mb-2"><div class="font-medium">${escapeHtml(p.code)}</div><div class="text-xs">${escapeHtml(p.type)} — ${escapeHtml(String(p.value))}</div></div>`).join('');
        } catch (e) {
          console.error('loadPromos', e);
        }
      }

      async function applyPromo() {
        const code = (document.getElementById('promoCodeInput')?.value || '').trim();
        if (!code) return alert('Masukkan kode promo');
        try {
          const subtotal = calcSubtotal();
          const r = await fetch(`${API_BASE}/promotions.php`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              action: 'validate',
              code,
              subtotal
            })
          });
          const j = await r.json();
          if (j.valid) {
            currentPromo = j.promo;
            alert('Promo diterapkan: ' + j.promo.code);
            if (typeof window.closePromoModal === 'function') window.closePromoModal();
            renderCart();
          } else alert('Promo invalid');
        } catch (e) {
          console.error(e);
          alert('Gagal memeriksa promo');
        }
      }

      // --- CHECKOUT & PAYMENT (modified to open receipt & auto-print) ---
      function openCheckoutModalGuarded() {
        if (!Array.isArray(cart) || cart.length === 0) return alert('Cart kosong');
        if (typeof window.openCheckoutModal === 'function') return window.openCheckoutModal();
        const el = document.getElementById('checkoutModal');
        if (el) {
          el.classList.remove('hidden');
          el.classList.add('flex');
          el.classList.add('modal-show');
          updateCheckoutModal();
        }
      }

      async function updateCheckoutModal() {
        const t = await calcTotals();
        const el = document.getElementById('rcSubtotal');
        if (el) el.innerText = fmt(t.subtotal);
        const d = document.getElementById('rcDiscount');
        if (d) d.innerText = '-' + fmt(t.discount);
        const tax = document.getElementById('rcTax');
        if (tax) tax.innerText = fmt(t.tax);
        const rnd = document.getElementById('rcRounding');
        if (rnd) rnd.innerText = fmt(t.rounding);
        const tot = document.getElementById('rcTotal');
        if (tot) tot.innerText = fmt(t.total);
        const pai = document.getElementById('payAmountInput');
        if (pai) pai.value = t.total;
        const listRoot = document.getElementById('checkoutItems');
        if (listRoot) listRoot.innerHTML = cart.map(i => `<li class='flex justify-between'><div class='text-sm'>${escapeHtml(i.name)} <span class='text-xs text-gray-500'>x${i.qty}</span></div><div class='text-sm font-medium'>${fmt(i.price*i.qty)}</div></li>`).join('');
      }

      (function attachPaymentButtons() {
        const cash = document.getElementById('payCashBtn');
        const card = document.getElementById('payCardBtn');
        if (cash) cash.addEventListener('click', () => {
          selectedPayMethod = 'CASH';
          cash.classList.add('ring-2', 'ring-slate-300');
          card?.classList.remove('ring-2');
        });
        if (card) card.addEventListener('click', () => {
          selectedPayMethod = 'CARD';
          card.classList.add('ring-2', 'ring-slate-300');
          cash?.classList.remove('ring-2');
        });
      })();

      // helper to open receipt in new tab with auto_print param
      function openReceiptAutoPrint(receiptUrl) {
        if (!receiptUrl) return;
        const url = receiptUrl + (receiptUrl.includes('?') ? '&' : '?') + 'auto_print=1';
        // try open new tab (should be allowed since triggered from user click)
        const w = window.open(url, '_blank');
        if (w) {
          try {
            w.focus();
          } catch (e) {}
          setStatus('Struk terbuka di tab baru. Cek tab tersebut untuk cetak.', false, 6000);
        } else {
          // blocked: fallback to navigate current tab
          setStatus('Popup diblokir — membuka struk di tab saat ini...', true, 5000);
          window.location.href = url;
        }
      }

      async function submitPayment() {
        // disable pay button to avoid duplicate submission
        const payBtn = Array.from(document.querySelectorAll('#checkoutModal button')).find(b => b && b.textContent && b.textContent.trim().toLowerCase().includes('bayar'));
        if (payBtn) {
          payBtn.disabled = true;
          payBtn.textContent = 'Memproses...';
        }

        try {
          const amount = Number(document.getElementById('payAmountInput')?.value || 0);
          const totals = await calcTotals();
          if (selectedPayMethod === 'CASH' && amount !== totals.total) {
            alert('Pembayaran CASH harus pas.');
            if (payBtn) {
              payBtn.disabled = false;
              payBtn.textContent = 'Bayar';
            }
            return;
          }

          // prepare payload
          const payload = {
            items: cart.map(i => ({
              menu_id: i.menu_id,
              qty: i.qty,
              price: i.price
            })),
            member_id: currentMember ? currentMember.id : null,
            promo: currentPromo ? currentPromo.code : null,
            visit_type: document.getElementById('btnDine')?.classList.contains('bg-slate-900') ? 'DINE' : 'TAKEAWAY',
            payment_method: selectedPayMethod,
            paid_amount: amount
          };

          const r = await fetch(`${API_BASE}/orders.php`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
          });

          if (!r.ok) {
            const txt = await r.text();
            console.error('orders.php error', r.status, txt);
            let msg = 'Order gagal: server error';
            try {
              const j = JSON.parse(txt);
              if (j && j.error) msg = 'Order gagal: ' + j.error;
            } catch (e) {}
            alert(msg);
            return;
          }

          const j = await r.json();
          if (j.success) {
            // optionally server already saved payment row
            setStatus('Transaksi berhasil. Menyiapkan struk...', false, 5000);

            // open receipt (server returns receipt_url)
            const receiptUrl = j.receipt_url || (`${API_BASE}/receipt.php?order_id=${j.order_id}`);
            openReceiptAutoPrint(receiptUrl);

            // reset cart and UI
            cart = [];
            currentPromo = null;
            currentMember = null;
            renderCart();
            updateMemberDisplay();
            if (typeof window.closeCheckoutModal === 'function') window.closeCheckoutModal();
          } else {
            alert('Order gagal: ' + (j.error || 'unknown'));
          }
        } catch (e) {
          console.error('submitPayment failed', e);
          alert('Order gagal: server error');
        } finally {
          if (payBtn) {
            payBtn.disabled = false;
            payBtn.textContent = 'Bayar';
          }
        }
      }

      // --- Generic modal helpers (same as before) ---
      function initModal(modalId, options = {}) {
        const modalEl = document.getElementById(modalId);
        if (!modalEl) return {
          open: () => {},
          close: () => {}
        };
        const open = () => {
          modalEl.classList.remove('hidden');
          modalEl.classList.add('flex');
          requestAnimationFrame(() => modalEl.classList.add('modal-show'));
          document.body.style.overflow = 'hidden';
          const focus = modalEl.querySelector('input,button,[tabindex]');
          if (focus) try {
            focus.focus();
          } catch (e) {}
          if (typeof options.onOpen === 'function') try {
            options.onOpen();
          } catch (e) {
            console.error(e);
          }
        };
        const close = () => {
          modalEl.classList.remove('modal-show');
          setTimeout(() => {
            modalEl.classList.add('hidden');
            modalEl.classList.remove('flex');
            document.body.style.overflow = '';
            if (typeof options.onClose === 'function') options.onClose();
          }, 200);
        };
        modalEl.addEventListener('click', (ev) => {
          if (ev.target === modalEl) close();
        });
        document.addEventListener('keydown', (ev) => {
          if (ev.key === 'Escape' && !modalEl.classList.contains('hidden')) close();
        });
        if (Array.isArray(options.openTriggerIds)) options.openTriggerIds.forEach(id => {
          const b = document.getElementById(id);
          if (b) b.addEventListener('click', (ev) => {
            ev.preventDefault();
            open();
          });
        });
        return {
          open,
          close
        };
      }

      // create modal controllers
      const memberModalObj = initModal('memberModal', {
        openTriggerIds: ['memberBtn'],
        onOpen: () => {
          if (memberSearchInput) {
            memberSearchInput.value = '';
            memberSearchInput.focus();
          }
        }
      });
      window.openMemberModal = memberModalObj.open;
      window.closeMemberModal = memberModalObj.close;
      const promoModalObj = initModal('promoModal', {
        openTriggerIds: ['promoBtn'],
        onOpen: () => {
          const i = document.getElementById('promoCodeInput');
          if (i) {
            i.value = '';
            i.focus();
          }
          loadPromos();
        }
      });
      window.openPromoModal = promoModalObj.open;
      window.closePromoModal = promoModalObj.close;
      const checkoutModalObj = initModal('checkoutModal', {
        onOpen: () => {
          updateCheckoutModal();
        }
      });
      window.openCheckoutModal = function() {
        if (!Array.isArray(cart) || cart.length === 0) return alert('Cart kosong');
        checkoutModalObj.open();
      };
      window.closeCheckoutModal = checkoutModalObj.close;

      // --- hooks for buttons on page outside script ---
      (function attachTopButtons() {
        const clearBtn = document.getElementById('btnClear');
        if (clearBtn) clearBtn.addEventListener('click', clearCart);
        const saveBtn = document.getElementById('btnSave');
        if (saveBtn) saveBtn.addEventListener('click', () => openCheckoutModalGuarded());
        const bDine = document.getElementById('btnDine'),
          bTake = document.getElementById('btnTakeaway');
        if (bDine) bDine.addEventListener('click', () => {
          bDine.classList.add('bg-slate-900', 'text-white');
          bTake?.classList.remove('bg-slate-900', 'text-white');
        });
        if (bTake) bTake.addEventListener('click', () => {
          bTake.classList.add('bg-slate-900', 'text-white');
          bDine?.classList.remove('bg-slate-900', 'text-white');
        });
      })();

      // bootstrap
      renderCategoryBar();

      // expose a few useful functions for debugging or inline handlers
      window.__pos_debug = {
        products,
        cart,
        currentMember,
        currentPromo,
        fetchProducts,
        renderCart
      };
      window.createMember = createMember;
      window.applyPromo = applyPromo;
      window.submitPayment = submitPayment;

    })();
  </script>

</body>

</html>