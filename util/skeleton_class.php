#!/usr/bin/env php
<?php
/**
 * creates a skeleton class from your object definition
 *
 * @author Craig Campbell
 */
use \Sonic\App;
use \Sonic\Object\DefinitionFactory;
$lib_path = str_replace('/util/skeleton_class.php', '/libs', realpath(__FILE__));

set_include_path($lib_path);

include 'Sonic/App.php';
$app = App::getInstance();
$app->start(App::COMMAND_LINE);

if (!isset($_SERVER['argv'][1])) {
    echo 'need to pass in a table or class as an argument!' . "\n";
    exit;
}

$table = $_SERVER['argv'][1];

$definitions = DefinitionFactory::getDefinitions();

// class name
if (isset($definitions[$table])) {
    $definition = $definitions[$table];
    outputClassFromDefinition($definition, $table);
    exit;
}

foreach ($definitions as $key => $definition) {
    if ($definition['table'] == $table) {
        outputClassFromDefinition($definition, $key);
        exit;
    }
}
echo 'no definition found for table: ' . $table . "\n";
exit;

function outputClassFromDefinition($definition, $class)
{
    $bits = explode('\\', $class);
    $class = array_pop($bits);
    $namespace = implode('\\', $bits);
    output('<?php');
    output('namespace ' . $namespace . ';');
    output('use Sonic\\Object;');
    output('');
    output('/*');
    output(' * generated from util/skeleton_class.php');
    output(' *');
    output(' * @version ' . gmdate('Y-m-d H:i:s') . ' GMT');
    output(' */');
    output('class ' . $class . ' extends Object');
    output('{');

    foreach ($definition['columns'] as $property => $column) {
        $string = 'protected $' . $property;

        // if (isset($column['default'])) {
            // $is_string = is_string($column['default']);
            // $string .= ' = ' . ($is_string ? '\'' : '') . $column['default'] . ($is_string ? '\'' : '');
        // }
        output('    ' . $string . ';');
    }
    output('}');
    output('');
}

function output($string)
{
    echo $string . "\n";
}
