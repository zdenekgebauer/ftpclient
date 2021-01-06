# FTP Client

Object oriented FTP (SFTP) client for PHP

![](https://github.com/zdenekgebauer/ftpclient/workflows/build/badge.svg)

## Installation

`composer require zdenekgebauer/ftp-client`

## Usage

```php
$ftp = new ZdenekGebauer\FtpClient\FtpClient();
$ftpOptions = new ZdenekGebauer\FtpClient\FtpOptions();
// set connection parameters
$ftpOptions->host = 'localhost';
$ftpOptions->username = 'login';
$ftpOptions->password = 'password';
// optional parameters  
// $ftpOptions->port = 21;
// $ftpOptions->timeout = 120; 
// $ftpOptions->ssl = true;

try {
    $ftp->connect($ftpOptions);
} catch (\ZdenekGebauer\FtpClient\FtpException $exception) {
    // do something
}
// set passive mode 
$ftp->pasv(true);
echo 'PWD: ', $ftp->pwd();


// upload local file to server
try { 
    $ftp->put('remote.txt', 'local.txt');
} catch (\ZdenekGebauer\FtpClient\FtpException $exception) {
    // do something
}

// download file from server (most of methods throws FtpException)   
$ftp->get( 'download.txt', 'remote.txt');

// create directory on server, recursive 
$ftp->mkdir('/', 'new/folder', 0777);

// delete remote directory
$ftp->rmdir('/folder/subfolder');

// change directory
$ftp->chdir('remote/folder');

// upload directory
$ftp->putDirectory('/', '/local/folder', 'remote/folder');

// get tree with remote directories 
/** @var array<FtpFileInfo> $tree */
$tree = $ftp->tree('tree');

// delete remote file 
$ftp->delete('remote.txt');

// rename remote file or directory
$ftp->rename('file.txt', 'new-name.txt');
$ftp->rename('folder', 'new-folder');

// get content of remote file 
var_dump($ftp->getFileContent('remote.txt'));

// check if directory exists
var_dump($ftp->isDirectory('/folder'));

// check if file exists
var_dump($ftp->isFile('file.txt'));

// copy remote file
$ftp->copyFile('file.txt', 'copy.txt');

// copy remote directory
$ftp->copyDirectory('/', '/folder', '/copy-folder');

// chmod remote file 
$ftp->chmod('file.txt', 0777);

$ftp->close();
```

## Testing
Tested with [Codeception](https://codeception.com/) framework. Tests requires valid connection parameters for
FTP server specified in environment file(s) /tests/_envs/*.yml   

## Licence
Released under the [WTFPL license](copying.txt) http://www.wtfpl.net/about/.
