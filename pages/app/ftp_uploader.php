<?php
// /app/ftp_uploader.php

function upload_files_via_ftp(array $files_to_upload, array $ftp_config, AppLogger $log): bool {
    $log->add("Начинается процесс выгрузки по FTP...");
    
    $ftp_pass = getenv($ftp_config['ftp_pass_env_var'] ?? '');
    if (empty($ftp_config['ftp_host']) || empty($ftp_config['ftp_user']) || empty($ftp_pass)) {
        $log->add("ОШИБКА FTP: Не указаны хост, пользователь или пароль. Выгрузка отменена.");
        return false;
    }

    $conn = ftp_connect($ftp_config['ftp_host'], $ftp_config['ftp_port'] ?? 21);
    if (!$conn) {
        $log->add("ОШИБКА FTP: Не удалось подключиться к {$ftp_config['ftp_host']}.");
        return false;
    }

    if (!ftp_login($conn, $ftp_config['ftp_user'], $ftp_pass)) {
        $log->add("ОШИБКА FTP: Не удалось авторизоваться под пользователем {$ftp_config['ftp_user']}.");
        ftp_close($conn);
        return false;
    }

    ftp_pasv($conn, $ftp_config['ftp_passive_mode'] ?? true);
    $log->add("FTP-соединение установлено.");

    $all_successful = true;
    foreach ($files_to_upload as $local_path => $remote_path) {
        if (!file_exists($local_path)) {
            $log->add("ОШИБКА: Локальный файл не найден, пропуск: {$local_path}");
            $all_successful = false;
            continue;
        }

        $remote_dir = dirname($remote_path);
        if ($remote_dir !== '.' && !ftp_directory_exists($conn, $remote_dir)) {
            if (ftp_mkdir_recursive($conn, $remote_dir)) {
                $log->add("FTP: Создана директория '{$remote_dir}'.");
            } else {
                $log->add("ОШИБКА FTP: Не удалось создать директорию '{$remote_dir}'.");
                $all_successful = false;
                continue;
            }
        }

        if (ftp_put($conn, $remote_path, $local_path, FTP_BINARY)) {
            $log->add("FTP: Файл '{$local_path}' успешно выгружен в '{$remote_path}'.");
        } else {
            $log->add("ОШИБКА FTP: Не удалось выгрузить файл '{$local_path}' в '{$remote_path}'.");
            $all_successful = false;
        }
    }

    ftp_close($conn);
    $log->add("FTP-соединение закрыто.");
    return $all_successful;
}

function ftp_mkdir_recursive($conn, $dir): bool {
    $parts = explode('/', $dir);
    $path = '';
    foreach ($parts as $part) {
        if (empty($part)) continue;
        $path .= $part . '/';
        if (!ftp_directory_exists($conn, $path)) {
            if (!@ftp_mkdir($conn, $path)) {
                return false;
            }
        }
    }
    return true;
}

function ftp_directory_exists($conn, $dir): bool {
    $current_dir = ftp_pwd($conn);
    if (@ftp_chdir($conn, $dir)) {
        ftp_chdir($conn, $current_dir);
        return true;
    }
    return false;
}