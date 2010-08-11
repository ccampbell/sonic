<?php
namespace Sonic\Database\Query;
use Sonic\Database\Query;

/**
 * Experimental join killer class
 *
 * @package Query
 * @subpackage JoinKiller
 * @author Craig Campbell
 */
class JoinKiller
{
    /**
     * @var Query
     */
    protected $_query;

    protected $_sql = array();
    protected $_execute_order = array();
    protected $_depends_on = array();
    protected $_results = array();
    protected $_remove = array();

    public function __construct(Query $query)
    {
        $this->_query = $query;
    }

    public function fetchRow()
    {
        $this->_analyze();
        foreach ($this->_execute_order as $table_alias) {
            $sql = $this->_sql[$table_alias];
            $row = $this->_query($sql, $table_alias)->fetchRow();

            // with an inner join we need to make sure the data is there
            if (!$row) {
                return array();
            }

            $this->_results[$table_alias] = $row;
        }

        foreach ($this->_remove as $alias => $column) {
            unset($this->_results[$alias][$column]);
        }

        $row = array();
        foreach ($this->_results as $result) {
            $row = array_merge($row, $result);
        }

        return $row;
    }

    public function fetchAll()
    {
        $this->_analyze();
        foreach ($this->_execute_order as $table_alias) {
            $sql = $this->_sql[$table_alias];
            $rows = $this->_query($sql, $table_alias, true)->getStatement()->fetchAll(\PDO::FETCH_ASSOC);

            // with an inner join we need to make sure the data is there
            if (!$rows) {
                return array();
            }

            $this->_results[$table_alias] = $rows;
        }

        foreach ($this->_remove as $alias => $column) {
            foreach ($this->_results[$alias] as $key => $row) {
                unset($this->_results[$alias][$key][$column]);
            }
        }


            // var_dump($results);
            $args = array();
            foreach ($this->_results as $result) {
                $args[] = $result;
            }

            function my_array_merge ($arr,$ins) {
                if(is_array($arr))
                {
                    if(is_array($ins)) foreach($ins as $k=>$v)
                    {
                        if(isset($arr[$k])&&is_array($v)&&is_array($arr[$k]))
                        {
                            $arr[$k] = my_array_merge($arr[$k],$v);
                        }
                        else {
                            // This is the new loop :)
                            while (isset($arr[$k]))
                                $k++;
                            $arr[$k] = $v;
                        }
                    }
                }
                elseif(!is_array($arr)&&(strlen($arr)==0||$arr==0))
                {
                    $arr=$ins;
                }
                return($arr);
            }

            $i = 0;
            foreach ($this->_results as $result) {
                ++$i;
                if ($i == 1) {
                    $start = $result;
                    continue;
                }
                $start = my_array_merge($start, $result);
            }

        return $start;
    }

    protected function _query($sql, $table_alias, $fetch_all = false)
    {
        $sql = $this->_replaceDependencies($sql, $table_alias, $fetch_all);
        var_dump($sql);
        $query = new Query($sql);
        $query->execute();
        return $query;
    }

    protected function _replaceDependencies($sql, $table_alias, $fetch_all = false)
    {
        if (!isset($this->_depends_on[$table_alias])) {
            return $sql;
        }

        foreach ($this->_depends_on[$table_alias] as $other_alias => $columns) {
            foreach ($columns as $column) {
                $string = $other_alias . '.' . $column;

                if (!isset($this->_where_conditions[$other_alias])) {
                    $this->_where_conditions[$other_alias] = array();
                }

                if ($fetch_all) {
                    $found = false;
                    foreach ($this->_where_conditions[$other_alias] as $condition) {
                        if (strpos($condition, $string) !== false) {
                            $found = true;
                            break;
                        }
                    }

                    if ($found === false) {
                        $rows = $this->_results[$other_alias];
                        $data = array();
                        foreach ($rows as $row) {
                            $data[] = $row[$column];
                        }
                        $sql = str_replace(' = ' . $string, ' IN (' . implode(',', $data), $sql) . ')';
                        unset($this->_where_conditions[$table_alias]);
                        continue;
                    }

                }

                if (isset($this->_results[$other_alias][$column])) {
                    $sql = str_replace($string, $this->_results[$other_alias][$column], $sql);
                }
            }
        }
        return $sql;
    }

    protected function _analyze()
    {
        $sql = $this->_query->getSql();
        $binds = $this->_query->getBinds();
        foreach ($binds as $key => $bind) {
            $sql = str_replace($key, '\'' . $bind . '\'', $sql);
        }
        var_dump($sql);

        if (strpos($sql, 'ORDER'))
            throw new Query\Exception('cannot use JoinKiller with ORDER for now');

        if (strpos($sql, 'LIMIT') || strpos($sql, 'OFFSET'))
            throw new Query\Exception('cannot use JoinKiller with LIMIT and OFFSET');

        // initialize all the vars we are going to need
        $tables = $select_list = $join_list = $where_list = array();

        // remove line breaks and other characters
        $sql = str_replace(array("\n", '`'), '', $sql);
        $sql = preg_replace("/\s+/", ' ', $sql);
        $sql = preg_replace("/,\s/", ',', $sql);


        $bits = explode(' FROM ', $sql);
        $select_bit = str_replace('SELECT ', '', $bits[0]);
        $more_bits = explode(' WHERE ', $bits[1]);
        $where_bit = $more_bits[1];

        $table_bits = explode(',', $more_bits[0]);
        foreach ($table_bits as $table_bit) {
            $bits = explode(' ', $table_bit);
            $alias = isset($bits[1]) ? $bits[1] : $bits[0];
            $tables[$alias] = $bits[0];
        }

        $selects = explode(',', $select_bit);
        foreach ($selects as $select) {
            $bits = explode(' ', $select);

            $more_bits = explode('.', $bits[0]);

            // what fields to select per table
            $select_list[$tables[$more_bits[0]]][] = $bits[0] . (isset($bits[1]) ? ' ' . $bits[1] : '');
        }

        $where_bits = explode(' AND ', $where_bit);

        foreach ($where_bits as $bit) {
            $bits = explode(' = ', $bit);
            $where_list[$bits[0]] = $bits[1];

            $count = substr_count($bit, '.');

            // join condition
            if ($count == 2) {
                $join_list[trim($bits[0])] = trim($bits[1]);
                continue;
            }
        }

        $where_conditions = array();
        $depends_on = array();
        foreach ($where_list as $key => $value) {
            $bits = explode('.', $key);
            $where_conditions[$bits[0]][] = $key . ' = ' . $value;

            // depends on another table
            if (strpos($value, '.') !== false) {
                $more_bits = explode('.', $value);
                $alias = $more_bits[0];
                $depends_on[$bits[0]][$alias][] = $more_bits[1];

                if (!in_array($value, $select_list[$tables[$alias]])) {
                    $select_list[$tables[$alias]][] = $value;
                    $bits = explode('.', $value);
                    $this->_remove[$alias] = $bits[1];
                }
            }
        }

        $this->_depends_on = $depends_on;

        // calculate dependencies to determine what order to run these queries
        $dependencies = array();
        foreach ($depends_on as $alias => $value) {
            $dependencies[$alias] = array_keys($value);
        }

        foreach ($tables as $alias => $table) {
            if (!isset($dependencies[$alias])) {
                $dependencies[$alias] = array();
            }
        }

        $this->_execute_order = \Sonic\Util::resolveDependencies($dependencies);

        // generate the sql
        foreach ($tables as $alias => $table) {
            $sql = 'SELECT ' . implode(', ', $select_list[$table]) . ' FROM `' . $table . '`' . ' ' . $alias;

            if (count($where_conditions) && isset($where_conditions[$alias])) {
                $sql .= ' WHERE ' . implode(' AND ', $where_conditions[$alias]);
            }
            $this->_where_conditions = $where_conditions;
            $this->_sql[$alias] = $sql;
        }
    }

}
