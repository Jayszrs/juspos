<?php
// scripts/create_admin.php
require __DIR__ . '/../includes/db.php'; // sesuaikan path jika perlu
// NOTE: jalankan via CLI: php scripts/create_admin.php
$accounts = [
  ['username'=>'kasir01','email'=>'laksa@tokojus.com','password'=>'password123','name'=>'Kasir Demo','role'=>'KASIR'],
  ['username'=>'leader01','email'=>'hapis@tokojus.com','password'=>'password123','name'=>'Leader Demo','role'=>'LEADER'],
  ['username'=>'admin','email'=>'admin@tokojus.com','password'=>'admin123','name'=>'Admin','role'=>'ADMIN'],
];

foreach ($accounts as $a) {
  $exists = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1");
  $exists->execute([$a['email'], $a['username']]);
  if ($exists->fetch()) {
    echo "User {$a['email']} already exists\n";
    continue;
  }
  $hash = password_hash($a['password'], PASSWORD_DEFAULT);
  $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, name, role, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
  $stmt->execute([$a['username'],$a['email'],$hash,$a['name'],$a['role']]);
  echo "Created user: {$a['email']} ({$a['role']})\n";
}
