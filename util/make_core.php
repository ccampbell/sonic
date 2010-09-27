#!/usr/bin/php
<?php
/**
 * generates a lib/Sonic/Core.php that includes all the Core classes to make your app faster
 *
 * @author Craig Campbell
 */
$lib_path = str_replace('/util/make_core.php', '/lib/Sonic', realpath(__FILE__));

// files to combine
$combine = array('App.php',
                 'Request.php',
                 'Router.php',
                 'Controller.php',
                 'View.php',
                 'Layout.php',
                 'Exception.php');

/**
 * gets path to lib file
 *
 * @param string $lib_path path to lib
 * @param string $file file name
 * @return string
 */
function getPath($lib_path, $file)
{
    return $lib_path . '/' . $file;
}

// remove existing core file
if (file_exists($lib_path . '/Core.php')) {
    unlink($lib_path . '/Core.php');
}

$minimize = in_array('--minimize', $_SERVER['argv']);

// figure out the last revision
shell_exec('cd ' . $lib_path);
$revision = shell_exec('git log | head -1');
$revision_text = 'last commit: ' . str_replace(array("\n", 'commit '), '', $revision);

// start output
$output = "<?php\n/**\n * combined core files to speed up your application (with comments stripped)\n *\n * includes " . implode(', ', $combine) . "\n *\n * @author Craig Campbell\n *\n * " . $revision_text . "\n * generated: " . date('Y-m-d H:i:s') . " EST\n */\nnamespace Sonic;\n";

foreach ($combine as $file) {
    echo 'adding file ' . $file . "\n";
    $path = getPath($lib_path, $file);
    $contents = file_get_contents($path);
    $contents = str_replace('<?php' . "\n", '', $contents);
    $contents = str_replace('namespace Sonic;' . "\n", '', $contents);

    if ($file == 'App.php') {
        $contents = preg_replace('/include \'(.*)/', '', $contents);
    }

    $contents = str_replace(' =', '=', $contents);
    $contents = str_replace('= ', '=', $contents);
    $contents = str_replace(', ', ',', $contents);
    $contents = str_replace(' && ', '&&', $contents);
    $contents = str_replace(' || ', '||', $contents);
    $contents = str_replace(' !=', '!=', $contents);
    $contents = str_replace(' . ', '.', $contents);
    $contents = str_replace('=> ', '=>', $contents);
    $contents = preg_replace('!/\*.*?\*/!s', '', $contents);
    $contents = preg_replace('/\/\/(.*)/', '', $contents);
    $contents = preg_replace('/\n\s*\n/', "\n", $contents);

    if ($minimize) {
        $contents = preg_replace('/\s+/', ' ', $contents);
        $contents = str_replace(' } ', '}', $contents);
        $contents = str_replace(' { ', '{', $contents);
        $contents = str_replace('; ', ';', $contents);
        $contents = str_replace(' class', 'class', $contents);
        $contents = str_replace('abstractclass', 'abstract class', $contents);
        $contents = str_replace('finalclass', 'final class', $contents);
        $contents = str_replace('divclass', 'div class', $contents);
        $contents = str_replace('if (', 'if(', $contents);
    }

    $output .= $contents;
}

if ($minimize) {
    $output .= "\n";
}

file_put_contents($lib_path . '/Core.php', $output);

echo 'done' . "\n";
exit(1);
