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

    /// Lists of words used in this game.
    protected $_lists;

    /// A collator, providing case-insensitive comparison.
    protected $_collator;

    /// Number of words in the currently loaded wordlists.
    protected $_wordsCount;

    /**
     * Creates a new instance of the game.
     *
     * \param Erebot_Module_Wordlists $wordlists
     *      An instance of Erebot_Module_Wordlists, the module
     *      responsible for providing access to wordlists.
     *
     * \param array $lists
     *      A list with the name of the dictionaries
     *      from which the target word may be selected.
     *
     * \throw Erebot_Module_AZ_IncompatibleException
     *      The given wordlists are not compatible with
     *      each other (eg. they do not use the same
     *      locales).
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
                $collator       = $wordlist->getCollator();

                if ($this->_collator !== NULL) {
                    if ($this->_collator->getLocale(Locale::ACTUAL_LOCALE) !=
                        $collator->getLocale(Locale::ACTUAL_LOCALE)) {
                        throw new Erebot_Module_AZ_IncompatibleException(
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

    /**
     * Returns the number of words contained
     * in the currently loaded wordlists.
     *
     * \retval int
     *      Number of words in the current lists.
     */
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
     *      FALSE is returned when this word is not
     *      part of a currently active wordlist.
     *      Otherwise, the word is returned the way
     *      it is written in the list (which may
     *      include case or accentuation variations).
     */
    protected function _isValidWord($word)
    {
        if (!Erebot_Module_Wordlists_Wordlist::isWord($word))
            return NULL;

        if ($this->_compareWords($this->_min, $word) >= 0 ||
            $this->_compareWords($word, $this->_max) >= 0)
            return NULL;

        foreach ($this->_lists as $list) {
            $res = $list->findWord($word);
            if ($res !== NULL)
                return $res;
        }
        return FALSE;
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
        $res = $this->_isValidWord($word);
        if ($res === NULL)
            return NULL;

        if ($res === FALSE) {
            $this->_invalidWords++;
            throw new Erebot_Module_AZ_InvalidWordException($word);
        }

        $this->_attempts++;
        $cmp = $this->_compareWords($this->_target, $res);
        if (!$cmp)
            return TRUE;

        if ($cmp < 0)
            $this->_max = $res;
        else
            $this->_min = $res;
        return FALSE;
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
        $nameType = Erebot_Module_Wordlists_Wordlist::METADATA_NAME;
        foreach ($this->_lists as $list) {
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
     *      Can be NULL if unavailable.
     *
     * \param mixed $b
     *      Second word to use in the comparison.
     *      Can be NULL if unavailable.
     *
     * \retval int
     *      Returns < 0 if $a is less than $b;
     *      > 0 if $a is greater than $b, and
     *      0 if they are equal. The comparison
     *      is case-insentive.
     */
    protected function _compareWords($a, $b)
    {
        if ($a === NULL)
            return -1;
        else if ($b === NULL)
            return -1;
        return $this->_collator->compare($a, $b);
    }
}

