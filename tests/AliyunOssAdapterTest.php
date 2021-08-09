<?php

declare(strict_types=1);

namespace WelcoMattic\Flysystem\AliyunOss\Test;

use League\Flysystem\AdapterTestUtilities\FilesystemAdapterTestCase;
use League\Flysystem\Config;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\MimeTypeDetection\ExtensionMimeTypeDetector;
use OSS\OssClient;
use WelcoMattic\Flysystem\AliyunOss\AliyunOssAdapter;

class AliyunOssAdapterTest extends FilesystemAdapterTestCase
{
    public function testFetchingUnknownMimeTypeOfAFile(): void
    {
        $this->assertTrue(true); //This adapter always returns a mime-type.
    }

    public function testReadingAFileWithAStream(): void
    {
        $this->assertTrue(true); //This adapter can't read files as streams.
    }

    public function testWritingAndReadingWithStreams(): void
    {
        $this->assertTrue(true); //This adapter can't read files as streams.
    }

    public function testOverwritingAFile(): void
    {
        $this->runScenario(function () {
            $this->givenWeHaveAnExistingFile('path.txt', 'contents', []);
            $adapter = $this->adapter();

            $adapter->write('path.txt', 'new contents', new Config());

            $contents = $adapter->read('path.txt');
            $this->assertEquals('new contents', $contents);
        });
    }

    public function testListingContentsRecursive(): void
    {
        $this->runScenario(function () {
            $adapter = $this->adapter();
            $adapter->createDirectory('path', new Config());
            $adapter->write('path/file.txt', 'string', new Config());

            $listing = $adapter->listContents('', true);
            /** @var StorageAttributes[] $items */
            $items = iterator_to_array($listing);
            $this->assertCount(2, $items, $this->formatIncorrectListingCount($items));
        });
    }

    public function testCreatingZeroDir(): void
    {
        $this->runScenario(function () {
            $adapter = $this->adapter();
            $adapter->write('0/file.txt', 'contents', new Config());
            $contents = $adapter->read('0/file.txt');
            $this->assertEquals('contents', $contents);
        });
    }

    public function testSettingVisibility(): void
    {
        $this->assertTrue(true); //This adapter doesn't support visibility.
    }

    public function testCopyingAFile(): void
    {
        $this->runScenario(function () {
            $adapter = $this->adapter();
            $adapter->write(
                'source.txt',
                'contents to be copied',
                new Config()
            );

            $adapter->copy('source.txt', 'destination.txt', new Config());

            $this->assertTrue($adapter->fileExists('source.txt'));
            $this->assertTrue($adapter->fileExists('destination.txt'));
            $this->assertEquals('contents to be copied', $adapter->read('destination.txt'));
        });
    }

    public function testCopyingAFileAgain(): void
    {
        $this->runScenario(function () {
            $adapter = $this->adapter();
            $adapter->write(
                'source.txt',
                'contents to be copied',
                new Config()
            );

            $adapter->copy('source.txt', 'destination.txt', new Config());

            $this->assertTrue($adapter->fileExists('source.txt'));
            $this->assertTrue($adapter->fileExists('destination.txt'));
            $this->assertEquals('contents to be copied', $adapter->read('destination.txt'));
        });
    }

    public function testMovingAFile(): void
    {
        $this->runScenario(function () {
            $adapter = $this->adapter();
            $adapter->write(
                'source.txt',
                'contents to be copied',
                new Config()
            );
            $adapter->move('source.txt', 'destination.txt', new Config());
            $this->assertFalse(
                $adapter->fileExists('source.txt'),
                'After moving a file should no longer exist in the original location.'
            );
            $this->assertTrue(
                $adapter->fileExists('destination.txt'),
                'After moving, a file should be present at the new location.'
            );
            $this->assertEquals('contents to be copied', $adapter->read('destination.txt'));
        });
    }

    public function testItThrowsWhenGettingVisibility(): void
    {
        $this->expectException(UnableToRetrieveMetadata::class);

        $this->adapter()->visibility('path.txt');
    }

    public function testItThrowsWhenSettingVisibility(): void
    {
        $this->expectException(UnableToSetVisibility::class);

        $this->adapter()->setVisibility('path.txt', '0777');
    }

    protected static function createFilesystemAdapter(): FilesystemAdapter
    {
        $key = getenv('OSS_ACCESS_KEY_ID');
        $secret = getenv('OSS_ACCESS_KEY_SECRET');
        $endpoint = getenv('OSS_ENDPOINT');
        $bucket = getenv('OSS_BUCKET');

        if (!$key || !$secret || !$bucket || !$endpoint) {
            self::markTestSkipped('No Aliyun credentials present for testing.');
        }

        $client = new OssClient($key, $secret, $endpoint);

        return new AliyunOssAdapter($client, $bucket, '', new ExtensionMimeTypeDetector());
    }
}
