<?php

declare(strict_types=1);

namespace ZdenekGebauer\FtpClient;

use RuntimeException;

class FtpClientMethodsTest extends BaseTest
{

    public function testPutGet(): void
    {
        $this->emptyTestFtpDirectory();
        $this->emptyTestLocalDirectory();
        $this->ftpClient->put('remote.txt', codecept_data_dir() . '/test.txt');
        $this->ftpClient->get(codecept_data_dir() . '/download.txt', 'remote.txt');

        $this->tester->assertFileEquals(codecept_data_dir() . '/test.txt', codecept_data_dir() . '/download.txt');
        $this->emptyTestFtpDirectory();
        $this->emptyTestLocalDirectory();
    }

    public function testPutFail(): void
    {
        $localFile = codecept_data_dir() . 'not-exists.txt';
        $remoteFile = 'remote.txt';

        $ftpClient = $this->ftpClient;
        $this->tester->expectThrowable(
            new FtpException('ftp_put "' . $localFile . '" to "' . $remoteFile . '" failed'),
            static function () use ($ftpClient, $remoteFile, $localFile) {
                $ftpClient->put($remoteFile, $localFile);
            }
        );
    }

    public function testGetFail(): void
    {
        $localFile = codecept_data_dir() . 'download.txt';
        $remoteFile = 'not-exists.txt';

        $ftpClient = $this->ftpClient;
        $this->tester->expectThrowable(
            new FtpException('ftp_get "' . $remoteFile . '" to "' . $localFile . '" failed'),
            static function () use ($ftpClient, $remoteFile, $localFile) {
                $ftpClient->get($localFile, $remoteFile);
            }
        );
    }

    /**
     * @env linux
     */
    public function testMkdirFail(): void
    {
        $directory = 'sub';

        $ftpClient = $this->ftpClient;
        $ftpClient->mkdir('/', 'test', 0555);
        $this->tester->expectThrowable(
            new FtpException('ftp_mkdir "' . $directory . '" failed'),
            static function () use ($ftpClient, $directory) {
                $ftpClient->mkdir('/test', $directory, 0777);
            }
        );
        $this->emptyTestFtpDirectory();
    }

    public function testPutGetDirectory(): void
    {
        $this->emptyTestFtpDirectory();
        $this->emptyTestLocalDirectory();

        $localDir = codecept_data_dir('upload');
        $this->ftpClient->putDirectory('/', $localDir, 'upload2');

        $uploadedItems = $this->listTestFtpDirectory('');
        $this->tester->assertContains('/upload2', $uploadedItems);

        $uploadedItems = $this->listTestFtpDirectory('/');
        $this->tester->assertContains('/upload2', $uploadedItems);

        $uploadedItems = $this->listTestFtpDirectory('/upload2');
        $this->tester->assertContains('/upload2/sub1', $uploadedItems);
        $this->tester->assertContains('/upload2/file.txt', $uploadedItems);
        $this->tester->assertContains('/upload2/sub_empty', $uploadedItems);

        $uploadedItems = $this->listTestFtpDirectory('/upload2/sub1');
        $this->tester->assertContains('/upload2/sub1/file1.txt', $uploadedItems);
        $this->tester->assertContains('/upload2/sub1/file2.txt', $uploadedItems);

        $localDirDownload = codecept_data_dir('download');
        $this->ftpClient->getDirectory('/upload2', $localDirDownload);
        $this->tester->assertFileExists($localDirDownload . '/file.txt');
        $this->tester->assertFileExists($localDirDownload . '/sub1/file1.txt');
        $this->tester->assertFileExists($localDirDownload . '/sub1/file2.txt');
        $this->tester->assertDirectoryExists($localDirDownload . '/sub_empty');

        $this->emptyTestFtpDirectory();
        $this->emptyTestLocalDirectory();
    }

    public function testGetDirectoryFail(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $ftpClient = $this->ftpClient;
            $this->tester->expectThrowable(
                new RuntimeException('Directory ":invalid" was not created'),
                static function () use ($ftpClient) {
                    $ftpClient->getDirectory('/', ':invalid');
                }
            );
        }
    }

    public function testTree(): void
    {
        $this->emptyTestFtpDirectory();

        $localDir = codecept_data_dir('upload');
        $this->ftpClient->putDirectory('/', $localDir, 'tree');

        /** @var array<FtpFileInfo> $tree */
        $tree = $this->ftpClient->tree('tree');

        $this->tester->assertCount(2, $tree);
        $this->tester->assertInstanceOf(FtpFileInfo::class, $tree[0]);
        $this->tester->assertEquals('sub1', $tree[0]->name);
        $this->tester->assertTrue($tree[0]->isDir());
        $this->tester->assertEquals('sub_empty', $tree[1]->name);

        $this->emptyTestFtpDirectory();
    }

    public function testRmDir(): void
    {
        $this->emptyTestFtpDirectory();

        $localDir = codecept_data_dir('upload');
        $this->ftpClient->putDirectory('/', $localDir, 'folder');

        $items = $this->ftpClient->list('/folder');
        $this->tester->assertCount(3, $items);

        $this->ftpClient->rmdir('/folder/sub1');
        $items = $this->ftpClient->list('/folder');
        $this->tester->assertCount(2, $items);

        $this->emptyTestFtpDirectory();
    }

    public function testRmdirFail(): void
    {
        $ftpClient = $this->ftpClient;
        $this->tester->expectThrowable(
            new FtpException('delete "not-exists" failed'),
            static function () use ($ftpClient) {
                $ftpClient->rmdir('not-exists');
            }
        );
    }

    public function testChdirFail(): void
    {
        $ftpClient = $this->ftpClient;
        $this->tester->expectThrowable(
            new FtpException('ftp_chdir "not-exists" failed'),
            static function () use ($ftpClient) {
                $ftpClient->chdir('not-exists');
            }
        );
    }

    public function testDeleteFail(): void
    {
        $ftpClient = $this->ftpClient;
        $this->tester->expectThrowable(
            new FtpException('ftp_delete "not-exists" failed'),
            static function () use ($ftpClient) {
                $ftpClient->delete('not-exists');
            }
        );
    }

    public function testRename(): void
    {
        $this->emptyTestFtpDirectory();

        $localDir = codecept_data_dir('upload');
        $this->ftpClient->putDirectory('/', $localDir, '/');

        $this->ftpClient->rename('file.txt', 'new.txt');
        $this->ftpClient->rename('sub1', 'sub-new');

        $items = $this->listTestFtpDirectory('');
        $this->tester->assertCount(3, $items);

        $this->tester->assertContains('/sub-new', $items);
        $this->tester->assertContains('/new.txt', $items);

        $this->emptyTestFtpDirectory();
    }

    public function testRenameFail(): void
    {
        $ftpClient = $this->ftpClient;
        $this->tester->expectThrowable(
            new FtpException('ftp_rename "not-exists" to "new-name" failed'),
            static function () use ($ftpClient) {
                $ftpClient->rename('not-exists', 'new-name');
            }
        );
    }

    public function testGetFileContent(): void
    {
        $this->emptyTestFtpDirectory();

        $localFile = codecept_data_dir() . '/test.txt';
        $remoteFile = '/new.txt';
        $this->ftpClient->put($remoteFile, $localFile);

        $this->tester->assertStringEqualsFile($localFile, $this->ftpClient->getFileContent($remoteFile));
        $this->tester->assertFalse($this->ftpClient->getFileContent('not-exists'));

        $this->emptyTestFtpDirectory();
    }

    public function testIsFileIsDirectory(): void
    {
        $this->emptyTestFtpDirectory();
        $this->ftpClient->put('file.txt', codecept_data_dir() . '/test.txt');
        $this->ftpClient->mkdir('/', 'new-folder/sub-folder', 0777);

        $this->tester->assertTrue($this->ftpClient->isDirectory('/'));
        $this->tester->assertTrue($this->ftpClient->isDirectory('/new-folder'));

        $this->tester->assertFalse($this->ftpClient->isDirectory('/file.txt'));
        $this->tester->assertFalse($this->ftpClient->isDirectory('/not-exists'));

        $this->tester->assertTrue($this->ftpClient->isFile('file.txt'));
        $this->tester->assertFalse($this->ftpClient->isFile('new-folder'));
        $this->tester->assertFalse($this->ftpClient->isFile('not-exists.txt'));

        $this->emptyTestFtpDirectory();
    }

    public function testCopyFile(): void
    {
        $this->emptyTestFtpDirectory();

        $ftpClient = $this->ftpClient;

        $this->ftpClient->put('file.txt', codecept_data_dir() . '/test.txt');

        $this->ftpClient->copyFile('file.txt', 'copy.txt');

        $items = $this->listTestFtpDirectory('');
        $this->tester->assertCount(2, $items);

        $this->tester->assertContains('/file.txt', $items);
        $this->tester->assertContains('/copy.txt', $items);

        $this->tester->expectThrowable(
            new FtpException('cannot read file "not-exists.txt"'),
            static function () use ($ftpClient) {
                $ftpClient->copyFile('not-exists.txt', 'copy.txt');
            }
        );

        $this->tester->expectThrowable(
            new FtpException('cannot write file "not-exists/copy.txt"'),
            static function () use ($ftpClient) {
                $ftpClient->copyFile('copy.txt', 'not-exists/copy.txt');
            }
        );

        $this->emptyTestFtpDirectory();
    }

    public function testCopyDirectory(): void
    {
        $this->emptyTestFtpDirectory();

        $ftpClient = $this->ftpClient;

        $ftpClient->mkdir('/', 'folder1', 0755);
        $ftpClient->mkdir('/', 'folder1/sub-folder1', 0755);
        $this->ftpClient->put('folder1/file1.txt', codecept_data_dir() . '/test.txt');
        $this->ftpClient->put('folder1/sub-folder1/file2.txt', codecept_data_dir() . '/test.txt');

        $this->ftpClient->copyDirectory('/', '/folder1', '/folder2');

        $items = $this->listTestFtpDirectory('/folder1');
        $this->tester->assertCount(2, $items);
        $this->tester->assertContains('/folder1/file1.txt', $items);
        $this->tester->assertContains('/folder1/sub-folder1', $items);

        $items = $this->listTestFtpDirectory('/folder2');
        $this->tester->assertCount(2, $items);
        $this->tester->assertContains('/folder2/file1.txt', $items);
        $this->tester->assertContains('/folder2/sub-folder1', $items);

        $this->emptyTestFtpDirectory();
    }

    /**
     * @env linux
     */
    public function testChmodFail(): void
    {
        $ftpClient = $this->ftpClient;
        $this->tester->expectThrowable(
            new FtpException('ftp_chmod "not-exists" mode "511" failed'),
            static function () use ($ftpClient) {
                $ftpClient->chmod('not-exists', 0777);
            }
        );
    }

    public function testParseRawListItem(): void
    {
        $ftpClient = new class() extends FtpClient {

            public function parseRawListItem(string $item, string $systemType): ?FtpFileInfo
            {
                return parent::parseRawListItem($item, $systemType);
            }
        };

        $this->tester->assertNull($ftpClient->parseRawListItem('', FtpClient::SYSTEM_TYPE_WINDOWS));
        $this->tester->assertNull($ftpClient->parseRawListItem('', ''));

        // file
        $ftpFile = $ftpClient->parseRawListItem('-rw-rw-rw- 1 user group 160 Feb 16 13:54 file.txt', '');
        $this->tester->assertEquals(FtpFileInfo::TYPE_FILE, $ftpFile->type);
        $this->tester->assertEquals(160, $ftpFile->size);
        $this->tester->assertEquals(date('Y') . '-02-16 13:54', $ftpFile->modified->format('Y-m-d H:i'));

        // link
        /** @noinspection SpellCheckingInspection */
        $ftpFile = $ftpClient->parseRawListItem(
            'lrwxrwxrwx   1 vincent  vincent        11 Jul 12 12:16 www -> public_html',
            ''
        );
        $this->tester->assertEquals(FtpFileInfo::TYPE_LINK, $ftpFile->type);
        $this->tester->assertEquals(11, $ftpFile->size);
    }
}
