-- Топ-20 пользователей по сумме оплаченных заказов за последние 30 дней.
SELECT
    u.id AS user_id,
    u.name AS user_name,
    SUM(o.total_amount) AS total_paid
FROM users u
JOIN orders o ON u.id = o.user_id
WHERE o.status = 'paid' AND o.created_at >= NOW() - INTERVAL 30 DAY
GROUP BY u.id, u.name
ORDER BY total_paid DESC
LIMIT 20;