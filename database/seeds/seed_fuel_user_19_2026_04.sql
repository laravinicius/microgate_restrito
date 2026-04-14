-- Seed de teste: fuel_logs para usuario 19 em abril/2026.
-- Objetivo: validar total das notas e valor a pagar no relatorio mensal.
--
-- Este script e idempotente no periodo porque remove os abastecimentos do mes antes de inserir.

START TRANSACTION;

DELETE FROM fuel_logs
WHERE user_id = 19
  AND fueled_at BETWEEN '2026-04-01 00:00:00' AND '2026-04-30 23:59:59';

INSERT INTO fuel_logs (
    user_id,
    fueled_at,
    fuel_price,
    liters,
    total_amount,
    current_km,
    receipt_photo,
    created_at,
    updated_at
) VALUES
(19, '2026-04-01 18:10:00', 6.09, 19.100, 116.32, 1112, 'uploads/abastecimento/19/2026-04-01_1.jpg', NOW(), NOW()),
(19, '2026-04-02 18:03:00', 6.15, 20.400, 125.46, 1240, 'uploads/abastecimento/19/2026-04-02_1.jpg', NOW(), NOW()),
(19, '2026-04-03 18:20:00', 6.19, 21.800, 134.94, 1376, 'uploads/abastecimento/19/2026-04-03_1.jpg', NOW(), NOW()),
(19, '2026-04-04 18:08:00', 6.05, 18.700, 113.14, 1490, 'uploads/abastecimento/19/2026-04-04_1.jpg', NOW(), NOW()),
(19, '2026-04-05 18:01:00', 6.12, 22.500, 137.70, 1622, 'uploads/abastecimento/19/2026-04-05_1.jpg', NOW(), NOW()),
(19, '2026-04-06 18:14:00', 6.23, 19.900, 123.98, 1712, 'uploads/abastecimento/19/2026-04-06_1.jpg', NOW(), NOW()),
(19, '2026-04-07 18:06:00', 6.17, 20.800, 128.34, 1842, 'uploads/abastecimento/19/2026-04-07_1.jpg', NOW(), NOW()),
(19, '2026-04-08 18:17:00', 6.11, 18.300, 111.81, 1958, 'uploads/abastecimento/19/2026-04-08_1.jpg', NOW(), NOW()),
(19, '2026-04-09 18:09:00', 6.26, 22.000, 137.72, 2125, 'uploads/abastecimento/19/2026-04-09_1.jpg', NOW(), NOW()),
(19, '2026-04-10 18:22:00', 6.18, 19.600, 121.13, 2250, 'uploads/abastecimento/19/2026-04-10_1.jpg', NOW(), NOW()),
(19, '2026-04-11 18:02:00', 6.08, 20.200, 122.82, 2368, 'uploads/abastecimento/19/2026-04-11_1.jpg', NOW(), NOW()),
(19, '2026-04-12 18:18:00', 6.27, 21.700, 136.06, 2460, 'uploads/abastecimento/19/2026-04-12_1.jpg', NOW(), NOW()),
(19, '2026-04-13 18:04:00', 6.14, 18.900, 116.05, 2585, 'uploads/abastecimento/19/2026-04-13_1.jpg', NOW(), NOW()),
(19, '2026-04-14 18:16:00', 6.16, 20.500, 126.28, 2708, 'uploads/abastecimento/19/2026-04-14_1.jpg', NOW(), NOW()),
(19, '2026-04-15 18:12:00', 6.24, 22.300, 139.15, 2866, 'uploads/abastecimento/19/2026-04-15_1.jpg', NOW(), NOW()),
(19, '2026-04-16 18:07:00', 6.09, 19.400, 118.15, 2995, 'uploads/abastecimento/19/2026-04-16_1.jpg', NOW(), NOW()),
(19, '2026-04-17 18:21:00', 6.13, 21.200, 129.96, 3138, 'uploads/abastecimento/19/2026-04-17_1.jpg', NOW(), NOW()),
(19, '2026-04-18 18:11:00', 6.22, 20.100, 125.02, 3238, 'uploads/abastecimento/19/2026-04-18_1.jpg', NOW(), NOW()),
(19, '2026-04-19 18:05:00', 6.10, 19.700, 120.17, 3365, 'uploads/abastecimento/19/2026-04-19_1.jpg', NOW(), NOW()),
(19, '2026-04-20 18:19:00', 6.20, 22.600, 140.12, 3500, 'uploads/abastecimento/19/2026-04-20_1.jpg', NOW(), NOW()),
(19, '2026-04-21 18:13:00', 6.25, 20.900, 130.63, 3678, 'uploads/abastecimento/19/2026-04-21_1.jpg', NOW(), NOW()),
(19, '2026-04-22 18:00:00', 6.12, 19.800, 121.18, 3808, 'uploads/abastecimento/19/2026-04-22_1.jpg', NOW(), NOW()),
(19, '2026-04-23 18:15:00', 6.18, 21.400, 132.25, 3938, 'uploads/abastecimento/19/2026-04-23_1.jpg', NOW(), NOW()),
(19, '2026-04-24 18:10:00', 6.23, 20.600, 128.34, 4042, 'uploads/abastecimento/19/2026-04-24_1.jpg', NOW(), NOW()),
(19, '2026-04-25 18:06:00', 6.11, 21.900, 133.81, 4178, 'uploads/abastecimento/19/2026-04-25_1.jpg', NOW(), NOW()),
(19, '2026-04-26 18:23:00', 6.15, 20.300, 124.85, 4306, 'uploads/abastecimento/19/2026-04-26_1.jpg', NOW(), NOW()),
(19, '2026-04-27 18:09:00', 6.28, 22.700, 142.56, 4482, 'uploads/abastecimento/19/2026-04-27_1.jpg', NOW(), NOW()),
(19, '2026-04-28 18:01:00', 6.16, 19.500, 120.12, 4620, 'uploads/abastecimento/19/2026-04-28_1.jpg', NOW(), NOW()),
(19, '2026-04-29 18:18:00', 6.14, 20.700, 127.10, 4752, 'uploads/abastecimento/19/2026-04-29_1.jpg', NOW(), NOW()),
(19, '2026-04-30 18:04:00', 6.22, 21.600, 134.35, 4868, 'uploads/abastecimento/19/2026-04-30_1.jpg', NOW(), NOW());

COMMIT;
