<?php

declare(strict_types=1);

namespace ZdenekGebauer\FtpClient;

class FtpOptions
{

    public string $host = 'localhost';

    public string $username = 'anonymous';

    public string $password = '';

    public bool $ssl = false;

    public int $port = 21;

    public int $timeout = 90;
}
