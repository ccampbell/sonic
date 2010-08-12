<?php
namespace Sonic\Database\Query;
use Sonic\Database\Query;
use ArrayIterator;

/**
 * Query Filter Class
 *
 * @package Query
 * @subpackage Filter
 * @author Craig Campbell
 */
class Filter
{
    /**
     * @var array
     */
    protected $_patterns = array();

    /**
     * adds a pattern to filter on
     *
     * @param string
     * @return void
     */
    public function addPattern($pattern)
    {
        $this->_patterns[] = $this->_processPattern($pattern);
    }

    /**
     * takes pattern like "id<5" and converts it to
     * array('id', '<', '5')
     *
     * @param string
     * @return array
     */
    protected function _processPattern($pattern)
    {
        // the order here matters cause if = came before == then it would match that even if the user used ==
        $allowed_symbols = array('<=', '>=', '<>', '!=', '===', '==', '<', '>', '=', 'LIKE', 'IN');

        $valid = false;
        foreach ($allowed_symbols as $symbol) {
            if (strpos($pattern, $symbol)) {
                $valid = true;
                break;
            }
        }

        // if the filter is invalid
        if (!$valid) {
            throw new \Sonic\Database\Exception('symbol used for filter must be one of: ' .
                implode(', ', $allowed_symbols));
        }

        $bits = explode($symbol, $pattern);

        return array(trim($bits[0]), $symbol, trim($bits[1]));
    }

    /**
     * takes unfiltered data and processes filters
     *
     * @uses Filter\Iterator
     * @param array $rows
     * @return array
     */
    public function process(array $rows)
    {
        $unfiltered = new Filter\Iterator();
        $unfiltered->setPatterns($this->_patterns);

        $filtered = $unfiltered->process($rows);

        return $filtered;
    }
}
