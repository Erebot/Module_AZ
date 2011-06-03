<?php
/*
    This file is part of Erebot.

    Erebot is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Erebot is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Erebot.  If not, see <http://www.gnu.org/licenses/>.
*/

require_once(
    dirname(__FILE__) .
    DIRECTORY_SEPARATOR . 'testenv' .
    DIRECTORY_SEPARATOR . 'bootstrap.php'
);

class AZ_TestHelper
extends Erebot_Module_AZ_Game
{
    public function __construct($lists)
    {
        parent::__construct($lists);
        $this->target = 'foo';
    }

    static public function getAvailableLists()
    {
        self::$_wordlistsDir = dirname(__FILE__).'/wordlists/';
        return parent::getAvailableLists();
    }

    static public function reset()
    {
        self::$_wordlistsDir = NULL;
        self::$_availableLists = NULL;
    }
}

class   AZTest
extends PHPUnit_Framework_TestCase
{
    public function testAvailableLists()
    {
        $this->assertEquals(array('test', 'twowords'), AZ_TestHelper::getAvailableLists());
        $az = new AZ_TestHelper(array('test'));
        $this->assertEquals(array('test'), $az->getLoadedListsNames());
        unset($az);
    }

    /**
     * @expectedException Erebot_Module_AZ_NotEnoughWordsException
     */
    public function testInsufficientNumberOfWords()
    {
        $az = new AZ_TestHelper(array('twowords'));
    }

    public function testWordProposal()
    {
        $az = new AZ_TestHelper(array('test'));
        $this->assertEquals('foo',  $az->getTarget());
        $this->assertEquals(0,      $az->getAttemptsCount());
        $this->assertEquals(0,      $az->getInvalidWordsCount());

        $this->assertEquals(NULL,   $az->getMinimum());
        $this->assertEquals(NULL,   $az->getMaximum());

        try {
            $az->proposeWord('uh');
            $this->fail('Expected Erebot_Module_AZ_InvalidWordException');
        }
        catch (Erebot_Module_AZ_InvalidWordException $e) {
        }

        $this->assertEquals(FALSE,  $az->proposeWord('qux'));
        $this->assertEquals(NULL,   $az->getMinimum());
        $this->assertEquals('qux',  $az->getMaximum());

        $this->assertEquals(FALSE,  $az->proposeWord('baz'));
        $this->assertEquals('baz',  $az->getMinimum());
        $this->assertEquals('qux',  $az->getMaximum());

        $this->assertEquals(NULL,   $az->proposeWord('bar'));
        $this->assertEquals('baz',  $az->getMinimum());
        $this->assertEquals('qux',  $az->getMaximum());

        $this->assertEquals(NULL,   $az->proposeWord('#!\\@/$^'));

        $this->assertEquals(TRUE,   $az->proposeWord('foo'));
        $this->assertEquals('foo',  $az->getTarget());
        $this->assertEquals(3,      $az->getAttemptsCount());
        $this->assertEquals(1,      $az->getInvalidWordsCount());
        unset($az);
    }

    public function testUnreadableWordlist()
    {
        $az = new AZ_TestHelper(array('test', 'does not exist'));
        unset($az);
    }
}

