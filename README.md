Dialogue module for Moodle (http://moodle.org/)
===============================================

The Dialogue module allows for private conversation between two or more parties.

They can be useful when the teacher wants a place to give private feedback to a
student on their online activity. For example, if a student is participating in
a language forum and made a grammatical error that the teacher wants to point
out without embarrassing the student, a dialogue is the perfect place.

Branches
--------
The git branches here support the following versions.

| Moodle version        | Branch      |
| -----------------     | ----------- |
| Mooodle 3.5 - 3.8     | MOODLE_35_STABLE |
| Moodle 3.9 - 3.10     | MOODLE_39_STABLE |
| Moodle 3.11           | MOODLE_311_STABLE |
| Moodle 4.0 and higher | MOODLE_400_STABLE |

Contributions
-------------
Originally written by Ray Kingdon

Rewritten by Troy Williams - 2013

Contributions by many others


Download
--------
https://moodle.org/plugins/view.php?plugin=mod_dialogue


Installation
------------
01) Make a folder called dialogue under /mod folder so that you have a /mod/dialogue folder.

02) Uncompress archive and copy the files into the /mod/dialogue folder.

03) Go to the /admin page and allow the module to be installed.

Upgrading
---------
Before upgrading it is advisable that you test the upgrade first on a COPY of your production site, to make sure it works as you expect.

### Backup important data ###
There are three areas that should be backed up before any upgrade:

* Moodle dataroot (For example, server/moodledata)
* Moodle database (For example, your Postgres or MySQL database dump)

### Version specific ###

#### Dialogue 2.8 ####
You can only upgrade from Dialogue 2.5 or later.

#### Dialogue 2.7, 2.6, 2.5 ####
You can only upgrade from Dialogue 2.0 or later. There are major changes to schema, and you with have to either upgrade dialogues manually from Moodle admin or the cli script cli/upgrade.php.
