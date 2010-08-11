<?php
namespace Sonic\Object\Sync;
use Sonic\Database\Factory, Sonic\Object\DefinitionFactory, Sonic\Object\Sync, Sonic\Database\Query;

/**
 * Sync Dao
 *
 * @package Sync
 * @subpackage Dao
 * @author Craig Campbell
 */
class Dao
{
    /**
     * @param array
     */
    protected static $_queries = array();

    /**
     * @var string
     */
    protected $_name;

    /**
     * @var string
     */
    protected $_schema;

    /**
     * constructor
     *
     * @param string $schema
     * @return void
     */
    public function __construct($schema)
    {
        $this->_schema = $schema;
    }

    /**
     * gets the name of the database
     *
     * @return string
     */
    public function getDatabaseName()
    {
        if ($this->_name === null) {
            $database = Factory::getDatabase($this->_schema);
            $this->_name = $database->getName();
        }
        return $this->_name;
    }

    /**
     * takes definitions for a table and updates the table accordingly
     *
     * @param array $definition
     * @return void
     */
    public function updateTableByDefinition($definition)
    {
        $table = $definition['table'];

        // if the table doesn't exist we need to create it
        if (!$this->tableExists($table)) {
            $this->createTable($definition);
            return;
        }

        // if the table does exist then go through each column and update them
        $columns = array();
        foreach ($definition['columns'] as $column_name => $column_definition) {
            $column_name = $column_name;
            $columns[] = $column_name;
            $this->updateColumn($table, $column_name, $column_definition);
        }

        // find if there are any columns that are in the database that
        // shouldn't be here anymore and delete them
        $table_columns = $this->getColumnsByTable($table);
        $columns_to_delete = array_diff($table_columns, $columns);

        foreach ($columns_to_delete as $column_name) {
            $this->deleteColumn($column_name, $table);
        }
    }

    /**
     * checks if a table exists
     *
     * @param string $table
     * @return bool
     */
    public function tableExists($table)
    {
        $sql = '/* ' . __METHOD__ . ' */' .
            "SELECT count(*) num_tables
               FROM information_schema.tables
              WHERE table_schema = :table_schema
                AND table_name = :table_name";

        $query = new Query($sql);
        $query->bindValue(':table_schema', $this->getDatabaseName());
        $query->bindValue(':table_name', $table);

        $row = $query->fetchRow();

        Sync::output('syncing table "' . $table . '"');

        return $row['num_tables'] == 1;
    }

    /**
     * creates a table based on a definition
     *
     * @param array $definition
     * @return void
     */
    public function createTable($definition)
    {
        $sql = '/* ' . __METHOD__ . ' */' . "\n" .
            Sync::getCreateTable($definition);

        $query = new Query($sql);

        Sync::output('creating table "' . $definition['table'] . '"');

        return Sync::execute($query);
    }

    /**
     * adds or updates a specific column in a table based on column definition
     *
     * @param string $table
     * @param string $column_name
     * @param array $definition
     * @return void
     */
    public function updateColumn($table, $column_name, array $definition)
    {
        $definition = Sync::normalizeColumn($definition);
        $columns = $this->getColumnsByTable($table);
        if (!in_array($column_name, $columns)) {
            $this->addColumn($table, $column_name, $definition);
            $this->addIndexForColumn($table, $column_name, $definition);
            $this->addForeignKeyForColumn($table, $column_name, $definition);
            return;
        }

        $this->alterColumn($table, $column_name, $definition);
        $this->addIndexForColumn($table, $column_name, $definition);
        $this->addForeignKeyForColumn($table, $column_name, $definition);
    }

    /**
     * gets all current columns for a specific table
     *
     * @param string $table
     * @return array
     */
    public function getColumnsByTable($table)
    {
        $cache_key = __METHOD__ . '_' . $table;

        if (isset(self::$_queries[$cache_key])) {
            return self::$_queries[$cache_key];
        }

        $sql = '/* ' . __METHOD__ . ' */' .
            "SELECT column_name
               FROM information_schema.columns
              WHERE table_schema = :table_schema
                AND table_name = :table_name";

        $query = new Query($sql);
        $query->bindValue(':table_schema', $this->getDatabaseName());
        $query->bindValue(':table_name', $table);
        $columns = $query->fetchAll();

        self::$_queries[$cache_key] = $columns;

        return $columns;
    }

    /**
     * alters a column for a specific table
     *
     * @param string $table
     * @param string $column_name
     * @param array $definition
     * @return void
     */
    public function alterColumn($table, $column_name, array $definition)
    {
        if ($this->_columnHasNotChanged($table, $column_name, $definition)) {
            return;
        }

        $sql = '/* ' . __METHOD__ . ' */' . "\n" .
               "ALTER TABLE `$table` CHANGE COLUMN `$column_name` " .
              Sync::getCreateField($column_name, $definition);

        $query = new Query($sql);

        Sync::output('altering column "' . $column_name . '" in table "' . $table . '"', true);

        return Sync::execute($query);
    }

    /**
     * determines if a column has changed
     *
     * @param string $table name of table
     * @param string $column_name name of column
     * @param array $definition definition of column
     * @return bool
     */
    protected function _columnHasChanged($table, $column_name, $definition)
    {
        $db_definition = $this->_getMysqlDefinition($column_name, $table);

        $db_definition['is_nullable'] = $db_definition['is_nullable'] == 'NO' ? false : true;

        if ($db_definition['column_default'] != $definition['default']) {
            return true;
        }

        if ($db_definition['is_nullable'] != $definition['null']) {
            return true;
        }

        $field_type = strtolower($definition['type']);
        if ($db_definition['data_type'] != $field_type) {
            return true;
        }

        if ($db_definition['data_type'] == 'int' && strpos($db_definition['column_type'], 'unsigned') !== false && !$definition['unsigned']) {
            return true;
        }

        if ($db_definition['data_type'] == 'enum') {
            $type = str_replace(array('enum(\'', '\')'), '', $db_definition['column_type']);
            $current_options = explode("','", $type);
            if ($current_options != $definition['options']) {
                return true;
            }
        }

        // remove unsigned word so we can figure out the length
        $data_types_with_length = array('int', 'varchar');
        if (!in_array($db_definition['data_type'], $data_types_with_length)) {
            return false;
        }

        $db_definition['column_type'] = str_replace(' unsigned', '', $db_definition['column_type']);

        $bits = explode('(', $db_definition['column_type']);
        $length = (int) rtrim($bits[1], ')');

        if (isset($definition['length']) && $length != $definition['length']) {
            return true;
        }

        return false;
    }

    /**
     * inverse of _columnHasChanged()
     *
     * @param string $table
     * @param string $column_name
     * @param array $definition
     */
    protected function _columnHasNotChanged($table, $column_name, $definition)
    {
        return !$this->_columnHasChanged($table, $column_name, $definition);
    }

    /**
     * adds an index to a column
     *
     * @param string $table
     * @param string $column_name
     * @param array $definition
     * @return void
     */
    public function addIndexForColumn($table, $column_name, $definition)
    {
        if (!$definition['indexed']) {
            return;
        }

        $indexes = self::getIndexes();

        $index_name = $table . '_' . $column_name . '_idx';

        // if an index already exists for this column don't try to add another
        if (isset($indexes[$table]) && in_array($column_name . ':' . $index_name . ':' . (int) $definition['unique'], $indexes[$table])) {
            return;
        }

        $sql = '/* ' . __METHOD__ . ' */' . "\n";
        $index_creation = "ALTER TABLE `$table` ADD INDEX $index_name (`$column_name`)";

        if ($definition['unique']) {
            $index_creation = "ALTER TABLE `$table` ADD UNIQUE INDEX $index_name (`$column_name`)";
        }

        $sql .= $index_creation;
        $query = new Query($sql);

        $word = '';
        if ($definition['unique']) {
            $word = 'unique ';
        }

        Sync::output('adding ' . $word . 'index "' . $index_name . '" for table "' . $table . '"', true);

        return Sync::execute($query);
    }

    /**
     * adds a foreign key to a column
     *
     * @todo implement this?
     * @param string $table
     * @param string $column_name
     * @param array $definition
     * @return void
     */
    public function addForeignKeyForColumn($table, $column_name, $definition)
    {
        if (!isset($definition['foreign_key'])) {
            return;
        }

        $foreign_key = Sync::getForeignKeyInfo($table, $column_name, $definition);

        $name = $foreign_key['name'];

        $indexes = self::getIndexes();

        // if a foreign key already exists for this column don't try to add another

        // foreign keys default to not being unique?!?!?
        $unique = 0;

        if (in_array($column_name . ':' . $name . ':' . $unique, $indexes[$table])) {
            return;
        }

        $sql = '/* ' . __METHOD__ . ' */' . "\n" .
            "ALTER TABLE `{$table}` ADD CONSTRAINT {$name} FOREIGN KEY ({$column_name}) REFERENCES `{$foreign_key['table']}` ({$foreign_key['column']}) ON DELETE CASCADE";

        $query = new Query($sql);

        Sync::output('creating foreign key "' . $name . '" for table "' . $table . '"', true);

        Sync::execute($query);
    }

    /**
     * deletes a column in a specific table
     *
     * @param string $column_name
     * @param string $table
     * @return void
     */
    public function deleteColumn($column_name, $table)
    {
        if ($column_name == 'id') {
            return;
        }

        $sql = '/* ' . __METHOD__ . ' */' . "\n" .
            "ALTER TABLE `$table` DROP COLUMN `$column_name`";

        $query = new Query($sql);

        Sync::output('deleting column "' . $column_name . '" from table "' . $table . '"');

        return Sync::execute($query);
    }

    /**
     * adds a column for a specific table
     *
     * @param string $table
     * @param string $column_name
     * @param array $definition
     * @return void
     */
    public function addColumn(
        $table,
        $column_name,
        array $definition)
    {
        $sql = '/* ' . __METHOD__ . ' */' . "\n" .
            "ALTER TABLE `$table` ADD COLUMN " .
              Sync::getCreateField($column_name, $definition);

        $query = new Query($sql);

        if (isset(self::$_queries['Sonic\Object\Sync\Dao::getColumnsByTable_' . $table])) {
            self::$_queries['Sonic\Object\Sync\Dao::getColumnsByTable_' . $table][] = $column_name;
        }

        Sync::output('adding column "' . $column_name . '" to table "' . $table . '"');

        return Sync::execute($query);
    }

    /**
     * gets the mysql definition for a specific column to compare against the app definition
     *
     * @param string $column_name
     * @param string $table
     * @return array
     */
    protected function _getMysqlDefinition($column_name, $table)
    {
        $cache_key = __METHOD__ . '_' . $table;

        if ($data = \Sonic\Cache\Instance::get($cache_key)) {
            return $data[$column_name];
        }

        $sql = "SELECT column_name,
                       column_default,
                       is_nullable,
                       data_type,
                       column_type,
                       column_key
                  FROM information_schema.columns
                 WHERE table_schema = :table_schema
                   AND table_name = :table_name";

        $query = new Query($sql);
        $query->bindValue(':table_schema', $this->getDatabaseName());
        $query->bindValue(':table_name', $table);
        $rows = $query->fetchAll();

        $data = array();
        foreach ($rows as $row) {
            $data[$row['column_name']] = $row;
        }

        \Sonic\Cache\Instance::set($cache_key, $data);

        return $data[$column_name];
    }

    /**
     * gets all indexes in the database
     *
     * @return array with stuff like (column:index_name:unique)
     */
    public function getIndexes()
    {
        $cache_key = __METHOD__;

        if ($indexes = \Sonic\Cache\Instance::get($cache_key)) {
            return $indexes;
        }

        $sql = '/* ' . __METHOD__ . ' */' .
            "SELECT table_name,
                    column_name,
                    index_name,
                    non_unique
               FROM information_schema.statistics
              WHERE table_schema = :table_schema
                AND index_name <> :index_name";

        $query = new Query($sql);
        $query->bindValue(':table_schema', $this->getDatabaseName());
        $query->bindValue(':index_name', 'PRIMARY');
        $records = $query->fetchAll();

        $indexes = array();
        foreach ($records as $record) {
            $unique = (int) !$record['non_unique'];
            $indexes[$record['table_name']][] = $record['column_name'] . ':' . $record['index_name'] . ':' . $unique;
        }

        \Sonic\Cache\Instance::set($cache_key, $indexes);

        return $indexes;
    }

    /**
     * drops a bunch of indexes
     *
     * @param string $table
     * @param array $indexes
     */
    public function dropIndexes($table, $indexes) {
        foreach ($indexes as $index) {
            $this->dropIndex($table, $index);
        }
    }

    /**
     * drops an index by name
     *
     * @param string $table
     * @param string $index_name
     */
    public function dropIndex($table, $index_name)
    {
        $this->disableForeignKeys();

        $sql = '/* ' . __METHOD__ . ' */' . "\n" .
            "ALTER TABLE `$table` DROP INDEX `$index_name`";

        if (substr($index_name, -3) == '_fk') {
            $sql = str_replace('DROP INDEX', 'DROP FOREIGN KEY', $sql);
        }

        $query = new Query($sql);

        Sync::execute($query);

        if (substr($index_name, -3) == '_fk') {
            $sql = str_replace('DROP FOREIGN KEY', 'DROP KEY', $sql);
            $query = new Query($sql);
            Sync::execute($query);
        }

        Sync::output('dropping index "' . $index_name . '" from table "' . $table . '"', true);

        $this->enableForeignKeys();
    }

    /**
     * disables foreign key checks
     *
     * @return void
     */
    public function disableForeignKeys()
    {
        $sql = '/* ' . __METHOD__ . ' */' . "\n" .
            "SET FOREIGN_KEY_CHECKS = 0";

        $query = new Query($sql);
        return Sync::execute($query);
    }

    /**
     * enables foreign key checks
     *
     * @return void
     */
    public function enableForeignKeys()
    {
        $sql = '/* ' . __METHOD__ . ' */' . "\n" .
            "SET FOREIGN_KEY_CHECKS = 1";

        $query = new Query($sql);
        return Sync::execute($query);
    }
}
