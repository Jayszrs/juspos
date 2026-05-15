<?php
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
    <title>Admin Dashboard — Menus</title>
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

        <!-- Mobile topbar button -->
        <div class="md:hidden bg-white p-3 border-b flex items-center justify-between">
            <div class="flex items-center gap-3">
                <button id="openSidebarMobile" class="p-2 border rounded">Menu</button>
                <div class="font-bold">Admin Panel</div>
            </div>
            <div class="text-sm text-gray-600"><?php echo $displayName ?? 'Admin'; ?></div>
        </div>

        <!-- MAIN -->
        <main class="flex-1 mt-6">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold">Menu — CRUD</h2>
                    <div class="flex gap-2">
                        <button id="btnRefresh" class="px-3 py-2 border rounded">Refresh</button>
                        <button id="btnAdd" class="px-3 py-2 bg-slate-900 text-white rounded">Tambah Menu</button>
                    </div>
                </div>

                <div class="flex gap-3 items-center mb-4">
                    <input id="searchInput" class="flex-1 p-2 border rounded" placeholder="Cari nama / kode..." />
                    <select id="perPage" class="p-2 border rounded">
                        <option value="10">10</option>
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                    </select>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm table-auto">
                        <thead>
                            <tr class="text-left text-xs text-gray-500">
                                <th class="p-2">ID</th>
                                <th class="p-2">Kode</th>
                                <th class="p-2">Nama</th>
                                <th class="p-2">Kategori</th>
                                <th class="p-2">Harga</th>
                                <th class="p-2">Stok</th>
                                <th class="p-2">Avail</th>
                                <th class="p-2">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="menusTbody">
                            <tr>
                                <td colspan="8" class="p-6 text-center text-gray-500">Memuat daftar...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex items-center justify-between">
                    <div>
                        <button id="prevPage" class="px-3 py-1 border rounded">Prev</button>
                        <button id="nextPage" class="px-3 py-1 border rounded">Next</button>
                    </div>
                    <div class="text-sm text-gray-500">Halaman <span id="pageNum">1</span> / <span id="pageTotal">1</span></div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal: create / edit menu -->
    <div id="menuModal" class="fixed inset-0 hidden items-center justify-center z-50 bg-black/40">
        <div class="bg-white rounded-lg w-full max-w-2xl p-6 shadow-lg">
            <div class="flex justify-between items-center mb-4">
                <h3 id="menuModalTitle" class="text-lg font-semibold">Tambah Menu</h3>
                <button id="menuModalClose" class="text-sm px-2 py-1 border rounded">Tutup</button>
            </div>
            <form id="menuForm">
                <input type="hidden" id="menuId" />
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs">Kode (opsional)</label>
                        <input id="menuCode" class="w-full p-2 border rounded" placeholder="AUTO jika kosong" />
                    </div>
                    <div>
                        <label class="text-xs">Nama</label>
                        <input id="menuName" class="w-full p-2 border rounded" required />
                    </div>
                    <div>
                        <label class="text-xs">Harga</label>
                        <input id="menuPrice" type="number" class="w-full p-2 border rounded" required />
                    </div>
                    <div>
                        <label class="text-xs">Stok</label>
                        <input id="menuStock" type="number" class="w-full p-2 border rounded" value="0" />
                    </div>
                    <div>
                        <label class="text-xs">Kategori</label>
                        <select id="menuCategory" class="w-full p-2 border rounded">
                            <option value="">-- Tidak ada / Pilih kategori --</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs">Tersedia</label>
                        <select id="menuAvailable" class="w-full p-2 border rounded">
                            <option value="1">Ya</option>
                            <option value="0">Tidak</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3">
                    <label class="text-xs">Deskripsi</label>
                    <textarea id="menuDesc" rows="3" class="w-full p-2 border rounded"></textarea>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" id="menuSaveBtn" class="px-4 py-2 bg-slate-900 text-white rounded">Simpan</button>
                    <button type="button" id="menuCancelBtn" class="px-4 py-2 border rounded">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function() {
            // mark active nav item based on file name
            function markActive() {
                try {
                    const path = location.pathname.split('/').pop().split('?')[0] || '';
                    const nav = document.getElementById('adminNav');
                    if (!nav) return;
                    const items = nav.querySelectorAll('.nav-item');
                    let matched = false;
                    items.forEach(a => {
                        const href = (a.getAttribute('href') || '').split('/').pop();
                        if (href && href === path) {
                            a.classList.add('active');
                            matched = true;
                        } else {
                            a.classList.remove('active');
                        }
                    });
                    // fallback for admin_dashboard include usage
                    if (!matched) {
                        const params = new URLSearchParams(location.search);
                        const adminParam = params.get('admin');
                        if (adminParam) {
                            items.forEach(a => {
                                const slug = a.dataset.slug || a.getAttribute('href') || '';
                                if (slug === adminParam || slug.endsWith(adminParam)) a.classList.add('active');
                            });
                        }
                    }
                } catch (e) {
                    console.error('markActive err', e);
                }
            }

            // mobile toggle
            document.getElementById('openSidebarMobile')?.addEventListener('click', function() {
                const s = document.getElementById('adminSidebar');
                if (!s) return;
                s.classList.toggle('hidden');
            });

            // logout small
            document.getElementById('logoutBtnSmall')?.addEventListener('click', async function() {
                try {
                    await fetch('../api/auth.php?action=logout', {
                        method: 'GET',
                        credentials: 'same-origin'
                    });
                } catch (e) {}
                window.location.href = 'login.php';
            });

            markActive();
        })();

        (function() {
            const API_ADMIN_MENUS = '../api/admin_menus.php';
            let currentPage = 1;
            const perPageSelect = document.getElementById('perPage');
            let perPage = perPageSelect ? Number(perPageSelect.value || 25) : 25;

            // DOM refs
            const tbody = document.getElementById('menusTbody');
            const searchInput = document.getElementById('searchInput');
            const btnAdd = document.getElementById('btnAdd');
            const btnRefresh = document.getElementById('btnRefresh');

            // modal refs
            const modal = document.getElementById('menuModal');
            const modalTitle = document.getElementById('menuModalTitle');
            const modalClose = document.getElementById('menuModalClose');
            const menuForm = document.getElementById('menuForm');
            const menuSaveBtn = document.getElementById('menuSaveBtn');
            const menuCancelBtn = document.getElementById('menuCancelBtn');
            const menuCategorySelect = document.getElementById('menuCategory');

            let categoriesCache = null; // {id: name, ...}

            function showModal() {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }

            function hideModal() {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }

            if (modalClose) modalClose.addEventListener('click', hideModal);
            if (menuCancelBtn) menuCancelBtn.addEventListener('click', hideModal);

            function buildQuery(params) {
                const a = [];
                for (const k in params) {
                    if (params[k] !== '' && params[k] !== null && params[k] !== undefined) {
                        a.push(encodeURIComponent(k) + '=' + encodeURIComponent(params[k]));
                    }
                }
                return a.length ? ('?' + a.join('&')) : '';
            }

            async function loadCategories() {
                // cache once
                if (categoriesCache) return categoriesCache;
                try {
                    const res = await fetch(API_ADMIN_MENUS + '?categories=1', { credentials: 'same-origin' });
                    if (!res.ok) {
                        console.error('Failed load categories', res.status);
                        categoriesCache = {};
                        return categoriesCache;
                    }
                    const j = await res.json();
                    if (!j || !j.success) {
                        categoriesCache = {};
                        return categoriesCache;
                    }
                    const arr = Array.isArray(j.data) ? j.data : (j.data && j.data.rows ? j.data.rows : []);
                    categoriesCache = {};
                    // populate select
                    menuCategorySelect.innerHTML = '<option value="">-- Tidak ada / Pilih kategori --</option>';
                    arr.forEach(c => {
                        categoriesCache[c.id] = c.name;
                        const opt = document.createElement('option');
                        opt.value = c.id;
                        opt.textContent = c.name;
                        menuCategorySelect.appendChild(opt);
                    });
                    return categoriesCache;
                } catch (e) {
                    console.error('loadCategories err', e);
                    categoriesCache = {};
                    return categoriesCache;
                }
            }

            async function loadMenus() {
                const q = (searchInput && searchInput.value) ? searchInput.value.trim() : '';
                const qs = buildQuery({ page: currentPage, per_page: perPage, q: q || undefined });
                try {
                    const res = await fetch(API_ADMIN_MENUS + qs, { credentials: 'same-origin' });
                    if (!res.ok) {
                        const txt = await res.text().catch(() => '');
                        console.error('HTTP error', res.status, txt);
                        renderFailure('Gagal memuat daftar: HTTP ' + res.status);
                        return;
                    }
                    const j = await res.json();
                    if (!j || !j.success) {
                        renderFailure('Gagal memuat daftar: ' + (j && j.error ? j.error : 'unknown'));
                        return;
                    }
                    const rows = (j.data && j.data.rows) ? j.data.rows : (Array.isArray(j.data) ? j.data : []);
                    renderMenus(rows);
                    const pg = (j.data && j.data.pagination) ? j.data.pagination : { page: 1, total_pages: 1, total_rows: 0 };
                    document.getElementById('pageNum').innerText = pg.page || 1;
                    document.getElementById('pageTotal').innerText = pg.total_pages || 1;
                } catch (e) {
                    console.error(e);
                    renderFailure('Gagal memuat daftar (network)');
                }
            }

            function renderFailure(msg) {
                if (!tbody) return;
                tbody.innerHTML = `<tr><td colspan="8" class="p-6 text-red-600 text-center">${escapeHtml(msg)}</td></tr>`;
            }

            function renderMenus(rows) {
                if (!tbody) return;
                if (!rows || rows.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="8" class="p-6 text-sm text-gray-500">Tidak ada data</td></tr>';
                    return;
                }
                tbody.innerHTML = '';
                rows.forEach(r => {
                    const tr = document.createElement('tr');
                    tr.className = 'border-t hover:bg-slate-50';
                    const catName = r.category_name || '';
                    tr.innerHTML = `
        <td class="p-2">${escapeHtml(String(r.id || ''))}</td>
        <td class="p-2">${escapeHtml(r.code || '')}</td>
        <td class="p-2">${escapeHtml(r.name || '')}</td>
        <td class="p-2">${escapeHtml(catName)}</td>
        <td class="p-2">${escapeHtml(formatIDR(Number(r.price || 0)))}</td>
        <td class="p-2">${escapeHtml(String(r.stock || 0))}</td>
        <td class="p-2">${Number(r.available) === 1 ? 'Yes' : 'No'}</td>
        <td class="p-2">
          <button class="edit-btn px-2 py-1 border rounded" data-id="${r.id}">Edit</button>
          <button class="del-btn px-2 py-1 border rounded text-red-600" data-id="${r.id}">Delete</button>
        </td>
      `;
                    tbody.appendChild(tr);
                });

                // attach handlers
                tbody.querySelectorAll('.edit-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        openEdit(id);
                    });
                });
                tbody.querySelectorAll('.del-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        confirmDelete(id);
                    });
                });
            }

            function escapeHtml(s) {
                if (s == null) return '';
                return String(s).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
            }

            function formatIDR(n) {
                return new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    maximumFractionDigits: 0
                }).format(Math.round(n || 0));
            }

            // create modal
            if (btnAdd) {
                btnAdd.addEventListener('click', async function() {
                    modalTitle.innerText = 'Tambah Menu';
                    if (menuForm) menuForm.reset();
                    document.getElementById('menuId').value = '';
                    // ensure categories loaded
                    await loadCategories();
                    // make sure select default is empty
                    menuCategorySelect.value = '';
                    showModal();
                });
            }

            async function openEdit(id) {
                try {
                    // ensure categories loaded first so select has options
                    await loadCategories();
                    const res = await fetch(API_ADMIN_MENUS + buildQuery({ id }), { credentials: 'same-origin' });
                    if (!res.ok) {
                        alert('Gagal memuat item (HTTP ' + res.status + ')');
                        return;
                    }
                    const j = await res.json();
                    if (!j || !j.success) {
                        alert('Gagal memuat item: ' + (j && j.error ? j.error : 'unknown'));
                        return;
                    }
                    const d = j.data;
                    modalTitle.innerText = 'Edit Menu';
                    document.getElementById('menuId').value = d.id || '';
                    document.getElementById('menuCode').value = d.code || '';
                    document.getElementById('menuName').value = d.name || '';
                    document.getElementById('menuPrice').value = d.price || 0;
                    document.getElementById('menuStock').value = d.stock || 0;
                    // set select: if category_id null -> ''
                    menuCategorySelect.value = (d.category_id === null || d.category_id === undefined) ? '' : String(d.category_id);
                    document.getElementById('menuAvailable').value = (d.available == 1 ? '1' : '0');
                    document.getElementById('menuDesc').value = d.description || '';
                    showModal();
                } catch (e) {
                    console.error(e);
                    alert('Gagal membuka editor');
                }
            }

            async function confirmDelete(id) {
                if (!confirm('Hapus menu ini?')) return;
                try {
                    const res = await fetch(API_ADMIN_MENUS, {
                        method: 'DELETE',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: id })
                    });
                    const j = await res.json();
                    if (j && j.success) {
                        alert('Terhapus');
                        loadMenus();
                    } else {
                        alert('Hapus gagal: ' + (j && j.error ? j.error : 'unknown'));
                    }
                } catch (e) {
                    console.error(e);
                    alert('Hapus gagal (network)');
                }
            }

            // save (create or update)
            if (menuSaveBtn) {
                menuSaveBtn.addEventListener('click', async function() {
                    const id = document.getElementById('menuId').value;
                    // read category select value; if '' -> null
                    const catVal = menuCategorySelect.value === '' ? null : Number(menuCategorySelect.value);
                    const payload = {
                        id: id ? Number(id) : undefined,
                        code: (document.getElementById('menuCode').value || '').trim(),
                        name: (document.getElementById('menuName').value || '').trim(),
                        price: Number(document.getElementById('menuPrice').value || 0),
                        stock: Number(document.getElementById('menuStock').value || 0),
                        category_id: catVal,
                        available: (document.getElementById('menuAvailable').value === '1' ? 1 : 0),
                        description: (document.getElementById('menuDesc').value || '').trim()
                    };

                    if (!payload.name) {
                        alert('Nama diperlukan');
                        return;
                    }

                    try {
                        let res;
                        if (!id) {
                            res = await fetch(API_ADMIN_MENUS, {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify(payload)
                            });
                        } else {
                            payload.id = Number(id);
                            res = await fetch(API_ADMIN_MENUS, {
                                method: 'PUT',
                                credentials: 'same-origin',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify(payload)
                            });
                        }

                        if (!res.ok) {
                            const txt = await res.text().catch(() => '');
                            console.error('Save HTTP error', res.status, txt);
                            alert('Gagal menyimpan: HTTP ' + res.status);
                            return;
                        }

                        const j = await res.json();
                        if (j && j.success) {
                            hideModal();
                            loadMenus();
                        } else {
                            alert('Gagal menyimpan: ' + (j && j.error ? j.error : 'unknown'));
                        }
                    } catch (e) {
                        console.error(e);
                        alert('Gagal menyimpan (network)');
                    }
                });
            }

            // search & pagination hooks
            if (searchInput) {
                searchInput.addEventListener('input', debounce(function() {
                    currentPage = 1;
                    loadMenus();
                }, 400));
            }

            if (perPageSelect) {
                perPageSelect.addEventListener('change', function(e) {
                    perPage = Number(e.target.value || 25);
                    currentPage = 1;
                    loadMenus();
                });
            }

            document.getElementById('prevPage')?.addEventListener('click', function() {
                if (currentPage > 1) {
                    currentPage--;
                    loadMenus();
                }
            });
            document.getElementById('nextPage')?.addEventListener('click', function() {
                const total = Number(document.getElementById('pageTotal')?.innerText || '1');
                if (currentPage < total) {
                    currentPage++;
                    loadMenus();
                }
            });

            if (btnRefresh) btnRefresh.addEventListener('click', loadMenus);

            function debounce(fn, wait = 300) {
                let t;
                return function() {
                    const args = arguments;
                    clearTimeout(t);
                    t = setTimeout(function() {
                        fn.apply(null, args);
                    }, wait);
                };
            }

            // initial: load categories first (so modal select is ready), then menus
            (async function init() {
                await loadCategories();
                loadMenus();
                // expose for debugging
                window.__adminMenus = { loadMenus, loadCategories };
            })();

        })();
    </script>


</body>

</html>
