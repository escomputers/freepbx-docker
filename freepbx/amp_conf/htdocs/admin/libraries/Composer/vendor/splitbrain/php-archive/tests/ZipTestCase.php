<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace splitbrain\PHPArchive;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

class ZipTestCase extends TestCase
{
    /** @var int callback counter */
    protected $counter = 0;

    /** @inheritdoc */
    protected function setUp()
    {
        vfsStream::setup('home_root_path');
    }

    /**
     * Callback check function
     * @param FileInfo $fileinfo
     */
    public function increaseCounter($fileinfo) {
        $this->assertInstanceOf('\\splitbrain\\PHPArchive\\FileInfo', $fileinfo);
        $this->counter++;
    }

    /*
     * dependency for tests needing zip extension to pass
     */
    public function testExtZipIsInstalled()
    {
        $this->assertTrue(function_exists('zip_open'));
    }

    /**
     * @expectedException \splitbrain\PHPArchive\ArchiveIOException
     */
    public function testMissing()
    {
        $tar = new Zip();
        $tar->open('nope.zip');
    }

    /**
     * simple test that checks that the given filenames and contents can be grepped from
     * the uncompressed zip stream
     *
     * No check for format correctness
     * @depends testExtZipIsInstalled
     */
    public function testCreateDynamic()
    {
        $zip = new Zip();

        $dir = dirname(__FILE__) . '/zip';
        $tdir = ltrim($dir, '/');

        $zip->create();
        $zip->setCompression(0);
        $zip->AddFile("$dir/testdata1.txt", "$dir/testdata1.txt");
        $zip->AddFile("$dir/foobar/testdata2.txt", 'noway/testdata2.txt');
        $zip->addData('another/testdata3.txt', 'testcontent3');

        $data = $zip->getArchive();

        $this->assertTrue(strpos($data, 'testcontent1') !== false, 'Content 1 in ZIP');
        $this->assertTrue(strpos($data, 'testcontent2') !== false, 'Content 2 in ZIP');
        $this->assertTrue(strpos($data, 'testcontent3') !== false, 'Content 3 in ZIP');

        // fullpath might be too long to be stored as full path FS#2802
        $this->assertTrue(strpos($data, "$tdir") !== false, 'Path in ZIP');
        $this->assertTrue(strpos($data, "testdata1.txt") !== false, 'File in ZIP');

        $this->assertTrue(strpos($data, 'noway/testdata2.txt') !== false, 'Path in ZIP');
        $this->assertTrue(strpos($data, 'another/testdata3.txt') !== false, 'Path in ZIP');

        // fullpath might be too long to be stored as full path FS#2802
        $this->assertTrue(strpos($data, "$tdir/foobar") === false, 'Path not in ZIP');
        $this->assertTrue(strpos($data, "foobar.txt") === false, 'File not in ZIP');

        $this->assertTrue(strpos($data, "foobar") === false, 'Path not in ZIP');
    }

    /**
     * simple test that checks that the given filenames and contents can be grepped from the
     * uncompressed zip file
     *
     * No check for format correctness
     * @depends testExtZipIsInstalled
     */
    public function testCreateFile()
    {
        $zip = new Zip();

        $dir = dirname(__FILE__) . '/zip';
        $tdir = ltrim($dir, '/');
        $tmp = vfsStream::url('home_root_path/test.zip');

        $zip->create($tmp);
        $zip->setCompression(0);
        $zip->addFile("$dir/testdata1.txt", "$dir/testdata1.txt");
        $zip->addFile("$dir/foobar/testdata2.txt", 'noway/testdata2.txt');
        $zip->addData('another/testdata3.txt', 'testcontent3');
        $zip->close();

        $this->assertTrue(filesize($tmp) > 30); //arbitrary non-zero number
        $data = file_get_contents($tmp);

        $this->assertTrue(strpos($data, 'testcontent1') !== false, 'Content in ZIP');
        $this->assertTrue(strpos($data, 'testcontent2') !== false, 'Content in ZIP');
        $this->assertTrue(strpos($data, 'testcontent3') !== false, 'Content in ZIP');

        // fullpath might be too long to be stored as full path FS#2802
        $this->assertTrue(strpos($data, "$tdir") !== false, "Path in ZIP '$tdir'");
        $this->assertTrue(strpos($data, "testdata1.txt") !== false, 'File in ZIP');

        $this->assertTrue(strpos($data, 'noway/testdata2.txt') !== false, 'Path in ZIP');
        $this->assertTrue(strpos($data, 'another/testdata3.txt') !== false, 'Path in ZIP');

        // fullpath might be too long to be stored as full path FS#2802
        $this->assertTrue(strpos($data, "$tdir/foobar") === false, 'Path not in ZIP');
        $this->assertTrue(strpos($data, "foobar.txt") === false, 'File not in ZIP');

        $this->assertTrue(strpos($data, "foobar") === false, 'Path not in ZIP');
    }

    /**
     * @expectedException \splitbrain\PHPArchive\ArchiveIOException
     */
    public function testCreateWithInvalidFilePath()
    {
        $zip = new Zip();

        $tmp = vfsStream::url('invalid_root_path/test.zip');

        $zip->create($tmp);
    }

    /**
     * @expectedException \splitbrain\PHPArchive\ArchiveIOException
     */
    public function testAddFileWithArchiveStreamIsClosed()
    {
        $zip = new Zip();

        $dir = dirname(__FILE__) . '/zip';

        $zip->setCompression(0);
        $zip->close();
        $zip->addFile("$dir/testdata1.txt", "$dir/testdata1.txt");
    }

    /**
     * @expectedException \splitbrain\PHPArchive\ArchiveIOException
     */
    public function testAddFileWithInvalidFile()
    {
        $zip = new Zip();

        $tmp = vfsStream::url('home_root_path/test.zip');

        $zip->create($tmp);
        $zip->setCompression(0);
        $zip->addFile('invalid_file', false);
        $zip->close();
    }

    /**
     * List the contents of the prebuilt ZIP file
     * @depends testExtZipIsInstalled
     */
    public function testZipContent()
    {
        $dir = dirname(__FILE__) . '/zip';

        $zip = new Zip();
        $file = "$dir/test.zip";

        $zip->open($file);
        $content = $zip->contents();

        $this->assertCount(5, $content, "Contents of $file");
        $this->assertEquals('zip/testdata1.txt', $content[2]->getPath(), "Contents of $file");
        $this->assertEquals(13, $content[2]->getSize(), "Contents of $file");

        $this->assertEquals('zip/foobar/testdata2.txt', $content[4]->getPath(), "Contents of $file");
        $this->assertEquals(13, $content[4]->getSize(), "Contents of $file");
    }

    /**
     * @expectedException \splitbrain\PHPArchive\ArchiveIOException
     */
    public function testZipContentWithArchiveStreamIsClosed()
    {
        $dir = dirname(__FILE__) . '/zip';

        $zip = new Zip();
        $file = "$dir/test.zip";

        $zip->open($file);
        $zip->close();
        $zip->contents();
    }

    /**
     * Create an archive and unpack it again
     * @depends testExtZipIsInstalled
     */
    public function testDogFood()
    {
        $input = glob(dirname(__FILE__) . '/../src/*');
        $archive = sys_get_temp_dir() . '/dwziptest' . md5(time()) . '.zip';
        $extract = sys_get_temp_dir() . '/dwziptest' . md5(time() + 1);

        $this->counter = 0;
        $zip = new Zip();
        $zip->setCallback(array($this, 'increaseCounter'));
        $zip->create($archive);
        foreach ($input as $path) {
            $file = basename($path);
            $zip->addFile($path, $file);
        }
        $zip->close();
        $this->assertFileExists($archive);
        $this->assertEquals(count($input), $this->counter);

        $this->counter = 0;
        $zip = new Zip();
        $zip->setCallback(array($this, 'increaseCounter'));
        $zip->open($archive);
        $zip->extract($extract, '', '/FileInfo\\.php/', '/.*\\.php/');

        $this->assertFileExists("$extract/Tar.php");
        $this->assertFileExists("$extract/Zip.php");
        $this->assertFileNotExists("$extract/FileInfo.php");

        $this->assertEquals(count($input) - 1, $this->counter);

        $this->nativeCheck($archive);
        $this->native7ZipCheck($archive);

        self::RDelete($extract);
        unlink($archive);
    }

    /**
     * @depends testExtZipIsInstalled
     */
    public function testUtf8()
    {
        $archive = sys_get_temp_dir() . '/dwziptest' . md5(time()) . '.zip';
        $extract = sys_get_temp_dir() . '/dwziptest' . md5(time() + 1);

        $zip = new Zip();
        $zip->create($archive);
        $zip->addData('tüst.txt', 'test');
        $zip->addData('snowy☃.txt', 'test');
        $zip->close();
        $this->assertFileExists($archive);

        $zip = new Zip();
        $zip->open($archive);
        $zip->extract($extract);

        $this->assertFileExists($extract . '/tüst.txt');
        $this->assertFileExists($extract . '/snowy☃.txt');

        $this->nativeCheck($archive);
        $this->native7ZipCheck($archive);

        self::RDelete($extract);
        unlink($archive);
    }

    /**
     * @expectedException \splitbrain\PHPArchive\ArchiveIOException
     */
    public function testAddDataWithArchiveStreamIsClosed()
    {
        $archive = sys_get_temp_dir() . '/dwziptest' . md5(time()) . '.zip';

        $zip = new Zip();
        $zip->create($archive);
        $zip->close();
        $zip->addData('tüst.txt', 'test');
    }

    public function testCloseWithArchiveStreamIsClosed()
    {
        $archive = sys_get_temp_dir() . '/dwziptest' . md5(time()) . '.zip';

        $zip = new Zip();
        $zip->create($archive);
        $zip->close();

        $zip->close();
        $this->assertTrue(true); // succeed if no exception, yet
    }

    public function testSaveArchiveFile()
    {
        $dir = dirname(__FILE__) . '/tar';
        $zip = new zip();
        $zip->setCompression(-1);
        $zip->create();
        $zip->addFile("$dir/zero.txt", 'zero.txt');

        $zip->save(vfsStream::url('home_root_path/archive_file'));
        $this->assertTrue(true); // succeed if no exception, yet
    }

    /**
     * @expectedException \splitbrain\PHPArchive\ArchiveIOException
     */
    public function testSaveWithInvalidFilePath()
    {
        $archive = sys_get_temp_dir() . '/dwziptest' . md5(time()) . '.zip';

        $zip = new Zip();
        $zip->create($archive);
        $zip->save(vfsStream::url('invalid_root_path/save.zip'));
    }

    /**
     * Test the given archive with a native zip installation (if available)
     *
     * @param $archive
     */
    protected function nativeCheck($archive)
    {
        if (!is_executable('/usr/bin/zipinfo')) {
            return;
        }
        $archive = escapeshellarg($archive);

        $return = 0;
        $output = array();
        $ok = exec("/usr/bin/zipinfo $archive 2>&1 >/dev/null", $output, $return);
        $output = join("\n", $output);

        $this->assertNotFalse($ok, "native zip execution for $archive failed:\n$output");
        $this->assertSame(0, $return, "native zip execution for $archive had non-zero exit code $return:\n$output");
        $this->assertSame('', $output, "native zip execution for $archive had non-empty output:\n$output");
    }

    /**
     * Test the given archive with a native 7zip installation (if available)
     *
     * @param $archive
     */
    protected function native7ZipCheck($archive)
    {
        if (!is_executable('/usr/bin/7z')) {
            return;
        }
        $archive = escapeshellarg($archive);

        $return = 0;
        $output = array();
        $ok = exec("/usr/bin/7z t $archive 2>&1 >/dev/null", $output, $return);
        $output = join("\n", $output);

        $this->assertNotFalse($ok, "native 7zip execution for $archive failed:\n$output");
        $this->assertSame(0, $return, "native 7zip execution for $archive had non-zero exit code $return:\n$output");
        $this->assertSame('', $output, "native 7zip execution for $archive had non-empty output:\n$output");
    }

    /**
     * Extract the prebuilt zip files
     * @depends testExtZipIsInstalled
     */
    public function testZipExtract()
    {
        $dir = dirname(__FILE__) . '/zip';
        $out = sys_get_temp_dir() . '/dwziptest' . md5(time());

        $zip = new Zip();
        $file = "$dir/test.zip";

        $zip->open($file);
        $zip->extract($out);

        clearstatcache();

        $this->assertFileExists($out . '/zip/testdata1.txt', "Extracted $file");
        $this->assertEquals(13, filesize($out . '/zip/testdata1.txt'), "Extracted $file");

        $this->assertFileExists($out . '/zip/foobar/testdata2.txt', "Extracted $file");
        $this->assertEquals(13, filesize($out . '/zip/foobar/testdata2.txt'), "Extracted $file");

        $this->assertFileExists($out . '/zip/compressable.txt', "Extracted $file");
        $this->assertEquals(1836, filesize($out . '/zip/compressable.txt'), "Extracted $file");
        $this->assertFileNotExists($out . '/zip/compressable.txt.gz', "Extracted $file");

        self::RDelete($out);
    }

    /**
     * @expectedException \splitbrain\PHPArchive\ArchiveIOException
     */
    public function testZipExtractWithArchiveStreamIsClosed()
    {
        $dir = dirname(__FILE__) . '/zip';
        $out = sys_get_temp_dir() . '/dwziptest' . md5(time());

        $zip = new Zip();
        $file = "$dir/test.zip";

        $zip->open($file);
        $zip->close();
        $zip->extract($out);
    }

    /**
     * Extract the prebuilt zip files with component stripping
     * @depends testExtZipIsInstalled
     */
    public function testCompStripExtract()
    {
        $dir = dirname(__FILE__) . '/zip';
        $out = sys_get_temp_dir() . '/dwziptest' . md5(time());

        $zip = new Zip();
        $file = "$dir/test.zip";

        $zip->open($file);
        $zip->extract($out, 1);

        clearstatcache();

        $this->assertFileExists($out . '/testdata1.txt', "Extracted $file");
        $this->assertEquals(13, filesize($out . '/testdata1.txt'), "Extracted $file");

        $this->assertFileExists($out . '/foobar/testdata2.txt', "Extracted $file");
        $this->assertEquals(13, filesize($out . '/foobar/testdata2.txt'), "Extracted $file");

        self::RDelete($out);
    }

    /**
     * Extract the prebuilt zip files with prefix stripping
     * @depends testExtZipIsInstalled
     */
    public function testPrefixStripExtract()
    {
        $dir = dirname(__FILE__) . '/zip';
        $out = sys_get_temp_dir() . '/dwziptest' . md5(time());

        $zip = new Zip();
        $file = "$dir/test.zip";

        $zip->open($file);
        $zip->extract($out, 'zip/foobar/');

        clearstatcache();

        $this->assertFileExists($out . '/zip/testdata1.txt', "Extracted $file");
        $this->assertEquals(13, filesize($out . '/zip/testdata1.txt'), "Extracted $file");

        $this->assertFileExists($out . '/testdata2.txt', "Extracted $file");
        $this->assertEquals(13, filesize($out . '/testdata2.txt'), "Extracted $file");

        self::RDelete($out);
    }

    /**
     * Extract the prebuilt zip files with include regex
     * @depends testExtZipIsInstalled
     */
    public function testIncludeExtract()
    {
        $dir = dirname(__FILE__) . '/zip';
        $out = sys_get_temp_dir() . '/dwziptest' . md5(time());

        $zip = new Zip();
        $file = "$dir/test.zip";

        $zip->open($file);
        $zip->extract($out, '', '', '/\/foobar\//');

        clearstatcache();

        $this->assertFileNotExists($out . '/zip/testdata1.txt', "Extracted $file");

        $this->assertFileExists($out . '/zip/foobar/testdata2.txt', "Extracted $file");
        $this->assertEquals(13, filesize($out . '/zip/foobar/testdata2.txt'), "Extracted $file");

        self::RDelete($out);
    }

    /**
     * Extract the prebuilt zip files with exclude regex
     * @depends testExtZipIsInstalled
     */
    public function testExcludeExtract()
    {
        $dir = dirname(__FILE__) . '/zip';
        $out = sys_get_temp_dir() . '/dwziptest' . md5(time());

        $zip = new Zip();
        $file = "$dir/test.zip";

        $zip->open($file);
        $zip->extract($out, '', '/\/foobar\//');

        clearstatcache();

        $this->assertFileExists($out . '/zip/testdata1.txt', "Extracted $file");
        $this->assertEquals(13, filesize($out . '/zip/testdata1.txt'), "Extracted $file");

        $this->assertFileNotExists($out . '/zip/foobar/testdata2.txt', "Extracted $file");

        self::RDelete($out);
    }

    /**
     * @depends testExtZipIsInstalled
     */
    public function testUmlautWinrar()
    {
        $out = vfsStream::url('home_root_path/dwtartest' . md5(time()));

        $zip = new Zip();
        $zip->open(__DIR__ . '/zip/issue14-winrar.zip');
        $zip->extract($out);
        $this->assertFileExists("$out/tüst.txt");
    }

    /**
     * @depends testExtZipIsInstalled
     */
    public function testUmlautWindows()
    {
        $out = vfsStream::url('home_root_path/dwtartest' . md5(time()));

        $zip = new Zip();
        $zip->open(__DIR__ . '/zip/issue14-windows.zip');
        $zip->extract($out);
        $this->assertFileExists("$out/täst.txt");
    }

    /**
     * recursive rmdir()/unlink()
     *
     * @static
     * @param $target string
     */
    public static function RDelete($target)
    {
        if (!is_dir($target)) {
            unlink($target);
        } else {
            $dh = dir($target);
            while (false !== ($entry = $dh->read())) {
                if ($entry == '.' || $entry == '..') {
                    continue;
                }
                self::RDelete("$target/$entry");
            }
            $dh->close();
            rmdir($target);
        }
    }

}
