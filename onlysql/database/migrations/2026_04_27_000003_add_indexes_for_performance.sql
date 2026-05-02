-- Индексы для таблицы orders
CREATE INDEX idx_orders_status_created_at ON orders (status, created_at);
CREATE INDEX idx_orders_user_id_created_at_id ON orders (user_id, created_at, id);
CREATE INDEX idx_orders_created_at_id ON orders (created_at, id);

-- Индекс для таблицы order_items
CREATE INDEX idx_order_items_created_at ON order_items (created_at);

-- Индекс для таблицы payments
CREATE INDEX idx_payments_status_created_at ON payments (status, created_at);