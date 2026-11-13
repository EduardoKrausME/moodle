<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file keeps track of upgrades to
 * the forum module
 *
 * Sometimes, changes between versions involve
 * alterations to database structures and other
 * major things that may break installations.
 *
 * The upgrade function in this file will attempt
 * to perform all the necessary actions to upgrade
 * your older installation to the current version.
 *
 * If there's something it cannot do itself, it
 * will tell you what you need to do.
 *
 * The commands in here will all be database-neutral,
 * using the methods of database_manager class
 *
 * Please do not forget to use upgrade_set_timeout()
 * before any action that may take longer time to finish.
 *
 * @package   mod_forum
 * @copyright 2003 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function xmldb_forum_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    // Automatically generated Moodle v4.4.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v4.5.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v5.0.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v5.1.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2025110500) {
        // Define field showimmediately to be added to forum.
        $table = new xmldb_table('forum');
        $field = new xmldb_field('showimmediately', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'lockdiscussionafter');

        // Conditionally launch add field showimmediately.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Forum savepoint reached.
        upgrade_mod_savepoint(true, 2025110500, 'forum');
    }

    if ($oldversion < 2026022300) {
        // Changing precision of field name on table forum to (1333).
        $table = new xmldb_table('forum');
        $field = new xmldb_field('name', XMLDB_TYPE_CHAR, '1333', null, XMLDB_NOTNULL, null, null, 'type');

        // Launch change of precision for field name.
        $dbman->change_field_precision($table, $field);

        // Forum savepoint reached.
        upgrade_mod_savepoint(true, 2026022300, 'forum');
    }

    // Automatically generated Moodle v5.2.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v5.3.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2026111200) {
        $table = new xmldb_table('forum');
        $field = new xmldb_field('showinmycourses', XMLDB_TYPE_INTEGER, '1', null,
            XMLDB_NOTNULL, null, '1', 'showimmediately');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026111200, 'forum');
    }

    if ($oldversion < 2026111210) {
        // Optional per-forum accepted solutions; existing forums remain unchanged.
        $table = new xmldb_table('forum');
        $field = new xmldb_field('allowsolutions', XMLDB_TYPE_INTEGER, '1', null,
            XMLDB_NOTNULL, null, '0', 'showinmycourses');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('forum_solutions');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('discussionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('postid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('discussion', XMLDB_KEY_FOREIGN, ['discussionid'], 'forum_discussions', ['id']);
            $table->add_key('post', XMLDB_KEY_FOREIGN, ['postid'], 'forum_posts', ['id']);
            $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
            $dbman->create_table($table);
        }
        upgrade_mod_savepoint(true, 2026111210, 'forum');
    }

    if ($oldversion < 2026111300) {
        // Guarantee a single accepted reply per discussion at the database level,
        // including when separate workers or restore tasks write concurrently.
        $table = new xmldb_table('forum_solutions');
        $index = new xmldb_index('discussionunique', XMLDB_INDEX_UNIQUE, ['discussionid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        upgrade_mod_savepoint(true, 2026111300, 'forum');
    }

    if ($oldversion < 2026111301) {
        // index_exists() checks column names but not the UNIQUE property.
        // Replace the old non-unique foreign-key index, which otherwise makes
        // a unique index appear present on some supported database drivers.
        $table = new xmldb_table('forum_solutions');
        $hasunique = false;
        foreach ($DB->get_indexes('forum_solutions') as $existingindex) {
            if (!empty($existingindex['unique']) &&
                    array_values($existingindex['columns']) === ['discussionid']) {
                $hasunique = true;
                break;
            }
        }
        if (!$hasunique) {
            $oldkey = new xmldb_key('discussion', XMLDB_KEY_FOREIGN,
                ['discussionid'], 'forum_discussions', ['id']);
            $dbman->drop_key($table, $oldkey);
            $newkey = new xmldb_key('discussion', XMLDB_KEY_FOREIGN_UNIQUE,
                ['discussionid'], 'forum_discussions', ['id']);
            $dbman->add_key($table, $newkey);
        }
        upgrade_mod_savepoint(true, 2026111301, 'forum');
    }

    return true;
}
