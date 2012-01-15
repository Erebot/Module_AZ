Configuration
=============

.. _`configuration options`:

Options
-------

This module provides several configuration options.

..  table:: Options for |project|

    +---------------+--------+---------------+------------------------------+
    | Name          | Type   | Default value | Description                  |
    +===============+========+===============+==============================+
    | trigger       | string | "az"          | The command to use to start  |
    |               |        |               | a new AZ game. May be passed |
    |               |        |               | a list of dictionaries.      |
    +---------------+--------+---------------+------------------------------+
    | default_lists | string | all available | A list of dictionaries from  |
    |               |        | dictionaries  | which the random word will   |
    |               |        |               | be chosen, separated by      |
    |               |        |               | spaces. Used if "az" is      |
    |               |        |               | called without any argument. |
    +---------------+--------+---------------+------------------------------+


Example
-------

The recommended way to use this module is to have it loaded at the general
configuration level and to disable it only for specific networks.

..  parsed-code:: xml

    <?xml version="1.0" ?>
    <configuration
      xmlns="http://localhost/Erebot/"
      version="..."
      language="fr-FR"
      timezone="Europe/Paris"
      commands-prefix="!">

      <modules>
        <!-- Other modules ignored for clarity. -->

        <!--
          Configure the module:
          - the game will be started using the "!az" command.
          - the random word to guess will be a Pokémon name
            from either the first or second generation :)
        -->
        <module name="|project|">
          <param name="trigger" value="az" />
          <param name="default_list" value="pkmn1en pkmn2en" />
        </module>
      </modules>
    </configuration>

.. vim: ts=4 et
