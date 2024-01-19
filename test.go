package main

import (
    "fmt"
    "os"
    "path/filepath"
    "strings"
    "sync"

    "github.com/aws/aws-sdk-go/aws"
    "github.com/aws/aws-sdk-go/aws/credentials"
    "github.com/aws/aws-sdk-go/aws/session"
    "github.com/aws/aws-sdk-go/service/s3/s3manager"
)

func main() {
    var localDirectory string
    if len(os.Args) > 1 {
        localDirectory = "/root/aparser/results/images/tiktok4/" + os.Args[1]
    } else {
        localDirectory = "/root/aparser/results/images/tiktok4"
    }

    bucketName := "ti4"
    s3Path := os.Args[1]

    sess := session.Must(session.NewSession(&aws.Config{
        Region:      aws.String("eu-west-1"),
        Credentials: credentials.NewStaticCredentials("KRGQHHNYT767Q1KB15BK", "FDpEGAAKcuYiOB2AYBsusljIi9AlHmvC1VaCVSps", ""),
        Endpoint:    aws.String("https://s3.nl.geostorage.net"),
    }))

    uploader := s3manager.NewUploader(sess)

    maxConcurrency := 30 // Максимальное количество одновременных горутин
    sem := make(chan bool, maxConcurrency)

    var wg sync.WaitGroup
    filepath.Walk(localDirectory, func(path string, info os.FileInfo, err error) error {
        if err != nil {
            return err
        }
        if !info.IsDir() {
            wg.Add(1)
            sem <- true // Попытка отправить в канал, блокируется, если канал полон
            go func(path string) {
                defer wg.Done()
                defer func() { <-sem }() // Освобождение места в канале после завершения горутины
                uploadToS3(uploader, bucketName, path, strings.Replace(path, localDirectory, s3Path, -1))
            }(path)
        }
        return nil
    })

    wg.Wait()
    fmt.Println("Завершено.")
}

func uploadToS3(uploader *s3manager.Uploader, bucketName, localPath, s3Path string) {
    file, err := os.Open(localPath)
    if err != nil {
        fmt.Printf("Не удалось открыть файл %v: %v\n", localPath, err)
        return
    }
    defer file.Close()

    _, err = uploader.Upload(&s3manager.UploadInput{
        Bucket: aws.String(bucketName),
        Key:    aws.String(s3Path),
        Body:   file,
    })
    if err != nil {
        fmt.Printf("Не удалось загрузить файл %v в S3: %v\n", localPath, err)
        return
    }

    fmt.Printf("Файл %v успешно загружен в S3 %v\n", localPath, s3Path)
    os.Remove(localPath)
}
