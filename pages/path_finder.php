<?php
// path_finder.php
// Этот скрипт определяет и выводит абсолютные пути к приватной и публичной директориям.

echo "--- Диагностика путей ---" . PHP_EOL . PHP_EOL;

// 1. Абсолютный путь к приватной директории (где запущен этот скрипт)
$privatePath = __DIR__;
$realPrivatePath = realpath($privatePath);

if (!$realPrivatePath) {
    echo "КРИТИЧЕСКАЯ ОШИБКА: Не удалось определить путь к текущей директории." . PHP_EOL;
    exit(1);
}

echo "Обнаружен абсолютный путь к приватной части (PAGES_PATH):" . PHP_EOL;
echo $realPrivatePath . PHP_EOL . PHP_EOL;


// 2. Пытаемся найти публичную директорию, которая должна быть на одном уровне
$potentialPublicPath = dirname($realPrivatePath) . '/prices';
$realPublicPath = realpath($potentialPublicPath);

if ($realPublicPath) {
    echo "Обнаружен абсолютный путь к публичной части (PUBLIC_PATH):" . PHP_EOL;
    echo $realPublicPath . PHP_EOL . PHP_EOL;
} else {
    echo "ВНИМАНИЕ: Публичная директория по пути '{$potentialPublicPath}' не найдена." . PHP_EOL;
    echo "Пожалуйста, убедитесь, что папка 'prices' существует и находится на одном уровне с папкой 'pages'." . PHP_EOL . PHP_EOL;
}


// 3. Если оба пути найдены, генерируем готовый код для вставки
if ($realPrivatePath && $realPublicPath) {
    echo "--- Готовый код для run_all.php и run.php ---" . PHP_EOL;
    echo "Скопируйте и вставьте этот блок в самое начало ваших скриптов:" . PHP_EOL;
    echo "--------------------------------------------------------" . PHP_EOL;
    echo "define('PAGES_PATH', '" . $realPrivatePath . "');" . PHP_EOL;
    echo "define('PUBLIC_PATH', '" . $realPublicPath . "');" . PHP_EOL;
    echo "--------------------------------------------------------" . PHP_EOL;
}