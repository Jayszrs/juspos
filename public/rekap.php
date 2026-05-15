<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$displayRole = $_SESSION['user_role'] ?? '';
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Rekap Penjualan — POS Toko Jus</title>

    <!-- Tailwind CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        /* small custom styles for spinner & skeleton */
        .spinner {
            border: 4px solid rgba(0, 0, 0, 0.08);
            border-top-color: rgba(2, 6, 23, 0.9);
            border-radius: 9999px;
            width: 28px;
            height: 28px;
            animation: spin .9s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .skeleton {
            background: linear-gradient(90deg, rgba(0, 0, 0, 0.04), rgba(0, 0, 0, 0.06), rgba(0, 0, 0, 0.04));
            background-size: 200% 100%;
            animation: shimmer 1.2s linear infinite;
        }

        @keyframes shimmer {
            0% {
                background-position: -200% 0
            }

            100% {
                background-position: 200% 0
            }
        }

        /* pill active */
        .pill-active {
            box-shadow: 0 4px 14px rgba(2, 6, 23, 0.08);
            transform: translateY(-2px);
        }
    </style>
</head>

<body class="bg-gray-50 text-gray-800">
    <div class="max-w-7xl mx-auto p-6">
        <header class="flex items-center justify-between mb-6">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold">Rekap Penjualan</h1>
                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($_SESSION['user_name'] ?? '-'); ?><?php if ($displayRole) echo ' • ' . htmlspecialchars($displayRole); ?></div>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="index.php" class="px-3 py-2 bg-white border rounded-lg hover:shadow-md transition">← Kembali</a>
                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'LEADER'): ?>
                    <a href="rekap.php" class="px-3 py-2 bg-white border rounded-lg hover:shadow-md transition flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-700" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M3 3h2v12H3V3zM8 7h2v8H8V7zM13 1h2v14h-2V1z" />
                        </svg>
                        Rekap
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <!-- Filters / Controls -->
        <div class="bg-white p-4 rounded-lg shadow mb-6">
            <div class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
                <div>
                    <label class="text-xs text-gray-500">Dari</label>
                    <input id="startDate" type="date" class="w-full p-2 border rounded focus:ring-1 focus:ring-slate-300" />
                </div>
                <div>
                    <label class="text-xs text-gray-500">Sampai</label>
                    <input id="endDate" type="date" class="w-full p-2 border rounded focus:ring-1 focus:ring-slate-300" />
                </div>
                <div>
                    <label class="text-xs text-gray-500">Kasir</label>
                    <select id="filterCashier" class="w-full p-2 border rounded hover:shadow-sm transition">
                        <option value="">Semua</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-gray-500">Metode Bayar</label>
                    <select id="filterMethod" class="w-full p-2 border rounded hover:shadow-sm transition">
                        <option value="">Semua</option>
                        <option value="CASH">CASH</option>
                        <option value="CARD">CARD</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-gray-500">Kategori</label>
                    <select id="filterCategory" class="w-full p-2 border rounded hover:shadow-sm transition">
                        <option value="">Semua</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button id="btnApply" class="px-4 py-2 bg-slate-900 text-white rounded hover:bg-slate-800 transition shadow-sm transform ">Terapkan</button>
                    <button id="btnReset" class="px-4 py-2 border rounded hover:shadow-sm transition">Reset</button>
                </div>
            </div>

            <!-- presets and group controls -->
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2">
                    <button class="preset-btn px-3 py-1 border rounded text-sm hover:bg-slate-50 transition" data-days="0">Hari ini</button>
                    <button class="preset-btn px-3 py-1 border rounded text-sm hover:bg-slate-50 transition" data-days="6">7 Hari</button>
                    <button class="preset-btn px-3 py-1 border rounded text-sm hover:bg-slate-50 transition" data-days="29">30 Hari</button>
                    <button class="preset-btn px-3 py-1 border rounded text-sm hover:bg-slate-50 transition" data-range="month">Bulan Ini</button>
                </div>

                <div class="ml-4 flex items-center gap-2">
                    <div class="text-xs text-gray-500 mr-2">Group by</div>
                    <div id="groupControls" class="flex gap-2">
                        <button data-group="day" class="group-btn px-3 py-1 bg-white border rounded text-sm hover:shadow-sm transition pill-active">Day</button>
                        <button data-group="week" class="group-btn px-3 py-1 bg-white border rounded text-sm hover:shadow-sm transition">Week</button>
                        <button data-group="month" class="group-btn px-3 py-1 bg-white border rounded text-sm hover:shadow-sm transition">Month</button>
                        <button data-group="category" class="group-btn px-3 py-1 bg-white border rounded text-sm hover:shadow-sm transition">Category</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI Cards -->
        <div id="summaryCards" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="skeleton p-6 rounded-lg"></div>
            <div class="skeleton p-6 rounded-lg"></div>
            <div class="skeleton p-6 rounded-lg"></div>
            <div class="skeleton p-6 rounded-lg"></div>
        </div>

        <!-- Chart + Methods -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="lg:col-span-2 bg-white p-4 rounded-lg shadow">
                <div class="flex items-center justify-between mb-3">
                    <div class="font-medium">Grafik Penjualan</div>
                    <div id="chartSpinner" class="hidden">
                        <div class="spinner"></div>
                    </div>
                </div>
                <canvas id="salesChart" height="120"></canvas>
            </div>

            <div class="bg-white p-4 rounded-lg shadow">
                <div class="mb-2 font-medium">Metode Bayar</div>
                <div id="methodsList" class="text-sm text-gray-600">Memuat...</div>
            </div>
        </div>

        <!-- Transactions -->
        <div class="bg-white p-4 rounded-lg shadow">
            <div class="flex items-center justify-between mb-4">
                <div class="font-medium">Daftar Transaksi <span id="totalRows" class="text-sm text-gray-500"></span></div>
                <div class="flex items-center gap-2">
                    <button id="exportCsv" class="px-3 py-1 border rounded hover:bg-slate-50 transition">Export CSV</button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table id="ordersTable" class="w-full text-sm table-auto">
                    <thead>
                        <tr class="text-left text-xs text-gray-500">
                            <th class="p-2">No. Order</th>
                            <th class="p-2">Tanggal</th>
                            <th class="p-2">Kasir</th>
                            <th class="p-2">Member</th>
                            <th class="p-2">Tipe</th>
                            <th class="p-2">Items</th>
                            <th class="p-2">Pembayaran</th>
                            <th class="p-2">Total</th>
                            <th class="p-2">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="ordersTbody">
                        <tr>
                            <td colspan="9" class="p-8 text-center text-gray-500">Memuat transaksi...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <button id="prevPage" class="px-3 py-1 border rounded hover:bg-slate-50 transition">Prev</button>
                    <button id="nextPage" class="px-3 py-1 border rounded hover:bg-slate-50 transition">Next</button>
                </div>
                <div class="text-sm text-gray-500">Halaman <span id="pageNum">1</span> / <span id="pageTotal">1</span></div>
            </div>
        </div>

    </div>

    <script>
        /************************************************************************
         * rekap.js (rapih & didokumentasikan)
         * - Tujuan: menampilkan ringkasan, grafik, metode pembayaran, dan daftar order
         * - API: ../api/rekap.php?action=...
         *
         * Struktur file JS:
         * 1) Konstanta & state
         * 2) Helpers (format, safeJson, arrayFromResp)
         * 3) Build query
         * 4) Loaders (dropdowns, summary, chart, methods, orders)
         * 5) Renderers (summary cards, chart builders, orders table)
         * 6) Event bindings (buttons, pagination, presets)
         * 7) Bootstrap (init)
         ************************************************************************/

        /* =========================
           1) Konstanta & state
           ========================= */
        const API = '../api/rekap.php'; // relatif terhadap public/
        let salesChart = null;
        let currentPage = 1,
            perPage = 10;
        let currentGroup = 'day';

        /* =========================
           2) Helpers
           ========================= */

        // format tanggal ISO (YYYY-MM-DD)
        function isoDate(d) {
            return d.toISOString().slice(0, 10);
        }

        // format rupiah tampilan
        function formatIDR(n) {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                maximumFractionDigits: 0
            }).format(Math.round(n || 0));
        }

        // escape html sederhana untuk output tabel
        function escapeHtml(s) {
            if (s == null) return '';
            return String(s).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
        }

        // debounce util
        function debounce(fn, wait = 350) {
            let t;
            return (...a) => {
                clearTimeout(t);
                t = setTimeout(() => fn(...a), wait);
            };
        }

        // safeJson: selalu mengembalikan object, tidak melempar exception saat response bukan JSON
        async function safeJson(resp) {
            if (!resp) return {
                success: false,
                error: 'no response'
            };
            const txt = await resp.text().catch(() => '');
            try {
                return JSON.parse(txt);
            } catch (e) {
                return {
                    success: false,
                    error: 'Invalid JSON',
                    status: resp.status,
                    raw: txt
                };
            }
        }

        // universal extractor: ambil array dari berbagai bentuk response (data, data.rows, data.data, langsung array, dsb)
        function arrayFromResp(j) {
            if (!j) return [];
            if (Array.isArray(j)) return j;
            if (Array.isArray(j.data)) return j.data;
            if (j.data && Array.isArray(j.data.rows)) return j.data.rows;
            if (j.data && Array.isArray(j.data.data)) return j.data.data;
            // sometimes server returns { data: { data: [...] } }
            if (j.data && typeof j.data === 'object') {
                for (const k in j.data) {
                    if (Array.isArray(j.data[k])) return j.data[k];
                }
            }
            // fallback: check top-level keys for arrays
            for (const k in j) {
                if (Array.isArray(j[k])) return j[k];
            }
            return [];
        }

        /* =========================
           3) Build query helper
           ========================= */
        function buildQuery(params) {
            const qs = new URLSearchParams(params || {}).toString();
            return API + (qs ? ('?' + qs) : '');
        }

        /* =========================
           4) Loaders: mengambil data dari API
           ========================= */

        // load dropdowns: kasir & kategori
        async function loadDropdowns() {
            try {
                const [cashRes, catRes] = await Promise.all([
                    fetch(buildQuery({
                        action: 'list_cashiers'
                    }), {
                        credentials: 'same-origin'
                    }),
                    fetch(buildQuery({
                        action: 'list_categories'
                    }), {
                        credentials: 'same-origin'
                    })
                ]);
                const cashJ = await safeJson(cashRes);
                const catJ = await safeJson(catRes);

                // isi kasir
                const cashSel = document.getElementById('filterCashier');
                const cashArr = arrayFromResp(cashJ);
                cashArr.forEach(c => {
                    const o = document.createElement('option');
                    o.value = c.id ?? c.user_id ?? ''; // support varian
                    o.textContent = c.name ?? c.username ?? String(c.id ?? '');
                    cashSel.appendChild(o);
                });

                // isi kategori
                const catSel = document.getElementById('filterCategory');
                const catArr = arrayFromResp(catJ);
                catArr.forEach(c => {
                    const o = document.createElement('option');
                    o.value = c.id ?? '';
                    o.textContent = c.name ?? String(c.id ?? '');
                    catSel.appendChild(o);
                });

            } catch (e) {
                console.error('loadDropdowns err', e);
                // jangan ganggu UX — tetap lanjutkan tanpa dropdown lengkap
            }
        }

        // load ringkasan KPI
        async function loadSummary() {
            const p = qs();
            try {
                const res = await fetch(buildQuery({
                    action: 'summary',
                    start: p.start,
                    end: p.end
                }), {
                    credentials: 'same-origin'
                });
                const j = await safeJson(res);
                // j.data mungkin berbentuk object; renderSummary menerima object
                if (j && j.success) renderSummary(j.data || j);
                else console.warn('summary returned unexpected:', j);
            } catch (e) {
                console.error('summary err', e);
            }
        }

        // load daily/group chart
        async function loadDailyChart() {
            const p = qs();
            document.getElementById('chartSpinner').classList.remove('hidden');
            try {
                if (currentGroup === 'category') {
                    const res = await fetch(buildQuery({
                        action: 'group',
                        type: 'category',
                        start: p.start,
                        end: p.end
                    }), {
                        credentials: 'same-origin'
                    });
                    const j = await safeJson(res);
                    const rows = arrayFromResp(j);
                    if (!rows || rows.length === 0) {
                        clearChart();
                        return;
                    }
                    buildCategoryChart(rows);
                } else {
                    const res = await fetch(buildQuery({
                        action: 'daily',
                        start: p.start,
                        end: p.end
                    }), {
                        credentials: 'same-origin'
                    });
                    const j = await safeJson(res);
                    const rows = arrayFromResp(j);
                    if (!rows || rows.length === 0) {
                        clearChart();
                        return;
                    }
                    const labels = rows.map(x => x.day || x.label || x.date);
                    const revenue = rows.map(x => Number(x.revenue || x.total || 0));
                    const ordersCount = rows.map(x => Number(x.orders_count || x.count || x.orders || 0));
                    buildDualChart(labels, revenue, ordersCount);
                }
            } catch (e) {
                console.error('daily chart err', e);
                clearChart();
            }
            document.getElementById('chartSpinner').classList.add('hidden');
        }

        // load metode pembayaran (breakdown)
        async function loadMethods() {
            const p = qs();
            try {
                const res = await fetch(buildQuery({
                    action: 'methods',
                    start: p.start,
                    end: p.end
                }), {
                    credentials: 'same-origin'
                });
                const j = await safeJson(res);
                const rows = arrayFromResp(j);
                const root = document.getElementById('methodsList');
                root.innerHTML = '';
                if (!rows || rows.length === 0) {
                    root.innerHTML = '<div class="text-sm text-gray-500">Belum ada pembayaran</div>';
                    return;
                }
                rows.forEach(m => {
                    const d = document.createElement('div');
                    d.className = 'mb-2';
                    d.innerHTML = `<div class="font-medium">${escapeHtml(m.method||m.name||'')}</div><div class="text-xs text-gray-500">${m.cnt||m.count||0} transaksi • ${formatIDR(m.total_amount||m.total||0)}</div>`;
                    root.appendChild(d);
                });
            } catch (e) {
                console.error('methods err', e);
            }
        }

        // load daftar order (paged + filter)
        async function loadOrders() {
            const p = qs();
            const params = {
                action: 'orders',
                start: p.start,
                end: p.end,
                q: p.q || '',
                page: currentPage,
                per_page: perPage
            };
            if (p.cashier_id) params.cashier_id = p.cashier_id;
            if (p.payment_method) params.payment_method = p.payment_method;
            if (p.category_id) params.category_id = p.category_id;

            try {
                const res = await fetch(buildQuery(params), {
                    credentials: 'same-origin'
                });
                const j = await safeJson(res);
                if (j && j.success && j.data) {
                    const rows = j.data.rows || j.data;
                    renderOrders(rows || []);
                    const pg = j.data.pagination || {};
                    document.getElementById('pageNum').textContent = pg.page || 1;
                    document.getElementById('pageTotal').textContent = pg.total_pages || 1;
                    document.getElementById('totalRows').textContent = '(' + (pg.total_rows || 0) + ' rows)';
                } else if (j && j.success && Array.isArray(j.data)) {
                    renderOrders(j.data);
                    document.getElementById('pageNum').textContent = 1;
                    document.getElementById('pageTotal').textContent = 1;
                    document.getElementById('totalRows').textContent = '(' + (j.data.length || 0) + ' rows)';
                } else {
                    console.warn('orders result unexpected', j);
                    document.getElementById('ordersTbody').innerHTML = '<tr><td colspan="9" class="p-4 text-red-600">Gagal memuat transaksi</td></tr>';
                }
            } catch (e) {
                console.error('orders err', e);
                document.getElementById('ordersTbody').innerHTML = '<tr><td colspan="9" class="p-4 text-red-600">Gagal memuat transaksi</td></tr>';
            }
        }

        /* =========================
           5) Renderers
           ========================= */

        // render ringkasan KPI
        function renderSummary(data) {
            const root = document.getElementById('summaryCards');
            root.innerHTML = '';
            const cards = [{
                    title: 'Total Pendapatan',
                    value: formatIDR(data.total_revenue || 0)
                },
                {
                    title: 'Total Transaksi',
                    value: (data.total_orders || 0) + ' transaksi',
                    subtitle: (data.total_items || 0) + ' item'
                },
                {
                    title: 'Rata-Rata Order',
                    value: formatIDR(Math.round(data.avg_order || 0))
                },
                {
                    title: 'Total Item',
                    value: (data.total_items || 0) + ' item'
                }
            ];
            cards.forEach(c => {
                const el = document.createElement('div');
                el.className = 'bg-white p-4 rounded-lg shadow-sm hover:shadow-md transition transform hover:-translate-y-0.5';
                el.innerHTML = `<div class="text-xs text-gray-400">${c.title}</div><div class="text-xl font-bold mt-2">${c.value}</div>${c.subtitle?'<div class="text-sm text-gray-500 mt-1">'+c.subtitle+'</div>':''}`;
                root.appendChild(el);
            });
        }

        // chart helpers
        function clearChart() {
            if (salesChart) {
                try {
                    salesChart.destroy();
                } catch (e) {}
                salesChart = null;
            }
            const canvas = document.getElementById('salesChart');
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }

        function buildDualChart(labels, revenue, ordersCount) {
            const ctx = document.getElementById('salesChart').getContext('2d');
            if (salesChart) salesChart.destroy();
            salesChart = new Chart(ctx, {
                data: {
                    labels,
                    datasets: [{
                            type: 'bar',
                            label: 'Pendapatan (Rp)',
                            data: revenue,
                            yAxisID: 'y',
                            backgroundColor: 'rgba(2,6,23,0.85)'
                        },
                        {
                            type: 'line',
                            label: 'Transaksi',
                            data: ordersCount,
                            yAxisID: 'y2',
                            borderColor: 'rgba(14,165,233,0.95)',
                            tension: 0.2,
                            pointRadius: 3,
                            fill: false
                        }
                    ]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: v => formatIDR(v)
                            }
                        },
                        y2: {
                            beginAtZero: true,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        function buildCategoryChart(rows) {
            const labels = rows.map(r => r.label || r.name || r.category || '—');
            const revenue = rows.map(r => Number(r.revenue || r.total || 0));
            const items = rows.map(r => Number(r.items || r.count || 0));
            buildDualChart(labels, revenue, items);
        }

        // render orders (tabel)
        function renderOrders(rows) {
            const t = document.getElementById('ordersTbody');
            t.innerHTML = '';
            if (!rows || rows.length === 0) {
                t.innerHTML = '<tr><td colspan="9" class="p-4 text-sm text-gray-500">Tidak ada transaksi</td></tr>';
                return;
            }
            rows.forEach(r => {
                const tr = document.createElement('tr');
                tr.className = 'border-t hover:bg-slate-50 transition';
                const dt = new Date(r.created_at).toLocaleString('id-ID', {
                    dateStyle: 'medium',
                    timeStyle: 'short'
                });
                const receiptUrl = '../api/receipt.php?order_id=' + encodeURIComponent(r.order_id) + '&reprint=1';
                const receiptBtn = `<a class="px-2 py-1 border rounded text-sm hover:bg-slate-50 transition" target="_blank" rel="noopener" href="${escapeHtml(receiptUrl)}">Print</a>`;
                tr.innerHTML = `
                <td class="p-2 align-top">${escapeHtml(r.order_no || ('#'+r.order_id))}</td>
                <td class="p-2 align-top">${escapeHtml(dt)}</td>
                <td class="p-2 align-top">${escapeHtml(r.cashier||'-')}</td>
                <td class="p-2 align-top">${escapeHtml(r.member_name||'-')}</td>
                <td class="p-2 align-top">${escapeHtml(r.visit_type||'')}</td>
                <td class="p-2 align-top">${escapeHtml(String(r.items_count||0))}</td>
                <td class="p-2 align-top">${escapeHtml(r.payment_method||'-')}</td>
                <td class="p-2 align-top font-semibold">${formatIDR(Number(r.total||0))}</td>
                <td class="p-2 align-top">${receiptBtn}</td>
                `;
                t.appendChild(tr);
            });
        }

        /* =========================
           6) Event bindings
           ========================= */

        document.getElementById('btnApply').addEventListener('click', () => {
            currentPage = 1;
            loadAll();
        });

        document.getElementById('btnReset').addEventListener('click', () => {
            document.getElementById('filterCashier').value = '';
            document.getElementById('filterMethod').value = '';
            document.getElementById('filterCategory').value = '';
            const e = new Date(),
                s = new Date();
            s.setDate(e.getDate() - 6);
            document.getElementById('startDate').value = isoDate(s);
            document.getElementById('endDate').value = isoDate(e);
            currentPage = 1;
            loadAll();
        });

        document.getElementById('prevPage').addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                loadOrders();
            }
        });

        document.getElementById('nextPage').addEventListener('click', () => {
            const total = Number(document.getElementById('pageTotal').textContent || '1');
            if (currentPage < total) {
                currentPage++;
                loadOrders();
            }
        });

        document.getElementById('exportCsv').addEventListener('click', () => exportCsvAll());

        // preset buttons (hari ini, 7 hari, 30 hari, bulan ini)
        document.querySelectorAll('.preset-btn').forEach(b => {
            b.addEventListener('click', () => {
                const days = b.dataset.days,
                    range = b.dataset.range;
                const end = new Date(),
                    start = new Date();
                if (range === 'month') start.setDate(1);
                else start.setDate(end.getDate() - (days || 0));
                document.getElementById('startDate').value = isoDate(start);
                document.getElementById('endDate').value = isoDate(end);
                currentPage = 1;
                loadAll();
            });
        });

        // group by buttons
        const groupBtns = document.querySelectorAll('.group-btn');
        groupBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                groupBtns.forEach(b => b.classList.remove('pill-active'));
                btn.classList.add('pill-active');
                currentGroup = btn.dataset.group;
                currentPage = 1;
                loadDailyChart();
            });
        });

        // optional search input if added later (keamanan: id mungkin tidak ada)
        const searchInput = document.getElementById('searchOrder') || null;
        if (searchInput) searchInput.addEventListener('input', debounce(() => {
            currentPage = 1;
            loadOrders();
        }, 450));

        // helper untuk mengambil nilai filter saat ini
        function qs() {
            return {
                start: document.getElementById('startDate').value,
                end: document.getElementById('endDate').value,
                cashier_id: document.getElementById('filterCashier').value || '',
                payment_method: document.getElementById('filterMethod').value || '',
                category_id: document.getElementById('filterCategory').value || '',
                q: (document.getElementById('searchOrder')?.value || '')
            };
        }

        /* =========================
           7) Export CSV client-side (fallback)
           ========================= */
        async function exportCsvAll() {
            const p = qs();
            const params = {
                action: 'orders',
                start: p.start,
                end: p.end,
                page: 1,
                per_page: 10000
            };
            if (p.cashier_id) params.cashier_id = p.cashier_id;
            if (p.payment_method) params.payment_method = p.payment_method;
            if (p.category_id) params.category_id = p.category_id;
            try {
                const res = await fetch(buildQuery(params), {
                    credentials: 'same-origin'
                });
                const j = await safeJson(res);
                if (!j || !j.success) return alert('Gagal export CSV');
                const rows = (j.data && (j.data.rows || j.data)) || [];
                const csv = ['No Order,Tanggal,Kasir,Member,Tipe,Items,Pembayaran,Total'];
                rows.forEach(r => {
                    const dt = new Date(r.created_at).toLocaleString('id-ID', {
                        dateStyle: 'short',
                        timeStyle: 'short'
                    });
                    csv.push([r.order_no || ('#' + r.order_id), dt, r.cashier || '', r.member_name || '', r.visit_type || '', r.items_count || 0, r.payment_method || '', Number(r.total || 0)].map(v => '"' + String(v).replace(/"/g, '""') + '"').join(','));
                });
                const blob = new Blob([csv.join('\n')], {
                    type: 'text/csv'
                });
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = `rekap_${p.start}_${p.end}.csv`;
                document.body.appendChild(a);
                a.click();
                a.remove();
            } catch (e) {
                console.error('export err', e);
                alert('Gagal export CSV');
            }
        }

        /* =========================
           8) Loader aggregator & bootstrap
           ========================= */

        // loadAll: ringkasan + chart + methods + orders
        async function loadAll() {
            document.getElementById('summaryCards').classList.add('skeleton');
            await Promise.all([loadSummary(), loadDailyChart(), loadMethods(), loadOrders()]);
            document.getElementById('summaryCards').classList.remove('skeleton');
        }

        // inisialisasi tanggal default (7 hari terakhir)
        (function initDefaultDates() {
            const dNow = new Date(),
                d7 = new Date();
            d7.setDate(dNow.getDate() - 6);
            document.getElementById('startDate').value = isoDate(d7);
            document.getElementById('endDate').value = isoDate(dNow);
        })();

        // bootstrap: isi dropdown dan muat data awal
        (async function init() {
            await loadDropdowns();
            await loadAll();
        })();
    </script>

</body>

</html>