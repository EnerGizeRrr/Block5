-- Вывести 100 последних заказов со списком позиций.

-- Шаг 1
SELECT *
FROM orders
ORDER BY id DESC
LIMIT 100;


-- Шаг 2
SELECT
    oi.*,
    p.title AS product_title
FROM order_items oi
JOIN products p ON oi.product_id = p.id
WHERE oi.order_id IN (...); -- <-- Сюда подставляются ID заказов из первого запроса