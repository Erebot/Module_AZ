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
 *  \brief
 *      Actual A-Z game implementation.
 *
 *  This class does all the heavy work.
 */
class Erebot_Module_AZ_Game
{
    /// Lower part of the current range (NULL if undefined).
    protected $_min;

    /// Higher part of the current range (NULL if undefined).
    protected $_max;

    /// The actual word that contestants must guess.
    protected $_target;

    /// Number of attempts made to find the correct word.
    protected $_attempts;

    /// Number of invalid words that were proposed (that passed WORD_FILTER).
    protected $_invalidWords;

    protected $_lists;

    protected $_collator;

    protected $_wordsCount;

    /**
     * Creates a new instance of the game.
     *
     * \param array $lists
     *      A list with the name of the dictionaries
     *      from which the target word may be selected.
     *
     * \throw Erebot_Module_AZ_NotEnoughWordsException
     *      There are not enough words in the selected
     *      lists.
     *
     * \note
     *      Invalid list names and lists whose content
     *      cannot be parsed are silently ignored.
     */
    public function __construct(Erebot_Module_Wordlists $wordlists, $lists)
    {
        $count = 0;
        $this->_collator = NULL;
        foreach ($lists as $list) {
            try {
                $wordlist       = $wordlists->getList($list);
                $collator       = $wordlist->getMetadata('locale');

                if ($this->_collator !== NULL) {
                    if ($this->_collator->getLocale(Locale::ACTUAL_LOCALE) !=
                        $collator->getLocale(Locale::ACTUAL_LOCALE)) {
                        throw new Erebot_Module_Wordlists_IncompatibleException(
                            "Incompatible wordlists"
                        );
                    }
                }
                else
                    $this->_collator = $collator;

                $count         += count($wordlist);
                $this->_lists[] = $wordlist;
            }
            catch (Erebot_Module_Wordlists_BadListNameException $e) {
            }
            catch (Erebot_Module_Wordlists_UnreadableFileException $e) {
            }
        }

        if ($count < 3)
            throw new Erebot_Module_AZ_NotEnoughWordsException();

        $target = rand(0, $count - 1);
        foreach ($this->_lists as $list) {
            $size = count($list);

            if ($target >= $size) {
                $target -= $size;
                continue;
            }

            $target = $list[$target];
            break;
        }

        $this->_attempts     =
        $this->_invalidWords = 0;
        $this->_min          =
        $this->_max          = NULL;
        $this->_wordsCount   = $count;
        $this->_target       = $target;
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
        return $this->_min;
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
        return $this->_max;
    }

    /**
     * Returns the target word for this game.
     *
     * \retval string
     *      Target word for this round.
     */
    public function getTarget()
    {
        return $this->_target;
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
        return $this->_attempts;
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
        return $this->_invalidWords;
    }

    public function getWordsCount()
    {
        return $this->_wordsCount;
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
     *      NULL is returned when the given $word
     *      does not look like a word (eg. "#$!@")
     *      or is outside the game's current range.
     *      TRUE is returned when this word is part
     *      of a currently active wordlist.
     *      Otherwise, FALSE is returned.
     */
    protected function _isValidWord($word)
    {
        if (!Erebot_Module_Wordlists_Wordlist::isWord($word))
            return NULL;

        if ($this->_compareWords($this->_min, $word) >= 0 ||
            $this->_compareWords($word, $this->_max) >= 0)
            return NULL;

        $ok = FALSE;
        foreach ($this->_lists as $list) {
            if (isset($list[$word])) {
                $ok = TRUE;
                break;
            }
        }
        unset($list);
        return $ok;
    }

    /**
     * Submit a proposition for this game.
     *
     * \param string $word
     *      The word to propose.
     *
     * \retval mixed
     *      NULL is returned when the given $word
     *      does not look like a word (eg. "#$!@")
     *      or is outside the game's current range.
     *      TRUE is returned when the target word
     *      has been proposed (we have a winner!).
     *      Otherwise, FALSE is returned.
     *
     * \throw Erebot_Module_AZ_InvalidWordException
     *      The given $word may have looked like
     *      a valid word at first, but really isn't.
     *      Such words increase the "invalid words"
     *      counter.
     */
    public function proposeWord($word)
    {
        Erebot_Module_Wordlists_Wordlist::normalizeWord($word, NULL, 'UTF-8');
        $ok = $this->_isValidWord($word);

        if ($ok === NULL)
            return NULL;

        if (!$ok) {
            $this->_invalidWords++;
            throw new Erebot_Module_AZ_InvalidWordException($word);
        }

        $this->_attempts++;
        $cmp = $this->_compareWords($this->_target, $word);
        if (!$cmp)
            return TRUE;

        if ($cmp < 0)
            $this->_max = $word;
        else
            $this->_min = $word;

        return FALSE;
    }

    public function getLoadedListsNames()
    {
        $names = array();
        foreach ($this->_lists as $list) {
            $names[] = $list->getName();
        }
        return $names;
    }

    protected function _compareWords($a, $b)
    {
        if ($a === NULL)
            return -1;
        else if ($b === NULL)
            return -1;
        return $this->_collator->compare($a, $b);
    }
}

