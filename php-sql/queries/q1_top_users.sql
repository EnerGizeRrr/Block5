-- Топ-20 пользователей по сумме оплаченных заказов за последние 30 дней.

SELECT
    u.id,
    u.email,
    u.name,
    SUM(o.total_amount) AS total_paid_amount
FROM users u
JOIN orders o ON u.id = o.user_id
WHERE o.status = 'paid' AND o.created_at >= NOW() - INTERVAL 30 DAY
GROUP BY u.id, u.email, u.name
ORDER BY total_paid_amount DESC
LIMIT 20;