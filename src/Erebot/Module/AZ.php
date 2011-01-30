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
    static protected $_metadata = array(
        'requires'  =>  array(
            'Erebot_Module_TriggerRegistry',
        ),
    );
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
                foreach ($this->_handlers as &$handler)
                    $this->_connection->removeEventHandler($handler);
                unset($handler);
                $registry->freeTriggers($this->_trigger, $matchAny);
            }

            $trigger        = $this->parseString('trigger', 'az');
            $this->_handlers = array();
            $this->_trigger  = $registry->registerTriggers($trigger, $matchAny);
            if ($this->_trigger === NULL) {
                $translator = $this->getTranslator(FALSE);
                throw new Exception($translator->gettext('Could not register AZ creation trigger'));
            }

            $this->_handlers['create']   =  new Erebot_EventHandler(
                array($this, 'handleCreate'),
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
                array($this, 'handleRawText'),
                new Erebot_Event_Match_All(
                    new Erebot_Event_Match_InstanceOf('Erebot_Event_ChanText'),
                    new Erebot_Event_Match_TextRegex(Erebot_Module_AZ_Game::WORD_FILTER)
                )
            );
            $this->_connection->addEventHandler($this->_handlers['rawText']);
        }
    }

    protected function _unload()
    {
    }

    public function handleCreate(Erebot_Interface_Event_Generic &$event)
    {
        $chan       = $event->getChan();
        $text       = $event->getText();
        $translator = $this->getTranslator($chan);

        $cmds = array("list", "lists");
        if (in_array(strtolower($text->getTokens(1)), $cmds)) {
            $msg = $translator->gettext('The following wordlists are available: '.
                        '<for from="lists" item="list"><b><var name="list"/>'.
                        '</b></for>.');
            $tpl = new Erebot_Styling($msg, $translator);
            $tpl->assign('lists', Erebot_Module_AZ_Game::getAvailableLists());
            $this->sendMessage($chan, $tpl->render());
            return $event->preventDefault(TRUE);
        }

        if (isset($this->_chans[$chan])) {
            $game =& $this->_chans[$chan];
            $cmds = array("cancel", "end", "stop");
            if (in_array(strtolower($text->getTokens(1)), $cmds)) {
                $msg = $translator->gettext('The <b>A-Z</b> game was stopped after '.
                        '<b><var name="attempts"/></b> attempts and <b><var '.
                        'name="bad"/></b> invalid words. The answer was <u>'.
                        '<var name="answer"/></u>.');
                $tpl = new Erebot_Styling($msg, $translator);
                $tpl->assign('attempts',    $game->getAttemptsCount());
                $tpl->assign('bad',         $game->getInvalidWordsCount());
                $tpl->assign('answer',      $game->getTarget());
                $this->sendMessage($chan, $tpl->render());
                $this->stopGame($chan);
                return $event->preventDefault(TRUE);
            }

            $min = $game->getMinimum();
            if ($min === NULL)
                $min = '???';

            $max = $game->getMaximum();
            if ($max === NULL)
                $max = '???';

            $msg = $translator->gettext('<b>A-Z</b> (<for from="lists" item="list">'.
                    '<var name="list"/></for>). Current range: <b><var '.
                    'name="min"/></b> -- <b><var name="max"/></b> (<b><var '.
                    'name="attempts"/></b> attempts and <b><var name="bad"/>'.
                    '</b> invalid words)');
            $tpl = new Erebot_Styling($msg, $translator);
            $tpl->assign('attempts',    $game->getAttemptsCount());
            $tpl->assign('bad',         $game->getInvalidWordsCount());
            $tpl->assign('min',         $min);
            $tpl->assign('max',         $max);
            $tpl->assign('lists',       $game->getLoadedListsNames());
            $this->sendMessage($chan, $tpl->render());
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
        catch (EAZNotEnoughWords $e) {
            $msg = $translator->gettext('There are not enough words in the '.
                        'selected wordlists to start a new game.');
            $this->sendMessage($chan, $msg);
            return $event->preventDefault(TRUE);
        }
        $this->_chans[$chan] =& $game;

        $msg = $translator->gettext('A new <b>A-Z</b> game has been started on '.
                    '<b><var name="chan"/></b> using the following wordlists: '.
                    '<for from="lists" item="list"><b><var name="list"/></b>'.
                    '</for>.');
        $tpl    = new Erebot_Styling($msg, $translator);
        $tpl->assign('chan',    $chan);
        $tpl->assign('lists',   $game->getLoadedListsNames());
        $this->sendMessage($chan, $tpl->render());
        return $event->preventDefault(TRUE);
    }

    protected function stopGame($chan)
    {
        unset($this->_chans[$chan]);
    }

    public function handleRawText(Erebot_Interface_Event_Generic &$event)
    {
        $chan       = $event->getChan();
        $translator = $this->getTranslator($chan);

        if (!isset($this->_chans[$chan]))
            return;

        $game =& $this->_chans[$chan];
        try {
            $found = $game->proposeWord((string) $event->getText());
        }
        catch (EAZInvalidWord $e) {
            $msg    =   $translator->gettext('<b><var name="word"/></b> doesn\'t '.
                            'exist or is incorrect for this game.');
            $tpl    = new Erebot_Styling($msg, $translator);
            $tpl->assign('word', $e->getMessage());
            $this->sendMessage($chan, $tpl->render());
            return;
        }

        if ($found === NULL)
            return;

        if ($found) {
            $msg = $translator->gettext('<b>BINGO!</b> The answer was indeed '.
                        '<u><var name="answer"/></u>. Congratulations '.
                        '<b><var name="nick"/></b>!');
            $tpl = new Erebot_Styling($msg, $translator);
            $tpl->assign('answer',  $game->getTarget());
            $tpl->assign('nick',    $event->getSource());
            $this->sendMessage($chan, $tpl->render());

            $msg = $translator->gettext('The answer was found after '.
                        '<b><var name="attempts"/></b> attempts and '.
                        '<b><var name="bad"/></b> incorrect words.');
            $tpl = new Erebot_Styling($msg, $translator);
            $tpl->assign('attempts',    $game->getAttemptsCount());
            $tpl->assign('bad',         $game->getInvalidWordsCount());
            $this->sendMessage($chan, $tpl->render());

            $this->stopGame($chan);
            return $event->preventDefault(TRUE);
        }

        $min = $game->getMinimum();
        if ($min === NULL)
            $min = '???';

        $max = $game->getMaximum();
        if ($max === NULL)
            $max = '???';

        $msg = $translator->gettext('New range: <b><var name="min"/></b> -- '.
                    '<b><var name="max"/></b>');
        $tpl = new Erebot_Styling($msg, $translator);
        $tpl->assign('min', $min);
        $tpl->assign('max', $max);
        $this->sendMessage($chan, $tpl->render());
    }
}

