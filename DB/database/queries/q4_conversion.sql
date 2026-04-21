-- Конверсия paid за период
SELECT
    COUNT(*) AS total_orders,
    SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid_orders,
    (SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) * 100.0 / COUNT(*)) AS conversion_rate
FROM orders
WHERE created_at BETWEEN '2026-04-06' AND '2026-04-07';