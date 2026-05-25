<?php
// public/admin_export.php
session_start();
if (empty($_SESSION['user_id'])) {
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
  <title>Admin — Export CSV</title>
  <script src="https://cdn.tailwindcss.com"></script>

  <style>
    .nav-item.active {
      background: rgba(15, 23, 42, 0.06);
      font-weight: 600;
    }

    /* ensure sidebar takes full height on desktop */
    .sidebar {
      min-height: 100vh;
    }
  </style>
</head>

<body class="bg-gray-50 min-h-screen">
  <div class="max-w-7xl flex gap-6">
    <aside class="sidebar w-64 bg-white border-r hidden md:block" id="adminSidebar">
      <div class="p-4 border-b">
        <div class="text-xl font-bold">ADMIN PANEL</div>
        <div class="text-xs text-gray-500 mt-1"><?php echo $displayName ?? 'Admin'; ?></div>
      </div>

      <nav class="p-4 space-y-1 text-sm" id="adminNav">
        <a href="admin_dashboard.php" class="nav-item block w-full text-left p-3 rounded flex items-center gap-3" data-slug="admin_dashboard.php">
          <svg class="w-4 h-4 text-slate-700" viewBox="0 0 24 24" fill="none">
            <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zM13 21h8V11h-8v10zM13 3v6h8V3h-8z" fill="currentColor" />
          </svg>
          Dashboard
        </a>

        <a href="admin_menus.php" class="nav-item block w-full text-left p-3 rounded flex items-center gap-3" data-slug="admin_menus.php">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none">
            <path d="M4 6h16v2H4zM4 11h16v2H4zM4 16h16v2H4z" fill="currentColor" />
          </svg>
          Menu (CRUD)
        </a>

        <a href="admin_promotions.php" class="nav-item block w-full text-left p-3 rounded flex items-center gap-3" data-slug="admin_promotions.php">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none">
            <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5" fill="currentColor" />
          </svg>
          Promo
        </a>

        <a href="admin_users.php" class="nav-item block w-full text-left p-3 rounded flex items-center gap-3" data-slug="admin_users.php">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none">
            <path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-5 0-8 2.5-8 5v1h16v-1c0-2.5-3-5-8-5z" fill="currentColor" />
          </svg>
          Users
        </a>

        <a href="admin_members.php" class="nav-item block w-full text-left p-3 rounded flex items-center gap-3" data-slug="admin_members.php">
          <svg class="w-4 h-4 text-slate-700" viewBox="0 0 24 24" fill="none">
            <path d="M16 11c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM8 11c1.657 0 3-1.343 3-3S9.657 5 8 5 5 6.343 5 8s1.343 3 3 3zM8 13c-2.33 0-7 1.17-7 3.5V19h14v-2.5C15 14.17 10.33 13 8 13zM16 13c-.29 0-.575.01-.855.03C16.941 14.1 20 15.02 20 16.5V19h4v-2.5C24 14.17 19.33 13 16 13z" fill="currentColor" />
          </svg>
          Members
        </a>

        <a href="admin_export.php" class="nav-item block w-full text-left p-3 rounded flex items-center gap-3" data-slug="admin_export.php">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none">
            <path d="M12 3v10m0 0l4-4m-4 4-4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          Export
        </a>

        <div class="border-t mt-3 pt-3">
          <button id="logoutBtnSmall" class="w-full text-left p-2 rounded border text-red-600 text-sm">Logout</button>
        </div>
      </nav>
    </aside>

    <!-- Mobile topbar button (paste near top of page where appropriate if page is responsive) -->
    <div class="md:hidden bg-white p-3 border-b flex items-center justify-between">
      <div class="flex items-center gap-3">
        <button id="openSidebarMobile" class="p-2 border rounded">Menu</button>
        <div class="font-bold">Admin Panel</div>
      </div>
      <div class="text-sm text-gray-600"><?php echo $displayName ?? 'Admin'; ?></div>
    </div>

    <main class="flex-1 mt-6">
      <div class="bg-white rounded-lg shadow p-6 space-y-6">
        <div class="flex items-center justify-between mb-2">
          <h2 class="text-xl font-semibold">Export Data — CSV</h2>
          <div id="status" class="text-sm text-gray-500"></div>
        </div>

        <!-- Export Menus -->
        <section class="p-4 border rounded">
          <div class="flex items-center justify-between">
            <div>
              <div class="font-medium">Export Menus</div>
              <div class="text-sm text-gray-500">Unduh semua data menu sebagai CSV</div>
            </div>
            <div>
              <button id="btnExportMenus" class="px-4 py-2 bg-slate-900 text-white rounded">Export Menus</button>
            </div>
          </div>
        </section>

        <!-- Export Promotions -->
        <section class="p-4 border rounded">
          <div class="flex items-center justify-between">
            <div>
              <div class="font-medium">Export Promotions</div>
              <div class="text-sm text-gray-500">Unduh semua data promosi sebagai CSV</div>
            </div>
            <div>
              <button id="btnExportPromos" class="px-4 py-2 bg-slate-900 text-white rounded">Export Promotions</button>
            </div>
          </div>
        </section>

        <!-- Export Orders -->
        <section class="p-4 border rounded">
          <div class="mb-3">
            <div class="font-medium">Export Orders (Transaksi)</div>
            <div class="text-sm text-gray-500">Pilih rentang tanggal lalu unduh CSV</div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
            <div>
              <label class="text-xs text-gray-500">Dari</label>
              <input id="startDate" type="date" class="w-full p-2 border rounded" />
            </div>
            <div>
              <label class="text-xs text-gray-500">Sampai</label>
              <input id="endDate" type="date" class="w-full p-2 border rounded" />
            </div>
            <div>
              <button id="btnExportOrders" class="w-full px-4 py-2 bg-slate-900 text-white rounded">Export Orders</button>
            </div>
          </div>

          <div class="mt-3 text-sm text-gray-500">Catatan: akan mengambil semua baris (per_page=10000). Jika data sangat besar, batasi rentang tanggal.</div>
        </section>

      </div>
    </main>
  </div>

  <script>
    (function() {
      const API_EXPORT = '/api/export.php';

      function setStatus(msg, isError = false) {
        const s = document.getElementById('status');
        s.innerText = msg || '';
        s.className = isError ? 'text-sm text-red-600' : 'text-sm text-gray-500';
      }

      // open export (download) URL in new tab/window
      function openExportURL(url) {
        // open in new window/tab so Content-Disposition is handled by browser
        window.open(url, '_blank');
      }

      // fallback client-side CSV (only if server export fails)
      function fallbackAlert(msg) {
        alert('Fallback: ' + msg + '\nSilakan hubungi admin jika masalah berlanjut.');
      }

      document.getElementById('btnExportMenus').addEventListener('click', function() {
        setStatus('Memproses export menus ...');
        const u = new URL(API_EXPORT, location.origin);
        u.searchParams.set('type', 'menus');
        // open download
        try {
          openExportURL(u.toString());
          setStatus('Permintaan export dikirim. Unduhan akan dimulai (jika server merespons).');
        } catch (e) {
          console.error(e);
          setStatus('Gagal memulai export (client)', true);
          fallbackAlert('Gagal memulai export menus.');
        }
      });

      document.getElementById('btnExportPromos').addEventListener('click', function() {
        setStatus('Memproses export promotions ...');
        const u = new URL(API_EXPORT, location.origin);
        u.searchParams.set('type', 'promotions');
        try {
          openExportURL(u.toString());
          setStatus('Permintaan export dikirim. Unduhan akan dimulai (jika server merespons).');
        } catch (e) {
          console.error(e);
          setStatus('Gagal memulai export (client)', true);
          fallbackAlert('Gagal memulai export promotions.');
        }
      });

      document.getElementById('btnExportOrders').addEventListener('click', function() {
        const s = document.getElementById('startDate').value;
        const e = document.getElementById('endDate').value;
        if (!s || !e) {
          alert('Isi tanggal mulai dan sampai');
          return;
        }
        setStatus('Memproses export orders ...');
        const u = new URL(API_EXPORT, location.origin);
        u.searchParams.set('type', 'orders');
        u.searchParams.set('start', s);
        u.searchParams.set('end', e);
        try {
          openExportURL(u.toString());
          setStatus('Permintaan export dikirim. Unduhan akan dimulai (jika server merespons).');
        } catch (err) {
          console.error(err);
          setStatus('Gagal memulai export (client)', true);
          fallbackAlert('Gagal memulai export orders.');
        }
      });

      // init sidebar handlers (keep your existing markActive)
      document.getElementById('openSidebarMobile')?.addEventListener('click', function() {
        const s = document.getElementById('adminSidebar');
        if (!s) return;
        s.classList.toggle('hidden');
      });
      document.getElementById('logoutBtnSmall')?.addEventListener('click', async function() {
        try {
          await fetch('../api/auth.php?action=logout', {
            method: 'GET',
            credentials: 'same-origin'
          });
        } catch (e) {}
        window.location.href = 'login.php';
      });

      // --- NEW: mark active sidebar item based on filename ---
      function markActive() {
        try {
          const path = location.pathname.split('/').pop().split('?')[0] || '';
          const items = document.querySelectorAll('#adminNav .nav-item');
          items.forEach(a => a.classList.remove('active'));
          let matched = false;
          items.forEach(a => {
            const slug = a.dataset.slug || (a.getAttribute('href') || '').split('/').pop();
            if (!slug) return;
            if (slug === path || slug === '') {
              a.classList.add('active');
              matched = true;
            }
          });
          // fallback: if none matched, try to match by full href ending
          if (!matched) {
            items.forEach(a => {
              const href = a.getAttribute('href') || '';
              if (!href) return;
              if (href.endsWith(path)) a.classList.add('active');
            });
          }
        } catch (err) {
          console.error('markActive error', err);
        }
      }

      // call once on load
      markActive();

      // optional: if SPA navigation used and you change location via history.pushState elsewhere,
      // call markActive() after navigation.
    })();
  </script>


</body>

</html>
