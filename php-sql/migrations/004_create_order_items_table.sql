CREATE TABLE IF NOT EXISTS `order_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `qty` INT UNSIGNED NOT NULL,
    `price` DECIMAL(10, 2) NOT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `order_items_order_id_foreign`
        FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `order_items_product_id_foreign`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
        ON DELETE RESTRICT,
    CONSTRAINT `order_items_qty_check` CHECK (`qty` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;