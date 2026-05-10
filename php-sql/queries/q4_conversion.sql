-- Конверсия оплат: сколько заказов в статусе 'paid' из всех заказов за период.

SELECT
    COUNT(*) AS total_orders,
    SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid_orders,
    (SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) / COUNT(*)) * 100 AS conversion_rate_percent
FROM orders
WHERE
    created_at BETWEEN :date_from AND :date_to;