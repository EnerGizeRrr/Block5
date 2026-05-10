# Анализ плана выполнения запросов с помощью EXPLAIN

Этот документ демонстрирует влияние индексов на план выполнения SQL-запросов. Для каждого запроса приводится анализ плана до и после добавления соответствующих индексов.

---

## 1. `q1_top_users.sql` (Топ пользователей)

```sql
EXPLAIN SELECT u.id, u.email, u.name, SUM(o.total_amount) AS total_paid_amount FROM users u JOIN orders o ON u.id = o.user_id WHERE o.status = 'paid' AND o.created_at >= NOW() - INTERVAL 30 DAY GROUP BY u.id, u.email, u.name ORDER BY total_paid_amount DESC LIMIT 20;
```

### До индексов
*   **План выполнения:** Полный перебор (`Full Table Scan`) таблицы `orders`. Создание временной таблицы и сортировка на диске (`Using temporary; Using filesort`).
*   **Вывод:** Очень медленно. Основное время тратится на полный перебор `orders`.

### После индексов (`orders_status_created_at_index`, `orders_user_id_index`)
*   **План выполнения:** Быстрый поиск по композитному индексу `orders_status_created_at_index` для фильтрации `orders`. Эффективное соединение с `users` по индексу.
*   **Вывод:** Запрос выполняется на порядки быстрее. `EXPLAIN` покажет `Using index condition`.

---

## 2. `q2_products_sales.sql` (Топ товаров)

```sql
EXPLAIN SELECT p.id, p.sku, p.title, SUM(oi.qty) AS total_qty_sold FROM products p JOIN order_items oi ON p.id = oi.product_id JOIN orders o ON oi.order_id = o.id WHERE o.status = 'paid' AND o.created_at BETWEEN '2024-01-01' AND '2024-01-31' GROUP BY p.id, p.sku, p.title ORDER BY total_qty_sold DESC LIMIT 50;
```

### До индексов
*   **План выполнения:** Каскадный `Full Table Scan` по всем таблицам (`orders`, `order_items`).
*   **Вывод:** Катастрофически медленно. Запрос может не выполниться за приемлемое время.

### После индексов (`orders_status_created_at_index`, `order_items_order_id_index`, `order_items_product_id_index`)
*   **План выполнения:** Оптимальный. Сначала по индексу находятся нужные заказы, затем по индексам присоединяются позиции и товары.
*   **Вывод:** Эффективное выполнение. Каждый `JOIN` работает по индексу.

---

## 3. `q3_orders_with_items.sql` (Заказы с позициями)

```sql
EXPLAIN SELECT oi.*, p.title AS product_title FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id IN (1,2,3,4,5);
```

### До индексов
*   **План выполнения:** Полный перебор (`Full Table Scan`) таблицы `order_items`.
*   **Вывод:** Неэффективно. Время выполнения растет линейно с ростом таблицы.

### После индексов (`order_items_order_id_index`)
*   **План выполнения:** Быстрый поиск по диапазону (`Index Range Scan`) в `order_items`.
*   **Вывод:** Почти мгновенное выполнение.

---

## 4. `q4_conversion.sql` (Конверсия)

```sql
EXPLAIN SELECT COUNT(*) AS total_orders, SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid_orders FROM orders WHERE created_at BETWEEN '2024-01-01' AND '2024-01-31';
```

### До индексов
*   **План выполнения:** Полный перебор (`Full Table Scan`) таблицы `orders`.
*   **Вывод:** Медленно и ресурсоемко.

### После индексов (`orders_created_at_index`)
*   **План выполнения:** Поиск по диапазону (`Index Range Scan`) с использованием индекса.
*   **Вывод:** Значительное ускорение. `EXPLAIN` покажет `Using index condition`.

---

## 5. `q5_suspicious.sql` (Подозрительные пользователи)

```sql
EXPLAIN WITH FailedPaymentsWithLag AS (SELECT o.user_id, p.created_at, LAG(p.created_at, 2) OVER (PARTITION BY o.user_id ORDER BY p.created_at) AS prev_2_payment_at FROM payments p JOIN orders o ON p.order_id = o.id WHERE p.status = 'failed') SELECT DISTINCT fp.user_id, u.email FROM FailedPaymentsWithLag fp JOIN users u ON fp.user_id = u.id WHERE fp.prev_2_payment_at IS NOT NULL AND fp.created_at <= fp.prev_2_payment_at + INTERVAL 24 HOUR;
```

### До индексов
*   **План выполнения:** Полный перебор `payments`. Огромная сортировка всех найденных записей для работы оконной функции `LAG`.
*   **Вывод:** Очень медленно из-за сортировки большого объема данных.

### После индексов (`payments_status_created_at_index`, `orders_user_id_index`)
*   **План выполнения:** Быстрый поиск по индексу `payments_status_created_at_index`. Сортировка для `LAG` применяется к гораздо меньшему, предварительно отфильтрованному набору данных.
*   **Вывод:** Запрос значительно ускоряется.