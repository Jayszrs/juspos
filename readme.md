1. Import migrations/converted_mysql_schema.sql ke database juspos.
2. Import seed/seed_data.sql.
3. Sesuaikan includes/config.php DB creds dan BASE_URL.
4. Pastikan public/receipts writable (chmod 775).
5. Upload files ke server (public sebagai root web dir).
6. Buka http://localhost/juspos/public/index.php .
7. Register admin via POST /api/auth.php?action=register (gunakan tool seperti Postman) atau buat user manual di DB.
