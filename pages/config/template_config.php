<?php

// template_config.php
// НАСТРОЙКИ ВНЕШНЕГО ВИДА И ОФОРМЛЕНИЯ ВЫХОДНОГО ПРАЙС-ЛИСТА

function get_template_config() {
    return [
        // --- КОНТАКТЫ МЕНЕДЖЕРА ---
        'manager_name'  => 'Ваш менеджер: Дмитрий',
        'manager_phone' => '+7 (978) 742-77-63',
        // 'manager_email' => 'manager@mebel-idea.com',
        'manager_email' => 'ideamebel@gmail.com',

        // --- НАСТРОЙКИ HTML-СТРАНИЦЫ ---
        'html_index_page' => [
            'page_title' => 'Прайс-листы',
            'table_columns' => 5,
        ],

        // --- ОБЩИЕ ПАРАМЕТРЫ ФУНКЦИОНАЛА ---
        'ftp_active' => true,
        
        // --- НАСТРОЙКИ FTP ---
        'ftp_settings' => [
            'ftp_host' => '37.140.192.70',
            'ftp_port' => 21,
            'ftp_user' => 'u0617251_brw-crimea-prices',
            'ftp_pass_env_var' => 'FTP_PASSWORD_BRW_CRIMEA',
            'ftp_remote_path' => '',
            'ftp_passive_mode' => true,
        ],

        // --- СООБЩЕНИЯ ДЛЯ ЛОГА ---
        'log_messages' => [
            'ftp_skipped_active_false' => 'FTP отключена.', 'ftp_skipped_host_empty' => 'Хост FTP не указан.',
            'ftp_skipped_user_empty' => 'Пользователь FTP не указан.', 'ftp_skipped_pass_empty' => 'Пароль FTP не задан.',
            'ftp_connect_error' => 'Ошибка подключения к FTP %s:%d. Причина: %s.',
            'ftp_login_error' => 'Ошибка авторизации на FTP %s под пользователем %s.',
            'ftp_upload_error' => 'Ошибка загрузки файла %s в \'%s\'. Причина: %s.',
            'ftp_upload_success' => 'Файл успешно выгружен по FTP в: %s.',
            'html_index_generated' => 'HTML-индекс сгенерирован: index.html.',
            'html_index_skipped' => 'HTML-индекс не требует обновления (файлы прайс-листов не изменились).',
        ],
        
        // --- ЦВЕТА ---
        'colors' => [
            'border' => 'CCC085', 'header_fill' => 'f4ecc5', 'collection_fill' => 'f8f2d8',
            'name_removed' => '5E5E30', 'out_of_stock_text' => 'A9A9A9',
        ],

        // --- СТИЛИ ШРИФТОВ ДЛЯ ШАПКИ ---
        'font_styles' => [
            'manager_contact_main' => ['bold' => true, 'size' => 16],
            'manager_contact_email' => ['size' => 12],
            'price_list_note' => ['size' => 13],
        ],

        // --- РАЗМЕРЫ ---
        'dimensions' => [
            'logo_height' => 45, 'header_row_height' => 42.5,
            'header_main_row_height' => 35,
            'column_widths' => [ 
                'A' => 12,
                'B' => 80,
                'C' => 15, 
                'D' => 10, 'E' => 12,
                'F' => 12, 'G' => 12, 'H' => 12, 'I' => 15, 
            ],
        ],

        // --- ФОРМАТЫ ДАННЫХ ---
        'formats' => [
            'date_header' => 'dd MMMM yyyy г. HH:mm', 'price' => '#,##0', 'stock' => '0',
            'percentage' => '0%', 'arrival_date' => 'dd.mm.yyyy',
        ],

        // --- ПРЕСЕТЫ СТИЛЕЙ ДЛЯ КОЛЛЕКЦИЙ ---
        'collection_style_presets' => [
            'bold_font' => ['style_rules' => ['font_bold' => true]],
            'color_font_00005c' => ['style_rules' => ['font_color' => '#00005c']],
        ],

        // --- МАРКЕРЫ СНЯТЫХ ТОВАРОВ ---
        'discontinued_markers' => [
            'снято', 'архив', 'выведен из ассортимента', 'не производится', 'распродажа остатков'
        ],
    ];
}