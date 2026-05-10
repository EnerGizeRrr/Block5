-- Индекс для поддержки keyset-пагинации по заказам пользователя.
-- Порядок колонок (user_id, created_at, id) и направление сортировки (DESC)
-- идеально соответствуют запросу, что обеспечивает максимальную производительность.
ALTER TABLE `orders` ADD INDEX `orders_user_created_id_index` (`user_id`, `created_at` DESC, `id` DESC);