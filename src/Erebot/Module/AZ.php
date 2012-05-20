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

/**
 * \brief
 *      A module that provides a game called "A-Z".
 *
 * In the "A-Z" game, the bot selects a word from a dictionary
 * and contestants must guess that word. Each time a word is
 * proposed, the range of possible words is reduced, until only
 * one word remains.
 */
class   Erebot_Module_AZ
extends Erebot_Module_Base
{
    /// Handlers created by this module.
    protected $_handlers;

    /// Triggers registered by this module (as tokens).
    protected $_triggers;

    /// Associative array mapping channels to games currently in progress.
    protected $_chans;

    /**
     * This method is called whenever the module is (re)loaded.
     *
     * \param int $flags
     *      A bitwise OR of the Erebot_Module_Base::RELOAD_*
     *      constants. Your method should take proper actions
     *      depending on the value of those flags.
     *
     * \note
     *      See the documentation on individual RELOAD_*
     *      constants for a list of possible values.
     */
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

            $this->_handlers['game'] = new Erebot_EventHandler(
                new Erebot_Callable(array($this, 'handleGame')),
                new Erebot_Event_Match_All(
                    new Erebot_Event_Match_InstanceOf('Erebot_Event_ChanText'),
                    new Erebot_Event_Match_Any(
                        new Erebot_Event_Match_TextStatic($trigger, TRUE),
                        new Erebot_Event_Match_TextWildcard($trigger.' *', TRUE)
                    )
                )
            );
            $this->_connection->addEventHandler($this->_handlers['game']);

            $this->_handlers['rawText'] = new Erebot_EventHandler(
                new Erebot_Callable(array($this, 'handleRawText')),
                new Erebot_Event_Match_All(
                    new Erebot_Event_Match_InstanceOf('Erebot_Event_ChanText'),
                    new Erebot_Event_Match_TextRegex(
                        Erebot_Module_Wordlists_Wordlist::WORD_FILTER
                    )
                )
            );
            $this->_connection->addEventHandler($this->_handlers['rawText']);
        }
    }

    /**
     * Handles a request to list available dictionaries.
     *
     * \param Erebot_Interface_Event_Base_ChanText $event
     *      A message asking the bot to list available
     *      dictionaries.
     */
    protected function _handleList(Erebot_Interface_Event_ChanText $event)
    {
        $chan   = $event->getChan();
        $fmt    = $this->getFormatter($chan);

        try {
            $words = $this->_connection->getModule('Erebot_Module_Wordlists');
            $availableLists = $words->getAvailableLists();
        }
        catch (Erebot_NotFoundException $e) {
            $msg = $fmt->_('No list available.');
            $this->sendMessage($chan, $msg);
            return $event->preventDefault(TRUE);
        }

        $msg = $fmt->_(
            'The following wordlists are available: '.
            '<for from="lists" item="list">'.
            '<b><var name="list"/></b></for>.',
            array('lists' => $availableLists)
        );
        $this->sendMessage($chan, $msg);
        return $event->preventDefault(TRUE);
    }

    /**
     * Handles a request to display information about
     * a currently running game.
     *
     * \param Erebot_Interface_Event_Base_ChanText $event
     *      A message asking the bot to display information
     *      about a running game.
     */
    protected function _handleExisting(Erebot_Interface_Event_ChanText $event)
    {
        $chan   = $event->getChan();
        $fmt    = $this->getFormatter($chan);
        $game   = $this->_chans[$chan];

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

    /**
     * Handles a request to stop the game.
     *
     * \param Erebot_Interface_Event_Base_ChanText $event
     *      A message asking the bot to stop the game.
     */
    protected function _handleStop(Erebot_Interface_Event_ChanText $event)
    {
        $chan   = $event->getChan();
        $fmt    = $this->getFormatter($chan);
        $game   = $this->_chans[$chan];

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
        unset($this->_chans[$chan]);
        return $event->preventDefault(TRUE);
    }

    /**
     * Handles a request to create a new game.
     *
     * \param Erebot_Interface_Event_Base_ChanText $event
     *      A message asking the bot to create a new game.
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    protected function _handleCreate(Erebot_Interface_Event_ChanText $event)
    {
        $chan       = $event->getChan();
        $text       = $event->getText();
        $fmt        = $this->getFormatter($chan);

        try {
            $words = $this->_connection->getModule('Erebot_Module_Wordlists');
            $availableLists = $words->getAvailableLists();
        }
        catch (Erebot_NotFoundException $e) {
            $msg = $fmt->_('No list available.');
            $this->sendMessage($chan, $msg);
            return $event->preventDefault(TRUE);
        }

        $lists = $text->getTokens(1);
        if ($lists === NULL) {
            $lists = $this->parseString(
                'default_lists',
                implode(' ', $availableLists)
            );
        }

        $lists = explode(' ', $lists);
        try {
            $game = new Erebot_Module_AZ_Game($words, $lists);
        }
        catch (Erebot_Module_AZ_NotEnoughWordsException $e) {
            $msg = $fmt->_(
                'There are not enough words in the '.
                'selected wordlists to start a new game.'
            );
            $this->sendMessage($chan, $msg);
            return $event->preventDefault(TRUE);
        }
        catch (Erebot_Module_AZ_IncompatibleException $e) {
            $msg = $fmt->_(
                'The given wordlists are not compatible '.
                'with each other.'
            );
            $this->sendMessage($chan, $msg);
            return $event->preventDefault(TRUE);
        }
        $this->_chans[$chan] =& $game;

        $msg = $fmt->_(
            'A new <b>A-Z</b> game has been started on '.
            '<b><var name="chan"/></b> using the following wordlists: '.
            '<for from="lists" item="list"><b><var name="list"/></b></for> '.
            '(<var name="wordsCount"/> words).',
            array(
                'chan'          => $chan,
                'lists'         => $game->getLoadedListsNames(),
                'wordsCount'    => $game->getWordsCount(),
            )
        );
        $this->sendMessage($chan, $msg);
        return $event->preventDefault(TRUE);
    }

    /**
     * This method can be used to create or stop a game,
     * display information about a running game or to list
     * available dictionaries.
     *
     * \param Erebot_Interface_EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot_Interface_Event_Base_ChanText $event
     *      A message that asks the bot to create a new game,
     *      stop a running game, display information about
     *      a running game or list available dictionaries.
     *    /**
     * This method can be used to create or stop a game,
     * display information about a running game or to list
     * available dictionaries.
     *
     * \param Erebot_Interface_EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot_Interface_Event_Base_ChanText $event
     *      A message that asks the bot to create a new game,
     *      stop a running game, display information about
     *      a running game or list available dictionaries.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleGame(
        Erebot_Interface_EventHandler   $handler,
        Erebot_Interface_Event_ChanText $event
    )
    {
        $chan       = $event->getChan();
        $text       = $event->getText();

        $cmds = array("list", "lists");
        if (in_array(strtolower($text->getTokens(1)), $cmds))
            return $this->_handleList($event);

        if (isset($this->_chans[$chan])) {
            $cmds = array("cancel", "end", "stop");
            if (in_array(strtolower($text->getTokens(1)), $cmds))
                return $this->_handleStop($event);
            return $this->_handleExisting($event);
        }

        return $this->_handleCreate($event);
    }

    /**
     * Handles messages: words are interpreted
     * as propositions for the game.
     *
     * \param Erebot_Interface_EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot_Interface_Event_Base_ChanText $event
     *      A message with a proposition for the game.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
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
            unset($this->_chans[$chan]);
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

