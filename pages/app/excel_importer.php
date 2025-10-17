<?php
// /app/excel_importer.php

use PhpOffice\PhpSpreadsheet\IOFactory;

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/helpers.php';

function import_from_excel_to_db(PDO $db, string $supplierCode, array $config, array $templateConfig, string $outputFilePath, AppLogger $log, bool $forceProcess = false): array {
    $localFileForProcessing = null;
    $remoteFileWasDownloaded = false;
    $result = ['success' => false, 'should_process' => false, 'date_string' => ''];
    $cacheDir = PAGES_PATH . '/data/cache';

    if (strpos($config['input_file'], 'http') === 0) {
        if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);
        $cachedFilePath = $cacheDir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $supplierCode) . '.xls';
        $localFileForProcessing = $cachedFilePath;
        $shouldDownload = false;
        if (!file_exists($cachedFilePath)) { $log->add("Кэш отсутствует. Требуется скачивание."); $shouldDownload = true; } 
        else {
            $remoteTime = get_remote_modification_time($config['input_file']);
            if ($remoteTime === 0) { $log->add("Не удалось получить время модификации удаленного файла. Использую кэш."); } 
            elseif ($remoteTime > filemtime($cachedFilePath)) { $log->add("Новая версия файла на сервере. Обновляю кэш."); $shouldDownload = true; }
        }
        if ($shouldDownload) {
            $fileData = @file_get_contents($config['input_file']);
            if ($fileData === false) { $log->add("ОШИБКА: Не удалось скачать файл."); return $result; }
            file_put_contents($cachedFilePath, $fileData); $log->add("Файл успешно скачан в кэш."); $remoteFileWasDownloaded = true;
        }
    } else {
        $localFileForProcessing = $config['input_file'];
    }

    if (!file_exists($localFileForProcessing)) {
        $log->add("КРИТИЧЕСКАЯ ОШИБКА: Исходный файл не найден: '{$localFileForProcessing}'.");
        return $result;
    }

    $result['should_process'] = $forceProcess || $remoteFileWasDownloaded || !file_exists($outputFilePath) || filemtime($localFileForProcessing) > filemtime($outputFilePath);
    if (!$result['should_process']) { return $result; }
    
    try {
        $spreadsheet = IOFactory::load($localFileForProcessing);
        $worksheet = $spreadsheet->getActiveSheet();
        $result['date_string'] = parse_date_string($worksheet, $config['source_date_cell'] ?? '', $localFileForProcessing, $log);

        $productsForDb = [];
        $currentCollection = 'Без коллекции';
        
        foreach ($worksheet->getRowIterator($config['start_row'] ?? 1) as $row) {
            $rowIndex = $row->getRowIndex();
            // Логирование обработки объединённых ячеек
            $nameCell = ($config['column_mapping']['name'] ?? 'A') . $rowIndex;
            if (isCellMerged($worksheet, $nameCell)) {
                $name = trim(getMergedCellValue($worksheet, $nameCell) ?? '');
            } else {
                $name = trim($worksheet->getCell($nameCell)->getValue() ?? '');
            }
            
            // Чтение названия с учетом объединённых ячеек
            $nameCell = ($config['column_mapping']['name'] ?? 'A') . $rowIndex;
            if (isCellMerged($worksheet, $nameCell)) {
                foreach ($config['column_mapping'] as $dbField => $excelColumn) {
                    if ($dbField === 'name') continue; // Имя уже получили
                    $cellAddress = $excelColumn . $rowIndex;
                    if (isCellMerged($worksheet, $cellAddress)) {
                        $rawValue = getMergedCellValue($worksheet, $cellAddress);
                    } else {
                        $rawValue = $worksheet->getCell($cellAddress)->getValue();
                    }
                    $productData[$dbField] = trim($rawValue ?? '');
                }
                $name = trim(getMergedCellValue($worksheet, $nameCell) ?? '');
            } else {
                $name = trim($worksheet->getCell($nameCell)->getValue() ?? '');
            }
            
            if (empty($name)) continue;

            if (isCollection($config['collection_detection'] ?? [], $worksheet, $rowIndex, $config['column_mapping'], $templateConfig, $log)) {
                $currentCollection = $name;
            } else {
                // ОБНОВЛЕННАЯ ЛОГИКА: Динамическое чтение всех полей из конфига
                $productData = [
                    'collection' => $currentCollection,
                    'name' => $name,
                ];

                foreach ($config['column_mapping'] as $dbField => $excelColumn) {
                    if ($dbField === 'name') continue; // Имя уже получили
                    $cellAddress = $excelColumn . $rowIndex;
                    if (isCellMerged($worksheet, $cellAddress)) {
                        $rawValue = getMergedCellValue($worksheet, $cellAddress);
                    } else {
                        $rawValue = $worksheet->getCell($cellAddress)->getValue();
                    }
                    $productData[$dbField] = trim($rawValue ?? '');
                }

                // ОБНОВЛЕННАЯ ЛОГИКА: Расчет вычисляемых полей
                $priceIncrease = $config['price_increase_percentage'] ?? 0;
                if (isset($productData['base_price'])) {
                    $basePrice = (float)str_replace([',', ' '], ['.', ''], $productData['base_price']);
                    $productData['our_selling_price'] = $basePrice > 0 ? ($basePrice * (1 + ($priceIncrease / 100))) : 0;
                }

                // ОБНОВЛЕННАЯ ЛОГИКА: Расчет полей для совместимости с формой заказа
                $stockOnHand = (int)preg_replace('/\s+/', '', $productData['stock_on_hand'] ?? '0');
                $stockInTransit = (int)preg_replace('/\s+/', '', $productData['stock_in_transit'] ?? '0');
                $productData['stock'] = $stockOnHand + $stockInTransit;

                $priceToSave = 0;
                $d_price = (float)str_replace([',', ' '], ['.', ''], $productData['discounted_retail_price'] ?? '0');
                $r_price = (float)str_replace([',', ' '], ['.', ''], $productData['retail_price'] ?? '0');
                $o_price = (float)($productData['our_selling_price'] ?? '0');

                if ($d_price > 0) $priceToSave = $d_price;
                elseif ($r_price > 0) $priceToSave = $r_price;
                else $priceToSave = $o_price;
                $productData['price'] = $priceToSave;

                $productsForDb[] = $productData;
            }
        }

        $log->add("Найдено " . count($productsForDb) . " товарных позиций для импорта в '{$supplierCode}'.");
        
        $db->beginTransaction();
        try {
            $deletedRows = clear_supplier_data($db, $supplierCode);
            $log->add("Удалено {$deletedRows} старых записей.");
            save_products_to_db($db, $supplierCode, $productsForDb);
            $log->add("Сохранено " . count($productsForDb) . " новых записей.");
            $db->commit();
            $log->add("Данные для поставщика '{$supplierCode}' успешно сохранены в БД.");
            $result['success'] = true;
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        $log->add("КРИТИЧЕСКАЯ ОШИБКА при импорте XLS: " . $e->getMessage());
        $result['success'] = false;
    }
    return $result;
}