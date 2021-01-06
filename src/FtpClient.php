<?php

/** @noinspection PhpUsageOfSilenceOperatorInspection */

declare(strict_types=1);

namespace ZdenekGebauer\FtpClient;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Directory;
use RuntimeException;

use function is_array;

class FtpClient
{

    /**
     * @var resource|false
     */
    protected $connection;

    public const SYSTEM_TYPE_WINDOWS = 'Windows_NT';

    public function connect(FtpOptions $options): void
    {
        if ($options->ssl) {
            $this->connection = @ftp_ssl_connect($options->host, $options->port, $options->timeout);
        } else {
            $this->connection = @ftp_connect($options->host, $options->port, $options->timeout);
        }

        if (!$this->connection) {
            $lastError = error_get_last();
            throw new FtpException($lastError === null ? 'connection error' : $lastError['message']);
        }

        if (!@ftp_login($this->connection, $options->username, $options->password)) {
            $lastError = error_get_last();
            throw new FtpException($lastError === null ? 'login error' : $lastError['message']);
        }
    }

    public function __destruct()
    {
        if ($this->connection) {
            @ftp_close($this->connection);
        }
    }

    private function assertConnection(): void
    {
        if (!$this->connection) {
            throw new FtpException('connection is not opened');
        }
    }

    public function pasv(bool $pasv): void
    {
        $this->assertConnection();
        ftp_pasv($this->connection, $pasv);
    }

    public function put(
        string $remoteFile,
        string $localFile,
        int $mode = FTP_BINARY,
        int $startPos = 0,
        int $permissions = 0644
    ): void {
        $this->assertConnection();

        if (!@ftp_put($this->connection, $remoteFile, $localFile, $mode, $startPos)) {
            throw new FtpException('ftp_put "' . $localFile . '" to "' . $remoteFile . '" failed');
        }
        $this->chmod($remoteFile, $permissions);
    }

    public function get(string $localFile, string $remoteFile, int $mode = FTP_BINARY, int $startPos = 0): void
    {
        $this->assertConnection();

        if (!@ftp_get($this->connection, $localFile, $remoteFile, $mode, $startPos)) {
            throw new FtpException('ftp_get "' . $remoteFile . '" to "' . $localFile . '" failed');
        }
    }

    public function chdir(string $directory): void
    {
        $this->assertConnection();

        if (!@ftp_chdir($this->connection, $directory)) {
            throw new FtpException('ftp_chdir "' . $directory . '" failed');
        }
    }

    /**
     * rename/move file or directory
     *
     * @param string $oldName
     * @param string $newName
     * @throws FtpException
     */
    public function rename(string $oldName, string $newName): void
    {
        $this->assertConnection();

        if (!@ftp_rename($this->connection, $oldName, $newName)) {
            throw new FtpException('ftp_rename "' . $oldName . '" to "' . $newName . '" failed');
        }
    }

    /**
     * @param string $baseDirectory
     * @param string $directory
     * @param int $mode
     * @throws FtpException
     */
    public function mkdir(string $baseDirectory, string $directory, int $mode): void
    {
        $this->assertConnection();

        $parts = explode('/', $directory);
        $parts = array_filter(array_map('trim', $parts));

        $this->chdir($baseDirectory);

        foreach ($parts as $part) {
            if (!@ftp_chdir($this->connection, $part)) {
                if (!@ftp_mkdir($this->connection, $part)) {
                    $this->chdir($baseDirectory);
                    throw new FtpException('ftp_mkdir "' . $part . '" failed');
                }
                $this->chmod($part, $mode);
                ftp_chdir($this->connection, $part);
            }
        }
        $this->chdir($baseDirectory);
    }

    public function rmdir(string $directory): void
    {
        $this->assertConnection();

        try {
            $children = $this->list($directory);
        } catch (FtpException $exception) {
            throw new FtpException('delete "' . $directory . '" failed', 0, $exception);
        }

        foreach ($children as $child) {
            if ($child->isDir()) {
                $this->rmdir($directory . '/' . $child->name);
            } else {
                $this->delete($directory . '/' . $child->name);
            }
        }

        if (!@ftp_rmdir($this->connection, $directory)) {
            throw new FtpException('delete "' . $directory . '" failed');
        }
    }

    public function delete(string $path): void
    {
        $this->assertConnection();

        if (!@ftp_delete($this->connection, $path)) {
            throw new FtpException('ftp_delete "' . $path . '" failed');
        }
    }

    public function close(): void
    {
        if ($this->connection) {
            @ftp_close($this->connection);
        }
        @$this->connection = false; // suppress notice about SSL shutdown
    }

    /**
     * @param string $directory
     * @return array<FtpFileInfo>
     * @throws FtpException
     */
    public function list(string $directory): iterable
    {
        $this->assertConnection();

        $result = [];
        $systemType = (string)ftp_systype($this->connection);
        // ftp_mlsd() returns false without any error
        $items = ftp_rawlist($this->connection, $directory); // third parameter $recursive doesn't work
        if ($items === false) {
            throw new FtpException('ftp_rawlist "' . $directory . '" failed');
        }

        foreach ($items as $item) {
            $fileInfo = $this->parseRawListItem($item, $systemType);
            if ($fileInfo !== null) {
                $result[$fileInfo->name] = $fileInfo;
            }
        }
        return $result;
    }

    protected function parseRawListItem(string $item, string $systemType): ?FtpFileInfo
    {
        $result = null;
        if ($systemType === self::SYSTEM_TYPE_WINDOWS) {
            $regex = '/(\d{2})-(\d{2})-(\d{2}) +(\d{2}):(\d{2})(AM|PM) +(\d+|<DIR>) +(.+)/';
            if (preg_match($regex, $item, $matches) === 1) {
                $year = ($matches[3] < 70 ? '20' : 19) . $matches[3];
                $hour = ($matches[6] === 'PM' && (int)$matches[4] !== 12 ? $matches[4] + 12 : $matches[4]);
                $modified = new DateTime('now', new DateTimeZone('UTC'));
                $modified->setDate((int)$year, (int)$matches[1], (int)$matches[2]);
                $modified->setTime((int)$hour, (int)$matches[5]);
                $fileInfo = new FtpFileInfo();
                $fileInfo->name = $matches[8];
                $fileInfo->type = ($matches[7] === '<DIR>' ? FtpFileInfo::TYPE_DIR : FtpFileInfo::TYPE_FILE);
                $fileInfo->size = ($fileInfo->type === FtpFileInfo::TYPE_DIR ? 0 : (int)$matches[7]);

                $fileInfo->modified = DateTimeImmutable::createFromMutable($modified);
                $result = $fileInfo;
            }
        } else {
            // UNIX
            /** @var array<string> $parts */
            $parts = preg_split("/\s+/", $item, 9);
            if (isset($parts[8])) {
                $fileInfo = new FtpFileInfo();
                $fileInfo->name = $parts[8];

                switch ($parts[0][0]) {
                    case 'd':
                        $fileInfo->type = FtpFileInfo::TYPE_DIR;
                        break;
                    case 'l':
                        $fileInfo->type = FtpFileInfo::TYPE_LINK;
                        $fileInfo->size = (int)$parts[4];
                        break;
                    default:
                        $fileInfo->type = FtpFileInfo::TYPE_FILE;
                        $fileInfo->size = (int)$parts[4];
                }
                $modified = date_create_from_format('M d H:i', $parts[5] . ' ' . $parts[6] . ' ' . $parts[7]);
                if ($modified instanceof DateTime) {
                    $fileInfo->modified = DateTimeImmutable::createFromMutable($modified);
                }
                $result = $fileInfo;
            }
        }
        return $result;
    }

    /**
     * @param string $directory
     * @return iterable<FtpFileInfo>
     * @throws FtpException
     */
    public function tree(string $directory = '/'): iterable
    {
        $this->assertConnection();

        $systemType = (string)ftp_systype($this->connection);
        return $this->getSubFolders($directory, $systemType);
    }

    /**
     * @param string $directory
     * @param string $systemType
     * @return iterable<FtpFileInfo>
     * @throws FtpException
     */
    private function getSubFolders(string $directory, string $systemType): iterable
    {
        $this->assertConnection();

        $result = [];

        $items = ftp_rawlist($this->connection, $directory); // third parameter recursive doesn't work
        if (is_array($items)) {
            foreach ($items as $item) {
                $fileInfo = $this->parseRawListItem($item, $systemType);
                if ($fileInfo !== null) {
                    $path = ($directory . '/' . $fileInfo->name);
                    $path = ltrim($path, '/');

                    $fileInfo->path = $path;
                    if ($fileInfo->isDir()) {
                        $fileInfo->subDirectories
                            = $this->getSubFolders($directory . '/' . $fileInfo->name, $systemType);
                        $result[] = $fileInfo;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Downloads a file from the FTP server into a string
     *
     * @param string $remote_file
     * @param int $mode
     * @param int $offset
     * @return string|false
     * @throws FtpException
     */
    public function getFileContent(string $remote_file, int $mode = FTP_BINARY, int $offset = 0): string|false
    {
        $this->assertConnection();

        /** @var resource $handle */
        $handle = fopen('php://temp', 'rb+');

        if (@ftp_fget($this->connection, $handle, $remote_file, $mode, $offset)) {
            rewind($handle);
            return stream_get_contents($handle);
        }
        return false;
    }

    public function pwd(): string
    {
        $this->assertConnection();

        $result = @ftp_pwd($this->connection);
        if ($result === false) {
            throw new FtpException('Unable to resolve the current directory');
        }
        return $result;
    }

    public function putDirectory(
        string $baseDirectory,
        string $sourceDirectory,
        string $remoteDir,
        int $mode = FTP_BINARY,
        int $permissions = 0755
    ): void {
        $this->mkdir($baseDirectory, $remoteDir, $permissions);

        $dir = dir($sourceDirectory);
        if ($dir instanceof Directory) {
            while (($file = $dir->read()) !== false) {
                # To prevent an infinite loop
                if ($file === '.' || $file === '..') {
                    continue;
                }
                if (is_dir($sourceDirectory . '/' . $file)) {
                    $this->putDirectory(
                        $baseDirectory,
                        $sourceDirectory . '/' . $file,
                        $remoteDir . '/' . $file,
                        $mode,
                        $permissions
                    );
                    ftp_cdup($this->connection);
                } else {
                    $this->put($remoteDir . '/' . $file, $sourceDirectory . '/' . $file, $mode);
                }
            }
            $dir->close();
        }
    }

    /**
     * @param string $remoteDir '.' means current directory
     * @param string $localDir
     * @param int $mode transfer mode
     * @throws FtpException
     */
    public function getDirectory(string $remoteDir, string $localDir, int $mode = FTP_BINARY): void
    {
        $this->assertConnection();

        if ($remoteDir !== '.') {
            $this->chdir($remoteDir);
        }

        if (!is_dir($localDir) && !@mkdir($localDir) && !is_dir($localDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $localDir));
        }

        $files = ftp_nlist($this->connection, '.');
        if (is_array($files)) {
            foreach ($files as $file) {
                if (@ftp_chdir($this->connection, $file)) {
                    ftp_cdup($this->connection);
                    $this->getDirectory($file, $localDir . '/' . $file);
                } else {
                    $this->get($localDir . '/' . $file, $file, $mode);
                }
            }
        }

        ftp_cdup($this->connection);
    }

    public function isFile(string $remoteFile): bool
    {
        $this->assertConnection();

        return ftp_size($this->connection, $remoteFile) >= 0;
    }

    public function isDirectory(string $directory): bool
    {
        $this->assertConnection();

        $oldPwd = $this->pwd();

        if (@ftp_chdir($this->connection, $directory)) {
            $this->chdir($oldPwd);
            return true;
        }

        $this->chdir($oldPwd);
        return false;
    }

    /**
     * @param string $source
     * @param string $destination
     * @throws FtpException
     */
    public function copyFile(string $source, string $destination): void
    {
        $this->assertConnection();

        $handle = fopen('php://temp', 'rb+');
        if (!$handle) {
            throw new RuntimeException('cannot open temp');
        }

        if (@ftp_fget($this->connection, $handle, $source, FTP_BINARY)) {
            rewind($handle);
        } else {
            fclose($handle);
            throw new FtpException('cannot read file "' . $source . '"');
        }
        if (!@ftp_fput($this->connection, $destination, $handle, FTP_BINARY)) {
            fclose($handle);
            throw new FtpException('cannot write file "' . $destination . '"');
        }
        fclose($handle);
    }

    public function copyDirectory(string $baseDirectory, string $source, string $destination): void
    {
        $this->mkdir($baseDirectory, $destination, 0777);
        $children = $this->list($source);

        foreach ($children as $child) {
            if ($child->isDir()) {
                $this->copyDirectory($baseDirectory, $source . '/' . $child->name, $destination . '/' . $child->name);
            } else {
                $this->copyFile($source . '/' . $child->name, $destination . '/' . $child->name);
            }
        }
    }

    public function chmod(string $remoteFile, int $mode): void
    {
        $this->assertConnection();

        if (ftp_systype($this->connection) === self::SYSTEM_TYPE_WINDOWS) {
            return;
        }

        if (!@ftp_chmod($this->connection, $mode, $remoteFile)) {
            throw new FtpException('ftp_chmod "' . $remoteFile . '" mode "' . $mode . '" failed');
        }
    }
}
