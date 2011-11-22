<?php

/**
 * This page prints a particular instance of Dialogue
 * 
 * @package dialogue
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

    require_once("../../config.php");
    require_once("lib.php");
    require_once("locallib.php");
    require_once("dialogue_open_form.php");

    $id   = required_param('id', PARAM_INT);
    $pane = optional_param('pane', 1, PARAM_INT);
    $group = optional_param('group',-1, PARAM_INT);

    $PAGE->set_url('/mod/dialogue/view.php', array('id' => $id, 
                                                   'pane' => $pane,
                                                   'group' => $group));

    if (! $cm = get_coursemodule_from_id('dialogue', $id)) {
        print_error("Course Module ID was incorrect");
    }
 
    if (! $course = $DB->get_record("course", array('id' => $cm->course))) {
        print_error("Course is misconfigured");
    }

    if (! $dialogue = $DB->get_record("dialogue", array('id' => $cm->instance))) {
        print_error("Course module is incorrect");
    }

    require_login($course, false, $cm);

    $context           = get_context_instance(CONTEXT_MODULE, $cm->id); // m odule context
    $hascapopen        = has_capability('mod/dialogue:open', $context);
    $hascapparticipate = has_capability('mod/dialogue:participate', $context);
    $hascapviewall     = has_capability('mod/dialogue:viewall', $context);
    $hascapmanage      = has_capability('mod/dialogue:manage', $context);
    $currentgroup      = groups_get_activity_group($cm, true);
    $groupmode         = groups_get_activity_groupmode($cm);

    /// Some capability checks.
    if (empty($cm->visible) and !has_capability('moodle/course:viewhiddenactivities', $context)) {
        echo $OUTPUT->notification(get_string("activityiscurrentlyhidden"));
    }

    
    
    add_to_log($course->id, "dialogue", "view", "view.php?id=$cm->id", $dialogue->id, $cm->id);

    $strdialogue = get_string("modulename", "dialogue");
    $strdialogues = get_string("modulenameplural", "dialogue");

    $PAGE->set_context($context);
    $PAGE->set_title(format_string($dialogue->name));
    $PAGE->set_heading(format_string($course->fullname));

    echo $OUTPUT->header();
    
    if (!$hascapparticipate) { // no access
        echo $OUTPUT->notification(get_string("notavailable", "dialogue"));
        echo $OUTPUT->footer($course);
        die;
    }
    /// find out current groups mode
    groups_print_activity_menu($cm, new moodle_url($CFG->wwwroot . '/mod/dialogue/view.php', array('id' => $cm->id,
                                                                                                   'pane' => $pane)));
    $currentgroup = groups_get_activity_group($cm);
    $groupmode = groups_get_activity_groupmode($cm);

    /// print intro text
    echo $OUTPUT->box(format_module_intro('dialogue', $dialogue, $cm->id), 'generalbox', 'intro');

    // get some stats
    $countopen = dialogue_count_open($dialogue, $USER, $hascapviewall, $currentgroup);
    $countclosed = dialogue_count_closed($dialogue, $USER, $hascapviewall, $currentgroup);
    // set up tab table
    $names[0] = get_string("pane0", "dialogue");
    if ($countopen == 1) {
        $names[1] = get_string("pane1one", "dialogue");
    } else {
        $names[1] = get_string("pane1", "dialogue", $countopen);
    }
    if ($countclosed == 1) {
        $names[3] = get_string("pane3one", "dialogue");
    } else {
        $names[3] = get_string("pane3", "dialogue", $countclosed);
    }

    $tabs = array();
    if ($hascapopen) {
        $URL = "view.php?id=$cm->id&amp;pane=".DIALOGUEPANE_OPEN;
        $groupmode = groups_get_activity_groupmode($cm, $course);
        if (($groupmode == SEPARATEGROUPS) || ($groupmode == VISIBLEGROUPS)) { 
            // pass the user's groupid if in groups mode to view.php
            // NOTE: this defaults to users first group, needs to be updated to handle grouping mode also
            // ULPGC ecastro activity_group handles grouping & group
            if($firstgroup = groups_get_activity_group($cm, true)) {
                $URL .= "&amp;group=" . $firstgroup;
            }
        }
        $tabs[0][] =  new tabobject (0, $URL, $names[0]);
    }
    $tabs[0][] =  new tabobject (1, "view.php?id=$cm->id&amp;pane=".DIALOGUEPANE_CURRENT, $names[1]);
    $tabs[0][] =  new tabobject (3, "view.php?id=$cm->id&amp;pane=".DIALOGUEPANE_CLOSED, $names[3]);

    print_tabs($tabs, $pane, NULL, NULL, false);
    echo "<br />\n";

    $names = dialogue_get_available_users($dialogue, $context, 0);

    switch ($pane) {
        case 0: // Open dialogue
            if (! $hascapopen) {
                echo $OUTPUT->notification(get_string("notavailable", "dialogue"));
                echo $OUTPUT->continue_button("view.php?id=$cm->id");
                break;
            }
            if ($groupmode && ! $hascapmanage) {
                if ($group>0) {
                    $members = groups_get_members($group, 'u.id');
                    if (! in_array($USER->id, array_keys($members))) {
                        echo $OUTPUT->notification(get_string("cannotadd", "dialogue"));
                        echo $OUTPUT->continue_button("view.php?id=$cm->id");
                        break;
                    }
                } else {
                        echo $OUTPUT->notification(get_string("cannotaddall", "dialogue"));
                        echo $OUTPUT->continue_button("view.php?id=$cm->id");
                        break;
                }
            }
            if ($names) {
                // setup form for opening a new conversation
                $mform = new mod_dialogue_open_form('dialogues.php', array('context'=>$context,
                                                                           'names'=>$names,
                                                                           ));
                //$draftitemid = file_get_unused_draft_itemid();

                //$draftitemid = file_get_submitted_draft_itemid('attachment');
                //print_object($draftitemid);
                //file_prepare_draft_area($draftitemid, $context->id, 'mod_glossary', 'attachment', null);

                $mform->set_data(array('id' => $cm->id, 
                                       'action' => 'openconversation'));
                //,
                  //                     'attachment' => $draftitemid));
                $mform->display();

            } else {
                echo $OUTPUT->notification(get_string("noavailablepeople", "dialogue"));
                echo $OUTPUT->continue_button("view.php?id=$cm->id");
            }
            break;

        case 1: // Current dialogues
        case 2:
            // print active conversations requiring a reply from the other person.
            dialogue_list_conversations($dialogue, $currentgroup, 'open');
            break;

        case 3: // Closed dialogues
            dialogue_list_conversations($dialogue, $currentgroup, 'closed');
            break;
    }

    echo $OUTPUT->footer($course);

?>
