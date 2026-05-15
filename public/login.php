<?php
session_start();
// jika sudah login, langsung arahkan berdasarkan role:
// ADMIN -> admin_dashboard.php
// LEADER -> index.php (rekap tersedia di index untuk leader)
// lainnya -> index.php
if (!empty($_SESSION['user_id'])) {
    $role = strtoupper($_SESSION['user_role'] ?? '');
    if ($role === 'ADMIN') {
        header('Location: admin_dashboard.php');
        exit;
    } else {
        header('Location: index.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Masuk — POS Toko Jus</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gradient-to-br from-slate-50 to-white min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full px-6">
        <div class="bg-white rounded-2xl p-6 shadow-lg">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-12 h-12 bg-slate-900 text-white rounded-lg flex items-center justify-center font-bold">JP</div>
                <div>
                    <h1 class="text-xl font-bold">POS Toko Jus</h1>
                    <p class="text-sm text-gray-500">Masuk ke akun Anda</p>
                </div>
            </div>

            <form id="loginForm" class="space-y-4" onsubmit="return false;">
                <div>
                    <label class="text-xs text-gray-600">Email atau Username</label>
                    <input id="email" type="text" class="mt-1 block w-full p-3 border rounded-lg" placeholder="email@contoh.com atau username" required>
                </div>
                <div>
                    <label class="text-xs text-gray-600">Password</label>
                    <input id="password" type="password" class="mt-1 block w-full p-3 border rounded-lg" placeholder="••••••••" required>
                </div>

                <div>
                    <button id="loginBtn" class="w-full px-4 py-3 bg-slate-900 text-white rounded-lg font-medium">Masuk</button>
                </div>

                <div id="loginError" class="text-sm text-red-600 hidden"></div>

                <div class="pt-4 text-sm text-center text-gray-600">
                    Belum punya akun? <a href="register.php" class="text-slate-900 font-medium">Daftar</a>
                </div>
            </form>
        </div>

        <div class="text-center text-xs text-gray-400 mt-4">© <?php echo date('Y'); ?> POS Toko Jus</div>
    </div>

    <script>
        const loginBtn = document.getElementById('loginBtn');
        const loginError = document.getElementById('loginError');
        loginBtn.addEventListener('click', async () => {
            loginError.classList.add('hidden');
            loginBtn.disabled = true;
            loginBtn.textContent = 'Memproses...';
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const API_AUTH = '../api/auth.php';
            try {
                const res = await fetch(API_AUTH + '?action=login', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        email,
                        password
                    })
                });

                // server expected to return JSON
                const j = await res.json();

                if (res.ok && j.success) {
                    // prefer server-provided redirect (relative to this login page)
                    if (j.redirect) {
                        // ensure redirect is a safe relative path
                        window.location.href = j.redirect;
                    } else {
                        window.location.href = 'index.php';
                    }
                } else {
                    loginError.textContent = j.error || j.detail || 'Login gagal: periksa kredensial';
                    loginError.classList.remove('hidden');
                }
            } catch (e) {
                console.error(e);
                loginError.textContent = 'Tidak dapat terhubung ke server';
                loginError.classList.remove('hidden');
            } finally {
                loginBtn.disabled = false;
                loginBtn.textContent = 'Masuk';
            }
        });

        // small UX: submit Enter in inputs
        document.getElementById('password').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') loginBtn.click();
        });
    </script>
</body>

</html>