<?php
// /config/suppliers_config.php
// Этот файл должен содержать ТОЛЬКО функцию get_suppliers_config()

function get_suppliers_config() {
    return [
         'BRW_Brilan' => [
            'input_file' => 'http://sky.brw.name/ostatki_brw.xls',
            'output_filename' => 'brw.xlsx',
            'logo_filename' => 'brw_logo.jpg',
            'start_row' => 5,
            'manufacturer_info' => 'Остатки «БРВ» в Москве с ценами',
            'source_date_cell' => 'A2', // ИСПРАВЛЕНО
            'price_increase_percentage' => 8.0,
            'price_list_note' => 'Внимание! Если остаток "0", но товар есть в прайсе, значит он есть на остатках в Бресте. Звоните или пишите, получим подтверждение завода и ответим.',
            'collection_detection' => [
                'method' => 'by_preset',
                'preset_name' => 'bold_font',
                'column_to_check' => 'B'
            ],
            
             'output_column_order' => [
                'article' => 'Артикул',
                'name' => 'MANUFACTURER', 
                'stock_on_hand' => 'DATE_STRING',
                'stock_in_transit' => 'В пути',
                'stock_arrival_date' => 'Дата поступления', 
                'our_selling_price' => 'Закупочная',
                'retail_price' => 'Розничная цена без скидки', 
                'discount_percent' => 'Акционная скидка (%)',
                'discounted_retail_price' => 'Розничная цена со скидкой',
            ],
            'column_mapping' => [
                'article' => 'A', 
                'name' => 'B', 
                'stock_on_hand' => 'C', 
                'stock_in_transit' => 'D',
                'base_price' => 'N', 
                'retail_price' => 'O',  
                'discount_percent' => 'P', 
                'discounted_retail_price' => 'Q',
            ],
        ],
        
         'Anrex_Anrex' => [
            'input_file' => 'https://anrex.info/files/import/ostatki-osnjvnoi-sklad.xls',
            'output_filename' => 'anrex_1.xlsx',
            'logo_filename' => 'anrex_logo.png',
            'start_row' => 10,
            'manufacturer_info' => 'Остатки «АНРЭКС» в Москве',
            'source_date_cell' => 'B4', // Оставлено без изменений
            'price_increase_percentage' => 0.0,
            'price_list_note' => 'Остатки по основному складу.',
            'collection_detection' => [
                'method' => 'by_preset',
                'preset_name' => 'bold_font',
                'column_to_check' => 'C'
            ],
            'output_column_order' => [
                'article' => 'Артикул',
                'name' => 'MANUFACTURER',
                'stock_on_hand' => 'DATE_STRING'
            ],
            'column_mapping' => [
                'name' => 'C', 
                'article' => 'B', 
                'stock_on_hand' => 'F',
            ],
        ],
        
        'Anrex_Brilan' => [
            'input_file' => 'http://anrex.brw.name/ostatki_anrex.xls',
            'output_filename' => 'anrex_2.xlsx',
            'logo_filename' => 'anrex_logo.png',
            'start_row' => 5,
            'manufacturer_info' => 'Остатки с ценами «АНРЭКС» ч.2',
            'source_date_cell' => 'A2', // ИСПРАВЛЕНО
            'price_increase_percentage' => 8.0,
            'price_list_note' => 'Внимание! Если остаток "0", но товар есть в прайсе, значит он есть на остатках в Бресте. Звоните или пишите, получим подтверждение завода и ответим.',
            'collection_detection' => [
                'method' => 'by_preset',
                'preset_name' => 'bold_font',
                'column_to_check' => 'B'
            ],
             'output_column_order' => [
                'article' => 'Артикул',
                'name' => 'MANUFACTURER', 
                'stock_on_hand' => 'DATE_STRING',
                'stock_in_transit' => 'В пути',
                'stock_arrival_date' => 'Дата поступления', 
                'our_selling_price' => 'Закупочная',
                'retail_price' => 'Розничная цена без скидки', 
                'discount_percent' => 'Акционная скидка (%)',
                'discounted_retail_price' => 'Розничная цена со скидкой',
            ],
            'column_mapping' => [
                'article' => 'A', 
                'name' => 'B', 
                'stock_on_hand' => 'C', 
                'stock_in_transit' => 'D',
                'base_price' => 'N', 
                'retail_price' => 'O',  
                'discount_percent' => 'P', 
                'discounted_retail_price' => 'Q',
            ],
        ],
//**** */
       'Anrex_Prices' => [
            'input_file' => 'https://anrex.info/files/import/Ostatki_Price_Anreks.xls',
            'output_filename' => 'anrex_prices.xlsx',
            'logo_filename' => 'anrex_logo.png',
            'start_row' => 5,
            'manufacturer_info' => 'Остатки с ценами «АНРЭКС» с ценами',
            'source_date_cell' => '', // ИСПРАВЛЕНО
            'price_increase_percentage' => 8.0,
            'price_list_note' => 'Внимание! Звоните или пишите, получим подтверждение завода и ответим.',
            'collection_detection' => [
                'method' => 'by_preset',
                'preset_name' => 'bold_font',
                'column_to_check' => 'A'
            ],
             'output_column_order' => [
                'article' => 'Артикул',
                'name' => 'MANUFACTURER', 
                'stock_on_hand' => 'DATE_STRING',
                //'stock_in_transit' => 'В пути',
                //'stock_arrival_date' => 'Дата поступления', 
                'our_selling_price' => 'Опт',
                'retail_price' => 'Розничная цена без скидки', 
                'discount_percent' => 'Акционная скидка (%)',
                'discounted_retail_price' => 'Розничная цена со скидкой',
            ],
            'column_mapping' => [
                'article' => 'A', 
                'name' => 'B', 
                'stock_on_hand' => 'L', 
                //'stock_in_transit' => 'D',
                'base_price' => 'F', 
                'retail_price' => 'I',  
                'discount_percent' => 'J', 
                'discounted_retail_price' => 'K',
            ],
        ],
//****** */
    ];
}