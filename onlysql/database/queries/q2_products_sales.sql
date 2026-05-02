-- Топ-50 товаров по количеству продаж за период 
SELECT
    p.id AS product_id,
    p.title AS product_name,
    SUM(oi.qty) AS total_quantity_sold
FROM products p
JOIN order_items oi ON p.id = oi.product_id
WHERE oi.created_at BETWEEN '2026-04-06' AND '2026-04-07' 
GROUP BY p.id, p.title 
ORDER BY total_quantity_sold DESC
LIMIT 50;