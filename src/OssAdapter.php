<?php

namespace Huiwang\Flysystem\Oss;

use DateTimeInterface;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Adapter\Polyfill\StreamedTrait;
use League\Flysystem\Config;
use League\Flysystem\Util;
use OSS\OssClient;

class OssAdapter extends AbstractAdapter
{
    use StreamedTrait;

    /**
     * @var OssClient
     */
    private $client;

    /**
     * @var string
     */
    private $bucket;

    /**
     * @var array
     */
    private $options;

    public function __construct(OssClient $client, $bucket, $prefix = '', array $options = [])
    {
        $this->client = $client;
        $this->bucket = $bucket;
        $this->setPathPrefix($prefix);
        $this->options = $options;
    }

    /**
     * get the OssClient.
     *
     * @return OssClient
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * get the OssClient bucket.
     *
     * @return string
     */
    public function getBucket()
    {
        return $this->bucket;
    }

    /**
     * set the OssClient bucket.
     *
     * @param $bucket
     *
     * @return string
     */
    public function setBucket($bucket)
    {
        $this->bucket = $bucket;

        return $this;
    }

    protected function getOptionsFromConfig(Config $config)
    {
        $options = $this->options;

        if ($config->has('options')) {
            $options = array_merge($options, $config->get('options', []));
        }

        return $options;
    }

    public function write($path, $contents, Config $config)
    {
        $object = $this->applyPathPrefix($path);
        $options = $this->getOptionsFromConfig($config);

        return $this->getClient()->putObject($this->getBucket(), $object, $contents, $options);
    }

    public function update($path, $contents, Config $config)
    {
        return $this->write($path, $contents, $config);
    }

    public function has($path)
    {
        $object = $this->applyPathPrefix($path);

        return $this->getClient()->doesObjectExist($this->getBucket(), $object);
    }

    public function read($path)
    {
        $object = $this->applyPathPrefix($path);

        $contents = $this->getClient()->getObject($this->getBucket(), $object);

        return compact('contents');
    }

    public function copy($path, $newPath)
    {
        $object = $this->applyPathPrefix($path);
        $newObject = $this->applyPathPrefix($newPath);

        return $this->getClient()->copyObject($this->getBucket(), $object, $this->getBucket(), $newObject);
    }

    public function delete($path)
    {
        $object = $this->applyPathPrefix($path);

        return $this->getClient()->deleteObject($this->getBucket(), $object);
    }

    public function rename($path, $newPath)
    {
        if (!$this->copy($path, $newPath)) {
            return false;
        }

        return $this->delete($path);
    }

    public function createDir($dirname, Config $config)
    {
        $object = $this->applyPathPrefix($dirname);

        $options = $this->getOptionsFromConfig($config);

        return $this->getClient()->createObjectDir($this->getBucket(), $object, $options);
    }

    public function deleteDir($dirname)
    {
        $list = $this->listContents($dirname, true);

        $objects = [];
        foreach ($list as $object) {
            if ('file' === $object['type']) {
                $objects[] = $this->applyPathPrefix($object['path']);
            } else {
                $objects[] = $this->applyPathPrefix($object['path']).'/';
            }
        }

        return $this->getClient()->deleteObjects($this->getBucket(), $objects);
    }

    public function listContents($directory = '', $recursive = false)
    {
        $directory = rtrim($this->applyPathPrefix($directory), '/');
        if ($directory) {
            $directory .= '/';
        }

        $nextMarker = '';
        $result = [];

        do {
            $options = [
                'max-keys' => 1000,
                'prefix' => $directory,
                'delimiter' => '/',
                'marker' => $nextMarker,
            ];

            $listObjectInfo = $this->getClient()->listObjects($this->getBucket(), $options);

            $nextMarker = $listObjectInfo->getNextMarker();

            $prefixList = $listObjectInfo->getPrefixList();
            $objectList = $listObjectInfo->getObjectList();

            foreach ($objectList as $objectInfo) {
                $result[] = [
                    'type' => 'file',
                    'path' => $this->removePathPrefix($objectInfo->getKey()),
                    'timestamp' => strtotime($objectInfo->getLastModified()),
                    'size' => $objectInfo->getSize(),
                ];
            }

            foreach ($prefixList as $prefixInfo) {
                $result[] = [
                    'type' => 'dir',
                    'path' => $this->removePathPrefix(rtrim($prefixInfo->getPrefix(), '/')),
                    'timestamp' => 0,
                    'size' => 0,
                ];
                if ($recursive) {
                    $next = $this->listContents($this->removePathPrefix($prefixInfo->getPrefix()), $recursive);
                    $result = array_merge($result, $next);
                }
            }
        } while ($nextMarker);

        return $result;
    }

    public function getMetadata($path)
    {
        $object = $this->applyPathPrefix($path);

        $result = $this->getClient()->getObjectMeta($this->getBucket(), $object);

        return [
            'type' => 'file',
            'dirname' => Util::dirname($path),
            'path' => $path,
            'timestamp' => strtotime($result['last-modified']),
            'mimetype' => $result['content-type'],
            'size' => $result['content-length'],
        ];
    }

    public function getSize($path)
    {
        return $this->getMetadata($path);
    }

    public function getMimetype($path)
    {
        return $this->getMetadata($path);
    }

    public function getTimestamp($path)
    {
        return $this->getMetadata($path);
    }

    public function getVisibility($path)
    {
        $object = $this->applyPathPrefix($path);

        $visibility = $this->getClient()->getObjectAcl($this->getBucket(), $object);
        if ('default' === $visibility) {
            $visibility = $this->getClient()->getBucketAcl($this->getBucket());
        }

        return compact('visibility');
    }

    public function setVisibility($path, $visibility)
    {
        $object = $this->applyPathPrefix($path);

        return $this->getClient()->putObjectAcl($this->getBucket(), $object, $visibility);
    }

    /**
     * Get resource url.
     *
     * @param $path
     *
     * @return string
     */
    public function getUrl($path)
    {
        $object = $this->applyPathPrefix($path);

        $result = $this->getClient()->getBucketMeta($this->getBucket());

        return trim($result['oss-request-url'], '/').'/'.trim($object, '/');
    }

    /**
     * Get resource temporary url.
     *
     * @param       $path
     * @param       $expiration
     *
     * @param array $options
     *
     * @return \OSS\Http\ResponseCore|string
     *
     * @throws \OSS\Core\OssException
     */
    public function temporaryUrl($path, $expiration, array $options = [])
    {
        $object = $this->applyPathPrefix($path);

        $timeout = $expiration;
        if ($expiration instanceof DateTimeInterface) {
            $timeout = $expiration->getTimestamp() - time();
        }

        return $this->getClient()->signUrl($this->getBucket(), $object, $timeout, OssClient::OSS_HTTP_GET, $options);
    }
}
