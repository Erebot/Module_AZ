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

class AZ_TestHelper
extends Erebot_Module_AZ_Game
{
    public function __construct($module, $lists)
    {
        parent::__construct($module, $lists);
        $this->_target = 'foo';
    }

    public function setTarget($target)
    {
        if (!$this->_isValidWord($target)) {
            throw new Exception("Not a valid target");
        }
        $this->_target = $target;
    }
}

class   AZTest
extends Erebot_Testenv_Module_TestCase
{
    public function setUp()
    {
        $this->_module = new Erebot_Module_Wordlists(NULL);
        $this->_module->registerPath(
            dirname(__FILE__) . DIRECTORY_SEPARATOR .
            "wordlists"
        );
        parent::setUp();
        $this->_module->reload($this->_connection, 0);
    }

    public function testAvailableLists()
    {
        $this->assertEquals(
            array('test', 'twowords'),
            Erebot_Module_Wordlists::getAvailableLists()
        );
        $az = new AZ_TestHelper($this->_module, array('test'));
        $this->assertEquals(array('test'), $az->getLoadedListsNames());
        unset($az);
    }

    /**
     * @expectedException Erebot_Module_AZ_NotEnoughWordsException
     */
    public function testInsufficientNumberOfWords()
    {
        $az = new AZ_TestHelper($this->_module, array('twowords'));
    }

    public function testWordProposal()
    {
        $az = new AZ_TestHelper($this->_module, array('test'));
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
        $az = new AZ_TestHelper(
            $this->_module,
            array('test', 'does not exist')
        );
        $this->assertEquals(array('test'), $az->getLoadedListsNames());
        unset($az);
    }
}

