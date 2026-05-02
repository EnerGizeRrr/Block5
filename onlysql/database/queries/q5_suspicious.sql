-- Пользователи у которых >3 failed за период
SELECT
    o.user_id,
    COUNT(p.id) AS failed_payments_count
FROM payments p
JOIN orders o ON p.order_id = o.id
WHERE p.status = 'failed' AND p.created_at BETWEEN '2026-04-06' AND '2026-04-06' + INTERVAL 24 HOUR
GROUP BY o.user_id
HAVING COUNT(p.id) > 3
ORDER BY failed_payments_count DESC;