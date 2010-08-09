<?php
namespace Sonic\Object;
use Sonic\Database;
use Sonic\Object\Sync\Dao;

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
     * runs the database sync magics
     */
    public static function run()
    {
        $database = DefinitionFactory::getDatabase();

        // use schema of first object
        $definitions = DefinitionFactory::getDefinitions();

        if (empty($definitions)) {
            self::output('nothing to process');
            exit;
        }

        foreach ($definitions as $definition) {
            $schema = $definition['schema'];
            break;
        }

        $dao = new Dao($schema);
        $indexes = $dao->getIndexes();
        foreach ($indexes as $table => $existing_indexes) {
            if (!$definition = self::getDefinitionForTable($table)) {
                continue;
            }

            $to_drop = self::_getIndexesToDrop($definition, $existing_indexes);

            $dao->dropIndexes($table, $to_drop);
        }

        foreach ($definitions as $definition) {
            self::syncObject($definition, $database);
            // var_dump(self::getCreateTable($definition));
        }

        self::output('database sync complete');
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
     * @param array $existing_indexes
     * @return array
     */
    protected static function _getIndexesToDrop($definition, array $existing_indexes)
    {
        $drop = array();
        foreach ($existing_indexes as $index) {
            $bits = explode(':', $index);
            $unique = (bool) $bits[2];
            $column = $bits[0];

            $column_definition = $definition['columns'][$column];
            $column_definition = self::normalizeColumn($column_definition);

            // if this field no longer has an index associated with it then we
            // should drop the index
            if (!$column_definition['indexed']) {
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
    public static function syncObject($definition, $database)
    {
        $dao = new Dao($definition['schema']);
        try {
            $dao->updateTableByDefinition($definition, $database);
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
        // if ($definition->hasForeignKey() && !is_null($table_name)) {
        //     $model = $definition->getForeignKeyModel();
        //     $foreign_key = $table_name . '_' . $field_name . '_' . $model->getDefinitions()->getTable() . '_fk';
        //     $sql .= ', CONSTRAINT ' . $foreign_key . ' FOREIGN KEY (`' . $field_name .
        //         '`) REFERENCES ' . $model->getDefinitions()->getTable() .
        //         ' (id) ON DELETE CASCADE';
        // }

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
     * outputs stuff to command line
     *
     * @todo only output from command line
     * @todo support verbose mode only
     * @param string $string
     * @return void
     */
    public static function output($string, $verbose_only = false)
    {
        echo $string . "\n";
    }
}
