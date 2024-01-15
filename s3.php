<?php
require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

try {
    $bucketName = 'instabucket';
    $prefix = 'photos/M_171/';

    // Создание клиента S3 с опцией таймаута
    $s3Client = new S3Client([
        'version'     => 'latest',
        'region'      => 'eu-west-1',
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

    $continuationToken = null;

    do {
        // Получение списка объектов в указанной папке с использованием маркера продолжения
        $objects = $s3Client->listObjectsV2([
            'Bucket' => $bucketName,
            'Prefix' => $prefix,
            'ContinuationToken' => $continuationToken,
        ]);

        // Удаление каждого объекта
        foreach ($objects['Contents'] as $object) {
            $key = $object['Key'];
            $n++;
            echo "Удаляем объект [$n]: $key ";

            // Попытка удаления файла с обработкой таймаута
            try {
                $s3Client->deleteObject([
                    'Bucket' => $bucketName,
                    'Key' => $key,
                ]);
                echo " => удален\n";
            } catch (AwsException $e) {
                // Запись информации о таймауте в журнал ошибок
                error_log("Таймаут при удалении объекта $key: " . $e->getMessage());
                echo " => таймаут\n";
            }
        }

        // Обновление маркера продолжения
        $continuationToken = $objects['NextContinuationToken'];
    } while ($objects['IsTruncated']);
} catch (AwsException $e) {
    // Вывод ошибки, если что-то пошло не так
    echo $e->getMessage() . "\n";
}
