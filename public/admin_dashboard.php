<?php
// public/admin_dashboard.php
session_start();
// akses hanya untuk role ADMIN (atau LEADER)
$userRole = $_SESSION['user_role'] ?? null;
if (!$userRole || !in_array($userRole, ['ADMIN', 'LEADER'])) {
  header('Location: login.php');
  exit;
}
$displayName = htmlspecialchars($_SESSION['user_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Admin Dashboard — POS Toko Jus</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    /* small tweaks */
    .sidebar {
      min-height: 100vh;
    }

    .nav-item.active {
      background: rgba(15, 23, 42, 0.04);
      font-weight: 600;
    }

    .content-scroll {
      max-height: calc(100vh - 96px);
      overflow: auto;
    }

    .admin-card {
      transition: transform .12s, box-shadow .12s;
    }

    .admin-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 8px 30px rgba(2, 6, 23, 0.08);
    }
  </style>
</head>

<body class="bg-gray-50 text-gray-800">
  <div class="max-w-7xl flex ">
    <!-- SIDEBAR -->
    <aside class="sidebar w-64 bg-white border-r hidden md:block" id="adminSidebar">
      <div class="p-4 border-b">
        <div class="text-xl font-bold">ADMIN PANEL</div>
        <div class="text-xs text-gray-500 mt-1"><?php echo $displayName; ?></div>
      </div>

      <nav class="p-4 space-y-1 text-sm" id="adminNav">
        <button data-page="admin_dashboard.php" class="nav-item w-full text-left p-3 rounded flex items-center gap-3" data-slug="admin_dashboard.php">
          <svg class="w-4 h-4 text-slate-700" viewBox="0 0 24 24" fill="none">
            <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zM13 21h8V11h-8v10zM13 3v6h8V3h-8z" fill="currentColor" />
          </svg>
          Dashboard
        </button>

        <button data-page="admin_menus.php" class="nav-item w-full text-left p-3 rounded flex items-center gap-3" data-slug="admin_menus.php">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none">
            <path d="M4 6h16v2H4zM4 11h16v2H4zM4 16h16v2H4z" fill="currentColor" />
          </svg>
          Menu (CRUD)
        </button>

        <button data-page="admin_promotions.php" class="nav-item w-full text-left p-3 rounded flex items-center gap-3" data-slug="admin_promotions.php">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none">
            <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5" fill="currentColor" />
          </svg>
          Promo
        </button>

        <button data-page="admin_users.php" class="nav-item w-full text-left p-3 rounded flex items-center gap-3" data-slug="admin_users.php">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none">
            <path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-5 0-8 2.5-8 5v1h16v-1c0-2.5-3-5-8-5z" fill="currentColor" />
          </svg>
          Users
        </button>

        <!-- NEW: Members nav item -->
        <button data-page="admin_members.php" class="nav-item w-full text-left p-3 rounded flex items-center gap-3" data-slug="admin_members.php">
          <svg class="w-4 h-4 text-slate-700" viewBox="0 0 24 24" fill="none">
            <path d="M16 11c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM8 11c1.657 0 3-1.343 3-3S9.657 5 8 5 5 6.343 5 8s1.343 3 3 3zM8 13c-2.33 0-7 1.17-7 3.5V19h14v-2.5C15 14.17 10.33 13 8 13zM16 13c-.29 0-.575.01-.855.03C16.941 14.1 20 15.02 20 16.5V19h4v-2.5C24 14.17 19.33 13 16 13z" fill="currentColor" />
          </svg>
          Members
        </button>

        <button data-page="admin_export.php" class="nav-item w-full text-left p-3 rounded flex items-center gap-3" data-slug="admin_export.php">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none">
            <path d="M12 3v10m0 0l4-4m-4 4-4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          Export
        </button>

        <div class="border-t mt-3 pt-3">
          <button id="logoutBtn" class="w-full text-left p-2 rounded border text-red-600 text-sm">Logout</button>
        </div>
      </nav>
    </aside>

    <!-- MAIN -->
    <div class="flex-1 min-h-screen">
      <!-- topbar (mobile) -->
      <header class="md:hidden bg-white p-3 border-b flex items-center justify-between">
        <div class="flex items-center gap-3">
          <button id="openSidebar" class="p-2 border rounded">Menu</button>
          <div class="font-bold">Admin Panel</div>
        </div>
        <div class="text-sm text-gray-600"><?php echo $displayName; ?></div>
      </header>

      <!-- content -->
      <main class="p-6">
        <div id="adminContent" class="bg-white rounded-lg p-4 content-scroll shadow-sm">
          <!-- dashboard cards will be shown by JS -->
          <div class="text-center py-12">
            <div class="text-lg font-semibold">Memuat halaman admin...</div>
            <div class="text-sm text-gray-500 mt-2">Pilih menu di sidebar untuk memulai.</div>
          </div>
        </div>
      </main>
    </div>
  </div>

  <script>
    // base folder (directory of current file) so relative links resolve to public/
    const baseDir = (function() {
      const p = location.pathname;
      return p.substring(0, p.lastIndexOf('/') + 1);
    })();

    function resolvePath(p) {
      if (!p) return p;
      if (p.startsWith('/')) return p;
      return baseDir + p;
    }

    const adminContent = document.getElementById('adminContent');

    // show dashboard cards (initial view)
    function showDashboardCards() {
      const html = `
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="admin-card p-6 rounded-lg border cursor-pointer" data-page="admin_menus.php" role="button" tabindex="0">
          <div class="flex items-center justify-between">
            <div>
              <div class="text-xl font-semibold">Menu (CRUD)</div>
              <div class="text-sm text-gray-500 mt-2">Tambah, edit, dan hapus item menu. Kelola stok & harga.</div>
            </div>
            <div class="bg-slate-100 rounded-full p-3">
              <svg class="w-6 h-6 text-slate-700" viewBox="0 0 24 24" fill="none"><path d="M4 6h16v2H4zM4 11h16v2H4zM4 16h16v2H4z" fill="currentColor"/></svg>
            </div>
          </div>
        </div>

        <div class="admin-card p-6 rounded-lg border cursor-pointer" data-page="admin_promotions.php" role="button" tabindex="0">
          <div class="flex items-center justify-between">
            <div>
              <div class="text-xl font-semibold">Promotions</div>
              <div class="text-sm text-gray-500 mt-2">Buat dan kelola promo (percent/amount).</div>
            </div>
            <div class="bg-slate-100 rounded-full p-3">
              <svg class="w-6 h-6 text-slate-700" viewBox="0 0 24 24" fill="none"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5" fill="currentColor"/></svg>
            </div>
          </div>
        </div>

        <div class="admin-card p-6 rounded-lg border cursor-pointer" data-page="admin_users.php" role="button" tabindex="0">
          <div class="flex items-center justify-between">
            <div>
              <div class="text-xl font-semibold">Users</div>
              <div class="text-sm text-gray-500 mt-2">Kelola akun, peran, dan akses pengguna.</div>
            </div>
            <div class="bg-slate-100 rounded-full p-3">
              <svg class="w-6 h-6 text-slate-700" viewBox="0 0 24 24" fill="none"><path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-5 0-8 2.5-8 5v1h16v-1c0-2.5-3-5-8-5z" fill="currentColor"/></svg>
            </div>
          </div>
        </div>

        <!-- NEW: Members card -->
        <div class="admin-card p-6 rounded-lg border cursor-pointer" data-page="admin_members.php" role="button" tabindex="0">
          <div class="flex items-center justify-between">
            <div>
              <div class="text-xl font-semibold">Members</div>
              <div class="text-sm text-gray-500 mt-2">Lihat dan kelola member (pelanggan, loyalty, data kontak).</div>
            </div>
            <div class="bg-slate-100 rounded-full p-3">
              <svg class="w-6 h-6 text-slate-700" viewBox="0 0 24 24" fill="none"><path d="M16 11c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM8 11c1.657 0 3-1.343 3-3S9.657 5 8 5 5 6.343 5 8s1.343 3 3 3z" fill="currentColor"/></svg>
            </div>
          </div>
        </div>

        <div class="admin-card p-6 rounded-lg border cursor-pointer" data-page="admin_export.php" role="button" tabindex="0">
          <div class="flex items-center justify-between">
            <div>
              <div class="text-xl font-semibold">Export</div>
              <div class="text-sm text-gray-500 mt-2">Export CSV untuk menus, promos, dan transaksi.</div>
            </div>
            <div class="bg-slate-100 rounded-full p-3">
              <svg class="w-6 h-6 text-slate-700" viewBox="0 0 24 24" fill="none"><path d="M12 3v10m0 0l4-4m-4 4-4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
          </div>
        </div>
      </div>
      <div class="mt-6 text-sm text-gray-500">Klik kartu untuk membuka halaman pengelolaan.</div>
      `;
      adminContent.innerHTML = html;

      // wire card clicks -> navigate to full page
      adminContent.querySelectorAll('.admin-card').forEach(card => {
        card.addEventListener('click', () => {
          const page = card.dataset.page;
          if (!page) return;
          window.location.href = resolvePath(page);
        });
        card.addEventListener('keydown', (ev) => {
          if (ev.key === 'Enter' || ev.key === ' ') {
            ev.preventDefault();
            card.click();
          }
        });
      });

      // mark Dashboard nav active locally (visual only)
      markActiveBySlug('admin_dashboard.php');
    }

    // mark active sidebar link by slug (visual only on this page)
    function markActiveBySlug(slug) {
      try {
        document.querySelectorAll('#adminNav .nav-item').forEach(n => n.classList.remove('active'));
        if (!slug) return;
        const nav = Array.from(document.querySelectorAll('#adminNav .nav-item')).find(n => {
          const ds = (n.dataset.slug || n.dataset.page || n.getAttribute('href') || '').toString();
          return ds === slug || ds.endsWith(slug);
        });
        if (nav) nav.classList.add('active');
      } catch (e) {
        console.error('markActiveBySlug', e);
      }
    }

    // sidebar nav clicks -> navigate to full page (except Dashboard which stays here)
    document.querySelectorAll('#adminNav .nav-item').forEach(btn => {
      btn.addEventListener('click', (ev) => {
        const page = btn.dataset.page;
        if (!page) return;
        if (page === 'admin_dashboard.php') {
          // show dashboard cards
          showDashboardCards();
          history.replaceState({}, '', resolvePath('admin_dashboard.php'));
          markActiveBySlug('admin_dashboard.php');
          return;
        }
        // navigate to the full page (no AJAX)
        window.location.href = resolvePath(page);
      });
    });

    // logout: call API then redirect (path resolves to ../api/auth.php)
    document.getElementById('logoutBtn').addEventListener('click', async () => {
      try {
        // resolving '../api/auth.php?action=logout' relative to /public/
        await fetch(resolvePath('../api/auth.php?action=logout'), {
          method: 'GET',
          credentials: 'same-origin'
        });
      } catch (e) {}
      window.location.href = 'login.php';
    });

    // mobile sidebar toggler
    document.getElementById('openSidebar')?.addEventListener('click', () => {
      const s = document.getElementById('adminSidebar');
      if (!s) return;
      s.classList.toggle('hidden');
    });

    // initial: always show dashboard cards (no ?admin param usage)
    (function() {
      showDashboardCards();
      // mark Dashboard active
      markActiveBySlug('admin_dashboard.php');
    })();
  </script>
</body>

</html>