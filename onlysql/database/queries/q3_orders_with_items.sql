-- 100 последних заказов.
SELECT *
FROM orders
ORDER BY created_at DESC
LIMIT 100;

-- Все позиции для 100 последних заказов.
SELECT
    oi.*
FROM
    order_items AS oi
JOIN
    (SELECT id FROM orders ORDER BY created_at DESC LIMIT 100) AS latest_orders
ON
    oi.order_id = latest_orders.id;