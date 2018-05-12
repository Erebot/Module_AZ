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

namespace Erebot\Module;

/**
 * \brief
 *      A module that provides a game called "A-Z".
 *
 * In the "A-Z" game, the bot selects a word from a dictionary
 * and contestants must guess that word. Each time a word is
 * proposed, the range of possible words is reduced, until only
 * one word remains.
 */
class AZ extends \Erebot\Module\Base
{
    /// Handlers created by this module.
    protected $handlers;

    /// Triggers registered by this module (as tokens).
    protected $triggers;

    /// Associative array mapping channels to games currently in progress.
    protected $chans;

    /**
     * This method is called whenever the module is (re)loaded.
     *
     * \param int $flags
     *      A bitwise OR of the Erebot::Module::Base::RELOAD_*
     *      constants. Your method should take proper actions
     *      depending on the value of those flags.
     *
     * \note
     *      See the documentation on individual RELOAD_*
     *      constants for a list of possible values.
     */
    public function reload($flags)
    {
        if ($flags & self::RELOAD_HANDLERS) {
            $registry   = $this->connection->getModule(
                '\\Erebot\\Module\\TriggerRegistry'
            );

            if (!($flags & self::RELOAD_INIT)) {
                foreach ($this->handlers as $handler) {
                    $this->connection->removeEventHandler($handler);
                }
                $registry->freeTriggers($this->trigger, $registry::MATCH_ANY);
            }

            $trigger        = $this->parseString('trigger', 'az');
            $this->handlers = array();
            $this->trigger  = $registry->registerTriggers($trigger, $registry::MATCH_ANY);
            if ($this->trigger === null) {
                $fmt = $this->getFormatter(false);
                throw new \Exception(
                    $fmt->_(
                        'Could not register AZ creation trigger'
                    )
                );
            }

            $this->handlers['game'] = new \Erebot\EventHandler(
                array($this, 'handleGame'),
                new \Erebot\Event\Match\All(
                    new \Erebot\Event\Match\Type('\\Erebot\\Event\\ChanText'),
                    new \Erebot\Event\Match\Any(
                        new \Erebot\Event\Match\TextStatic($trigger, true),
                        new \Erebot\Event\Match\TextWildcard($trigger.' *', true)
                    )
                )
            );
            $this->connection->addEventHandler($this->handlers['game']);

            $this->handlers['rawText'] = new \Erebot\EventHandler(
                array($this, 'handleRawText'),
                new \Erebot\Event\Match\All(
                    new \Erebot\Event\Match\Type('\\Erebot\\Event\\ChanText'),
                    new \Erebot\Event\Match\TextRegex(
                        \Erebot\Module\Wordlists\Wordlist::WORD_FILTER
                    )
                )
            );
            $this->connection->addEventHandler($this->handlers['rawText']);
        }
    }

    /**
     * Handles a request to list available dictionaries.
     *
     * \param Erebot::Interfaces::Event::Base::ChanText $event
     *      A message asking the bot to list available
     *      dictionaries.
     */
    protected function handleList(\Erebot\Interfaces\Event\ChanText $event)
    {
        $chan   = $event->getChan();
        $fmt    = $this->getFormatter($chan);

        try {
            $words = $this->connection->getModule('\\Erebot\\Module\\Wordlists');
            $availableLists = $words->getAvailableLists();
        } catch (\Erebot\NotFoundException $e) {
            $msg = $fmt->_('No list available.');
            $this->sendMessage($chan, $msg);
            return $event->preventDefault(true);
        }

        $msg = $fmt->_(
            'The following wordlists are available: '.
            '<for from="lists" item="list">'.
            '<b><var name="list"/></b></for>.',
            array('lists' => $availableLists)
        );
        $this->sendMessage($chan, $msg);
        return $event->preventDefault(true);
    }

    /**
     * Handles a request to display information about
     * a currently running game.
     *
     * \param Erebot::Interfaces::Event::Base::ChanText $event
     *      A message asking the bot to display information
     *      about a running game.
     */
    protected function handleExisting(\Erebot\Interfaces\Event\ChanText $event)
    {
        $chan   = $event->getChan();
        $fmt    = $this->getFormatter($chan);
        $game   = $this->chans[$chan];

        $min = $game->getMinimum();
        if ($min === null) {
            $min = '???';
        }

        $max = $game->getMaximum();
        if ($max === null) {
            $max = '???';
        }

        $cls = $this->getFactory('!Styling\\Variables\\Duration');
        $msg = $fmt->_(
            '<b>A-Z</b> (<for from="lists" item="list">'.
            '<var name="list"/></for>). Current range: '.
            '<b><var name="min"/></b> -- <b><var name="max"/></b> '.
            '(<b><var name="attempts"/></b> attempts and '.
            '<b><var name="bad"/></b> invalid words, <var name="duration"/>)',
            array(
                'attempts' => $game->getAttemptsCount(),
                'bad' => $game->getInvalidWordsCount(),
                'min' => $min,
                'max' => $max,
                'lists' => $game->getLoadedListsNames(),
                'duration' => new $cls($game->getElapsedTime()),
            )
        );
        $this->sendMessage($chan, $msg);
        return $event->preventDefault(true);
    }

    /**
     * Handles a request to stop the game.
     *
     * \param Erebot::Interfaces::Event::Base::ChanText $event
     *      A message asking the bot to stop the game.
     */
    protected function handleStop(\Erebot\Interfaces\Event\ChanText $event)
    {
        $chan   = $event->getChan();
        $fmt    = $this->getFormatter($chan);
        $game   = $this->chans[$chan];

        $cls = $this->getFactory('!Styling\\Variables\\Duration');
        $msg = $fmt->_(
            'The <b>A-Z</b> game was stopped after <var name="duration"/>, '.
            '<b><var name="attempts"/></b> attempts and <b><var '.
            'name="bad"/></b> invalid words. The answer was <u>'.
            '<var name="answer"/></u>.',
            array(
                'attempts' => $game->getAttemptsCount(),
                'bad' => $game->getInvalidWordsCount(),
                'answer' => $game->getTarget(),
                'duration' => new $cls($game->getElapsedTime()),
            )
        );
        $this->sendMessage($chan, $msg);
        unset($this->chans[$chan]);
        return $event->preventDefault(true);
    }

    /**
     * Handles a request to create a new game.
     *
     * \param Erebot::Interfaces::Event::Base::ChanText $event
     *      A message asking the bot to create a new game.
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    protected function handleCreate(\Erebot\Interfaces\Event\ChanText $event)
    {
        $chan       = $event->getChan();
        $text       = $event->getText();
        $fmt        = $this->getFormatter($chan);

        try {
            $words = $this->connection->getModule('\\Erebot\\Module\\Wordlists');
            $availableLists = $words->getAvailableLists();
        } catch (\Erebot\NotFoundException $e) {
            $msg = $fmt->_('No list available.');
            $this->sendMessage($chan, $msg);
            return $event->preventDefault(true);
        }

        $lists = $text->getTokens(1);
        if ($lists === null) {
            $lists = $this->parseString(
                'default_lists',
                implode(' ', $availableLists)
            );
        }

        $lists = explode(' ', $lists);
        try {
            $game = new \Erebot\Module\AZ\Game($words, $lists);
        } catch (\Erebot\Module\AZ\NotEnoughWordsException $e) {
            $msg = $fmt->_(
                'There are not enough words in the '.
                'selected wordlists to start a new game.'
            );
            $this->sendMessage($chan, $msg);
            return $event->preventDefault(true);
        } catch (\Erebot\Module\AZ\IncompatibleException $e) {
            $msg = $fmt->_(
                'The given wordlists are not compatible '.
                'with each other.'
            );
            $this->sendMessage($chan, $msg);
            return $event->preventDefault(true);
        }
        $this->chans[$chan] =& $game;

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
        return $event->preventDefault(true);
    }

    /**
     * This method can be used to create or stop a game,
     * display information about a running game or to list
     * available dictionaries.
     *
     * \param Erebot::Interfaces::EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot::Interfaces::Event::Base::ChanText $event
     *      A message that asks the bot to create a new game,
     *      stop a running game, display information about
     *      a running game or list available dictionaries.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleGame(
        \Erebot\Interfaces\EventHandler   $handler,
        \Erebot\Interfaces\Event\ChanText $event
    ) {
        $chan       = $event->getChan();
        $text       = $event->getText();

        $cmds = array("list", "lists");
        if (in_array(strtolower($text->getTokens(1)), $cmds)) {
            return $this->handleList($event);
        }

        if (isset($this->chans[$chan])) {
            $cmds = array("cancel", "end", "stop");
            if (in_array(strtolower($text->getTokens(1)), $cmds)) {
                return $this->handleStop($event);
            }
            return $this->handleExisting($event);
        }

        return $this->handleCreate($event);
    }

    /**
     * Handles messages: words are interpreted
     * as propositions for the game.
     *
     * \param Erebot::Interfaces::EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot::Interfaces::Event::Base::ChanText $event
     *      A message with a proposition for the game.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleRawText(
        \Erebot\Interfaces\EventHandler   $handler,
        \Erebot\Interfaces\Event\ChanText $event
    ) {
        $chan   = $event->getChan();
        $fmt    = $this->getFormatter($chan);

        if (!isset($this->chans[$chan])) {
            return;
        }

        $game =& $this->chans[$chan];
        try {
            $found = $game->proposeWord((string) $event->getText());
        } catch (\Erebot\Module\AZ\InvalidWordException $e) {
            $msg = $fmt->_(
                '<b><var name="word"/></b> doesn\'t '.
                'exist or is incorrect for this game.',
                array('word' => $e->getMessage())
            );
            $this->sendMessage($chan, $msg);
            return;
        }

        if ($found === null) {
            return;
        }

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

            $cls = $this->getFactory('!Styling\\Variables\\Duration');
            $msg = $fmt->_(
                'The answer was found after <var name="duration"/>, '.
                '<b><var name="attempts"/></b> attempts and '.
                '<b><var name="bad"/></b> incorrect words.',
                array(
                    'attempts' => $game->getAttemptsCount(),
                    'bad' => $game->getInvalidWordsCount(),
                    'duration' => new $cls($game->getElapsedTime()),
                )
            );
            $this->sendMessage($chan, $msg);
            unset($this->chans[$chan]);
            return $event->preventDefault(true);
        }

        $min = $game->getMinimum();
        if ($min === null) {
            $min = '???';
        }

        $max = $game->getMaximum();
        if ($max === null) {
            $max = '???';
        }

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
