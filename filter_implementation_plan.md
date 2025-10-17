# План реализации фильтрации выбранных товаров

## Описание задачи
Необходимо реализовать возможность вывода в прайсы только выбранных товаров, учитывая особенность системы, что таблица товаров каждый раз сбрасывается при обработке.

## Требования
- Фильтрация по артикулам
- Хранение отфильтрованных артикулов в отдельном файле конфигурации
- Сохранение настроек между перезапусками системы
- Логирование применения фильтрации

## Архитектурное решение

### 1. Создание файла конфигурации фильтрации
Файл: `pages/config/filter_config.php`
Содержание:
- Массив с артикулами для фильтрации
- Настройки фильтрации для каждого поставщика (опционально)

```php
<?php
// /config/filter_config.php

function get_filter_config() {
    return [
        // Глобальный список артикулов для фильтрации
        'global_articles' => [
            // 'ART001', 'ART002', 'ART003'
        ],
        
        // Фильтрация по конкретным поставщикам
        'suppliers' => [
            'BRW_Brilan' => [
                // 'ART001', 'ART002'
            ],
            'Anrex_Anrex' => [
                // 'ART100', 'ART101'
            ],
            // другие поставщики...
        ],
        
        // Режим фильтрации: 'include' (включать только указанные) или 'exclude' (исключать указанные)
        'filter_mode' => 'include' // по умолчанию - включать только указанные
    ];
}
```

### 2. Модификация excel_importer.php
Внести изменения в функцию `import_from_excel_to_db` для фильтрации товаров перед сохранением в базу данных:

```php
// В функции import_from_excel_to_db, перед сохранением в базу
$filteredProducts = apply_article_filter($productsForDb, $supplierCode, $config);

// Функция фильтрации
function apply_article_filter($products, $supplierCode, $config) {
    $filterConfig = get_filter_config();
    
    // Определяем список артикулов для фильтрации
    $articlesToFilter = [];
    
    // Сначала проверяем специфичные для поставщика фильтры
    if (isset($filterConfig['suppliers'][$supplierCode])) {
        $articlesToFilter = $filterConfig['suppliers'][$supplierCode];
    } 
    // Если специфичных нет, используем глобальный список
    elseif (!empty($filterConfig['global_articles'])) {
        $articlesToFilter = $filterConfig['global_articles'];
    }
    
    // Если фильтры не заданы, возвращаем все продукты без изменений
    if (empty($articlesToFilter)) {
        return $products;
    }
    
    $filterMode = $filterConfig['filter_mode'] ?? 'include';
    
    $result = [];
    foreach ($products as $product) {
        $article = $product['article'] ?? '';
        
        $shouldInclude = false;
        if ($filterMode === 'include') {
            // Включаем только если артикул есть в списке
            $shouldInclude = in_array($article, $articlesToFilter);
        } else {
            // Включаем все, кроме тех, что в списке
            $shouldInclude = !in_array($article, $articlesToFilter);
        }
        
        if ($shouldInclude) {
            $result[] = $product;
        }
    }
    
    return $result;
}
```

### 3. Добавление логирования
Внести в лог информацию о применении фильтрации:

```php
// В функции import_from_excel_to_db после фильтрации
if (!empty($articlesToFilter)) {
    $log->add("Применена фильтрация товаров. Режим: {$filterMode}. Всего артикулов в фильтре: " . count($articlesToFilter) . ". До фильтрации: " . count($productsForDb) . " товаров, после: " . count($filteredProducts) . " товаров.");
} else {
    $log->add("Фильтрация товаров не применялась.");
}
```

### 4. Интеграция в основной процесс
Убедиться, что файл конфигурации фильтрации подключается в основных скриптах:
- `run.php`
- `run_all.php`
- `excel_importer.php`

## Тестирование
1. Создать тестовый набор артикулов для фильтрации
2. Запустить процесс импорта с фильтрацией
3. Проверить, что в базу данных попадают только отфильтрованные товары
4. Проверить логи на наличие информации о фильтрации
5. Проверить генерацию XLSX-файлов с учетом фильтрации

## Влияние на систему
- Фильтрация будет применяться на этапе импорта данных в базу данных
- Это не повлияет на кэширование исходных XLS-файлов
- Сгенерированные XLSX-файлы будут содержать только отфильтрованные товары
- Фронтенд (index.html и button_data.json) будет работать как обычно, но с отфильтрованными данными