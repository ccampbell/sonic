<?php
namespace Sonic\Database\Query\Filter;
use Sonic\Database\Query\Filter;
use FilterIterator;

/**
 * Query Filter Class
 *
 * @package Query
 * @subpackage Filter
 * @author Craig Campbell
 */
class Iterator extends FilterIterator
{
    /**
     * @var array
     */
    protected $_patterns = array();

    /**
     * function to determine if this row in the filter should be allowed through
     *
     * @return bool
     */
    public function accept()
    {
        $record = $this->getInnerIterator()->current();
        foreach ($this->_patterns as $pattern) {
            $value = $record[$pattern[0]];
            if ($this->doesNotMatch($value, $pattern[1], $pattern[2])) {
                return false;
            }
        }
        return true;
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
     * @param string $comparison comparison
     * @param string $other_value value to match against
     * @return bool
     */
    public function matches($value, $comparison, $other_value)
    {
        // strip out quotes
        $other_value = str_replace(array('\'', '"', '`'), '', $other_value);

        switch ($comparison) {
            case '===':
            case '==':
            case '=':
                // if this is a comma separated field in the database
                // (such as tags) then we should treat it as an array
                if (strpos($other_value, ',') === false && strpos($value, ',') !== false) {
                    return in_array($other_value, explode(',', $value));
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
            case 'IN':
                return in_array($value, explode(',', $other_value));
                break;
        }
    }

    /**
     * inverse of Iterator::matches()
     *
     * @uses Iterator::matches()
     * @param string $value
     * @param string $comparison
     * @param string $other_value
     * @return bool
     */
    public function doesNotMatch($value, $comparison, $other_value)
    {
        return !$this->matches($value, $comparison, $other_value);
    }
}
