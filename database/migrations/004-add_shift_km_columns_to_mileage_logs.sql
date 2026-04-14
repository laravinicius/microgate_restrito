-- Migration: adiciona colunas para KM rodado por fora e KM interno pago.

ALTER TABLE mileage_logs
    ADD COLUMN km_outside_shift INT DEFAULT NULL AFTER km_end,
    ADD COLUMN km_inside_shift INT DEFAULT NULL AFTER km_outside_shift,
    ADD KEY idx_mileage_user_date_shift (user_id, log_date, km_outside_shift, km_inside_shift);
