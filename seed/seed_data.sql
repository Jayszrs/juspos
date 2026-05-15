-- seed/seed_data.sql
INSERT INTO categories (code, name) VALUES
('JUS','Jus Segar'),
('SMO','Smoothie'),
('ADD','Tambahan');

INSERT INTO menus (category_id, code, name, price, stock, available, description) VALUES
(1,'J001','Jus Alpukat',15000.00,50,1,'Jus Alpukat Segar - 300ml'),
(1,'J002','Jus Mangga',14000.00,40,1,'Jus Mangga Manis - 300ml'),
(1,'J003','Jus Jeruk',12000.00,30,1,'Jus Jeruk Segar - 300ml'),
(2,'S001','Smoothie Berry',20000.00,20,1,'Smoothie mix berry - 350ml'),
(3,'A001','Topping Boba',3000.00,100,1,'Tambahkan boba');

INSERT INTO members (code, name, phone, points) VALUES
('M001','Andi','081234000001',0),
('M002','Sari','081234000002',0);

INSERT INTO promotions (code, type, value, active) VALUES
('WELCOME10','PERCENT',10.00,1);
