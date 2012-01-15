Usage
=====

This section assumes default values are used for all triggers.
Please refer to :ref:`configuration options <configuration options>`
for more information on how to customize triggers.


Provided commands
-----------------

This module provides the following commands:

..  table:: Commands provided by |project|

    +-------------------------------+---------------------------------------+
    | Command                       | Description                           |
    +===============================+=======================================+
    | :samp:`!az {wordlists...}`    | Starts a new game using the given     |
    |                               | ``wordlists`` or the default wordlist |
    |                               | if none is given.                     |
    |                               | Once the game starts, you may propose |
    |                               | words directly to the bot.            |
    +-------------------------------+---------------------------------------+
    | ``!az list`` or               | Displays a list of available          |
    | ``!az lists``                 | wordlists.                            |
    +-------------------------------+---------------------------------------+
    | ``!az cancel`` or             | Stops the current game.               |
    | ``!az end`` or                |                                       |
    | ``!az stop``                  |                                       |
    +-------------------------------+---------------------------------------+


Examples
--------

The listing below shows a game (in french) played using Pokemon names
from the first generation as the dictionary.

..  sourcecode:: irc

    10:51:13 <@Clicky> !az list
    10:51:13 < Erebot> Les listes de mots suivantes sont disponibles : english, french, haddock, pkmn1en & pkmn1fr.

    11:06:19 <@Clicky> !az pkmn1fr
    11:06:19 < Erebot> Une nouvelle partie de A-Z commence sur #erebot avec les listes suivantes : pkmn1fr (151 mots).
    11:06:40 <@foobar> ah
    11:06:40 < Erebot> ah n'existe pas ou n'est pas admissible pour ce jeu.
    11:06:42 <@foobar> pikachu
    11:06:42 < Erebot> Nouvel intervalle : ??? -- pikachu
    11:06:47 <@foobar> evoli
    11:06:47 < Erebot> Nouvel intervalle : evoli -- pikachu
    11:06:51 <@foobar> lamantine
    11:06:51 < Erebot> Nouvel intervalle : evoli -- lamantine
    11:06:54 <@foobar> feunard
    11:06:54 < Erebot> Nouvel intervalle : feunard -- lamantine
    11:06:58 <@foobar> grodoudou
    11:06:58 < Erebot> Nouvel intervalle : grodoudou -- lamantine
    11:07:07 <@foobar> insecateur
    11:07:07 < Erebot> Nouvel intervalle : grodoudou -- insecateur
    11:07:11 <@foobar> grolem
    11:07:11 < Erebot> Nouvel intervalle : grolem -- insecateur
    11:08:49 <@foobar> herbizarre
    11:08:49 < Erebot> BINGO ! La réponse était effectivement herbizarre. Félicitations foobar !
    11:08:49 < Erebot> La réponse a été trouvée après 8 essais et 1 mots incorrects.

    14:50:53 <@Clicky> !az pkmn1fr
    14:50:53 < Erebot> Une nouvelle partie de A-Z commence sur #erebot avec les listes suivantes : pkmn1fr (151 mots).
    14:50:56 <@Clicky> !az cancel
    14:50:56 < Erebot> La partie de A-Z a été arrêtée après 0 essais et 0 mots incorrects. La réponse était roucoups.

..  vim: ts=4 et
