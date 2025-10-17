<?php
/**
 * Файл: /run_all.php
 * Назначение: Главный управляющий скрипт.
 *
 * Ключевые изменения:
 * - Разделение на статический index.html и динамический button_data.json.
 * - index.html и логотипы обновляются только при изменении состава поставщиков.
 * - button_data.json обновляется при каждом запуске, содержа актуальные даты.
 * - Для отслеживания изменений в составе поставщиков используется manifest.json.
 */

require_once __DIR__ . '/config/paths_config.php';
require_once PAGES_PATH . '/vendor/autoload.php';
require_once PAGES_PATH . '/config/suppliers_config.php';
require_once PAGES_PATH . '/config/template_config.php';
require_once PAGES_PATH . '/app/AppLogger.php';
require_once PAGES_PATH . '/app/excel_importer.php';
require_once PAGES_PATH . '/app/excel_generator.php';
require_once PAGES_PATH . '/app/html_generator.php';
require_once PAGES_PATH . '/app/ftp_uploader.php';
require_once PAGES_PATH . '/app/database.php';

// --- ИНИЦИАЛИЗАЦИЯ ---
$allSuppliers = get_suppliers_config();
$templateConfig = get_template_config();
$log = new AppLogger(true, PAGES_PATH . '/logs/cron_log.txt');
$log->add("--- Начало массовой проверки всех прайс-листов ---");

$db = get_db_connection(PAGES_PATH . '/data/products.db');
$overallSuccess = true;
$files_to_upload = [];
$updatedSupplierInfo = [];

$localGenerationPath = PAGES_PATH . '/data/generated_files';
if (!is_dir($localGenerationPath)) {
    mkdir($localGenerationPath, 0755, true);
}

// --- ЭТАП 1: ОБРАБОТКА ПРАЙС-ЛИСТОВ И ГЕНЕРАЦИЯ XLSX ---
foreach ($allSuppliers as $supplierCode => $config) {
    $log->add("--- Обработка: '{$supplierCode}' ---");
    $public_output_path = PUBLIC_PATH . '/xls/' . $config['output_filename'];
    $importResult = import_from_excel_to_db($db, $supplierCode, $config, $templateConfig, $public_output_path, $log);

    if (!$importResult['should_process']) {
        $log->add("Обновление не требуется для '{$supplierCode}'.");
        continue;
    }
    if (!$importResult['success']) {
        $log->add("ОШИБКА импорта для '{$supplierCode}'. Пропускаем.");
        $overallSuccess = false;
        continue;
    }

    $local_output_path = $localGenerationPath . '/' . $config['output_filename'];
    $full_logo_path = !empty($config['logo_filename']) ? PAGES_PATH . '/logo/' . $config['logo_filename'] : '';
    $generationResult = generate_xlsx_from_db($db, $supplierCode, $config, $templateConfig, $importResult['date_string'], $local_output_path, $full_logo_path, $log);

    if ($generationResult['success']) {
        $files_to_upload[$local_output_path] = 'xls/' . $config['output_filename'];
        $updatedSupplierInfo[$supplierCode] = ['date_string' => $importResult['date_string']];
    } else {
        $log->add("ОШИБКА генерации XLSX для '{$supplierCode}'.");
        $overallSuccess = false;
    }
}

// --- ЭТАП 2: ГЕНЕРАЦИЯ ДИНАМИЧЕСКОГО ФАЙЛА ДАННЫХ (ВСЕГДА) ---
$dataForJson = [];
foreach ($allSuppliers as $supplierCode => $config) {
    $dateString = '';
    // Если поставщик только что обновился, берем точную дату из импорта.
    if (isset($updatedSupplierInfo[$supplierCode])) {
        $dateString = $updatedSupplierInfo[$supplierCode]['date_string'];
    } else {
        // Иначе, берем дату модификации уже существующего локального файла.
        $localPricePath = $localGenerationPath . '/' . $config['output_filename'];
        if (file_exists($localPricePath)) {
            $dateString = "от " . date('d.m.Y H:i', filemtime($localPricePath));
        }
    }

    if (!empty($dateString)) {
        $dataForJson[] = [
            'filename' => $config['output_filename'],
            'logo_filename' => $config['logo_filename'] ?? '',
            'info' => $config['manufacturer_info'] ?? '',
            'date' => $dateString,
        ];
    }
}
$localButtonDataPath = $localGenerationPath . '/button_data.json';
file_put_contents($localButtonDataPath, json_encode($dataForJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
$log->add("Файл с данными button_data.json успешно сгенерирован.");


// --- ЭТАП 3: ПРОВЕРКА СТРУКТУРЫ И ОБНОВЛЕНИЕ СТАТИЧЕСКИХ ФАЙЛОВ (РЕДКО) ---
$structureChanged = false;
$manifestPath = PAGES_PATH . '/data/manifest.json';
$currentSuppliers = array_keys($allSuppliers);
$previousSuppliers = file_exists($manifestPath) ? json_decode(file_get_contents($manifestPath), true) : [];

if ($currentSuppliers !== $previousSuppliers) {
    $log->add("Обнаружено изменение в составе поставщиков. Генерируется новый index.html и обновляются логотипы.");
    $structureChanged = true;

    // 1. Генерируем новый index.html
    $htmlContent = generate_html_shell($templateConfig, $log);
    $local_index_path = $localGenerationPath . '/index.html';
    file_put_contents($local_index_path, $htmlContent);
    $files_to_upload[$local_index_path] = 'index.html';

    // 2. Добавляем все логотипы в очередь на выгрузку
    foreach ($allSuppliers as $config) {
        if (!empty($config['logo_filename'])) {
            $local_logo_path = PAGES_PATH . '/logo/' . $config['logo_filename'];
            if (file_exists($local_logo_path)) {
                $files_to_upload[$local_logo_path] = 'logo/' . $config['logo_filename'];
            }
        }
    }
    
    // 3. Обновляем manifest-файл
    file_put_contents($manifestPath, json_encode($currentSuppliers));
    $log->add("Файл manifest.json обновлен.");
}

// --- ЭТАП 4: ВЫГРУЗКА ПО FTP ---
$hasDataUpdates = !empty($updatedSupplierInfo);

if ($hasDataUpdates || $structureChanged) {
    if ($hasDataUpdates) {
        $log->add("Обнаружены обновления данных. Добавляем button_data.json и БД в очередь на выгрузку.");
        $files_to_upload[$localButtonDataPath] = 'button_data.json';
        $files_to_upload[PAGES_PATH . '/data/products.db'] = 'data/products.db';
    }

    if ($templateConfig['ftp_active']) {
        if (!upload_files_via_ftp($files_to_upload, $templateConfig['ftp_settings'], $log)) {
            $log->add("ОШИБКА: Процесс выгрузки по FTP завершился с ошибками.");
            $overallSuccess = false;
        }
    } else {
        $log->add("Выгрузка по FTP отключена в конфигурации.");
    }
} else {
    $log->add("Нет данных для выгрузки по FTP.");
}

$log->add("--- Массовая проверка завершена ---");
exit($overallSuccess ? 0 : 1);