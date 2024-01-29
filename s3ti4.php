<?php
require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;


global $nn;
if($_SERVER['argv'][1]) {
    $localDirectory = '/root/aparser/results/images/tiktok4/' . $_SERVER['argv'][1]; // Локальная папка, которую вы хотите скопировать
} else {
    $localDirectory = '/root/aparser/results/images/tiktok4'; // Локальная папка, которую вы хотите скопировать
}


echo $localDirectory."\n";
//die();

//$localDirectory = '/storage/tiktok'; // Локальная папка, которую вы хотите скопировать
$bucketName = 'ti4'; // Название бакета S3, в который вы хотите скопировать файлы
$s3Path = $_SERVER['argv'][1];

// Создание клиента S3
$s3Client = new S3Client([
    'version'     => 'latest',
    'region'      => 'eu-west-1', // Укажите свой регион
    'endpoint'    => 'https://s3.nl.geostorage.net',
    'credentials' => [
        'key'    => 'KRGQHHNYT767Q1KB15BK',
        'secret' => 'FDpEGAAKcuYiOB2AYBsusljIi9AlHmvC1VaCVSps',
    ],
    'use_path_style_endpoint' => true,
    'http' => [
        'timeout' => 5, // Установите желаемый таймаут (в секундах)
    ],
]);

function isEmptyDir($dir) {
    if (!is_readable($dir)) return NULL;
    return (count(scandir($dir)) == 2); // '.' and '..' are always present
}


// Рекурсивная функция для копирования файлов и папок
function copyToS3($localPath, $s3Path) {
    global $s3Client, $bucketName, $nn;

    if (is_dir($localPath)) {
        $objects = scandir($localPath);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                copyToS3("$localPath/$object", "$s3Path/$object");
            }
        }
        // После обработки всех файлов в каталоге, проверяем, пуст ли он
        if (isEmptyDir($localPath)) {
            rmdir($localPath); // Удаляем каталог, если он пуст
            echo "Пустой каталог $localPath удален\n";
        }
    } else {
        try {
            $s3Path = ltrim($s3Path, '/');
            $s3Client->putObject([
                'Bucket' => $bucketName,
                'Key' => $s3Path,
                'SourceFile' => $localPath,
            ]);
            echo "Файл $localPath успешно загружен в S3 $s3Path";
            unlink($localPath); // Удаление файла
            echo " удален\n";
            $nn++;
            if(!($nn%1000)) echo "$nn files\n\n";

            // Проверка и удаление родительского каталога, если он пуст
            $parentDir = dirname($localPath);
            if (isEmptyDir($parentDir)) {
                rmdir($parentDir);
                echo "Пустой родительский каталог $parentDir удален\n";
            }
        } catch (AwsException $e) {
            echo "Ошибка при загрузке файла $localPath в S3: " . $e->getMessage() . "\n";
        }
    }
}

// Начинаем копирование с локальной директории "tiktok" в корневую директорию S3
copyToS3($localDirectory, $s3Path);

echo "Завершено.\n";
