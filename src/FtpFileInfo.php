<?php

declare(strict_types=1);

namespace ZdenekGebauer\FtpClient;

use DateTimeImmutable;

class FtpFileInfo
{

    public const TYPE_FILE = 'file';

    public const TYPE_DIR = 'dir';

    public const TYPE_LINK = 'link';

    public string $name = '';

    public string $type = '';

    public int $size = 0;

    /**
     * @var iterable<FtpFileInfo>
     */
    public iterable $subDirectories = [];

    public DateTimeImmutable $modified;

    public string $path = '';

    public function isDir(): bool
    {
        return $this->type === self::TYPE_DIR;
    }
}
