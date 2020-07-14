<?php

declare(strict_types=1);

namespace winwin\winner;

class TarsPackage
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $revision;

    /**
     * @var string
     */
    private $pathPrefix;

    /**
     * @var array
     */
    private $files;

    /**
     * TarsPackage constructor.
     */
    public function __construct(string $name, string $revision, array $files, string $pathPrefix = '')
    {
        $this->name = $name;
        $this->revision = $revision;
        $this->files = $files;
        $this->pathPrefix = $pathPrefix;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRevision(): string
    {
        return $this->revision;
    }

    public function setRevision(string $revision): void
    {
        $this->revision = $revision;
    }

    public function getFiles(): array
    {
        return $this->files;
    }

    public function setFiles(array $files): void
    {
        $this->files = $files;
    }

    public function getPathPrefix(): string
    {
        return $this->pathPrefix;
    }

    public function setPathPrefix(string $pathPrefix): void
    {
        $this->pathPrefix = $pathPrefix;
    }

    public function update(JsonRpcGatewayClient $client): array
    {
        $files = $client->call($client->getConfig()->getTarsFileRegistryServantName(), 'listFiles',
            $this->getName(), $this->getRevision());
        $pathPrefix = 'tars/'.($this->getPathPrefix() ? $this->getPathPrefix().'/' : '');
        $updatedFiles = [];
        foreach ($files as $file) {
            if (!in_array($file['fileName'], $this->getFiles(), true)) {
                continue;
            }
            $fileName = $pathPrefix.$file['fileName'];
            if (file_exists($fileName) && $file['md5'] === md5_file($fileName)) {
                continue;
            }
            $ret = file_put_contents($fileName, $client->call($client->getConfig()->getTarsFileRegistryServantName(), 'getContent', [
                'packageName' => $this->getName(),
                'revision' => $this->getRevision(),
                'fileName' => $file['fileName'],
            ]));
            $updatedFiles[] = $file['fileName'];
            if (false === $ret) {
                throw new \RuntimeException("文件 $fileName 写入失败");
            }
        }

        return $updatedFiles;
    }
}
