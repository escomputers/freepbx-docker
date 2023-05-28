<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace splitbrain\PHPArchive;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

class TarTestCase extends TestCase
{
    /** @var int callback counter */
    protected $counter = 0;

    /**
     * file extensions that several tests use
     */
    protected $extensions = array('tar');

    /** @inheritdoc */
    protected function setUp()
    {
        parent::setUp();
        if (extension_loaded('zlib')) {
            $this->extensions[] = 'tgz';
            $this->extensions[] = 'tar.gz';
        }
        if (extension_loaded('bz2')) {
            $this->extensions[] = 'tbz';
            $this->extensions[] = 'tar.bz2';
        }
        vfsStream::setup('home_root_path');
    }

    /** @inheritdoc */
    protected function tearDown()
    {
        parent::tearDown();
        $this->extensions[] = null;
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
     * dependency for tests needing zlib extension to pass
     */
    public function testExtZlibIsInstalled()
    {
        $this->assertTrue(function_exists('gzopen'));
    }

    /*
     * dependency for tests needing bz2 extension to pass
     */
    public function testExtBz2IsInstalled()
    {
        $this->assertTrue(function_exists('bzopen'));
    }

    /**
     * @expectedException \splitbrain\PHPArchive\ArchiveIOException
     */
    public function testTarFileIsNotExisted()
    {
        $tar = new Tar();
        $tar->open('non_existed_file.tar');
    }

    /**
     * simple test that checks that the given filenames and contents can be grepped from
     * the uncompressed tar stream
     *
     * No check for format correctness
     */
    public function testCreateDynamic()
    {
        $tar = new Tar();

        $dir = dirname(__FILE__) . '/tar';
        $tdir = ltrim($dir, '/');

        $tar->create();
        $tar->addFile("$dir/testdata1.txt");
        $tar->addFile("$dir/foobar/testdata2.txt", 'noway/testdata2.txt');
        $tar->addData('another/testdata3.txt', 'testcontent3');

        $data = $tar->getArchive();

        $this->assertTrue(strpos($data, 'testcontent1') !== false, 'Content in TAR');
        $this->assertTrue(strpos($data, 'testcontent2') !== false, 'Content in TAR');
        $this->assertTrue(strpos($data, 'testcontent3') !== false, 'Content in TAR');

        // fullpath might be too long to be stored as full path FS#2802
        $this->assertTrue(strpos($data, "$tdir") !== false, 'Path in TAR');
        $this->assertTrue(strpos($data, "testdata1.txt") !== false, 'File in TAR');

        $this->assertTrue(strpos($data, 'noway/testdata2.txt') !== false, 'Path in TAR');
        $this->assertTrue(strpos($data, 'another/testdata3.txt') !== false, 'Path in TAR');

        // fullpath might be too long to be stored as full path FS#2802
        $this->assertTrue(strpos($data, "$tdir/foobar") === false, 'Path not in TAR');
        $this->assertTrue(strpos($data, "foobar.txt") === false, 'File not in TAR');

        $this->assertTrue(strpos($data, "foobar") === false, 'Path not in TAR');
    }

    /**
     * simple test that checks that the given filenames and contents can be grepped from the
     * uncompressed tar file
     *
     * No check for format correctness
     */
    public function testCreateFile()
    {
        $tar = new Tar();

        $dir = dirname(__FILE__) . '/tar';
        $tdir = ltrim($dir, '/');
        $tmp = vfsStream::url('home_root_path/test.tar');

        $tar->create($tmp);
        $tar->addFile("$dir/testdata1.txt");
        $tar->addFile("$dir/foobar/testdata2.txt", 'noway/testdata2.txt');
        $tar->addData('another/testdata3.txt', 'testcontent3');
        $tar->close();

        $this->assertTrue(filesize($tmp) > 30); //arbitrary non-zero number
        $data = file_get_contents($tmp);

        $this->assertTrue(strpos($data, 'testcontent1') !== false, 'Content in TAR');
        $this->assertTrue(strpos($data, 'testcontent2') !== false, 'Content in TAR');
        $this->assertTrue(strpos($data, 'testcontent3') !== false, 'Content in TAR');

        // fullpath might be too long to be stored as full path FS#2802
        $this->assertTrue(strpos($data, "$tdir") !== false, "Path in TAR '$tdir'");
        $this->assertTrue(strpos($data, "testdata1.txt") !== false, 'File in TAR');

        $this->assertTrue(strpos($data, 'noway/testdata2.txt') !== false, 'Path in TAR');
        $this->assertTrue(strpos($data, 'another/testdata3.txt') !== false, 'Path in TAR');

        // fullpath might be too long to be stored as full path FS#2802
        $this->assertTrue(strpos($data, "$tdir/foobar") === false, 'Path not in TAR');
        $this->assertTrue(strpos($data, "foobar.txt") === false, 'File not in TAR');

        $this->assertTrue(strpos($data, "foobar") === false, 'Path not in TAR');
    }

    /**
     * List the contents of the prebuilt TAR files
     */
    public function testTarcontent()
    {
        $dir = dirname(__FILE__) . '/tar';

        foreach ($this->extensions as $ext) {
            $tar = new Tar();
            $file = "$dir/test.$ext";

            $tar->open($file);
            /** @var FileInfo[] $content */
            $content = $tar->contents();

            $this->assertCount(4, $content, "Contents of $file");
            $this->assertEquals('tar/testdata1.txt', $content[1]->getPath(), "Contents of $file");
            $this->assertEquals(13, $content[1]->getSize(), "Contents of $file");

            $this->assertEquals('tar/foobar/testdata2.txt', $content[3]->getPath(), "Contents of $file");
            $this->assertEquals(13, $content[3]->getSize(), "Contents of $file");
        }
    }

    /**
     * Create an archive and unpack it again
     */
    public function testDogfood()
    {
        foreach ($this->extensions as $ext) {
            $input = glob(dirname(__FILE__) . '/../src/*');
            $archive = sys_get_temp_dir() . '/dwtartest' . md5(time()) . '.' . $ext;
            $extract = sys_get_temp_dir() . '/dwtartest' . md5(time() + 1);

            $this->counter = 0;
            $tar = new Tar();
            $tar->setCallback(array($this, 'increaseCounter'));
            $tar->create($archive);
            foreach ($input as $path) {
                $file = basename($path);
                $tar->addFile($path, $file);
            }
            $tar->close();
            $this->assertFileExists($archive);
            $this->assertEquals(count($input), $this->counter);

            $this->counter = 0;
            $tar = new Tar();
            $tar->setCallback(array($this, 'increaseCounter'));
            $tar->open($archive);
            $tar->extract($extract, '', '/FileInfo\\.php/', '/.*\\.php/');

            $this->assertFileExists("$extract/Tar.php");
            $this->assertFileExists("$extract/Zip.php");
            $this->assertFileNotExists("$extract/FileInfo.php");

            $this->assertEquals(count($input) - 1, $this->counter);

            $this->nativeCheck($archive, $ext);

            self::RDelete($extract);
            unlink($archive);
        }
    }

    /**
     * Test the given archive with a native tar installation (if available)
     *
     * @param $archive
     * @param $ext
     */
    protected function nativeCheck($archive, $ext)
    {
        if (!is_executable('/usr/bin/tar')) {
            return;
        }

        $switch = array(
            'tar' => '-tf',
            'tgz' => '-tzf',
            'tar.gz' => '-tzf',
            'tbz' => '-tjf',
            'tar.bz2' => '-tjf',
        );
        $arg = $switch[$ext];
        $archive = escapeshellarg($archive);

        $return = 0;
        $output = array();
        $ok = exec("/usr/bin/tar $arg $archive 2>&1 >/dev/null", $output, $return);
        $output = join("\n", $output);

        $this->assertNotFalse($ok, "native tar execution for $archive failed:\n$output");
        $this->assertSame(0, $return, "native tar execution for $archive had non-zero exit code $return:\n$output");
        $this->assertSame('', $output, "native tar execution for $archive had non-empty output:\n$output");
    }

    /**
     * Extract the prebuilt tar files
     */
    public function testTarExtract()
    {
        $dir = dirname(__FILE__) . '/tar';
        $out = sys_get_temp_dir() . '/dwtartest' . md5(time());

        foreach ($this->extensions as $ext) {
            $tar = new Tar();
            $file = "$dir/test.$ext";

            $tar->open($file);
            $tar->extract($out);

            clearstatcache();

            $this->assertFileExists($out . '/tar/testdata1.txt', "Extracted $file");
            $this->assertEquals(13, filesize($out . '/tar/testdata1.txt'), "Extracted $file");

            $this->assertFileExists($out . '/tar/foobar/testdata2.txt', "Extracted $file");
            $this->assertEquals(13, filesize($out . '/tar/foobar/testdata2.txt'), "Extracted $file");

            self::RDelete($out);
        }
    }

    /**
     * Extract the prebuilt tar files with component stripping
     */
    public function testCompStripExtract()
    {
        $dir = dirname(__FILE__) . '/tar';
        $out = sys_get_temp_dir() . '/dwtartest' . md5(time());

        foreach ($this->extensions as $ext) {
            $tar = new Tar();
            $file = "$dir/test.$ext";

            $tar->open($file);
            $tar->extract($out, 1);

            clearstatcache();

            $this->assertFileExists($out . '/testdata1.txt', "Extracted $file");
            $this->assertEquals(13, filesize($out . '/testdata1.txt'), "Extracted $file");

            $this->assertFileExists($out . '/foobar/testdata2.txt', "Extracted $file");
            $this->assertEquals(13, filesize($out . '/foobar/testdata2.txt'), "Extracted $file");

            self::RDelete($out);
        }
    }

    /**
     * Extract the prebuilt tar files with prefix stripping
     */
    public function testPrefixStripExtract()
    {
        $dir = dirname(__FILE__) . '/tar';
        $out = sys_get_temp_dir() . '/dwtartest' . md5(time());

        foreach ($this->extensions as $ext) {
            $tar = new Tar();
            $file = "$dir/test.$ext";

            $tar->open($file);
            $tar->extract($out, 'tar/foobar/');

            clearstatcache();

            $this->assertFileExists($out . '/tar/testdata1.txt', "Extracted $file");
            $this->assertEquals(13, filesize($out . '/tar/testdata1.txt'), "Extracted $file");

            $this->assertFileExists($out . '/testdata2.txt', "Extracted $file");
            $this->assertEquals(13, filesize($out . '/testdata2.txt'), "Extracted $file");

            self::RDelete($out);
        }
    }

    /**
     * Extract the prebuilt tar files with include regex
     */
    public function testIncludeExtract()
    {
        $dir = dirname(__FILE__) . '/tar';
        $out = sys_get_temp_dir() . '/dwtartest' . md5(time());

        foreach ($this->extensions as $ext) {
            $tar = new Tar();
            $file = "$dir/test.$ext";

            $tar->open($file);
            $tar->extract($out, '', '', '/\/foobar\//');

            clearstatcache();

            $this->assertFileNotExists($out . '/tar/testdata1.txt', "Extracted $file");

            $this->assertFileExists($out . '/tar/foobar/testdata2.txt', "Extracted $file");
            $this->assertEquals(13, filesize($out . '/tar/foobar/testdata2.txt'), "Extracted $file");

            self::RDelete($out);
        }
    }

    /**
     * Extract the prebuilt tar files with exclude regex
     */
    public function testExcludeExtract()
    {
        $dir = dirname(__FILE__) . '/tar';
        $out = sys_get_temp_dir() . '/dwtartest' . md5(time());

        foreach ($this->extensions as $ext) {
            $tar = new Tar();
            $file = "$dir/test.$ext";

            $tar->open($file);
            $tar->extract($out, '', '/\/foobar\//');

            clearstatcache();

            $this->assertFileExists($out . '/tar/testdata1.txt', "Extracted $file");
            $this->assertEquals(13, filesize($out . '/tar/testdata1.txt'), "Extracted $file");

            $this->assertFileNotExists($out . '/tar/foobar/testdata2.txt', "Extracted $file");

            self::RDelete($out);
        }
    }

    /**
     * Check the extension to compression guesser
     */
    public function testFileType()
    {
        $tar = new Tar();
        $this->assertEquals(Tar::COMPRESS_NONE, $tar->filetype('foo'));
        $this->assertEquals(Tar::COMPRESS_GZIP, $tar->filetype('foo.tgz'));
        $this->assertEquals(Tar::COMPRESS_GZIP, $tar->filetype('foo.tGZ'));
        $this->assertEquals(Tar::COMPRESS_GZIP, $tar->filetype('foo.tar.GZ'));
        $this->assertEquals(Tar::COMPRESS_GZIP, $tar->filetype('foo.tar.gz'));
        $this->assertEquals(Tar::COMPRESS_BZIP, $tar->filetype('foo.tbz'));
        $this->assertEquals(Tar::COMPRESS_BZIP, $tar->filetype('foo.tBZ'));
        $this->assertEquals(Tar::COMPRESS_BZIP, $tar->filetype('foo.tar.BZ2'));
        $this->assertEquals(Tar::COMPRESS_BZIP, $tar->filetype('foo.tar.bz2'));

        $dir = dirname(__FILE__) . '/tar';
        $this->assertEquals(Tar::COMPRESS_NONE, $tar->filetype("$dir/test.tar"));
        $this->assertEquals(Tar::COMPRESS_GZIP, $tar->filetype("$dir/test.tgz"));
        $this->assertEquals(Tar::COMPRESS_BZIP, $tar->filetype("$dir/test.tbz"));
        $this->assertEquals(Tar::COMPRESS_NONE, $tar->filetype("$dir/test.tar.guess"));
        $this->assertEquals(Tar::COMPRESS_GZIP, $tar->filetype("$dir/test.tgz.guess"));
        $this->assertEquals(Tar::COMPRESS_BZIP, $tar->filetype("$dir/test.tbz.guess"));
    }

    /**
     * @depends testExtZlibIsInstalled
     */
    public function testLongPathExtract()
    {
        $dir = dirname(__FILE__) . '/tar';
        $out = vfsStream::url('home_root_path/dwtartest' . md5(time()));

        foreach (array('ustar', 'gnu') as $format) {
            $tar = new Tar();
            $tar->open("$dir/longpath-$format.tgz");
            $tar->extract($out);

            $this->assertFileExists(
                $out . '/1234567890/1234567890/1234567890/1234567890/1234567890/1234567890/1234567890/1234567890/1234567890/1234567890/1234567890/1234567890/test.txt'
            );
        }
    }

    // FS#1442
    public function testCreateLongFile()
    {
        $tar = new Tar();
        $tar->setCompression(0);
        $tmp = vfsStream::url('home_root_path/dwtartest');

        $path = '0123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789.txt';

        $tar->create($tmp);
        $tar->addData($path, 'testcontent1');
        $tar->close();

        $this->assertTrue(filesize($tmp) > 30); //arbitrary non-zero number
        $data = file_get_contents($tmp);

        // We should find the complete path and a longlink entry
        $this->assertTrue(strpos($data, 'testcontent1') !== false, 'content in TAR');
        $this->assertTrue(strpos($data, $path) !== false, 'path in TAR');
        $this->assertTrue(strpos($data, '@LongLink') !== false, '@LongLink in TAR');
    }

    public function testCreateLongPathTar()
    {
        $tar = new Tar();
        $tar->setCompression(0);
        $tmp = vfsStream::url('home_root_path/dwtartest');

        $path = '';
        for ($i = 0; $i < 11; $i++) {
            $path .= '1234567890/';
        }
        $path = rtrim($path, '/');

        $tar->create($tmp);
        $tar->addData("$path/test.txt", 'testcontent1');
        $tar->close();

        $this->assertTrue(filesize($tmp) > 30); //arbitrary non-zero number
        $data = file_get_contents($tmp);

        // We should find the path and filename separated, no longlink entry
        $this->assertTrue(strpos($data, 'testcontent1') !== false, 'content in TAR');
        $this->assertTrue(strpos($data, 'test.txt') !== false, 'filename in TAR');
        $this->assertTrue(strpos($data, $path) !== false, 'path in TAR');
        $this->assertFalse(strpos($data, "$path/test.txt") !== false, 'full filename in TAR');
        $this->assertFalse(strpos($data, '@LongLink') !== false, '@LongLink in TAR');
    }

    public function testCreateLongPathGnu()
    {
        $tar = new Tar();
        $tar->setCompression(0);
        $tmp = vfsStream::url('home_root_path/dwtartest');

        $path = '';
        for ($i = 0; $i < 20; $i++) {
            $path .= '1234567890/';
        }
        $path = rtrim($path, '/');

        $tar->create($tmp);
        $tar->addData("$path/test.txt", 'testcontent1');
        $tar->close();

        $this->assertTrue(filesize($tmp) > 30); //arbitrary non-zero number
        $data = file_get_contents($tmp);

        // We should find the complete path/filename and a longlink entry
        $this->assertTrue(strpos($data, 'testcontent1') !== false, 'content in TAR');
        $this->assertTrue(strpos($data, 'test.txt') !== false, 'filename in TAR');
        $this->assertTrue(strpos($data, $path) !== false, 'path in TAR');
        $this->assertTrue(strpos($data, "$path/test.txt") !== false, 'full filename in TAR');
        $this->assertTrue(strpos($data, '@LongLink') !== false, '@LongLink in TAR');
    }

    /**
     * Extract a tarbomomb
     * @depends testExtZlibIsInstalled
     */
    public function testTarBomb()
    {
        $dir = dirname(__FILE__) . '/tar';
        $out = vfsStream::url('home_root_path/dwtartest' . md5(time()));

        $tar = new Tar();

        $tar->open("$dir/tarbomb.tgz");
        $tar->extract($out);

        clearstatcache();

        $this->assertFileExists(
            $out . '/AAAAAAAAAAAAAAAAA/BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB.txt'
        );
    }

    /**
     * A single zero file should be just a header block + the footer
     */
    public function testZeroFile()
    {
        $dir = dirname(__FILE__) . '/tar';
        $tar = new Tar();
        $tar->setCompression(0);
        $tar->create();
        $tar->addFile("$dir/zero.txt", 'zero.txt');
        $file = $tar->getArchive();

        $this->assertEquals(512 * 3, strlen($file)); // 1 header block + 2 footer blocks
    }

    public function testZeroData()
    {
        $tar = new Tar();
        $tar->setCompression(0);
        $tar->create();
        $tar->addData('zero.txt', '');
        $file = $tar->getArchive();

        $this->assertEquals(512 * 3, strlen($file)); // 1 header block + 2 footer blocks
    }

    /**
     * A file of exactly one block should be just a header block + data block + the footer
     */
    public function testBlockFile()
    {
        $dir = dirname(__FILE__) . '/tar';
        $tar = new Tar();
        $tar->setCompression(0);
        $tar->create();
        $tar->addFile("$dir/block.txt", 'block.txt');
        $file = $tar->getArchive();

        $this->assertEquals(512 * 4, strlen($file)); // 1 header block + data block + 2 footer blocks
    }

    public function testBlockData()
    {
        $tar = new Tar();
        $tar->setCompression(0);
        $tar->create();
        $tar->addData('block.txt', str_pad('', 512, 'x'));
        $file = $tar->getArchive();

        $this->assertEquals(512 * 4, strlen($file)); // 1 header block + data block + 2 footer blocks
    }

    /**
     * @depends testExtZlibIsInstalled
     */
    public function testGzipIsValid()
    {
        foreach (['tgz', 'tar.gz'] as $ext) {
            $input = glob(dirname(__FILE__) . '/../src/*');
            $archive = sys_get_temp_dir() . '/dwtartest' . md5(time()) . '.' . $ext;
            $extract = sys_get_temp_dir() . '/dwtartest' . md5(time() + 1);

            $tar = new Tar();
            $tar->setCompression(9, Tar::COMPRESS_GZIP);
            $tar->create();
            foreach ($input as $path) {
                $file = basename($path);
                $tar->addFile($path, $file);
            }
            $tar->save($archive);
            $this->assertFileExists($archive);

            try {
                $phar = new \PharData($archive);
                $phar->extractTo($extract);
            } catch(\Exception $e) {
            };

            $this->assertFileExists("$extract/Tar.php");
            $this->assertFileExists("$extract/Zip.php");

            $this->nativeCheck($archive, $ext);

            self::RDelete($extract);
            unlink($archive);
        }
    }

    /**
     * @expectedException \splitbrain\PHPArchive\ArchiveIOException
     */
    public function testContentsWithInvalidArchiveStream()
    {
        $tar = new Tar();
        $tar->contents();
    }

    /**
     * @expectedException \splitbrain\PHPArchive\ArchiveIOException
     */
    public function testExtractWithInvalidOutDir()
    {
        $dir = dirname(__FILE__) . '/tar';
        $out = '/root/invalid_out_dir';

        $tar = new Tar();

        $tar->open("$dir/tarbomb.tgz");
        $tar->extract($out);
    }

    /**
     * @expectedException \splitbrain\PHPArchive\ArchiveIOException
     */
    public function testExtractWithArchiveStreamIsClosed()
    {
        $dir = dirname(__FILE__) . '/tar';
        $out = '/root/invalid_out_dir';

        $tar = new Tar();

        $tar->open("$dir/tarbomb.tgz");
        $tar->close();
        $tar->extract($out);
    }

    /**
     * @expectedException \splitbrain\PHPArchive\ArchiveIOException
     */
    public function testCreateWithInvalidFile()
    {
        $dir = dirname(__FILE__) . '/tar';
        $tar = new Tar();

        $tar->open("$dir/tarbomb.tgz");
        $tar->create('/root/invalid_file');
    }

    /**
     * @expectedException \splitbrain\PHPArchive\ArchiveIOException
     */
    public function testAddFileWithArchiveStreamIsClosed()
    {
        $archive = sys_get_temp_dir() . '/dwtartest' . md5(time()) . '.tar';

        $tar = new Tar();
        $tar->create($archive);
        $tar->close();
        $tar->addFile('archive_file', false);
    }

    /**
     * @expectedException \splitbrain\PHPArchive\ArchiveIOException
     */
    public function testAddFileWithInvalidFile()
    {
        $archive = sys_get_temp_dir() . '/dwtartest' . md5(time()) . '.tar';

        $tar = new Tar();
        $tar->create($archive);
        $tar->addFile('archive_file', false);
    }

    /**
     * @expectedException \splitbrain\PHPArchive\ArchiveIOException
     */
    public function testAddDataWithArchiveStreamIsClosed()
    {
        $archive = sys_get_temp_dir() . '/dwtartest' . md5(time()) . '.tar';

        $tar = new Tar();
        $tar->create($archive);
        $tar->close();
        $tar->addData(false, '');
    }

    public function testCloseHasBeenClosed()
    {
        $archive = sys_get_temp_dir() . '/dwtartest' . md5(time()) . '.tar';

        $tar = new Tar();
        $tar->create($archive);
        $tar->close();

        $tar->close();
        $this->assertTrue(true); // succeed if no exception, yet
    }

    /**
     * @depends testExtBz2IsInstalled
     */
    public function testGetArchiveWithBzipCompress()
    {
        $dir = dirname(__FILE__) . '/tar';
        $tar = new Tar();
        $tar->setCompression(9, Tar::COMPRESS_BZIP);
        $tar->create();
        $tar->addFile("$dir/zero.txt", 'zero.txt');
        $file = $tar->getArchive();

        $this->assertInternalType('string', $file); // 1 header block + 2 footer blocks
    }

    public function testSaveWithCompressionAuto()
    {
        $dir = dirname(__FILE__) . '/tar';
        $tar = new Tar();
        $tar->setCompression(-1);
        $tar->create();
        $tar->addFile("$dir/zero.txt", 'zero.txt');

        $tar->save(vfsStream::url('home_root_path/archive_file'));
        $this->assertTrue(true); // succeed if no exception, yet
    }

    /**
     * @expectedException \splitbrain\PHPArchive\ArchiveIOException
     */
    public function testSaveWithInvalidDestinationFile()
    {
        $dir = dirname(__FILE__) . '/tar';
        $tar = new Tar();
        $tar->setCompression();
        $tar->create();
        $tar->addFile("$dir/zero.txt", 'zero.txt');

        $tar->save(vfsStream::url('archive_file'));
        $this->assertTrue(true); // succeed if no exception, yet
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
