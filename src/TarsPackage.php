<?php

declare(strict_types=1);

namespace winwin\winner;

class TarsPackage
{
    public const TARS_FILE_PATH = 'tars';

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

    public function getFullNames(): array
    {
        return array_map(function (string $name) {
            return empty($this->pathPrefix) ? $name : $this->pathPrefix.'/'.$name;
        }, $this->files);
    }

    /**
     * 下载更新定义文件内容.
     *
     * @return string[]
     */
    public function update(JsonRpcGatewayClient $client): array
    {
        $pathPrefix = self::TARS_FILE_PATH.'/'.($this->getPathPrefix() ? $this->getPathPrefix().'/' : '');
        if (!is_dir($pathPrefix) && !mkdir($pathPrefix) && !is_dir($pathPrefix)) {
            throw new \RuntimeException("无法创建目录 $pathPrefix");
        }
        $updatedFiles = $this->getUpdatedFiles($client, $this->getRevision());
        foreach ($updatedFiles as $file) {
            $fileName = $pathPrefix.$file;
            $content = $client->call(
                [$client->getConfig()->getTarsFileRegistryServantName(), 'getContent'],
                [
                    'packageName' => $this->getName(),
                    'revision' => $this->getRevision(),
                    'fileName' => $file,
                ]
            );
            if (false === file_put_contents($fileName, $content)) {
                throw new \RuntimeException("文件 $fileName 写入失败");
            }
        }

        return $updatedFiles;
    }

    public function getServants(string $serverName): array
    {
        $pathPrefix = self::TARS_FILE_PATH.'/'.($this->getPathPrefix() ? $this->getPathPrefix().'/' : '');
        if (!is_dir($pathPrefix) && !mkdir($pathPrefix) && !is_dir($pathPrefix)) {
            throw new \RuntimeException("无法创建目录 $pathPrefix");
        }
        $servants = [];
        foreach ($this->getFiles() as $file) {
            $fileName = $pathPrefix.$file;
            $content = file_get_contents($fileName);
            if (preg_match('/module\s+(\w+)/ms', $content, $matches)) {
                $module = $matches[1];
                preg_match_all('/\binterface\s+(\w+)/ms', $content, $matches);
                foreach ($matches[1] as $name) {
                    $servants[$module.'.'.$name] = $serverName.'.'.$name.'Obj';
                }
            }
        }

        return $servants;
    }

    public function getUpdatedFiles(JsonRpcGatewayClient $client, string $revision): array
    {
        $files = $client->call(
            [$client->getConfig()->getTarsFileRegistryServantName(), 'listFiles'],
            $this->getName(), $revision
        );
        $pathPrefix = self::TARS_FILE_PATH.'/'.($this->getPathPrefix() ? $this->getPathPrefix().'/' : '');
        $updatedFiles = [];
        foreach ($files as $file) {
            if (!in_array($file['fileName'], $this->getFiles(), true)) {
                continue;
            }
            $fileName = $pathPrefix.$file['fileName'];
            if (!file_exists($fileName) || $file['md5'] !== md5_file($fileName)) {
                $updatedFiles[] = $file['fileName'];
            }
        }

        return $updatedFiles;
    }

    public function removeFiles(): void
    {
        $pathPrefix = self::TARS_FILE_PATH.'/'.($this->getPathPrefix() ? $this->getPathPrefix().'/' : '');
        foreach ($this->getFiles() as $file) {
            $fileName = $pathPrefix.$file;
            @unlink($fileName);
        }
    }
}
