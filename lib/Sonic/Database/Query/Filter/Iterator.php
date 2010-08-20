<?php
namespace Sonic\Database\Query\Filter;

/**
 * Query Filter Iterator Class
 *
 * @todo optimize explode(',') calls so they don't have to happen on every iteration
 * @package Query
 * @subpackage Filter
 * @author Craig Campbell
 */
class Iterator
{
    /**
     * @var array
     */
    protected $_patterns = array();

    /**
     * @var arrays
     */
    protected $_arrays = array();

    /**
     * function to determine if this row in the filter should be allowed through
     *
     * @return bool
     */
    public function process($rows, $total_weight = null)
    {
        $filtered_data = array();
        foreach ($rows as $row) {
            foreach ($this->_patterns as $pattern) {

                // for fulltext search let's update the row to add relevancy scores
                if ($pattern['comparison'] == 'FULLTEXT') {
                    $row = $this->_processFullText($row, $pattern, $total_weight);
                    continue;
                }

                $value = $row[$pattern['column']];
                if (!$this->matches($value, $pattern)) {
                    continue 2;
                }
            }
            $filtered_data[] = $row;
        }

        return $filtered_data;
    }

    /**
     * processes full text for a given row
     *
     * @param array $row
     * @param array $pattern
     * @param int $total_weight
     */
    protected function _processFullText($row, $pattern, $total_weight)
    {
        if (!isset($row['score'])) {
            $row['score'] = 0;
        }

        $value = $row[$pattern['column']];
        $other_value = $pattern['value'];
        $weight = $pattern['args'];

        // if there are over 255 bytes then we have to use similar text
        if (isset($value[255]) || isset($other_value[255])) {
            similar_text($value, $other_value, $percent);
            $row[$pattern['column'] . '_score'] = $percent;
            $row['score'] += $percent * ($weight / $total_weight);
            return $row;;
        }

        // otherwise use levenschtein
        $chars = levenshtein($value, $other_value);
        $percent = 100 * (1 - $chars / max(strlen($value), strlen($other_value)));
        $row[$pattern['column'] . '_score'] = $percent;
        $row['score'] += $percent * ($weight / $total_weight);

        return $row;
    }

    /**
     * sets patterns for this filter
     *
     * @param array
     * @return void
     */
    public function setPatterns(array $patterns)
    {
        $this->_patterns = $patterns;
    }

    /**
     * determines if a value matches a filter
     *
     * @param string $value value of current database field
     * @param array $pattern pattern to match
     * @return bool
     */
    public function matches($value, $pattern)
    {
        $comparison = $pattern['comparison'];
        $other_value = $pattern['value'];

        // strip out quotes
        $other_value = str_replace(array('\'', '"', '`'), '', $other_value);

        switch ($comparison) {
            case '===':
            case '==':
            case '=':
                // if this is a comma separated field in the database
                // (such as tags) then we should treat it as an array
                if (strpos($other_value, ',') === false && strpos($value, ',') !== false) {
                    return in_array($other_value, $this->_getArray($pattern));
                }
                return $value == $other_value;
                break;
            case '<=':
                return $value <= $other_value;
                break;
            case '>=':
                return $value >= $other_value;
                break;
            case '<>':
            case '!=':
                return $value != $other_value;
                break;
            case '<':
                return $value < $other_value;
                break;
            case '>':
                return $value > $other_value;
                break;
            case 'LIKE':
                return stripos($value, $other_value) !== false;
                break;
            case 'NOT IN':
                return !in_array($value, $this->_getArray($pattern));
                break;
            case 'IN':
                return in_array($value, $this->_getArray($pattern));
                break;
        }
    }

    /**
     * gets an array for a specific pattern
     * caches it so we don't have to explode on every iteration
     *
     * @param array
     * @return array
     */
    protected function _getArray(array $pattern)
    {
        $cache_key = $pattern['comparison'] . $pattern['value'];
        if (!isset($this->_arrays[$cache_key])) {
            $this->_arrays[$cache_key] = explode(',', $pattern['value']);
        }
        return $this->_arrays[$cache_key];
    }
}
