<?php

declare(strict_types=1);

use ZdenekGebauer\FtpClient\FtpException;
use ZdenekGebauer\FtpClient\FtpOptions;

class FtpClientConnectTest extends \Codeception\Test\Unit
{
    protected UnitTester $tester;

    public function testConnect(): void
    {
        $testParams = $this->tester->getCustomParams();

        $ftp = new ZdenekGebauer\FtpClient\FtpClient();
        $ftpOptions = new FtpOptions();
        $ftpOptions->host = $testParams['FTP_HOST'] ;
        $ftpOptions->username = $testParams['FTP_LOGIN'] ;
        $ftpOptions->password = $testParams['FTP_PASSWORD'] ;
        $ftpOptions->port = $testParams['FTP_PORT'];

        $ftp->connect($ftpOptions);
        $ftp->pasv($testParams['FTP_PASV']);
        $this->tester->assertEquals('/', $ftp->pwd());
        $ftp->close();
    }

    public function testSecureConnect(): void
    {
        $testParams = $this->tester->getCustomParams();
        if (!$testParams['FTP_TEST_SSL']) {
            return;
        }

        $ftp = new ZdenekGebauer\FtpClient\FtpClient();
        $ftpOptions = new FtpOptions();
        $ftpOptions->host = $testParams['FTP_HOST'] ;
        $ftpOptions->username = $testParams['FTP_LOGIN'] ;
        $ftpOptions->password = $testParams['FTP_PASSWORD'] ;
        $ftpOptions->port = $testParams['FTP_PORT'];
        $ftpOptions->ssl = true;

        $ftp->connect($ftpOptions);

        $ftp->pasv($testParams['FTP_PASV']);
        $this->tester->assertEquals('/', $ftp->pwd());
        $ftp->close();
    }

    public function testDestruct(): void
    {
        $testParams = $this->tester->getCustomParams();

        $ftp = new ZdenekGebauer\FtpClient\FtpClient();
        $ftpOptions = new FtpOptions();
        $ftpOptions->host = $testParams['FTP_HOST'] ;
        $ftpOptions->username = $testParams['FTP_LOGIN'] ;
        $ftpOptions->password = $testParams['FTP_PASSWORD'] ;
        $ftpOptions->port = $testParams['FTP_PORT'];

        $ftp->connect($ftpOptions);
        unset($ftp);
    }

    public function testConnectFail(): void
    {
        $ftpOptions = new FtpOptions();
        $ftpOptions->host = 'invalid';

        $ftp = new ZdenekGebauer\FtpClient\FtpClient();
        $this->tester->expectThrowable(
            FtpException::class,
            static function () use ($ftp, $ftpOptions) {
                $ftp->connect($ftpOptions);
            }
        );

        $testParams = $this->tester->getCustomParams();
        $ftpOptions->host = $testParams['FTP_HOST'] ;
        $ftpOptions->username = 'invalid';

        $this->tester->expectThrowable(
            FtpException::class,
            static function () use ($ftp, $ftpOptions) {
                $ftp->connect($ftpOptions);
            }
        );
    }

    public function testNotConnectionFail(): void
    {
        $ftp = new ZdenekGebauer\FtpClient\FtpClient();
        $this->tester->expectThrowable(
            new FtpException('connection is not opened'),
            static function () use ($ftp) {
                $ftp->chdir('/');
            }
        );
    }
}
