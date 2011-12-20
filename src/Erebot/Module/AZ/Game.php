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
    /// Pattern used to recognize (composed) "words".
    const WORD_FILTER = '@^
        [\\p{N}\\p{L}\\-\\.\\(\\)_\']+          # A "word", ie. a sequence of
                                                # Unicode letters/numbers plus
                                                # some additional characters.
        (?:\\ [\\p{N}\\p{L}\\-\\.\\(\\)_\']+)?  # Another such word.
        $@ux';

    /// Path to the directory where wordlists are kept.
    static protected $_wordlistsDir = NULL;

    /// List of available wordlists.
    static protected $_availableLists = NULL;

    /**
     * \brief
     *      Maps each currently active list's name
     *      to a list of its words.
     *
     * That is, it maps each of the wordlists given to
     * this class' constructor to the words it contains.
     */
    protected $_loadedLists;

    /// First word in the currently selected wordlists.
    protected $_min;

    /// Last word in the currently selected wordlists.
    protected $_max;

    /// The actual word that contestants must guess.
    protected $_target;

    /// Number of attempts made to find the correct word.
    protected $_attempts;

    /// Number of invalid words that were proposed (that passed WORD_FILTER).
    protected $_invalidWords;

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
    public function __construct($lists)
    {
        self::getAvailableLists();
        $wordlist = array();
        $this->_loadedLists = array();
        foreach ($lists as $list) {
            try {
                $this->loadWordlist($list);
                $wordlist = array_merge($wordlist, $this->_loadedLists[$list]);
            }
            catch (Erebot_Module_AZ_BadListNameException $e) {
            }
            catch (Erebot_Module_AZ_UnreadableFileException $e) {
            }
        }

        $wordlist = array_unique($wordlist);
        if (count($wordlist) < 3)
            throw new Erebot_Module_AZ_NotEnoughWordsException();

        $this->_attempts     =
        $this->_invalidWords = 0;
        $this->_min          =
        $this->_max          = NULL;
        $this->_target       = $wordlist[array_rand($wordlist)];
    }

    /// Destructor.
    public function __destruct()
    {
        unset($this->_loadedLists);
    }

    /**
     * Returns the names of available lists.
     *
     * \retval list
     *      The names of all the lists available.
     */
    static public function getAvailableLists()
    {
        if (self::$_wordlistsDir === NULL) {
            $base = '@data_dir@';
            // Running from the repository.
            if ($base == '@'.'data_dir'.'@') {
                $parts = array(
                    dirname(dirname(dirname(dirname(dirname(__FILE__))))),
                    'vendor',
                    'Erebot_Module_Wordlists',
                    'data',
                );
            }
            else {
                $parts = array(
                    dirname($base . DIRECTORY_SEPARATOR),
                    'Erebot_Module_Wordlists',
                );
            }
            self::$_wordlistsDir = implode(DIRECTORY_SEPARATOR, $parts);
        }

        if (self::$_availableLists === NULL) {
            self::$_availableLists = array();
            $files = scandir(self::$_wordlistsDir);
            foreach ($files as $file) {
                if (substr($file, -4) == '.txt')
                    self::$_availableLists[] = substr($file, 0, -4);
            }
        }
        return self::$_availableLists;
    }

    /**
     * Returns a list of currently active wordlists.
     *
     * An active wordlist is a list whose name was
     * passed to this class' constructor, is valid
     * and was successfully parsed.
     *
     * \retval list
     *      The names of currently active wordlists.
     */
    public function getLoadedListsNames()
    {
        return array_keys($this->_loadedLists);
    }

    /**
     * Filters non-words out.
     *
     * Only texts that passed this filtering step
     * are considered as propositions for the game.
     *
     * \param string $word
     *      A possible "word" to test.
     *
     * \retval bool
     *      TRUE if the given $word really is a word,
     *      FALSE otherwise.
     *
     * \note
     *      This method uses a rather broad definition
     *      of what is a word. In particular, it accepts
     *      sequences of (alphanumeric and other) characters
     *      separated using a single space (eg. "Fo'o. B4-r_").
     */
    protected function _filterWord($word)
    {
        return (bool) preg_match(self::WORD_FILTER, $word);
    }

    /**
     * Loads a list of words.
     *
     * \param string $list
     *      Name of the list to load.
     *
     * \throw Erebot_Module_AZ_BadListNameException
     *      The given $list is not a valid list name.
     *
     * \throw Erebot_Module_AZ_UnreadableFileException
     *      The content of the given $list could not
     *      be parsed.
     */
    protected function loadWordlist($list)
    {
        if (isset($this->_loadedLists[$list]))
            return;

        if (!in_array($list, self::$_availableLists, TRUE))
            throw new Erebot_Module_AZ_BadListNameException($list);

        $file = self::$_wordlistsDir.DIRECTORY_SEPARATOR.$list.'.txt';
        if (!is_readable($file))
            throw new Erebot_Module_AZ_UnreadableFileException($file);

        $wordlist = file($file);
        if ($wordlist === FALSE)
            throw new Erebot_Module_AZ_UnreadableFileException($file);
        $wordlist = array_map('trim', $wordlist);

        $encoding = 'ASCII';
        if (isset($wordlist[0][0]) && $wordlist[0][0] == '#')
            $encoding = trim(substr(array_shift($wordlist), 1));
        $encodingArray = array_fill(0, count($wordlist), $encoding);

        $wordlist = array_map(
            array($this, '_toUTF8'),
            $wordlist,
            $encodingArray
        );
        $wordlist = array_map('strtolower', $wordlist);
        $wordlist = array_filter($wordlist, array($this, '_filterWord'));
        $this->_loadedLists[$list] = $wordlist;
    }

    /**
     * Encodes some text in UTF-8.
     *
     * \param string $text
     *      Text to encode in UTF-8.
     *
     * \param string $from
     *      (optional) The text's current encoding.
     *
     * \retval string
     *      The text, encoded in UTF-8 or returned
     *      without any modification in case no
     *      mechanism could be found to change
     *      the text's encoding.
     *
     * \note
     *      This method has been duplicated from Erebot_Utils
     *      as we need a way to convert some random text to UTF-8
     *      without depending on Erebot's inner workings.
     *
     * \warning
     *      Contrary to Erebot's method, this method does not
     *      throw an exception when the given text could not
     *      be encoded in UTF-8 (because no mechanism could be
     *      found to do so). Instead, the text is returned
     *      unchanged.
     */
    static protected function _toUTF8($text, $from='iso-8859-1')
    {
        if (!strcasecmp($from, 'utf-8'))
            return $text;

        if (!strcasecmp($from, 'iso-8859-1') &&
            function_exists('utf8_encode'))
            return utf8_encode($text);

        if (function_exists('iconv'))
            return iconv($from, 'UTF-8', $text);

        if (function_exists('recode'))
            return recode($from.'..utf-8', $text);

        if (function_exists('mb_convert_encoding'))
            return mb_convert_encoding($text, 'UTF-8', $from);

        if (function_exists('html_entity_decode'))
            return html_entity_decode(
                htmlentities($text, ENT_QUOTES, $from),
                ENT_QUOTES,
                'UTF-8'
            );

        return $text;
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
     * Checks whether some text is a valid word.
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
    protected function _checkWord($word)
    {
        if (!preg_match(self::WORD_FILTER, $word))
            return NULL;

        if ($this->_min !== NULL && strcmp($this->_min, $word) >= 0)
            return NULL;

        if ($this->_max !== NULL && strcmp($this->_max, $word) <= 0)
            return NULL;

        $ok = FALSE;
        foreach ($this->_loadedLists as &$list) {
            if (in_array($word, $list)) {
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
        $word = strtolower($word);
        $ok = $this->_checkWord($word);

        if ($ok === NULL)
            return NULL;

        if (!$ok) {
            $this->_invalidWords++;
            throw new Erebot_Module_AZ_InvalidWordException($word);
        }

        $this->_attempts++;
        $cmp = strcmp($this->_target, $word);
        if (!$cmp)
            return TRUE;

        if ($cmp < 0)
            $this->_max = $word;
        else
            $this->_min = $word;

        return FALSE;
    }
}

