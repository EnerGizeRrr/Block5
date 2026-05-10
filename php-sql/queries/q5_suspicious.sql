-- Найти пользователей, у которых больше 3 failed платежей за 24 часа.

WITH FailedPaymentsWithLag AS (
    -- Шаг 1
    SELECT
        o.user_id,
        p.created_at,
        LAG(p.created_at, 2) OVER (PARTITION BY o.user_id ORDER BY p.created_at) AS prev_2_payment_at
    FROM payments p
    JOIN orders o ON p.order_id = o.id
    WHERE p.status = 'failed'
)
-- Шаг 2
SELECT DISTINCT
    fp.user_id,
    u.email
FROM FailedPaymentsWithLag fp
JOIN users u ON fp.user_id = u.id
WHERE fp.prev_2_payment_at IS NOT NULL
  AND fp.created_at <= fp.prev_2_payment_at + INTERVAL 24 HOUR;