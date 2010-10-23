<?php

if (!defined('__DIR__')) {
  class __FILE_CLASS__ {
    function  __toString() {
      $X = debug_backtrace();
      return dirname($X[1]['file']);
    }
  }
  define('__DIR__', new __FILE_CLASS__);
} 

require_once(__DIR__.'/../src/game.php');

class AZ_TestHelper
extends AZ
{
    public function __construct($lists)
    {
        parent::__construct($lists);
        $this->target = 'foo';
    }

    static public function getAvailableLists()
    {
        self::$_wordlistsDir = __DIR__.'/wordlists/';
        return parent::getAvailableLists();
    }
}

class   AZTest
extends PHPUnit_Framework_TestCase
{
    public function testAvailableLists()
    {
        $this->assertEquals(array('test', 'twowords'), AZ_TestHelper::getAvailableLists());
        $this->_az = new AZ_TestHelper(array('test'));
        $this->assertEquals(array('test'), $this->_az->getLoadedListsNames());
    }

    /**
     * @expectedException EAZNotEnoughWords
     */
    public function testInsufficientNumberOfWords()
    {
        $this->_az = new AZ_TestHelper(array('twowords'));
    }

    public function testWordProposal()
    {
        $this->_az = new AZ_TestHelper(array('test'));
        $this->assertEquals(NULL, $this->_az->getMinimum());
        $this->assertEquals(NULL, $this->_az->getMaximum());

        $this->assertEquals(FALSE, $this->_az->proposeWord('qux'));
        $this->assertEquals(NULL, $this->_az->getMinimum());
        $this->assertEquals('qux', $this->_az->getMaximum());

        $this->assertEquals(FALSE, $this->_az->proposeWord('baz'));
        $this->assertEquals('baz', $this->_az->getMinimum());
        $this->assertEquals('qux', $this->_az->getMaximum());

        $this->assertEquals(NULL, $this->_az->proposeWord('bar'));
        $this->assertEquals('baz', $this->_az->getMinimum());
        $this->assertEquals('qux', $this->_az->getMaximum());

        $this->assertEquals(TRUE, $this->_az->proposeWord('foo'));
    }
}

