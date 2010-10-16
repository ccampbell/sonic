<?php
namespace Sonic\Database;
use Sonic\Database\MySqli\Statement;

/**
 * MySql2Pdo class
 *
 * @package Database
 * @subpackage MySql2Pdo
 * @author Craig Campbell
 */
class MySqli extends MySql
{
    /**
     * prepares a query
     *
     * @param string $sql
     * @return Statement
     */
    public function prepare($sql)
    {
        if ($this->_link === null) {
            $this->_link = mysqli_connect($this->_host, $this->_user, $this->_password);

            if (!$this->_link) {
                throw new Exception(mysqli_error($this->_link));
            }

            mysqli_select_db($this->_link, $this->_dbname);
        }
        return new Statement($sql, $this->_link);
    }

    /**
     * returns the insert id of the last insert
     *
     * @return int
     */
    public function lastInsertId()
    {
        return mysqli_insert_id($this->_link);
    }
}
