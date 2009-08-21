<?php  // $Id: view.php,v 1.7.10.14 2009/08/21 04:59:34 deeknow Exp $

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
    $group = optional_param('group',-1,PARAM_INT);
 
    if (! $cm = get_coursemodule_from_id('dialogue', $id)) {
        error("Course Module ID was incorrect");
    }

    if (! $course = get_record("course", "id", $cm->course)) {
        error("Course is misconfigured");
    }

    if (! $dialogue = get_record("dialogue", "id", $cm->instance)) {
        error("Course module is incorrect");
    }

    require_login($course, false, $cm);

    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    $hascapopen        = has_capability('mod/dialogue:open', $context);
    $hascapparticipate = has_capability('mod/dialogue:participate', $context);
    $hascapviewall     = has_capability('mod/dialogue:viewall', $context);
    $hascapmanage      = has_capability('mod/dialogue:manage', $context);
    $currentgroup = groups_get_activity_group($cm, true);
    $groupmode = groups_get_activity_groupmode($cm);

    add_to_log($course->id, "dialogue", "view", "view.php?id=$cm->id", $dialogue->id, $cm->id);

    $strdialogue = get_string("modulename", "dialogue");
    $strdialogues = get_string("modulenameplural", "dialogue");

    $navlinks = array(array('name' => $strdialogues, 'link' => "index.php?id=$course->id", 'type' => 'activity'),
                      array('name' => format_string($dialogue->name), 'link' => '', 'type' => 'activityinstance')
                     );
    $navigation = build_navigation($navlinks);

    print_header_simple(format_string($dialogue->name), "", $navigation,
                 "", "", true,
                  update_module_button($cm->id, $course->id, $strdialogue), navmenu($course, $cm));

    if (!$hascapparticipate) { // no access
        notify(get_string("notavailable", "dialogue"));
        print_footer($course);
        die;
    }

    groups_print_activity_menu($cm, "view.php?id=$cm->id&amp;pane=$pane");

    echo '<br />';
    print_simple_box(format_text($dialogue->intro), 'center', '70%', '', 5, 'generalbox', 'intro');
    echo "<br />";

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
                notify(get_string("notavailable", "dialogue"));
                print_continue("view.php?id=$cm->id");
                break;
            }
            if ($groupmode && ! $hascapmanage) {
                if ($group>0) {
                    $members = groups_get_members($group, 'u.id');
                    if (! in_array($USER->id, array_keys($members))) {
                        notify(get_string("cannotadd", "dialogue"));
                        print_continue("view.php?id=$cm->id");
                        break;
                    }
                } else {
                        notify(get_string("cannotaddall", "dialogue"));
                        print_continue("view.php?id=$cm->id");
                        break;
                }
            }
            if ($names) {
                $mform = new mod_dialogue_open_form('dialogues.php', array('names' => $names));
                $mform->set_data(array('id' => $cm->id, 
                                       'action' => 'openconversation'));
                $mform->display();

            } else {
                notify(get_string("noavailablepeople", "dialogue"));
                print_continue("view.php?id=$cm->id");
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

    print_footer($course);

?>
