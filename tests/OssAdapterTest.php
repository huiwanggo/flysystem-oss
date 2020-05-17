<?php

namespace Huiwang\Flysystem\Oss\Tests;

use Huiwang\Flysystem\Oss\OssAdapter;
use League\Flysystem\Config;
use Mockery;
use OSS\OssClient;
use PHPUnit\Framework\TestCase;

class OssAdapterTest extends TestCase
{
    protected $ossClient;

    protected function setUp()
    {
        $this->ossClient = Mockery::mock(OssClient::class, ['AccessKeyId', 'AccessKeySecret', 'endpoint']);
    }

    public function testBucket()
    {
        $adapter = new OssAdapter($this->ossClient, 'bucket', '', []);

        $this->assertEquals('bucket', $adapter->getBucket());

        $adapter->setBucket('newBucket');

        $this->assertEquals('newBucket', $adapter->getBucket());
    }

    public function testWrite()
    {
        $this->ossClient->shouldReceive('putObject')->andReturn(['etag' => 'etag']);

        $adapter = new OssAdapter($this->ossClient, 'bucket', '', []);

        $this->assertArrayHasKey('etag', $adapter->write('test.txt', 'content', new Config()));
    }

    public function testWriteStream()
    {
        $this->ossClient->shouldReceive('putObject')->andReturn(['etag' => 'etag']);

        $adapter = new OssAdapter($this->ossClient, 'bucket', '', []);

        $stream = tmpfile();
        fwrite($stream, 'content');
        rewind($stream);

        $this->assertArrayHasKey('etag', $adapter->writeStream('test.txt', $stream, new Config()));

        fclose($stream);
    }

    // TODO
}
