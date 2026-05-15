<?php
// public/admin_members.php
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
    <title>Admin Dashboard — Members</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .nav-item.active {
            background: rgba(15, 23, 42, 0.06);
            font-weight: 600;
        }

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

        <div class="md:hidden bg-white p-3 border-b flex items-center justify-between">
            <div class="flex items-center gap-3">
                <button id="openSidebarMobile" class="p-2 border rounded">Menu</button>
                <div class="font-bold">Admin Panel</div>
            </div>
            <div class="text-sm text-gray-600"><?php echo $displayName ?? 'Admin'; ?></div>
        </div>

        <main class="flex-1 mt-6">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold">Members — CRUD</h2>
                    <div class="flex gap-2">
                        <button id="btnRefresh" class="px-3 py-2 border rounded">Refresh</button>
                        <button id="btnAdd" class="px-3 py-2 bg-slate-900 text-white rounded">Tambah Member</button>
                    </div>
                </div>

                <div class="flex gap-3 items-center mb-4">
                    <input id="searchInput" class="flex-1 p-2 border rounded" placeholder="Cari code / nama / telepon..." />
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
                                <th class="p-2">Telepon</th>
                                <th class="p-2">Created</th>
                                <th class="p-2">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="membersTbody">
                            <tr>
                                <td colspan="6" class="p-6 text-center text-gray-500">Memuat daftar...</td>
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

    <!-- Modal -->
    <div id="memberModal" class="fixed inset-0 hidden items-center justify-center z-50 bg-black/40">
        <div class="bg-white rounded-lg w-full max-w-2xl p-6 shadow-lg">
            <div class="flex justify-between items-center mb-4">
                <h3 id="memberModalTitle" class="text-lg font-semibold">Tambah Member</h3>
                <button id="memberModalClose" class="text-sm px-2 py-1 border rounded">Tutup</button>
            </div>
            <form id="memberForm" onsubmit="return false;">
                <input type="hidden" id="memberId" />
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs">Kode</label>
                        <input id="memberCode" class="w-full p-2 border rounded" />
                    </div>
                    <div>
                        <label class="text-xs">Nama</label>
                        <input id="memberName" class="w-full p-2 border rounded" required />
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs">Telepon</label>
                        <input id="memberPhone" class="w-full p-2 border rounded" />
                    </div>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" id="memberSaveBtn" class="px-4 py-2 bg-slate-900 text-white rounded">Simpan</button>
                    <button type="button" id="memberCancelBtn" class="px-4 py-2 border rounded">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function() {
            // mark active nav item
            function markActive() {
                try {
                    const path = location.pathname.split('/').pop().split('?')[0] || '';
                    const nav = document.getElementById('adminNav');
                    if (!nav) return;
                    const items = nav.querySelectorAll('.nav-item, a.nav-item');
                    items.forEach(a => {
                        const href = (a.getAttribute('href') || '').split('/').pop();
                        if (href && href === path) a.classList.add('active');
                        else a.classList.remove('active');
                    });
                } catch (e) {
                    console.error(e);
                }
            }

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

            markActive();
        })();

        (function() {
            const API = '../api/admin_members.php';
            let currentPage = 1;
            let perPage = Number(document.getElementById('perPage')?.value || 25);

            const tbody = document.getElementById('membersTbody');
            const searchInput = document.getElementById('searchInput');
            const perPageEl = document.getElementById('perPage');
            const btnAdd = document.getElementById('btnAdd');
            const btnRefresh = document.getElementById('btnRefresh');

            const modal = document.getElementById('memberModal');
            const modalTitle = document.getElementById('memberModalTitle');
            const modalClose = document.getElementById('memberModalClose');
            const memberSaveBtn = document.getElementById('memberSaveBtn');
            const memberCancelBtn = document.getElementById('memberCancelBtn');

            function showModal() {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }

            function hideModal() {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }

            modalClose?.addEventListener('click', hideModal);
            memberCancelBtn?.addEventListener('click', hideModal);

            function buildQs(o) {
                const p = [];
                for (const k in o)
                    if (o[k] !== undefined && o[k] !== null && o[k] !== '') p.push(encodeURIComponent(k) + '=' + encodeURIComponent(o[k]));
                return p.length ? ('?' + p.join('&')) : '';
            }

            async function loadMembers() {
                const q = (searchInput && searchInput.value) ? searchInput.value.trim() : '';
                const qs = buildQs({
                    page: currentPage,
                    per_page: perPage,
                    q: q || undefined
                });
                const url = API + qs;
                try {
                    const res = await fetch(url, {
                        credentials: 'same-origin'
                    });
                    if (!res.ok) {
                        const txt = await res.text().catch(() => '');
                        tbody.innerHTML = '<tr><td colspan="6" class="p-6 text-red-600 text-center">Gagal memuat daftar: HTTP ' + res.status + '</td></tr>';
                        console.error('loadMembers error', res.status, txt);
                        return;
                    }
                    const j = await res.json();
                    if (!j || !j.success) {
                        tbody.innerHTML = '<tr><td colspan="6" class="p-6 text-red-600 text-center">Gagal memuat daftar: ' + (j && j.error ? j.error : 'unknown') + '</td></tr>';
                        return;
                    }
                    const rows = (j.data && j.data.rows) ? j.data.rows : [];
                    renderRows(rows);
                    const pg = (j.data && j.data.pagination) ? j.data.pagination : {
                        page: 1,
                        total_pages: 1
                    };
                    document.getElementById('pageNum').innerText = pg.page || 1;
                    document.getElementById('pageTotal').innerText = pg.total_pages || 1;
                } catch (e) {
                    console.error(e);
                    tbody.innerHTML = '<tr><td colspan="6" class="p-6 text-red-600 text-center">Gagal memuat daftar (network)</td></tr>';
                }
            }

            function renderRows(rows) {
                if (!rows || rows.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="p-6 text-sm text-gray-500">Tidak ada data</td></tr>';
                    return;
                }
                tbody.innerHTML = '';
                rows.forEach(r => {
                    const tr = document.createElement('tr');
                    tr.className = 'border-t hover:bg-slate-50';
                    tr.innerHTML = `
            <td class="p-2">${escapeHtml(String(r.id||''))}</td>
            <td class="p-2">${escapeHtml(r.code||'')}</td>
            <td class="p-2">${escapeHtml(r.name||'')}</td>
            <td class="p-2">${escapeHtml(r.phone||'')}</td>
            <td class="p-2">${escapeHtml(r.created_at||'')}</td>
            <td class="p-2">
              <button class="edit-btn px-2 py-1 border rounded" data-id="${r.id}">Edit</button>
              <button class="del-btn px-2 py-1 border rounded text-red-600" data-id="${r.id}">Delete</button>
            </td>`;
                    tbody.appendChild(tr);
                });

                tbody.querySelectorAll('.edit-btn').forEach(b => {
                    b.addEventListener('click', async function() {
                        const id = this.getAttribute('data-id');
                        try {
                            const res = await fetch(API + '?id=' + encodeURIComponent(id), {
                                credentials: 'same-origin'
                            });
                            if (!res.ok) {
                                alert('Gagal memuat member (HTTP ' + res.status + ')');
                                return;
                            }
                            const j = await res.json();
                            if (!j || !j.success) {
                                alert('Gagal memuat member');
                                return;
                            }
                            const d = j.data;
                            document.getElementById('memberId').value = d.id || '';
                            document.getElementById('memberCode').value = d.code || '';
                            document.getElementById('memberName').value = d.name || '';
                            document.getElementById('memberPhone').value = d.phone || '';
                            modalTitle.innerText = 'Edit Member';
                            showModal();
                        } catch (e) {
                            console.error(e);
                            alert('Gagal memuat member');
                        }
                    });
                });

                tbody.querySelectorAll('.del-btn').forEach(b => {
                    b.addEventListener('click', async function() {
                        const id = this.getAttribute('data-id');
                        if (!confirm('Hapus member ini?')) return;
                        try {
                            const res = await fetch(API, {
                                method: 'DELETE',
                                credentials: 'same-origin',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    id: id
                                })
                            });
                            const j = await res.json();
                            if (j && j.success) {
                                alert('Terhapus');
                                loadMembers();
                            } else alert('Hapus gagal: ' + (j && j.error ? j.error : 'unknown'));
                        } catch (e) {
                            console.error(e);
                            alert('Hapus gagal (network)');
                        }
                    });
                });
            }

            function escapeHtml(s) {
                if (s == null) return '';
                return String(s).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", "&#039;");
            }

            // open add
            btnAdd?.addEventListener('click', () => {
                modalTitle.innerText = 'Tambah Member';
                document.getElementById('memberForm').reset();
                document.getElementById('memberId').value = '';
                showModal();
            });

            memberSaveBtn?.addEventListener('click', async () => {
                const id = (document.getElementById('memberId').value || '').trim();
                const payload = {
                    code: (document.getElementById('memberCode').value || '').trim(),
                    name: (document.getElementById('memberName').value || '').trim(),
                    phone: (document.getElementById('memberPhone').value || '').trim()
                };
                if (!payload.name) {
                    alert('Nama diperlukan');
                    return;
                }

                try {
                    let res;
                    if (!id) {
                        res = await fetch(API, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify(payload)
                        });
                    } else {
                        payload.id = Number(id);
                        res = await fetch(API, {
                            method: 'PUT',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify(payload)
                        });
                    }
                    if (!res.ok) {
                        const txt = await res.text().catch(() => '');
                        console.error('HTTP error', res.status, txt);
                        alert('Gagal menyimpan: HTTP ' + res.status);
                        return;
                    }
                    const j = await res.json();
                    if (j && j.success) {
                        hideModal();
                        loadMembers();
                    } else alert('Gagal menyimpan: ' + (j && j.error ? j.error : 'unknown'));
                } catch (e) {
                    console.error(e);
                    alert('Gagal menyimpan (network)');
                }
            });

            // search / pagination
            searchInput?.addEventListener('input', debounce(() => {
                currentPage = 1;
                loadMembers();
            }, 350));
            perPageEl?.addEventListener('change', () => {
                perPage = Number(perPageEl.value || 25);
                currentPage = 1;
                loadMembers();
            });
            document.getElementById('prevPage')?.addEventListener('click', () => {
                if (currentPage > 1) {
                    currentPage--;
                    loadMembers();
                }
            });
            document.getElementById('nextPage')?.addEventListener('click', () => {
                const total = Number(document.getElementById('pageTotal')?.innerText || '1');
                if (currentPage < total) {
                    currentPage++;
                    loadMembers();
                }
            });
            btnRefresh?.addEventListener('click', loadMembers);

            function debounce(fn, wait = 300) {
                let t;
                return function() {
                    const args = arguments;
                    clearTimeout(t);
                    t = setTimeout(() => fn.apply(null, args), wait);
                }
            }

            // boot
            loadMembers();
            window.adminMembers = {
                loadMembers
            };
        })();
    </script>
</body>

</html>