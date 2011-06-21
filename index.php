<?php

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

    $url = new moodle_url('/mod/forum/index.php', array('id'=>$id));
    $PAGE->set_url($url);

    if (! $course = $DB->get_record('course', array('id' => $id))) {
        print_error('invalidcourseid');
    }

    require_course_login($course);
    $PAGE->set_pagelayout('incourse');
    $coursecontext = get_context_instance(CONTEXT_COURSE, $course->id);

    add_to_log($course->id, 'dialogue', 'view all', "index.php?id=$course->id", '');

    $strdialogue        = get_string('modulename', 'dialogue');
    $strdialogues       = get_string('modulenameplural', 'dialogue');
    $strname            = get_string('name');
    $stropendialogues   = get_string('opendialogues', 'dialogue');
    $strcloseddialogues = get_string('closeddialogues', 'dialogue');

    if (!$dialogues = get_all_instances_in_course('dialogue', $course)) {
        notice('There are no dialogues', "../../course/view.php?id=$course->id");
        die;
    }

    /// Output the page
    $PAGE->navbar->add($strdialogues);
    $PAGE->set_title("$course->shortname: $strdialogues");
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();

    $timenow = time();

    $table = new html_table();
    $table->head  = array ($strname, $stropendialogues, $strcloseddialogues);
    $table->align = array ('center', 'center', 'center');
 
    foreach ($dialogues as $dialogue) {
        $hascapviewall = has_capability('mod/dialogue:viewall', $coursecontext);

        $dimmedclass = '';
        if (!$dialogue->visible) {      // Show dimmed if the mod is hidden
            $dimmedclass = 'class="dimmed"';
        }
        $table->data[] = array ("<a $dimmedclass href=\"view.php?id=$dialogue->coursemodule\">".format_string($dialogue->name)."</a>",
                                dialogue_count_open($dialogue, $USER),
                                dialogue_count_closed($dialogue, $USER, $hascapviewall));
    }

    echo '<br />';
    echo html_writer::table($table);

    echo $OUTPUT->footer();
 
?>

