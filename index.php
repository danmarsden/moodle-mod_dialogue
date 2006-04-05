<?php // $Id: index.php,v 1.6 2006/04/05 13:48:00 thepurpleblob Exp $

    require_once("../../config.php");
    require_once("lib.php");
    require_once("locallib.php");

    $id = required_param('id',PARAM_INT);

    if (!$course = get_record("course", "id", $id)) {
        error("Course ID is incorrect");
    }

    require_login($course->id);
    add_to_log($course->id, "dialogue", "view all", "index.php?id=$course->id", "");

    $strdialogue = get_string("modulename", "dialogue");
    $strdialogues = get_string("modulenameplural", "dialogue");
    $strname = get_string("name");
    $stropendialogues = get_string("opendialogues", "dialogue");
    $strcloseddialogues = get_string("closeddialogues", "dialogue");

    print_header_simple("$strdialogues", "", "$strdialogues", 
                 "", "", true, "", navmenu($course));


    if (!$dialogues = get_all_instances_in_course("dialogue", $course)) {
        notice("There are no dialogues", "../../course/view.php?id=$course->id");
        die;
    }

    $timenow = time();

    $table->head  = array ($strname, $stropendialogues, $strcloseddialogues);
    $table->align = array ("center", "center", "center");
 
    foreach ($dialogues as $dialogue) {

       if (!$cm = get_coursemodule_from_instance("dialogue", $dialogue->id, $course->id)) {
           error("Course Module ID was incorrect");
       }
       $table->data[] = array ("<a href=\"view.php?id=$cm->id\">$dialogue->name</a>",
                dialogue_count_open($dialogue, $USER), dialogue_count_closed($dialogue, $USER));
    }
    echo "<br />";
    print_table($table);

    print_footer($course);
 
?>

