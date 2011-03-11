<?php
namespace Sonic;

/**
 * Util
 *
 * @category Sonic
 * @package Util
 * @author Craig Campbell
 */
class Util
{
    /**
     * gets a weighted random key from an array of weights
     *
     * for example if you pass in
     * array(0 => 10, 1 => 10, 2 => 20)
     *
     * half the time this would return 2
     * a quarter of the time it would return 0
     * a quarter of the time it would return 1
     *
     * @todo support any array keys and not numbers starting at 0
     * @param array $weights
     * @return mixed
     */
    public static function getWeightedRandomKey(array $weights)
    {
        // get the total
        $total_weight = array_sum($weights);

        // choose a random value between 0 and one less than the total
        $random_value = mt_rand(0, $total_weight - 1);

        // order the weights in order
        asort($weights);

        // subtract the weights in descending order until the number is now negative
        $i = count($weights);
        while ($random_value >= 0) {
            $random_value -= $weights[--$i];
        }

        return $i;
    }

    /**
     * resolves complicated dependencies to determine what order something can run in
     *
     * start with an array like:
     * array(
     *     'a' => array('b', 'c'),
     *     'b' => array(),
     *     'c' => array('b')
     * )
     *
     * a depends on b and c, c depends on b, and b depends on nobody
     * in this case we would return array('b', 'c', 'a')
     *
     * @param array $data
     * @return array
     */
    public static function resolveDependencies(array $data)
    {
        $new_data = array();
        $original_count = count($data);
        while (count($new_data) < $original_count) {
            foreach ($data as $name => $dependencies) {
                if (!count($dependencies)) {
                    $new_data[] = $name;
                    unset($data[$name]);
                    continue;
                }

                foreach ($dependencies as $key => $dependency) {
                    if (in_array($dependency, $new_data)) {
                        unset($data[$name][$key]);
                    }
                }
            }
        }
        return $new_data;
    }

    /**
     * helper function to extend an array
     *
     * this is similar to array_merge except that values in the first array
     * are never deleted just added or updated
     *
     * @param array $array1
     * @param array $array2
     * @param array $keys array of keys to not inherit
     * @return array
     */
    public static function extendArray($array1, $array2, $keys_to_skip = array())
    {
        $skipped = array();
        foreach ($array2 as $key => $value) {

            // if the key is an integer that means it is a straight up
            // array so we should just append it to the first array instead
            // of overwriting the key
            if (is_int($key) && isset($array1[$key])) {
                $array1[] = $value;
                continue;
            }

            // if this is a straight up value or a key that
            // should not be inherited then overwrite it
            if (!is_array($value) || in_array($key, $keys_to_skip)) {
                $skipped[] = $key;
                $array1[$key] = $value;
                continue;
            }

            // if it is an array that doesn't exist in the first array
            if (!isset($array1[$key])) {
                $array1[$key] = $value;
                continue;
            }

            // an array in both should come back around through this method
            $array1[$key] = self::extendArray($array1[$key], $value);
        }

        // anything in the first array that should not be inherited but wasn't
        // present in the second array needs to be removed
        $diff = array_diff($keys_to_skip, $skipped);
        foreach ($diff as $key_to_unset) {
            unset($array1[$key_to_unset]);
        }

        return $array1;
    }

    /**
     * takes an array of strings and checks if any of them are in another string
     *
     * @param array $needles
     * @param string $haystack
     * @return (string || false) first delimiter that matches on success
     *         false on failure
     */
    public static function inString(array $needles, $haystack)
    {
        foreach ($needles as $needle) {
            if (strpos($haystack, $needle) !== false) {
                return $needle;
            }
        }
        return false;
    }

    /**
     * takes a string and an array of delimiters and explodes at the first
     * delimiter that is found in the string
     *
     * @param array $delimiters
     * @param string $string
     * @return array
     */
    public static function explodeAtMatch(array $delimiters, $string)
    {
        $delimiter = self::inString($delimiters, $string);

        if (!$delimiter) {
            return array($string);
        }

        return explode($delimiter, $string);
    }

    /**
     * deletes a directory recursively
     *
     * php's native rmdir() function only removes a directory if there is nothing in it
     *
     * @param string $path
     * @return void
     */
    public static function removeDir($path)
    {
        $files = new \RecursiveDirectoryIterator($path);
        foreach ($files as $file) {
            if (in_array($file->getFilename(), array('.', '..'))) {
                continue;
            }

            if ($file->isLink()) {
                unlink($file->getPathName());
                continue;
            }

            if ($file->isFile()) {
                unlink($file->getRealPath());
                continue;
            }

            if ($file->isDir()) {
                self::removeDir($file->getRealPath());
            }
        }
        rmdir($path);
    }

    /**
     * copy a file or directory recursively
     *
     * php's copy() function only copies a single file
     *
     * @param string $src
     * @param string $dest
     * @param bool $force should we overwrite the dest if it already exists
     * @return void
     */
    public static function copy($src, $dest, $force = false)
    {
        // no file found
        if (!file_exists($src)) {
            throw new Exception('src file not found at path: ' . $src);
        }

        // the src is a single file and not a directory
        if (is_file($src)) {
            return self::_copyFile($src, $dest, $force);
        }

        // if the destination already exists and we are not using force
        if (is_dir($dest) && !$force) {
            return;
        }

        // if the destination directory already exists remove it
        if (is_dir($dest)) {
            self::removeDir($dest);
        }

        mkdir($dest);
        self::matchPermissions($src, $dest);

        $files = new \RecursiveDirectoryIterator($src);
        foreach ($files as $file) {
            self::copy($src . DIRECTORY_SEPARATOR . $file->getFilename(), $dest . DIRECTORY_SEPARATOR . $file->getFilename(), $force);
        }
    }

    /**
     * copies a file from one location to another
     *
     * @param string $src
     * @param string $dest
     * @param bool $force should we overwrite the file if it already exists
     * @return void
     */
    protected static function _copyFile($src, $dest, $force = false)
    {
        if (file_exists($dest) && !$force) {
            return;
        }

        if (file_exists($dest)) {
            unlink($dest);
        }

        copy($src, $dest);
        self::matchPermissions($src, $dest);
    }

    /**
     * matches permissions of two files
     *
     * @param string $src
     * @param string $dest
     * @return bool
     */
    public static function matchPermissions($src, $dest)
    {
        $perms = fileperms($src);
        if (fileperms($dest) != $perms) {
            return chmod($dest, $perms);
        }

        return false;
    }

    /**
     * maps english representation of time to seconds
     *
     * @param string
     * @return int
     */
     public static function toSeconds($time)
     {
         // time is already in seconds
         if (is_numeric($time)) {
             return (int) $time;
         }

         $bits = explode(' ', $time);

         if (!isset($bits[1])) {
             throw new Exception('time ' . $time . ' is not in proper format!');
         }

         $time = $bits[0];
         $unit = $bits[1];

         switch ($unit) {
             case 'second':
             case 'seconds':
                return $time;
                break;
             case 'minute':
             case 'minutes':
                return $time * 60;
                break;
             case 'hour':
             case 'hours':
                return $time * 60 * 60;
                break;
            case 'day':
            case 'days':
                return $time * 60 * 60 * 24;
                break;
            case 'week':
            case 'weeks':
                return $time * 60 * 60 * 24 * 7;
                break;
            case 'year':
            case 'years':
                return round($time * 60 * 60 * 24 * 365.242199);
                break;
         }
     }
}
