Welcome to the documentation for Erebot_Module_AZ!
==================================================

Erebot_Module_AZ is a module for `Erebot`_ that provides a game called "AZ".
A word is randomly selected from a dictionary. Contestants must find this word.
Each time a word is proposed, the range of possible words is reduced.

The listing below shows a game (in french) played using Pokemon names
from the first generation as the dictionary.

..  sourcecode:: irc

    11:06:19 < Erebot> Une nouvelle partie de A-Z commence sur #jeux avec les listes suivantes : pkmn1fr.
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


Contents:

..  toctree::
    :maxdepth: 2

    generic/Installation


..  _`Erebot`:
    https://www.erebot.net/
..  _`configuration`:
    Configuration.html

.. vim: ts=4 et
