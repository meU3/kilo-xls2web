<?php
// /app/database.php

/**
 * Устанавливает соединение с БД и создает/обновляет таблицу.
 */
function get_db_connection(string $dbPath): PDO {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // ОБНОВЛЕННАЯ СТРУКТУРА: добавлены все детальные поля из конфига
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            supplier_code TEXT NOT NULL,
            collection TEXT,
            article TEXT,
            name TEXT NOT NULL,
            stock_on_hand INTEGER,
            stock_in_transit INTEGER,
            stock_arrival_date TEXT,
            base_price REAL,
            our_selling_price REAL,
            retail_price REAL,
            discount_percent REAL,
            discounted_retail_price REAL,
            stock INTEGER,
            price REAL
        )");
    return $pdo;
}

/**
 * Очищает данные для конкретного поставщика.
 */
function clear_supplier_data(PDO $db, string $supplierCode): int {
    $stmt = $db->prepare("DELETE FROM products WHERE supplier_code = ?");
    $stmt->execute([$supplierCode]);
    return $stmt->rowCount();
}

/**
 * Сохраняет массив товаров в БД.
 * ОБНОВЛЕННАЯ ЛОГИКА: Динамически формирует INSERT-запрос.
 */
function save_products_to_db(PDO $db, string $supplierCode, array $products): void {
    if (empty($products)) {
        return;
    }

    // Получаем названия полей из первого товара (они у всех одинаковые)
    $firstProduct = $products[0];
    $fields = array_keys($firstProduct);
    
    // Добавляем supplier_code, так как он не приходит в массиве $product
    array_unshift($fields, 'supplier_code');

    // Формируем SQL-запрос
    $placeholders = implode(', ', array_fill(0, count($fields), '?'));
    $sql = "INSERT INTO products (" . implode(', ', $fields) . ") VALUES ({$placeholders})";
    $stmt = $db->prepare($sql);

    foreach ($products as $product) {
        $values = array_values($product);
        array_unshift($values, $supplierCode);
        $stmt->execute($values);
    }
}