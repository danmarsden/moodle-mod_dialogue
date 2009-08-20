<?php // $Id: index.php,v 1.8 2009/08/20 02:23:21 deeknow Exp $

/**
 * This page lists all the instances of Dialogue in a particular course
 * 
 * @package dialogue
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

    require_once('../../config.php');
    require_once('lib.php');
    require_once('locallib.php');

    $id = required_param('id', PARAM_INT);

    if (!$course = get_record('course', 'id', $id)) {
        error('Course ID is incorrect');
    }

    require_login($course);
    add_to_log($course->id, 'dialogue', 'view all', "index.php?id=$course->id", '');

    $strdialogue        = get_string('modulename', 'dialogue');
    $strdialogues       = get_string('modulenameplural', 'dialogue');
    $strname            = get_string('name');
    $stropendialogues   = get_string('opendialogues', 'dialogue');
    $strcloseddialogues = get_string('closeddialogues', 'dialogue');

    $navlinks = array(array('name' => $strdialogues, 'link' => '', 'type' => 'activity' ));
    $navigation = build_navigation($navlinks);
    
    print_header_simple($strdialogues, '', $navigation,  '', '', true, '', navmenu($course));


    if (!$dialogues = get_all_instances_in_course('dialogue', $course)) {
        notice('There are no dialogues', "../../course/view.php?id=$course->id");
        die;
    }

    $timenow = time();

    $table->head  = array ($strname, $stropendialogues, $strcloseddialogues);
    $table->align = array ('center', 'center', 'center');
 
    foreach ($dialogues as $dialogue) {
        $hascapviewall = has_capability('mod/dialogue:viewall', get_context_instance(CONTEXT_COURSE, $course->id));

        $dimmedclass = '';
        if (!$dialogue->visible) {      // Show dimmed if the mod is hidden
            $dimmedclass = 'class="dimmed"';
        }
        $table->data[] = array ("<a $dimmedclass href=\"view.php?id=$dialogue->coursemodule\">".format_string($dialogue->name)."</a>",
                                dialogue_count_open($dialogue, $USER),
                                dialogue_count_closed($dialogue, $USER, $hascapviewall));
    }

    echo '<br />';
    print_table($table);

    print_footer($course);
 
?>

