<?php
// /app/excel_generator.php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Drawing as PhpSpreadsheetDrawing;

require_once __DIR__ . '/database.php';

function generate_xlsx_from_db(PDO $db, string $supplierCode, array $config, array $templateConfig, string $dateString, string $outputFilePath, string $logoFilePath, AppLogger $log): array {
    $log->add("Начинается генерация XLSX для '{$supplierCode}' из базы данных...");
    try {
        $stmt = $db->prepare("SELECT * FROM products WHERE supplier_code = ? ORDER BY collection, name");
        $stmt->execute([$supplierCode]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $log->add("Получено " . count($products) . " записей из БД для генерации файла.");
        
        $cfg = $templateConfig;
        $dims = $cfg['dimensions']; $colors = $cfg['colors']; $formats = $cfg['formats'];
        
        // Стили
        $headerStyle = [ 'font' => ['bold' => true], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colors['header_fill']]], 'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $colors['border']]]], ];
        $collectionStyle = [ 'font' => ['bold' => true, 'color' => ['rgb' => $colors['name_removed']]], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colors['collection_fill']]], 'borders' => ['outline' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $colors['border']]]], ];
        $allCellsStyle = [ 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true], 'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $colors['border']]]], ];
        
        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setShowGridlines(false);

        $columnOrder = $config['output_column_order'] ?? ['name' => 'Наименование'];
        $lastHeaderColumnChar = 'A';
        if (!empty($columnOrder)) {
            $lastHeaderColumnChar = chr(ord('A') + count($columnOrder) - 1);
        }
        
        // --- ШАПКА ---
        $mainRowHeight = $dims['header_main_row_height'] ?? 35;
        $worksheet->getRowDimension('1')->setRowHeight($mainRowHeight);
        $worksheet->getRowDimension('2')->setRowHeight(20);
        $worksheet->getRowDimension('3')->setRowHeight(25);
        
        $worksheet->getCell('A1')->setValue($cfg['manager_name'] . ', ' . $cfg['manager_phone'])->getStyle()->getFont()->applyFromArray($cfg['font_styles']['manager_contact_main']);
        $worksheet->getCell('A1')->getStyle()->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_LEFT);
        
        if (!empty($logoFilePath) && file_exists($logoFilePath)) {
            $drawing = new Drawing();
            $drawing->setPath($logoFilePath);
            $drawing->setCoordinates($lastHeaderColumnChar . '1');
            list($imgWidth, $imgHeight) = getimagesize($logoFilePath);
            $imageAspectRatio = $imgWidth / $imgHeight;
            $colWidthUnits = $dims['column_widths'][$lastHeaderColumnChar] ?? 12;
            $colWidthPixels = PhpSpreadsheetDrawing::cellDimensionToPixels($colWidthUnits, $spreadsheet->getDefaultStyle()->getFont());
            $rowHeightPixels = PhpSpreadsheetDrawing::pointsToPixels($mainRowHeight);
            $containerAspectRatio = $colWidthPixels / $rowHeightPixels;
            if ($imageAspectRatio > $containerAspectRatio) { $drawing->setWidth($colWidthPixels - 4); } else { $drawing->setHeight($rowHeightPixels - 4); }
            $drawing->setResizeProportional(true);
            $drawing->setWorksheet($worksheet);
            $worksheet->getStyle($lastHeaderColumnChar . '1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setVertical(Alignment::VERTICAL_CENTER);
        }
        
        $emailCell = $worksheet->getCell($lastHeaderColumnChar . '2');
        $emailCell->setValue($cfg['manager_email'])->getHyperlink()->setUrl("mailto:" . $cfg['manager_email']);
        $emailFont = $emailCell->getStyle()->getFont();
        $emailFont->applyFromArray($cfg['font_styles']['manager_contact_email']);
        $emailFont->setUnderline('none')->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_BLUE));
        $emailCell->getStyle()->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        
        $worksheet->mergeCells('A3:' . $lastHeaderColumnChar . '3');
        $worksheet->getCell('A3')->setValue($config['price_list_note'] ?? '');
        $worksheet->getCell('A3')->getStyle()->getFont()->applyFromArray($cfg['font_styles']['price_list_note']);
        $worksheet->getCell('A3')->getStyle()->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // --- ЗАГОЛОВКИ ТАБЛИЦЫ ---
        foreach (array_values($columnOrder) as $colIndex => $label) {
            $colChar = chr(ord('A') + $colIndex);
            $value = '';
            switch ($label) {
                case 'MANUFACTURER': $value = $config['manufacturer_info'] ?? ''; break;
                case 'DATE_STRING': $value = $dateString; break; // ИЗМЕНЕНО: Прямое использование
                default: $value = $label; break;
            }
            $worksheet->setCellValue($colChar . '5', $value);
        }

        // --- ТЕЛО ТАБЛИЦЫ ---
        $worksheet->freezePane('A6');
        $newRowIndex = 6;
        $currentCollection = '---';
        foreach ($products as $product) {
            if ($product['collection'] !== $currentCollection) {
                $currentCollection = $product['collection'];
                $worksheet->setCellValue('A' . $newRowIndex, $currentCollection)->mergeCells('A' . $newRowIndex . ':' . $lastHeaderColumnChar . $newRowIndex);
                $worksheet->getStyle('A' . $newRowIndex)->applyFromArray($collectionStyle);
                $newRowIndex++;
            }
            
            $colIndex = 0;
            foreach ($columnOrder as $key => $label) {
                $colChar = chr(ord('A') + $colIndex);
                $value = $product[$key] ?? '';
                if ($key === 'discount_percent' && is_numeric($value)) {
                    $value = (float)$value / 100;
                }
                $worksheet->setCellValue($colChar . $newRowIndex, $value);
                $colIndex++;
            }
            
            $stockOnHand = $product['stock_on_hand'] ?? 0;
            $stockInTransit = $product['stock_in_transit'] ?? 0;
            $hasZeroStock = (empty($stockOnHand) || $stockOnHand == '0') && (empty($stockInTransit) || $stockInTransit == '0');

            if ($hasZeroStock) {
                $worksheet->getStyle('A' . $newRowIndex . ':' . $lastHeaderColumnChar . $newRowIndex)->getFont()->getColor()->setRGB($colors['out_of_stock_text']);
            } else {
                $isDiscontinued = false;
                $discontinuedMarkers = $cfg['discontinued_markers'] ?? [];
                foreach ($discontinuedMarkers as $marker) {
                    if (!empty($marker) && mb_stripos($product['name'], $marker) !== false) {
                        $isDiscontinued = true;
                        break;
                    }
                }
                if ($isDiscontinued) {
                    $nameColIndex = array_search('name', array_keys($columnOrder));
                    if ($nameColIndex !== false) {
                        $nameColChar = chr(ord('A') + $nameColIndex);
                        $worksheet->getStyle($nameColChar . $newRowIndex)->getFont()->getColor()->setRGB($colors['name_removed']);
                    }
                }
            }
            
            $newRowIndex++;
        }

        // --- ФИНАЛЬНОЕ ФОРМАТИРОВАНИЕ ---
        $lastRow = $newRowIndex - 1;
        if ($lastRow >= 6) {
            $worksheet->getStyle('A5:' . $lastHeaderColumnChar . '5')->applyFromArray($headerStyle);
            $worksheet->getRowDimension('5')->setRowHeight($dims['header_row_height'] ?? 42.5);
            $worksheet->getStyle('A6:' . $lastHeaderColumnChar . $lastRow)->applyFromArray($allCellsStyle);
            
            $colIndex = 0;
            foreach ($columnOrder as $key => $label) {
                $colChar = chr(ord('A') + $colIndex);
                $formatKey = '';
                if (in_array($key, ['our_selling_price', 'retail_price', 'discounted_retail_price', 'base_price', 'price'])) $formatKey = 'price';
                elseif (in_array($key, ['stock_on_hand', 'stock_in_transit', 'stock'])) $formatKey = 'stock';
                elseif ($key == 'discount_percent') $formatKey = 'percentage';
                elseif ($key == 'stock_arrival_date') $formatKey = 'arrival_date';
                
                if ($formatKey && isset($formats[$formatKey])) {
                    $worksheet->getStyle($colChar . '6:' . $colChar . $lastRow)->getNumberFormat()->setFormatCode($formats[$formatKey]);
                }
                $colIndex++;
            }

            foreach (array_keys($dims['column_widths']) as $colChar) {
                $worksheet->getColumnDimension($colChar)->setWidth($dims['column_widths'][$colChar]);
            }
        }
        
        $outputDirectory = dirname($outputFilePath);
        if (!is_dir($outputDirectory)) {
            if (!mkdir($outputDirectory, 0755, true)) { throw new Exception("Не удалось создать директорию: {$outputDirectory}"); }
        }

        // ИЗМЕНЕНО: Сброс активной ячейки перед сохранением
        $worksheet->setSelectedCell('A1');

        $writer = new Xlsx($spreadsheet);
        $writer->save($outputFilePath);

        if (!file_exists($outputFilePath) || filesize($outputFilePath) === 0) {
            throw new Exception("Файл не сохранился или пуст: {$outputFilePath}");
        }

        $log->add("Файл '{$outputFilePath}' успешно сгенерирован.");
        return ['success' => true];

    } catch(Exception $e) {
        $log->add("КРИТИЧЕСКАЯ ОШИБКА при генерации XLSX: " . $e->getMessage() . " (Строка: " . $e->getLine() . ")");
        return ['success' => false];
    }
}