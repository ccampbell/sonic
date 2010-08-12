<?php
namespace Sonic\Database;
use Sonic\Database;
use Sonic\Database\Sync\Dao;
use Sonic\App;
use Sonic\Object\DefinitionFactory;

/**
 * Sync class for syncing object definitions to the database
 *
 * @package Object
 * @subpackage Sync
 * @todo add mode for outputting sql that would run but not actually executing it
 * @author Craig Campbell
 */
class Sync
{
    /**
     * @var bool
     */
    protected static $_verbose = false;

    /**
     * @var bool
     */
    protected static $_dry_run = false;

    /**
     * @var int
     */
    protected static $_query_count = 0;

    /**
     * enables verbose mode
     *
     * @return void
     */
    public static function verbose()
    {
        self::$_verbose = true;
    }

    /**
     * enables verbose mode
     *
     * @return void
     */
    public static function isVerbose()
    {
        return self::$_verbose;
    }

    /**
     * enables dry run
     *
     * @return void
     */
    public static function dryRun()
    {
        self::$_dry_run = true;
    }

    /**
     * enables dry run
     *
     * @return void
     */
    public static function isDryRun()
    {
        return self::$_dry_run;
    }

    /**
     * runs the database sync magics
     */
    public static function run()
    {
        $definitions = DefinitionFactory::getDefinitions();

        if (empty($definitions)) {
            self::output('nothing to process');
            exit;
        }

        // use schema of first object to get existing indexes
        foreach ($definitions as $definition) {
            $schema = $definition['schema'];
            break;
        }

        $dao = new Dao($schema);
        $indexes = $dao->getIndexes();
        foreach ($indexes as $table => $existing_indexes) {

            // if this table exists in the database but is not associated
            // with an object then we should skip it
            if (!$definition = self::getDefinitionForTable($table)) {
                continue;
            }

            // figure out what indexes have been removed or changed
            $to_drop = self::_getIndexesToDrop($definition, $existing_indexes);

            // drop them
            $dao->dropIndexes($table, $to_drop);
        }

        // sync each object from the definition
        $definitions = self::resolveDependencies($definitions);

        foreach ($definitions as $definition) {
            self::syncObject($definition);
        }

        if (self::isDryRun()) {
            self::output("\n");
        }
        self::output('database sync complete');
    }

    /**
     * takes a list of definitions, resolves foreign key dependencies, and returns the new list
     *
     * @param array
     * @return array
     */
    public static function resolveDependencies($definitions)
    {
        // create a dependency array of what tables depend on other tables
        $dependencies = array();
        foreach ($definitions as $definition) {
            $dependencies[$definition['table']] = array();
            foreach ($definition['columns'] as $column) {
                if (!isset($column['foreign_key'])) {
                    continue;
                }
                $bits = explode(':', $column['foreign_key']);
                $dependencies[$definition['table']][] = $bits[0];
            }
        }

        $table_order = \Sonic\Util::resolveDependencies($dependencies);

        $new_order = array();
        foreach ($table_order as $table) {
            $new_order[] = self::getDefinitionForTable($table);
        }

        return $new_order;
    }

    /**
     * tries to find a definition matching the table name
     *
     * @param string $table
     * @return mixed
     */
    public static function getDefinitionForTable($table)
    {
        $definitions = DefinitionFactory::getDefinitions();
        foreach ($definitions as $definition) {
            if ($definition['table'] == $table) {
                return $definition;
                break;
            }
        }
        return null;
    }

    /**
     * figures out what indexes to drop based on existing indexes and definition
     *
     * @param array $definition
     * @param array $existing_indexes in format column_name:index_name:is_unique
     * @return array
     */
    protected static function _getIndexesToDrop($definition, array $existing_indexes)
    {
        $drop = array();
        foreach ($existing_indexes as $index) {
            $bits = explode(':', $index);
            $unique = (bool) $bits[2];
            $column = $bits[0];

            // field has been removed
            if (!isset($definition['columns'][$column])) {
                $drop[] = $bits[1];
                continue;
            }

            $column_definition = $definition['columns'][$column];
            $column_definition = self::normalizeColumn($column_definition);

            // if this field no longer has an index associated with it then we
            // should drop the index
            if (!$column_definition['indexed'] && !isset($column_definition['foreign_key'])) {
                $drop[] = $bits[1];
                continue;
            }

            // foreign keys don't check unique status for the current column
            if (isset($column_definition['foreign_key'])) {
                continue;
            }

            // if there is no foreign key set in the definition but there is a foreign key in the database then delete it!
            if (substr($bits[1], -3) == '_fk') {
                $drop[] = $bits[1];
                continue;
            }

            // if the index has changed from unique to non-unique or vice versa
            // then we should drop the index to allow it to be recreated
            if ($column_definition['unique'] != $unique) {
                $drop[] = $bits[1];
            }
        }
        return $drop;
    }

    /**
     * syncs a specific object
     *
     * @param array $definition
     * @return void
     */
    public static function syncObject($definition)
    {
        $dao = new Dao($definition['schema']);
        try {
            $dao->updateTableByDefinition($definition);
        } catch (Yoshi_Exception $e) {
            throw new \Sonic\Database\Exception('error syncing ' .
                $definition['table'] . ': ' . $e->getMessage());
        }
    }

    /**
     * gets create table syntax for a definition
     *
     * @param array
     * @return string
     */
    public static function getCreateTable($definition)
    {
        $sql = 'CREATE TABLE `' . $definition['table'] . '` (id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            CONSTRAINT ' . $definition['table'] . '_pk PRIMARY KEY (id)';

        foreach ($definition['columns'] as $column => $column_definition) {
            $column_definition = self::normalizeColumn($column_definition);
            $sql .= ",\n" . self::getCreateField($column, $column_definition, $definition['table']);
        }

        $sql .= ') ENGINE=INNODB;';

        return $sql;
    }

    /**
     * sets default values for a column
     *
     * @param array
     * @return array
     */
    public static function normalizeColumn($column)
    {
        // default to not allow null
        if (!isset($column['null'])) {
            $column['null'] = false;
        }

        // if no default is present set it to null
        // @todo not sure if this is necessary
        if (!isset($column['default'])) {
            $column['default'] = null;
        }

        // default to no index
        if (!isset($column['indexed'])) {
            $column['indexed'] = false;
        }

        // default to not unique
        if (!isset($column['unique'])) {
            $column['unique'] = false;
        }

        // default to unsigned fields
        if (self::isInteger($column) && !isset($column['unsigned'])) {
            $column['unsigned'] = true;
        }

        // if this field requires a length but doesn't have one
        if ($column['type'] == Database::VARCHAR && !isset($column['length'])) {
            throw new Database\Exception('need to set a length for a varchar field!');
        }

        return $column;
    }

    /**
     * checks if a column is an integer
     *
     * @param array $column
     * @return bool
     */
    public static function isInteger($column)
    {
        return in_array($column['type'], array(Database::INT, Database::TINY_INT));
    }

    /**
     * gets a single field definition
     *
     * @todo implement foreign key support
     * @param string $column name of column to create sql for
     * @param array $definition data about that column
     * @param string $table table name - if not present then we set the field information but not indexes or foreign keys
     */
    public static function getCreateField($column, $definition, $table = null)
    {
        $sql = '`' . $column . '` ' . strtoupper($definition['type']);

        if ($definition['type'] == Database::ENUM) {
            $sql .= '(\'' . implode('\',\'', $definition['options']) . '\')';
        }

        if (isset($definition['length'])) {
            $sql .= '(' . $definition['length'] . ')';
        }

        if (self::isInteger($definition) && $definition['unsigned']) {
            $sql .= ' UNSIGNED';
        }

        if ($definition['null'] == false) {
            $sql .= ' NOT NULL';
        }

        if (isset($definition['default'])) {
            $sql .= ' DEFAULT "' . $definition['default'] . '"';
        }

        if ($table === null) {
            return $sql;
        }

        // foreign key stuff later (code from yoshi)
        if (isset($definition['foreign_key'])) {
            $foreign_key = self::getForeignKeyInfo($table, $column, $definition);

            $sql .= ', CONSTRAINT ' . $foreign_key['name'] . ' FOREIGN KEY (`' . $column .
                '`) REFERENCES `' . $foreign_key['table'] . '`' .
                ' (' . $foreign_key['column'] . ') ON DELETE CASCADE';
        }

        // only add this stuff if no table is passed in (updating a column but not indexes)
        $index_name = $table . '_' . $column . '_idx';
        if ($definition['indexed'] && $definition['unique']) {
            $sql .= ', UNIQUE INDEX ' . $index_name . ' (`' . $column . '`)';
            return $sql;
        }

        if ($definition['indexed']) {
            $sql .= ', INDEX ' . $index_name . ' (' . $column . ')';
        }

        return $sql;
    }

    /**
     * gets foreign key information
     *
     * @param string $table
     * @param string $column
     * @param array $definition
     */
    public static function getForeignKeyInfo($table, $column, $definition)
    {
        $foreign_key = array();

        $bits = explode(':', $definition['foreign_key']);
        $foreign_key['table'] = $bits[0];
        $foreign_key['column'] = isset($bits[1]) ? $bits[1] : 'id';
        $foreign_key['name'] = $table . '_' . $column . '_' . $foreign_key['table'] . '_' . $foreign_key['column'] . '_fk';

        return $foreign_key;
    }

    /**
     * called from the dao to handle executing or displaying SQL based on argument
     *
     * @param Query $query
     * @return void
     */
    public static function execute(Database\Query $query)
    {
        ++self::$_query_count;
        if (self::isDryRun()) {
            self::outputQuery($query);
            return;
        }
        self::executeQuery($query);
    }

    /**
     * outputs the sql to command line
     *
     * @param Query $query
     * @return void
     */
    public static function outputQuery(Database\Query $query)
    {
        $sql = $query->getSql();

        self::output("\n" . $sql, false, true);
    }

    /**
     * helper function to execute a query
     *
     * @param Query $query
     * @return bool
     */
    public static function executeQuery(Database\Query $query)
    {
        return $query->execute();
    }

    /**
     * outputs stuff to command line
     *
     * @param string $string
     * @return void
     */
    public static function output($string, $verbose_only = false, $query = false)
    {
        if (App::getInstance()->getSetting('mode') != App::COMMAND_LINE) {
            return;
        }

        if ($verbose_only && !self::isVerbose()) {
            return;
        }

        if ($string == "\n") {
            echo "\n";
            return;
        }

        if (self::isDryRun() && !$query) {
            $string = '# ' . $string;

            // just to be safe any multiline outputs should also receive comments
            $string = str_replace("\n", "\n# ", $string);
        }

        echo $string . "\n";
    }
}
