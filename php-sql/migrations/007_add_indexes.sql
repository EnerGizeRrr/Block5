-- === Индексы для таблицы `orders` ===
ALTER TABLE `orders` ADD INDEX `orders_user_id_index` (`user_id`);
ALTER TABLE `orders` ADD INDEX `orders_status_created_at_index` (`status`, `created_at`);
ALTER TABLE `orders` ADD INDEX `orders_created_at_index` (`created_at`);
ALTER TABLE `orders` ADD INDEX `orders_user_status_created_index` (`user_id`, `status`, `created_at`);


-- === Индексы для таблицы `order_items` ===
ALTER TABLE `order_items` ADD INDEX `order_items_order_id_index` (`order_id`);
ALTER TABLE `order_items` ADD INDEX `order_items_product_id_index` (`product_id`);
ALTER TABLE `order_items` ADD INDEX `order_items_order_product_qty_index` (`order_id`, `product_id`, `qty`);
ALTER TABLE `order_items` ADD INDEX `order_items_product_order_index` (`product_id`, `order_id`);


-- === Индексы для таблицы `payments` ===
ALTER TABLE `payments` ADD INDEX `payments_status_created_at_index` (`status`, `created_at`);
ALTER TABLE `payments` ADD INDEX `payments_order_status_created_index` (`order_id`, `status`, `created_at`);
ALTER TABLE `payments` ADD INDEX `payments_status_order_index` (`status`, `order_id`);


-- === Индексы для таблицы `products` ===
ALTER TABLE `products` ADD INDEX `products_sku_index` (`sku`);

