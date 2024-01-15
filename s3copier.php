<?php
require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;


global $nn;

$localDirectory = '/storage/tiktok/'.$_SERVER['argv'][1]; // Локальная папка, которую вы хотите скопировать

echo $localDirectory."\n";
//die();

//$localDirectory = '/storage/tiktok'; // Локальная папка, которую вы хотите скопировать
$bucketName = 'tiktok-images'; // Название бакета S3, в который вы хотите скопировать файлы

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
        'timeout' => 1, // Установите желаемый таймаут (в секундах)
    ],
]);

// Рекурсивная функция для копирования файлов и папок
function copyToS3($localPath, $s3Path) {
    global $s3Client, $bucketName, $nn;

    // Проверка, является ли текущий элемент файлом или директорией
    if (is_dir($localPath)) {
        $objects = scandir($localPath);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                // Рекурсивно вызываем эту функцию для поддиректорий
                copyToS3("$localPath/$object", "$s3Path/$object");
            }
        }
    } else {
        try {
            $s3Path = ltrim($s3Path, '/');
            // Загрузка файла в бакет S3
            $s3Client->putObject([
                'Bucket' => $bucketName,
                'Key' => $s3Path,
                'SourceFile' => $localPath,
            ]);
            echo "Файл $localPath успешно загружен в S3 $s3Path";
            system("rm -rf $localPath");
            echo " удален\n";
            $nn++;
            //die();
            if(!($nn%1000)) echo "$nn files\n\n";
        } catch (AwsException $e) {
            echo "Ошибка при загрузке файла $localPath в S3: " . $e->getMessage() . "\n";
        }
    }
}

// Начинаем копирование с локальной директории "tiktok" в корневую директорию S3
copyToS3($localDirectory, '');

echo "Завершено.\n";
