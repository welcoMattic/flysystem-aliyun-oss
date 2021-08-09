<?php

namespace WelcoMattic\Flysystem\AliyunOss;

use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCheckFileExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\MimeTypeDetection\MimeTypeDetector;
use OSS\Core\OssException;
use OSS\OssClient;

class AliyunOssAdapter implements FilesystemAdapter
{
    /** @var array<string, string> */
    public static array $mappingOptions = [
        'mimetype' => OssClient::OSS_CONTENT_TYPE,
        'size' => OssClient::OSS_LENGTH,
        'filename' => OssClient::OSS_CONTENT_DISPOSTION,
    ];
    private PathPrefixer $prefixer;

    /** @var array<string, string> */
    private array $options = [];

    /**
     * @param array<string, string> $options
     */
    public function __construct(
        private OssClient $client,
        private string $bucket,
        string $prefix = '',
        private ?MimeTypeDetector $mimeTypeDetector = null,
        array $options = []
    ) {
        $this->prefixer = new PathPrefixer($prefix);
        $this->options = array_merge($this->options, $options);
    }

    public function fileExists(string $path): bool
    {
        try {
            return $this->client->doesObjectExist($this->bucket, $this->prefixer->prefixPath($path));
        } catch (OssException $e) {
            throw UnableToCheckFileExistence::forLocation($path, $e);
        }
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $key = $this->prefixer->prefixPath($path);
        $options = $this->getOptionsFromConfig($config);

        if (!\array_key_exists(OssClient::OSS_CONTENT_TYPE, $options)) {
            $options[OssClient::OSS_CONTENT_TYPE] = $this->mimeTypeDetector->detectMimeType($path, $contents);
        }

        try {
            $this->client->putObject($this->bucket, $key, $contents, $options);
        } catch (OssException $e) {
            throw UnableToWriteFile::atLocation($path, $e);
        }
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $key = $this->prefixer->prefixPath($path);
        $options = $this->getOptionsFromConfig($config);

        if (!\array_key_exists(OssClient::OSS_CONTENT_TYPE, $options)) {
            $options[OssClient::OSS_CONTENT_TYPE] = $this->mimeTypeDetector->detectMimeType($path, $contents);
        }

        try {
            $this->client->uploadStream($this->bucket, $key, $contents, $options);
        } catch (OssException $e) {
            throw UnableToWriteFile::atLocation($path, $e);
        }
    }

    public function read(string $path): string
    {
        $key = $this->prefixer->prefixPath($path);

        try {
            $result = $this->client->getObject($this->bucket, $key);
        } catch (OssException $e) {
            throw UnableToReadFile::fromLocation($path, $e);
        }

        return $result;
    }

    public function readStream(string $path)
    {
        throw UnableToReadFile::fromLocation($path);
    }

    public function delete(string $path): void
    {
        $key = $this->prefixer->prefixPath($path);

        try {
            $this->client->deleteObject($this->bucket, $key);
        } catch (OssException $e) {
            throw UnableToDeleteFile::atLocation($path, $e);
        }
    }

    public function deleteDirectory(string $path): void
    {
        $objects = [];
        /** @var DirectoryAttributes|FileAttributes $val */
        foreach ($this->listContents($path, true) as $val) {
            if ($val->type() === StorageAttributes::TYPE_FILE) {
                $objects[] = $this->prefixer->prefixPath($val->path());
            } else {
                $objects[] = $this->prefixer->prefixPath($val->path()) . '/';
            }
        }

        $this->client->deleteObjects($this->bucket, $objects);
    }

    public function createDirectory(string $path, Config $config): void
    {
        $key = $this->prefixer->prefixPath($path);
        $options = $this->getOptionsFromConfig($config);

        try {
            $this->client->createObjectDir($this->bucket, $key, $options);
        } catch (OssException $e) {
            throw UnableToCreateDirectory::atLocation($path, $e);
        }
    }

    public function setVisibility(string $path, string $visibility): void
    {
        throw UnableToSetVisibility::atLocation($path);
    }

    public function visibility(string $path): FileAttributes
    {
        throw UnableToRetrieveMetadata::visibility($path);
    }

    public function mimeType(string $path): FileAttributes
    {
        $attributes = $this->fetchFileMetadata($path, StorageAttributes::ATTRIBUTE_MIME_TYPE);

        if (null === $attributes->mimeType()) {
            throw UnableToRetrieveMetadata::mimeType($path);
        }

        return $attributes;
    }

    public function lastModified(string $path): FileAttributes
    {
        $attributes = $this->fetchFileMetadata($path, StorageAttributes::ATTRIBUTE_LAST_MODIFIED);

        if (null === $attributes->lastModified()) {
            throw UnableToRetrieveMetadata::lastModified($path);
        }

        return $attributes;
    }

    public function fileSize(string $path): FileAttributes
    {
        $attributes = $this->fetchFileMetadata($path, StorageAttributes::ATTRIBUTE_FILE_SIZE);

        if (!$attributes->fileSize()) {
            throw UnableToRetrieveMetadata::fileSize($path);
        }

        return $attributes;
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $directory = rtrim($this->prefixer->prefixPath($path), '\\/');

        if ($directory) {
            $directory .= '/';
        }

        $bucket = $this->bucket;
        $options = [
            'delimiter' => !$deep ? '/' : '',
            'prefix' => $directory,
            'max-keys' => 1000,
            'marker' => '',
        ];

        try {
            $listObjectInfo = $this->client->listObjects($bucket, $options);
        } catch (OssException) {
            throw UnableToReadFile::fromLocation($bucket);
        }

        $objectList = $listObjectInfo->getObjectList();
        $prefixList = $listObjectInfo->getPrefixList();

        foreach ($objectList as $objectInfo) {
            $lastModified = strtotime($objectInfo->getLastModified());

            if ($objectInfo->getSize() === 0 && $directory === $objectInfo->getKey()) {
                yield new DirectoryAttributes(
                    $this->prefixer->stripPrefix(rtrim($objectInfo->getKey(), '/')),
                    null,
                    !$lastModified ? null : $lastModified,
                );

                continue;
            }

            yield new FileAttributes(
                $this->prefixer->stripPrefix($objectInfo->getKey()),
                $objectInfo->getSize(),
                null,
                !$lastModified ? null : $lastModified,
                $this->mimeTypeDetector->detectMimeTypeFromPath($this->prefixer->stripPrefix($objectInfo->getKey())),
            );
        }

        foreach ($prefixList as $prefixInfo) {
            yield new DirectoryAttributes($this->prefixer->stripPrefix(rtrim($prefixInfo->getPrefix(), '/')), null, null);
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $this->copy($source, $destination, $config);
            $this->delete($source);
        } catch (UnableToCopyFile|UnableToDeleteFile|OssException) {
            throw UnableToMoveFile::fromLocationTo($source, $destination);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $sourceKey = $this->prefixer->prefixPath($source);
        $destinationKey = $this->prefixer->prefixPath($destination);

        try {
            $this->client->copyObject($this->bucket, $sourceKey, $this->bucket, $destinationKey);
        } catch (OssException $e) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $e);
        }
    }

    public function getUrl(string $path): string
    {
        $key = $this->prefixer->prefixPath($path);

        try {
            return $this->client->generatePresignedUrl($this->bucket, $key, (new \DateTime('+1hour'))->getTimestamp());
        } catch (OssException $e) {
            throw UnableToRetrieveMetadata::create($key, 'URL', $e->getMessage(), $e);
        }
    }

    private function fetchFileMetadata(string $path, string $type): FileAttributes
    {
        $key = $this->prefixer->prefixPath($path);

        try {
            $result = $this->client->getObjectMeta($this->bucket, $key);
        } catch (OssException $e) {
            throw UnableToRetrieveMetadata::create($path, $type, $e->getMessage(), $e);
        }

        return new FileAttributes(
            $path,
            $result['content-length'] !== null ? (int) $result['content-length'] : null,
            null,
            strtotime($result['last-modified']),
            $result['content-type']
        );
    }

    /**
     * @return array<string, string>
     */
    private function getOptionsFromConfig(Config $config): array
    {
        $options = $this->options;

        foreach (static::$mappingOptions as $option => $ossOption) {
            if (!$config->get($option)) {
                continue;
            }
            $options[$ossOption] = $config->get($option);
        }

        return $options;
    }
}
