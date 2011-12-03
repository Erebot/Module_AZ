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

class   Erebot_Module_AZ
extends Erebot_Module_Base
{
    protected $_handlers;
    protected $_triggers;
    protected $_chans;

    public function _reload($flags)
    {
        if ($flags & self::RELOAD_HANDLERS) {
            $registry   = $this->_connection->getModule(
                'Erebot_Module_TriggerRegistry'
            );
            $matchAny  = Erebot_Utils::getVStatic($registry, 'MATCH_ANY');

            if (!($flags & self::RELOAD_INIT)) {
                foreach ($this->_handlers as $handler)
                    $this->_connection->removeEventHandler($handler);
                $registry->freeTriggers($this->_trigger, $matchAny);
            }

            $trigger        = $this->parseString('trigger', 'az');
            $this->_handlers = array();
            $this->_trigger  = $registry->registerTriggers($trigger, $matchAny);
            if ($this->_trigger === NULL) {
                $fmt = $this->getFormatter(FALSE);
                throw new Exception(
                    $fmt->_(
                        'Could not register AZ creation trigger'
                    )
                );
            }

            $this->_handlers['create']   =  new Erebot_EventHandler(
                new Erebot_Callable(array($this, 'handleCreate')),
                new Erebot_Event_Match_All(
                    new Erebot_Event_Match_InstanceOf('Erebot_Event_ChanText'),
                    new Erebot_Event_Match_Any(
                        new Erebot_Event_Match_TextStatic($trigger, TRUE),
                        new Erebot_Event_Match_TextWildcard($trigger.' *', TRUE)
                    )
                )
            );
            $this->_connection->addEventHandler($this->_handlers['create']);

            $this->_handlers['rawText']  =  new Erebot_EventHandler(
                new Erebot_Callable(array($this, 'handleRawText')),
                new Erebot_Event_Match_All(
                    new Erebot_Event_Match_InstanceOf('Erebot_Event_ChanText'),
                    new Erebot_Event_Match_TextRegex(
                        Erebot_Module_AZ_Game::WORD_FILTER
                    )
                )
            );
            $this->_connection->addEventHandler($this->_handlers['rawText']);
        }
    }

    protected function _unload()
    {
    }

    public function handleCreate(
        Erebot_Interface_EventHandler   $handler,
        Erebot_Interface_Event_ChanText $event
    )
    {
        $chan       = $event->getChan();
        $text       = $event->getText();
        $fmt        = $this->getFormatter($chan);

        $cmds = array("list", "lists");
        if (in_array(strtolower($text->getTokens(1)), $cmds)) {
            $msg = $fmt->_(
                'The following wordlists are available: '.
                '<for from="lists" item="list">'.
                '<b><var name="list"/></b></for>.',
                array('lists' => Erebot_Module_AZ_Game::getAvailableLists())
            );
            $this->sendMessage($chan, $msg);
            return $event->preventDefault(TRUE);
        }

        if (isset($this->_chans[$chan])) {
            $game =& $this->_chans[$chan];
            $cmds = array("cancel", "end", "stop");
            if (in_array(strtolower($text->getTokens(1)), $cmds)) {
                $msg = $fmt->_(
                    'The <b>A-Z</b> game was stopped after '.
                    '<b><var name="attempts"/></b> attempts and <b><var '.
                    'name="bad"/></b> invalid words. The answer was <u>'.
                    '<var name="answer"/></u>.',
                    array(
                        'attempts' => $game->getAttemptsCount(),
                        'bad' => $game->getInvalidWordsCount(),
                        'answer' => $game->getTarget(),
                    )
                );
                $this->sendMessage($chan, $msg);
                $this->stopGame($chan);
                return $event->preventDefault(TRUE);
            }

            $min = $game->getMinimum();
            if ($min === NULL)
                $min = '???';

            $max = $game->getMaximum();
            if ($max === NULL)
                $max = '???';

            $msg = $fmt->_(
                '<b>A-Z</b> (<for from="lists" item="list">'.
                '<var name="list"/></for>). Current range: '.
                '<b><var name="min"/></b> -- <b><var name="max"/></b> '.
                '(<b><var name="attempts"/></b> attempts and '.
                '<b><var name="bad"/></b> invalid words)',
                array(
                    'attempts' => $game->getAttemptsCount(),
                    'bad' => $game->getInvalidWordsCount(),
                    'min' => $min,
                    'max' => $max,
                    'lists' => $game->getLoadedListsNames(),
                )
            );
            $this->sendMessage($chan, $msg);
            return $event->preventDefault(TRUE);
        }

        $lists = $text->getTokens(1);
        if ($lists === NULL)
            $lists = $this->parseString(
                'default_lists',
                implode(' ', Erebot_Module_AZ_Game::getAvailableLists())
            );
        $lists = explode(' ', $lists);
        try {
            $game = new Erebot_Module_AZ_Game($lists);
        }
        catch (Erebot_Module_AZ_NotEnoughWordsException $e) {
            $msg = $fmt->_(
                'There are not enough words in the '.
                'selected wordlists to start a new game.'
            );
            $this->sendMessage($chan, $msg);
            return $event->preventDefault(TRUE);
        }
        $this->_chans[$chan] =& $game;

        $msg = $fmt->_(
            'A new <b>A-Z</b> game has been started on '.
            '<b><var name="chan"/></b> using the following wordlists: '.
            '<for from="lists" item="list"><b><var name="list"/></b></for>.',
            array(
                'chan' => $chan,
                'lists' => $game->getLoadedListsNames(),
            )
        );
        $this->sendMessage($chan, $msg);
        return $event->preventDefault(TRUE);
    }

    protected function stopGame($chan)
    {
        unset($this->_chans[$chan]);
    }

    public function handleRawText(
        Erebot_Interface_EventHandler   $handler,
        Erebot_Interface_Event_ChanText $event
    )
    {
        $chan   = $event->getChan();
        $fmt    = $this->getFormatter($chan);

        if (!isset($this->_chans[$chan]))
            return;

        $game =& $this->_chans[$chan];
        try {
            $found = $game->proposeWord((string) $event->getText());
        }
        catch (Erebot_Module_AZ_InvalidWordException $e) {
            $msg = $fmt->_(
                '<b><var name="word"/></b> doesn\'t '.
                'exist or is incorrect for this game.',
                array('word' => $e->getMessage())
            );
            $this->sendMessage($chan, $msg);
            return;
        }

        if ($found === NULL)
            return;

        if ($found) {
            $msg = $fmt->_(
                '<b>BINGO!</b> The answer was indeed '.
                '<u><var name="answer"/></u>. Congratulations '.
                '<b><var name="nick"/></b>!',
                array(
                    'answer' => $game->getTarget(),
                    'nick' => $event->getSource(),
                )
            );
            $this->sendMessage($chan, $msg);

            $msg = $fmt->_(
                'The answer was found after '.
                '<b><var name="attempts"/></b> attempts and '.
                '<b><var name="bad"/></b> incorrect words.',
                array(
                    'attempts' => $game->getAttemptsCount(),
                    'bad' => $game->getInvalidWordsCount(),
                )
            );
            $this->sendMessage($chan, $msg);
            $this->stopGame($chan);
            return $event->preventDefault(TRUE);
        }

        $min = $game->getMinimum();
        if ($min === NULL)
            $min = '???';

        $max = $game->getMaximum();
        if ($max === NULL)
            $max = '???';

        $msg = $fmt->_(
            'New range: <b><var name="min"/></b> -- <b><var name="max"/></b>',
            array(
                'min' => $min,
                'max' => $max,
            )
        );
        $this->sendMessage($chan, $msg);
    }
}

