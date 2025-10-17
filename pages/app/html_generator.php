<?php
/**
 * Файл: /app/html_generator.php
 * Назначение: Генерирует статическую HTML-оболочку и динамический JSON-файл с данными.
 *
 * Ключевые изменения:
 * - `generate_html_shell`: Создает index.html с JavaScript-кодом. Этот JS асинхронно
 *   загружает button_data.json и строит на его основе список прайс-листов.
 * - `generate_button_data_json`: Готовит и сохраняет JSON-файл, содержащий
 *   актуальные данные (имена файлов, даты, логотипы) для отображения на index.html.
 */

/**
 * Генерирует статическую HTML-оболочку со встроенным JavaScript для динамической отрисовки.
 * @param array $templateConfig Конфигурация шаблона.
 * @param AppLogger $log Логгер.
 * @return string Содержимое файла index.html.
 */
function generate_html_shell(array $templateConfig, AppLogger $log): string {
    $log->add("Генерация статической HTML-оболочки для index.html...");
    
    $htmlCfg = $templateConfig['html_index_page'];
    $cols = $htmlCfg['table_columns'];
    
    // Преобразуем PHP-переменные в безопасный для JS формат
    $jsonConfig = json_encode([
        'columns' => $cols,
        'pageTitle' => $htmlCfg['page_title']
    ]);

    $html = <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>{$htmlCfg['page_title']}</title>
    <style>
        body { font-family: sans-serif; background-color: #f4f4f4; margin: 20px; }
        h1 { text-align: center; color: #333; }
        table { width: 100%; border-collapse: separate; border-spacing: 10px; }
        td { width: calc(100% / {$cols}); vertical-align: top; text-align: center; }
        a.price-link { display: block; padding: 10px; background-color: #fff; border: 1px solid #ddd; border-radius: 8px; text-decoration: none; color: #333; transition: box-shadow 0.2s, transform 0.2s; min-height: 150px; }
        a.price-link:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.15); transform: translateY(-2px); }
        img { max-width: 100%; height: 60px; object-fit: contain; margin-bottom: 10px; }
        .info { font-size: 1em; font-weight: bold; color: #333; }
        .date { font-size: 0.9em; color: #777; }
        .status { font-size: 1.2em; color: #888; text-align: center; padding: 50px; }
    </style>
</head>
<body>
    <h1>{$htmlCfg['page_title']}</h1>
    <table>
        <tbody id="price-list-container">
            <tr><td colspan="{$cols}" class="status">Загрузка актуальных прайс-листов...</td></tr>
        </tbody>
    </table>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const config = {$jsonConfig};
            const container = document.getElementById('price-list-container');

            async function loadPriceData() {
                try {
                    // Добавляем параметр для сброса кэша
                    const response = await fetch('button_data.json?v=' + new Date().getTime());
                    if (!response.ok) {
                        throw new Error(`Ошибка сети: \${response.status} \${response.statusText}`);
                    }
                    const data = await response.json();
                    
                    if (!data || data.length === 0) {
                         container.innerHTML = `<tr><td colspan="\${config.columns}" class="status">Прайс-листы не найдены.</td></tr>`;
                         return;
                    }

                    renderPriceList(data);

                } catch (error) {
                    console.error('Не удалось загрузить данные прайс-листов:', error);
                    container.innerHTML = `<tr><td colspan="\${config.columns}" class="status">Не удалось загрузить данные. Попробуйте обновить страницу.</td></tr>`;
                }
            }

            function renderPriceList(data) {
                let html = '';
                let colCounter = 0;

                data.forEach(item => {
                    if (colCounter === 0) {
                        html += '<tr>';
                    }

                    const logoPath = item.logo_filename ? `logo/\${item.logo_filename}` : '';
                    const logoImg = logoPath ? `<img src="\${logoPath}" alt="Логотип">` : '<div style="height:60px;"></div>';

                    html += `
                        <td>
                            <a href="xls/\${item.filename}" class="price-link">
                                \${logoImg}
                                <br>
                                <span class="info">\${item.info}</span>
                                <br>
                                <span class="date">\${item.date}</span>
                            </a>
                        </td>`;

                    colCounter++;
                    if (colCounter >= config.columns) {
                        html += '</tr>';
                        colCounter = 0;
                    }
                });

                if (colCounter > 0) {
                    html += '<td></td>'.repeat(config.columns - colCounter) + '</tr>';
                }

                container.innerHTML = html;
            }

            loadPriceData();
        });
    </script>
</body>
</html>
HTML;

    $log->add("HTML-оболочка успешно сгенерирована.");
    return $html;
}