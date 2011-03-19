<?php
use Sonic\UnitTest\TestCase;
use Sonic\Util;

class UtilTest extends TestCase
{
    public function testGetWeightedRandomKey()
    {
        $weights = array(
            0 => 5,
            1 => 10,
            2 => 20
        );

        $results = array_fill_keys(array_keys($weights), 0);

        // run the test 1000 times to get an idea of how the keys selected relate
        // to the original weights
        $i = 0;
        $run = 1000;
        for ($i = 0; $i < $run; ++$i) {
            $key = Util::getWeightedRandomKey($weights);
            ++$results[$key];
        }

        // number of times each key was selected
        // key 1 should be selected about twice as much as key 0
        // key 2 should be selected about twice as much as key 1
        // make sure the difference is less than 1/10 of the number of runs
        $diff = abs($results[0] * 2 - $results[1]);
        $diff2 = abs($results[1] * 2 - $results[2]);

        $this->isTrue($diff < ($run / 10));
        $this->isTrue($diff2 < ($run / 10));
    }

    public function testResolveDependencies()
    {
        $dependencies = array(
            'a' => array('b', 'c'),
            'b' => array(),
            'c' => array('b')
        );

        $resolution = Util::resolveDependencies($dependencies);
        $this->isEqual($resolution, array('b', 'c', 'a'));


        $dependencies = array(
            'a' => array('b', 'c'),
            'b' => array(),
            'c' => array('b'),
            'd' => array('c'),
            'e' => array('c')
        );

        $resolution = Util::resolveDependencies($dependencies);
        $this->isEqual($resolution, array('b', 'c', 'd', 'e', 'a'));

        $dependencies = array(
            'a' => array(),
            'b' => array(),
            'c' => array()
        );

        $resolution = Util::resolveDependencies($dependencies);
        $this->isEqual($resolution, array('a', 'b', 'c'));
    }

    public function testExtendArray()
    {
        $array1 = array(
            'key1' => 12,
            'key2' => 'second key',
            'urls' => array(
                'www' => 'http://www.example.com',
                'static' => 'http://static.example.com',
                'test' => 'http://test.example.com'
            ),
            'values' => array(1,2,3,4,5)
        );

        $array2 = array(
            'urls' => array(
                'www' => 'http://www.differentexample.com'
            ),
            'values' => array(6,7),
            'new_array' => array(1,2,3)
        );

        $combined = Util::extendArray($array1, $array2);

        $this->isEqual($combined, array(
            'key1' => 12,
            'key2' => 'second key',
            'urls' => array(
                'www' => 'http://www.differentexample.com',
                'static' => 'http://static.example.com',
                'test' => 'http://test.example.com'
            ),
            'values' => array(1,2,3,4,5,6,7),
            'new_array' => array(1,2,3)
        ));

        // combine them but do not inherit key1
        $combined = Util::extendArray($array1, $array2, array('key1'));

        $this->isEqual($combined, array(
            'key2' => 'second key',
            'urls' => array(
                'www' => 'http://www.differentexample.com',
                'static' => 'http://static.example.com',
                'test' => 'http://test.example.com'
            ),
            'values' => array(1,2,3,4,5,6,7),
            'new_array' => array(1,2,3)
        ));
    }

    public function testInString()
    {
        $stars = array('rihanna', 'drake', 'kanye', 'jayz');

        $string = 'jayz will be performing';
        $star = Util::inString($stars, $string);
        $this->isTrue($star);

        $string = '50 cent is in a movie';
        $star = Util::inString($stars, $string);
        $this->isFalse($star);

        $string = 'kanye west features rihanna';
        $star = Util::inString($stars, $string);
        $this->isTrue($star);
    }

    public function testCopy()
    {
        $app = Sonic\App::getInstance();
        $src = $app->getPath('../../lib/Sonic');
        $dest = $app->getPath('libs/SonicCopy');

        // copy sonic lib to lib path
        $result = Util::copy($src, $dest);
        $this->isTrue($result);

        // already exists returns false
        $result = Util::copy($src, $dest);
        $this->isFalse($result);

        // force returns true
        $force = true;
        $result = Util::copy($src, $dest, $force);
        $this->isTrue($result);

        // check that some files are there
        $this->isTrue(is_dir($dest));
        $this->isTrue(file_exists($dest . '/App.php'));
        $this->isTrue(file_exists($dest . '/Extension/Manager.php'));
    }

    public function testCopyException()
    {
        $app = Sonic\App::getInstance();
        $this->isException('Sonic\Exception');
        $src = $app->getPath('lib/ThingThatDoesNotExist');
        $dest = $app->getPath('tests/app/lib/ThingThatDoesNotExist');
        Util::copy($src, $dest);
    }

    public function testCopyFile()
    {
        $app = Sonic\App::getInstance();
        $src = $app->getPath('/random.txt');
        file_put_contents($src, 'nice');
        $dest = $app->getPath('/random2.txt');

        $result = Util::copy($src, $dest);
        $this->isTrue($result);

        $result = Util::copy($src, $dest);
        $this->isFalse($result);

        $force = true;
        $result = Util::copy($src, $dest, $force);
        $this->isTrue($result);

        $this->isTrue(file_exists($dest));
        $this->isEqual('nice', file_get_contents($dest));

        unlink($src);
        unlink($dest);
    }

    public function testRemoveDir()
    {
        $app = Sonic\App::getInstance();
        $dest = $app->getPath('libs/SonicCopy');
        $result = Util::removeDir($dest);
        $this->isTrue($result);
        $this->isFalse(file_exists($dest));
        $this->isFalse(is_dir($dest));
    }

    public function testRemoveSymlinkDir()
    {
        $app = Sonic\App::getInstance();
        $src = $app->getPath('../../lib/Sonic');
        $dest = $app->getPath('libs/SonicTest');
        symlink($src, $dest);

        $result = Util::removeDir($dest);
        $this->isTrue($result);
        $this->isFalse(file_exists($dest));
        $this->isFalse(is_dir($dest));
        $this->isTrue(file_exists($src));
    }

    public function testRemoveDirWithSymlink()
    {
        $app = Sonic\App::getInstance();
        $src = $app->getPath('../../lib/Sonic');
        $mkdir = $app->getPath('libs/what');
        mkdir($mkdir);
        $dest = $mkdir . '/Sonic';
        symlink($src, $dest);

        $result = Util::removeDir($mkdir);
        $this->isTrue($result);
        $this->isFalse(file_exists($mkdir));
        $this->isFalse(is_dir($mkdir));
        $this->isTrue(file_exists($src));
    }

    public function testMatchPermissions()
    {
        $app = Sonic\App::getInstance();

        $file1 = $app->getPath('/test.txt');
        $file2 = $app->getPath('/test2.txt');

        file_put_contents($file1, 'test');
        file_put_contents($file2, 'test');
        chmod($file1, 0664);

        $this->isNotEqual(fileperms($file1), fileperms($file2));
        Util::matchPermissions($file1, $file2);
        $this->isEqual(fileperms($file1), fileperms($file2));

        unlink($file1);
        unlink($file2);
    }

    public function testExplodeAtMatch()
    {
        $delims = array(', ', ',', ' and ');

        $string = 'this,that,the other thing';
        $bits1 = Util::explodeAtMatch($delims, $string);

        $string = 'this, that, the other thing';
        $bits2 = Util::explodeAtMatch($delims, $string);
        $this->isEqual($bits2, $bits1);

        $string = 'this and that and the other thing';
        $bits3 = Util::explodeAtMatch($delims, $string);
        $this->isEqual($bits3, $bits2);

        $string = 'this that the other thing';
        $bits4 = Util::explodeAtMatch($delims, $string);
        $this->isEqual(array($string), $bits4);
    }

    public function testToSeconds()
    {
        $time = time();
        $this->isEqual($time, Util::toSeconds($time));

        $time = '1 second';
        $this->isEqual(1, Util::toSeconds($time));

        $time = '10 seconds';
        $this->isEqual(10, Util::toSeconds($time));

        $time = '1 minute';
        $this->isEqual(60, Util::toSeconds($time));

        $time = '60 minutes';
        $this->isEqual(3600, Util::toSeconds($time));

        $time = '1 hour';
        $this->isEqual(3600, Util::toSeconds($time));

        $time = '2 hours';
        $this->isEqual(7200, Util::toSeconds($time));

        $time = '1 day';
        $this->isEqual(86400, Util::toSeconds($time));

        $time = '7 days';
        $this->isEqual(604800, Util::toSeconds($time));

        $time = '1 week';
        $this->isEqual(604800, Util::toSeconds($time));

        $time = '5 weeks';
        $this->isEqual(3024000, Util::toSeconds($time));

        $time = '1 year';
        $this->isEqual(31556926, Util::toSeconds($time));

        $time = '99 years';
        $this->isEqual(3124135673, Util::toSeconds($time));

        $this->isException('Sonic\Exception');
        $time = 'what';
        Util::toSeconds($time);
    }
}
