# flysystem-oss

💾 Flysystem adapter for the aliyun oss storage

## 安装

```
composer require "huiwang/flysystem-oss" -vvv
```

## 使用

```
$accessKeyId = '';
$accessKeySecret = '';
$endpoint = '';
$bucket = '',

$client = new \OSS\OssClient($accessKeyId, $accessKeySecret, $endpoint);

$adapter = new \Huiwang\Flysystem\Oss\OssAdapter($client, $bucket);

$filesystem = new \League\Flysystem\Filesystem($adapter, ['disable_asserts' => true]);

$filesystem->addPlugin(new \Huiwang\Flysystem\Oss\Plugins\FileUrl());
$filesystem->addPlugin(new \Huiwang\Flysystem\Oss\Plugins\TemporaryUrl());

```

## API

[API - Flysystem](https://flysystem.thephpleague.com/v1/docs/usage/filesystem-api/)


