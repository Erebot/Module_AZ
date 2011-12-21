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
 *      Helpers methods for the A-Z game.
 *
 *  This class provides several methods
 *  that make Erebot_Module_AZ_Game's life
 *  a lot easier.
 */
class Erebot_Module_AZ_Utils
{
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
     * Normalizes a word.
     *
     * \param string $word
     *      Word to normalize.
     *
     * \param mixed $key
     *      This parameter is ignored.
     *
     * \param string $encoding
     *      Encoding of the word.
     *
     * \return
     *      This method does not return anything.
     *
     * \warning
     *      $word is modified in place.
     *
     * \note
     *      This method's prototype is compatible
     *      with array_filter()'s expectations.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    static public function normalizeWord(&$word, $key, $encoding)
    {
        if (function_exists('mb_strtolower'))
            $word = mb_strtolower(self::_toUTF8($word, $encoding), 'UTF-8');
        else
            $word = strtolower($word);
    }
}
