<?php
namespace Sonic\Database;
use Sonic\Database\MySql2Pdo\Statement;

/**
 * MySql2Pdo class
 *
 * @package Database
 * @subpackage MySql2Pdo
 * @author Craig Campbell
 */
class MySql2Pdo
{
    const FETCH_NUM = 1;
    const FETCH_ASSOC = 2;
    const FETCH_CLASS = 3;
    const ATTR_ERRMODE = 4;
    const ERRMODE_EXCEPTION = 5;
    const ATTR_CASE = 6;
    const CASE_LOWER = 7;
    const ATTR_EMULATE_PREPARES = 8;
    protected $_attributes = array();
    protected $_dsn;
    protected $_host;
    protected $_dbname;
    protected $_user;
    protected $_password;
    protected $_link;

    public function __construct($dsn, $user, $password)
    {
        $this->_dsn = $dsn;
        $this->_user = $user;
        $this->_password = $password;
        $dsn = $this->_parseDsn($dsn);
        $this->_dbname = $dsn['dbname'];
        $this->_host = $dsn['host'];
    }

    public function prepare($sql)
    {
        if ($this->_link === null) {
            $this->_link = mysql_connect($this->_host, $this->_user, $this->_password, true);
            mysql_select_db($this->_dbname, $this->_link);
        }
        return new Statement($sql, $this->_link);
    }

    public function setAttribute($key, $value)
    {
        $this->_attributes[$key] = $value;
    }

    protected function _parseDsn($dsn)
    {
        $bits = explode(':', $dsn);
        $vars = isset($bits[1]) ? $bits[1] : $bits[0];
        $vars = explode(';', $vars);

        $server = array();
        foreach ($vars as $var) {
            $bits = explode('=', $var);
            $server[$bits[0]] = $bits[1];
        }

        return $server;
    }
}
