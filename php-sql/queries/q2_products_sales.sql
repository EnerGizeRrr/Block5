-- Топ-50 товаров по количеству продаж (qty) за период.

SELECT
    p.id,
    p.sku,
    p.title,
    SUM(oi.qty) AS total_qty_sold
FROM products p
JOIN order_items oi ON p.id = oi.product_id
JOIN orders o ON oi.order_id = o.id
WHERE o.status = 'paid' AND o.created_at BETWEEN :date_from AND :date_to
GROUP BY p.id, p.sku, p.title
ORDER BY total_qty_sold DESC
LIMIT 50;