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

namespace Erebot\Module\AZ;

/**
 *  \brief
 *      Actual A-Z game implementation.
 *
 *  This class does all the heavy work.
 */
class Game
{
    /// Lower part of the current range (\b null if undefined).
    protected $min;

    /// Higher part of the current range (\b null if undefined).
    protected $max;

    /// The actual word that contestants must guess.
    protected $target;

    /// Number of attempts made to find the correct word.
    protected $attempts;

    /// Number of invalid words that were proposed (that passed WORD_FILTER).
    protected $invalidWords;

    /// Lists of words used in this game.
    protected $lists;

    /// A collator, providing case-insensitive comparison.
    protected $collator;

    /// Number of words in the currently loaded wordlists.
    protected $wordsCount;

    /// Timestamp for game start.
    protected $started;

    /**
     * Creates a new instance of the game.
     *
     * \param Erebot::Module::Wordlists::$wordlists
     *      An instance of Erebot::Module::Wordlists, the module
     *      responsible for providing access to wordlists.
     *
     * \param array $lists
     *      A list with the name of the dictionaries
     *      from which the target word may be selected.
     *
     * \throw Erebot::Module::AZ::IncompatibleException
     *      The given wordlists are not compatible with
     *      each other (eg. they do not use the same
     *      locales).
     *
     * \throw Erebot::Module::AZ::NotEnoughWordsException
     *      There are not enough words in the selected
     *      lists.
     *
     * \note
     *      Invalid list names and lists whose content
     *      cannot be parsed are silently ignored.
     */
    public function __construct(\Erebot\Module\Wordlists $wordlists, $lists)
    {
        $count = 0;
        $this->collator = null;
        foreach ($lists as $list) {
            try {
                $wordlist       = $wordlists->getList($list);
                $collator       = $wordlist->getCollator();

                if ($this->collator !== null) {
                    if ($this->collator->getLocale(Locale::ACTUAL_LOCALE) !=
                        $collator->getLocale(Locale::ACTUAL_LOCALE)) {
                        throw new \Erebot\Module\AZ\IncompatibleException(
                            "Incompatible wordlists"
                        );
                    }
                } else {
                    $this->collator = $collator;
                }

                $count        += count($wordlist);
                $this->lists[] = $wordlist;
            } catch (\Erebot\Module\Wordlists\BadListNameException $e) {
            } catch (\Erebot\Module\Wordlists\UnreadableFileException $e) {
            }
        }

        if ($count < 3) {
            throw new \Erebot\Module\AZ\NotEnoughWordsException();
        }

        $target = rand(0, $count - 1);
        foreach ($this->lists as $list) {
            $size = count($list);

            if ($target >= $size) {
                $target -= $size;
                continue;
            }

            $target = $list[$target];
            break;
        }

        $this->attempts     =
        $this->invalidWords = 0;
        $this->min          =
        $this->max          = null;
        $this->wordsCount   = $count;
        $this->target       = $target;
        $this->started      = time();
    }

    /// Destructor.
    public function __destruct()
    {
    }

    /**
     * Returns the first word in the currently
     * active wordlists.
     *
     * \retval string
     *      First word in currently active wordlists.
     */
    public function getMinimum()
    {
        return $this->min;
    }

    /**
     * Returns the last word in the currently
     * active wordlists.
     *
     * \retval string
     *      Last word in currently active wordlists.
     */
    public function getMaximum()
    {
        return $this->max;
    }

    /**
     * Returns the target word for this game.
     *
     * \retval string
     *      Target word for this round.
     */
    public function getTarget()
    {
        return $this->target;
    }

    /**
     * Returns the number of attempts made to guess
     * the target word.
     *
     * \retval int
     *      Number of attempts made to find
     *      the target word.
     */
    public function getAttemptsCount()
    {
        return $this->attempts;
    }

    /**
     * Returns the number of invalid words that were
     * proposed during this game.
     *
     * \retval int
     *      Number of invalid words proposed.
     */
    public function getInvalidWordsCount()
    {
        return $this->invalidWords;
    }

    /**
     * Returns the number of words contained
     * in the currently loaded wordlists.
     *
     * \retval int
     *      Number of words in the current lists.
     */
    public function getWordsCount()
    {
        return $this->wordsCount;
    }

    /**
     * Checks whether some text is a valid word
     * for the current game.
     *
     * This method checks whether the given text
     * contains something that is recognized as
     * a word in the current range, and part of
     * the currently active wordlists.
     *
     * \param string $word
     *      Some "word" to test.
     *
     * \retval mixed
     *      \b null is returned when the given $word
     *      does not look like a word (eg. "#$!@")
     *      or is outside the game's current range.
     *      \b false is returned when this word is not
     *      part of a currently active wordlist.
     *      Otherwise, the word is returned the way
     *      it is written in the list (which may
     *      include case or accentuation variations).
     */
    protected function isValidWord($word)
    {
        if (!\Erebot\Module\Wordlists\Wordlist::isWord($word)) {
            return null;
        }

        if ($this->compareWords($this->min, $word) >= 0 ||
            $this->compareWords($word, $this->max) >= 0) {
            return null;
        }

        foreach ($this->lists as $list) {
            $res = $list->findWord($word);
            if ($res !== null) {
                return $res;
            }
        }
        return false;
    }

    /**
     * Submit a proposition for this game.
     *
     * \param string $word
     *      The word to propose.
     *
     * \retval mixed
     *      \b null is returned when the given $word
     *      does not look like a word (eg. "#$!@")
     *      or is outside the game's current range.
     *      \b true is returned when the target word
     *      has been proposed (we have a winner!).
     *      Otherwise, \b false is returned.
     *
     * \throw Erebot::Module::AZ::InvalidWordException
     *      The given $word may have looked like
     *      a valid word at first, but really isn't.
     *      Such words increase the "invalid words"
     *      counter.
     */
    public function proposeWord($word)
    {
        $res = $this->isValidWord($word);
        if ($res === null) {
            return null;
        }

        if ($res === false) {
            $this->invalidWords++;
            throw new \Erebot\Module\AZ\InvalidWordException($word);
        }

        $this->attempts++;
        $cmp = $this->compareWords($this->target, $res);
        if (!$cmp) {
            return true;
        }

        if ($cmp < 0) {
            $this->max = $res;
        } else {
            $this->min = $res;
        }
        return false;
    }

    /**
     * Returns the names of all currently
     * loaded wordlists.
     *
     * \retval array
     *      A list with the names of all
     *      currently loaded wordlists.
     */
    public function getLoadedListsNames()
    {
        $names = array();
        $nameType = \Erebot\Module\Wordlists\Wordlist::METADATA_NAME;
        foreach ($this->lists as $list) {
            $names[] = $list->getMetadata($nameType);
        }
        return $names;
    }

    /**
     * Compare two words in a case-insensitive
     * fashion.
     *
     * \param mixed $a
     *      First word to use in the comparison.
     *      Can be \b null if unavailable.
     *
     * \param mixed $b
     *      Second word to use in the comparison.
     *      Can be \b null if unavailable.
     *
     * \retval int
     *      Returns < 0 if $a is less than $b;
     *      > 0 if $a is greater than $b, and
     *      0 if they are equal. The comparison
     *      is case-insentive.
     */
    protected function compareWords($a, $b)
    {
        if ($a === null) {
            return -1;
        } elseif ($b === null) {
            return -1;
        }
        return $this->collator->compare($a, $b);
    }

    /**
     * Returns the game's duration in seconds.
     *
     * \retval int
     *      Elapsed time since the game was started.
     */
    public function getElapsedTime()
    {
        return time() - $this->started;
    }
}
