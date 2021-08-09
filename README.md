# Aliyun OSS Flysystem Adapter

## Installation

You can install the package via composer:

``` bash
composer require welcomattic/flysystem-aliyun-oss
```

## Usage

```php
use League\Flysystem\Filesystem;
use OSS\OssClient;
use welcoMattic\Flysystem\AliyunOss\AliyunOssAdapter;

$client = new OssClient($accessKeyId, $accessKeySecret, $endpoint);

// optional
$client->setUseSSL(true);

$adapter = new AliyunOssAdapter($client, $bucket);

$filesystem = new Filesystem($adapter);
```
