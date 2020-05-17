<?php

namespace Huiwang\Flysystem\Oss\Plugins;

use League\Flysystem\Plugin\AbstractPlugin;

class TemporaryUrl extends AbstractPlugin
{
    public function getMethod()
    {
        return 'temporaryUrl';
    }

    public function handle($path, $expiration, array $options = [])
    {
        return $this->filesystem->getAdapter()->temporaryUrl($path, $expiration, $options);
    }
}
