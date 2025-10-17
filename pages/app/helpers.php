<?php
// /app/helpers.php

function get_remote_modification_time(string $url): int {
    stream_context_set_default(['http' => ['method' => 'HEAD']]);
    $headers = @get_headers($url, 1);
    stream_context_set_default(['http' => ['method' => 'GET']]);
    if (empty($headers) || strpos($headers[0], '200') === false) return 0;
    $lastModified = $headers['Last-Modified'] ?? $headers['last-modified'] ?? null;
    return $lastModified ? (int)strtotime($lastModified) : time();
}

function isCollection(array $detectionConfig, PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $worksheet, int $rowIndex, array $columnMapping, array $templateConfig, AppLogger $log): bool {
    $method = $detectionConfig['method'] ?? 'by_empty_article';
    switch ($method) {
        case 'by_preset':
            $presetName = $detectionConfig['preset_name'] ?? null;
            if (!$presetName) { return false; }
            $presets = $templateConfig['collection_style_presets'] ?? [];
            if (!isset($presets[$presetName])) { return false; }
            $rules = $presets[$presetName]['style_rules'] ?? [];
            $checkColumn = $detectionConfig['column_to_check'] ?? ($columnMapping['name'] ?? 'A');
            
            // Проверяем, является ли ячейка объединенной
            $cellAddress = $checkColumn . $rowIndex;
            if (isCellMerged($worksheet, $cellAddress)) {
                // Для объединенных ячеек применяем стили к левой верхней ячейке диапазона
                $rangeBoundaries = getMergedRangeBoundaries($worksheet, $cellAddress);
                if ($rangeBoundaries) {
                    $firstColumn = $rangeBoundaries[0][0];
                    $firstRow = $rangeBoundaries[0][1];
                    $topLeftCell = PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($firstColumn) . $firstRow;
                    return checkStyleRules($rules, $worksheet->getStyle($topLeftCell));
                }
            }
            
            return checkStyleRules($rules, $worksheet->getStyle($checkColumn . $rowIndex));
        case 'by_style':
            $rules = $detectionConfig['style_rules'] ?? [];
            $checkColumn = $detectionConfig['column_to_check'] ?? ($columnMapping['name'] ?? 'A');
            
            // Проверяем, является ли ячейка объединенной
            $cellAddress = $checkColumn . $rowIndex;
            if (isCellMerged($worksheet, $cellAddress)) {
                // Для объединенных ячеек применяем стили к левой верхней ячейке диапазона
                $rangeBoundaries = getMergedRangeBoundaries($worksheet, $cellAddress);
                if ($rangeBoundaries) {
                    $firstColumn = $rangeBoundaries[0][0];
                    $firstRow = $rangeBoundaries[0][1];
                    $topLeftCell = PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($firstColumn) . $firstRow;
                    return checkStyleRules($rules, $worksheet->getStyle($topLeftCell));
                }
            }
            
            return checkStyleRules($rules, $worksheet->getStyle($checkColumn . $rowIndex));
        default:
            $articleCol = $columnMapping['article'] ?? '';
            if (!empty($articleCol)) {
                $cellAddress = $articleCol . $rowIndex;
                if (isCellMerged($worksheet, $cellAddress)) {
                    $value = getMergedCellValue($worksheet, $cellAddress);
                } else {
                    $value = $worksheet->getCell($cellAddress)->getValue();
                }
                return empty($value);
            }
            return true;
    }
}

function checkStyleRules(array $rules, PhpOffice\PhpSpreadsheet\Style\Style $style): bool {
    if (isset($rules['font_bold']) && $style->getFont()->getBold() != $rules['font_bold']) return false;
    // ... можно добавить другие проверки стиля при необходимости
    return true;
}

function parse_date_string(PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $worksheet, string $cellAddress, string $filePath): string {
    if (!empty($cellAddress) && $worksheet->getCell($cellAddress)->getValue()) {
        return trim($worksheet->getCell($cellAddress)->getValue() ?? '');
    }
    if (file_exists($filePath)) {
        return "от " . date('d.m.Y H:i', filemtime($filePath));
    }
    return "от " . date('d.m.Y H:i');
}

/**
 * Проверяет, является ли ячейка частью объединённого диапазона
 */
function isCellMerged(PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $worksheet, string $cellAddress): bool {
    $mergeRanges = $worksheet->getMergeCells();
    foreach ($mergeRanges as $range) {
        $rangeBoundaries = PhpOffice\PhpSpreadsheet\Cell\Coordinate::rangeBoundaries($range);
        $firstRow = $rangeBoundaries[0][1];
        $lastRow = $rangeBoundaries[1][1];
        $firstColumn = $rangeBoundaries[0][0];
        $lastColumn = $rangeBoundaries[1][0];
        
        $cellCoordinates = PhpOffice\PhpSpreadsheet\Cell\Coordinate::coordinateFromString($cellAddress);
        $cellRow = (int)$cellCoordinates[1];
        $cellColumnIndex = PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($cellCoordinates[0]);
        
        if ($cellRow >= $firstRow && $cellRow <= $lastRow && $cellColumnIndex >= $firstColumn && $cellColumnIndex <= $lastColumn) {
            return true;
        }
    }
    return false;
}

/**
 * Получает границы объединённого диапазона, к которому принадлежит ячейка
 */
function getMergedRangeBoundaries(PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $worksheet, string $cellAddress) {
    $mergeRanges = $worksheet->getMergeCells();
    foreach ($mergeRanges as $range) {
        $rangeBoundaries = PhpOffice\PhpSpreadsheet\Cell\Coordinate::rangeBoundaries($range);
        $firstRow = $rangeBoundaries[0][1];
        $lastRow = $rangeBoundaries[1][1];
        $firstColumn = $rangeBoundaries[0][0];
        $lastColumn = $rangeBoundaries[1][0];
        
        $cellCoordinates = PhpOffice\PhpSpreadsheet\Cell\Coordinate::coordinateFromString($cellAddress);
        $cellRow = (int)$cellCoordinates[1];
        $cellColumnIndex = PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($cellCoordinates[0]);
        
        if ($cellRow >= $firstRow && $cellRow <= $lastRow && $cellColumnIndex >= $firstColumn && $cellColumnIndex <= $lastColumn) {
            return $rangeBoundaries;
        }
    }
    return null;
}

/**
 * Получает значение из объединённой ячейки (из левой верхней ячейки диапазона)
 */
function getMergedCellValue(PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $worksheet, string $cellAddress) {
    $mergeRanges = $worksheet->getMergeCells();
    foreach ($mergeRanges as $range) {
        $rangeBoundaries = PhpOffice\PhpSpreadsheet\Cell\Coordinate::rangeBoundaries($range);
        $firstRow = $rangeBoundaries[0][1];
        $lastRow = $rangeBoundaries[1][1];
        $firstColumn = $rangeBoundaries[0][0];
        $lastColumn = $rangeBoundaries[1][0];
        
        $cellCoordinates = PhpOffice\PhpSpreadsheet\Cell\Coordinate::coordinateFromString($cellAddress);
        $cellRow = (int)$cellCoordinates[1];
        $cellColumnIndex = PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($cellCoordinates[0]);
        
        if ($cellRow >= $firstRow && $cellRow <= $lastRow && $cellColumnIndex >= $firstColumn && $cellColumnIndex <= $lastColumn) {
            // Возвращаем значение из левой верхней ячейки объединенного диапазона
            $topLeftCell = PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($firstColumn) . $firstRow;
            return $worksheet->getCell($topLeftCell)->getValue();
        }
    }
    
    // Если ячейка не объединена, возвращаем значение из самой ячейки
    return $worksheet->getCell($cellAddress)->getValue();
}

/**
 * Разъединяет все объединённые ячейки на листе и копирует значение в каждую ячейку диапазона
 */
function unmergeCells(PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $worksheet) {
    $mergeRanges = $worksheet->getMergeCells();
    
    foreach ($mergeRanges as $range) {
        // Получаем значение из левой верхней ячейки объединенного диапазона
        $rangeBoundaries = PhpOffice\PhpSpreadsheet\Cell\Coordinate::rangeBoundaries($range);
        $firstRow = $rangeBoundaries[0][1];
        $firstColumn = $rangeBoundaries[0][0];
        $lastRow = $rangeBoundaries[1][1];
        $lastColumn = $rangeBoundaries[1][0];
        
        $topLeftCell = PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($firstColumn) . $firstRow;
        $value = $worksheet->getCell($topLeftCell)->getValue();
        
        // Разъединяем ячейки
        $worksheet->unmergeCells($range);
        
        // Копируем значение в каждую ячейку диапазона
        for ($row = $firstRow; $row <= $lastRow; $row++) {
            for ($colIndex = $firstColumn; $colIndex <= $lastColumn; $colIndex++) {
                $colLetter = PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                $worksheet->setCellValue($colLetter . $row, $value);
            }
        }
    }
}