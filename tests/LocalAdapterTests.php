<?php

namespace League\Flysystem\Adapter;

function fopen($result)
{
    if (substr($result, -5) === 'false') {
        return false;
    }

    if (substr($result, -5) === 'dummy') {
        return 'dummy';
    }

    return call_user_func_array('fopen', func_get_args());
}

function fwrite($result)
{
    if (is_string($result)) {
        return 'dummy';
    }

    return call_user_func_array('fwrite', func_get_args());
}

function fclose($result)
{
    if (is_string($result) and substr($result, -5) === 'dummy') {
        return false;
    }

    return call_user_func_array('fclose', func_get_args());
}

class LocalAdapterTests extends \PHPUnit_Framework_TestCase
{
    protected $adapter;
    protected $root;

    public function setup()
    {
        $this->root = __DIR__.'/files/';
        $this->adapter = new Local($this->root);
    }

    public function teardown()
    {
        $it = new \RecursiveDirectoryIterator($this->root, \RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($it,
                     \RecursiveIteratorIterator::CHILD_FIRST);
        foreach($files as $file) {
            if ($file->getFilename() === '.' || $file->getFilename() === '..') {
                continue;
            }
            if ($file->isDir()){
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
    }

    public function testReadStream()
    {
        $adapter = $this->adapter;
        $adapter->write('file.txt', 'contents');
        $result = $adapter->readStream('file.txt');
        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('stream', $result);
        $this->assertInternalType('resource', $result['stream']);
        fclose($result['stream']);
        $adapter->delete('file.txt');
    }

    public function testWriteStream()
    {
        $adapter = $this->adapter;
        $temp = tmpfile();
        fwrite($temp, 'dummy');
        rewind($temp);
        $adapter->writeStream('dir/file.txt', $temp, 'public');
        fclose($temp);
        $this->assertTrue($adapter->has('dir/file.txt'));
        $result = $adapter->read('dir/file.txt');
        $this->assertEquals('dummy', $result['contents']);
        $adapter->deleteDir('dir');
    }

    public function testListingNonexistingDirectory()
    {
        $result = $this->adapter->listContents('nonexisting/directory');
        $this->assertEquals(array(), $result);
    }

    public function testUpdateStream()
    {
        $adapter = $this->adapter;
        $adapter->write('file.txt', 'initial');
        $temp = tmpfile();
        fwrite($temp, 'dummy');
        $adapter->updateStream('file.txt', $temp);
        fclose($temp);
        $this->assertTrue($adapter->has('file.txt'));
        $adapter->delete('file.txt');
    }

    public function testCreateZeroDir()
    {
        $this->adapter->createDir('0');
        $this->assertTrue(is_dir($this->adapter->applyPathPrefix('0')));
        $this->adapter->deleteDir('0');
    }

    public function testCopy()
    {
        $adapter = $this->adapter;
        $adapter->write('file.ext', 'content');
        $this->assertTrue($adapter->copy('file.ext', 'new.ext'));
        $this->assertTrue($adapter->has('new.ext'));
        $adapter->delete('file.ext');
        $adapter->delete('new.ext');
    }

    public function testFailingStreamCalls()
    {
        $this->assertFalse($this->adapter->writeStream('false', tmpfile()));
        $this->assertFalse($this->adapter->writeStream('dummy', tmpfile()));
    }

    public function testNullPrefix()
    {
        $this->adapter->setPathPrefix('');
        $path = 'some/path.ext';
        $this->assertEquals($path, $this->adapter->applyPathPrefix($path));
        $this->assertEquals($path, $this->adapter->removePathPrefix($path));
    }

    public function testGetPathPrefix()
    {
        $this->assertEquals(realpath($this->root) . DIRECTORY_SEPARATOR, $this->adapter->getPathPrefix());
    }

    public function testRenameToNonExistsingDirectory()
    {
        $this->adapter->write('file.txt', 'contents');
        $dirname = uniqid();
        $this->assertFalse(is_dir($this->root . DIRECTORY_SEPARATOR . $dirname));
        $this->assertTrue($this->adapter->rename('file.txt', $dirname.'/file.txt'));

    }
}
