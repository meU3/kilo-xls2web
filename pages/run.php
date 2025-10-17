<?php
// /run.php

// 1. Подключаем базовые пути
require_once __DIR__ . '/config/paths_config.php';

// 2. Подключаем автозагрузчик Composer
require_once PAGES_PATH . '/vendor/autoload.php';

// Включаем отображение ошибок для отладки
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 3. Подключаем конфигурацию и модули
require_once PAGES_PATH . '/config/suppliers_config.php';
require_once PAGES_PATH . '/config/template_config.php';
require_once PAGES_PATH . '/app/AppLogger.php';
require_once PAGES_PATH . '/app/excel_importer.php';
require_once PAGES_PATH . '/app/excel_generator.php';

// --- Начало основной логики ---

if ($argc < 2) {
    echo "Ошибка: Укажите ключ прайс-листа для обработки.\n";
    echo "Пример: php run.php Anrex_Brilan\n";
    exit(1);
}

$priceListKey = $argv[1];
$allSuppliers = get_suppliers_config();

if (!isset($allSuppliers[$priceListKey])) {
    echo "Ошибка: Прайс-лист с ключом '{$priceListKey}' не найден в конфигурации.\n";
    exit(1);
}

$config = $allSuppliers[$priceListKey];
$templateConfig = get_template_config();

$log = new AppLogger(true); // Вывод только в консоль
$log->add("--- Начало ручной обработки прайс-листа: '{$priceListKey}' ---");

$db = get_db_connection(PAGES_PATH . '/data/products.db');
$overallSuccess = false;

// Формируем полные пути на основе констант
$public_output_path = PUBLIC_PATH . '/xls/' . $config['output_filename'];
$full_logo_path = !empty($config['logo_filename']) ? PAGES_PATH . '/logo/' . $config['logo_filename'] : '';

// ЭТАП 1: Принудительный импорт данных из XLS в Базу Данных
$importResult = import_from_excel_to_db($db, $priceListKey, $config, $templateConfig, $public_output_path, $log, true);

if ($importResult['success']) {
    // ЭТАП 2: Генерация XLSX из Базы Данных в локальную папку
    $localGenerationPath = PAGES_PATH . '/data/generated_files';
    $local_output_path = $localGenerationPath . '/' . $config['output_filename'];
    
    $generationResult = generate_xlsx_from_db($db, $priceListKey, $config, $templateConfig, $importResult['date_string'], $local_output_path, $full_logo_path, $log);

    if ($generationResult['success']) {
        $log->add("Прайс-лист '{$priceListKey}' успешно обработан и сохранен в: {$local_output_path}");
        $log->add("Примечание: Этот скрипт не выполняет выгрузку по FTP. Для выгрузки всех обновлений запустите run_all.php");
        $overallSuccess = true;
    } else {
        $log->add("ОШИБКА: Не удалось сгенерировать XLSX для '{$priceListKey}'.");
    }
} else {
    $log->add("ОШИБКА: Не удалось импортировать данные для '{$priceListKey}'.");
}

$log->add("--- Ручная обработка завершена ---");

exit($overallSuccess ? 0 : 1);