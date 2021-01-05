<?php
/** @noinspection PhpUsageOfSilenceOperatorInspection */

declare(strict_types=1);

namespace ZdenekGebauer\FtpClient;

use UnitTester;

class BaseTest extends \Codeception\Test\Unit
{
    protected UnitTester $tester;

    protected FtpClient $ftpClient;

    protected function _before()
    {
        $this->emptyTestFtpDirectory();

        $testParams = $this->tester->getCustomParams();
        $this->ftpClient = new FtpClient();
        $ftpOptions = new FtpOptions();
        $ftpOptions->host = $testParams['FTP_HOST'] ;
        $ftpOptions->username = $testParams['FTP_LOGIN'] ;
        $ftpOptions->password = $testParams['FTP_PASSWORD'] ;
        $ftpOptions->port = $testParams['FTP_PORT'];
        
        $this->ftpClient->connect($ftpOptions);
        $this->ftpClient->pasv($testParams['FTP_PASV']);

    }

    protected function _after()
    {
        $this->ftpClient->close();
        $this->emptyTestFtpDirectory();
    }

    protected function emptyTestFtpDirectory(): void
    {
        $testParams = $this->tester->getCustomParams();
        $dir = '/';

        $connection = ftp_connect($testParams['FTP_HOST']);
        ftp_login($connection, $testParams['FTP_LOGIN'], $testParams['FTP_PASSWORD']);
        $this->ftpRmdirRecursive($connection, $dir);
        ftp_close($connection);
    }

    protected function ftpRmdirRecursive($connection, string $path, bool $deleteSelf = false): void
    {
        if (@ftp_delete ($connection, $path) === false) {
            if (($children = @ftp_nlist ($connection, $path)) !== false) {
                foreach ($children as $child) {
                    $this->ftpRmdirRecursive($connection, $child, true);
                }
            }
            if ($deleteSelf) {
                @ftp_rmdir($connection, $path);
            }
        }
    }

    protected function emptyTestLocalDirectory(): void
    {
        $directory = codecept_data_dir('download');
        $this->rmdirRecursive($directory);
    }

    protected function rmdirRecursive(string $src, bool $deleteSelf = false): void
    {
        $dir = opendir($src);
        while (($file = readdir($dir)) !== false) {
            if (($file !== '.') && ($file !== '..')) {
                $full = $src . '/' . $file;
                if (is_dir($full)) {
                    $this->rmdirRecursive($full, true);
                } else {
                    unlink($full);
                }
            }
        }
        closedir($dir);
        if ($deleteSelf) {
            rmdir($src);
        }
    }

    protected function listTestFtpDirectory(string $path)
    {
        $path = '/' . ltrim($path, '/');
        $testParams = $this->tester->getCustomParams();

        $connection = ftp_connect($testParams['FTP_HOST']);
        ftp_login($connection, $testParams['FTP_LOGIN'], $testParams['FTP_PASSWORD']);
        $items = ftp_nlist($connection, $path);
        ftp_close($connection);
        return $items;
    }

}
