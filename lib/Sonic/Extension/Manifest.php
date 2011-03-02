<?php
namespace Sonic\Extension;

/**
 * Manifest
 *
 * @category Sonic
 * @package Extension
 * @author Craig Campbell
 */
abstract class Manifest
{
    /**
     * @var string
     */
    const VERSION = '';

    /**
     * @var array
     */
    protected $_dependencies = array();

    /**
     * @var string
     */
    protected $_instructions = '';

    /**
     * gets dependencies
     *
     * @return array
     */
    public function getDependencies()
    {
        return array_map('strtolower', $this->_dependencies);
    }

    /**
     * gets extra installation instructions
     *
     * @return string
     */
    public final function getInstructions()
    {
        $instructions = '';
        if ($this->_instructions) {
            $instructions .= "\n" . '----------------------------' . "\n";
            $instructions .= " INSTRUCTIONS" . "\n";
            $instructions .= '----------------------------' . "\n";
            $instructions .= $this->_instructions . "\n";
        }
        return $instructions;
    }
}
