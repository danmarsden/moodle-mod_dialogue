<?PHP // $Id: index.php,v 1.1 2003/10/02 16:21:02 moodler Exp $

    require_once("../../config.php");
    require_once("lib.php");

    require_variable($id);   // course

    if (! $course = get_record("course", "id", $id)) {
        error("Course ID is incorrect");
    }

    require_login($course->id);
    add_to_log($course->id, "dialogue", "view all", "index.php?id=$course->id", "");

    if ($course->category) {
        $navigation = "<A HREF=\"../../course/view.php?id=$course->id\">$course->shortname</A> ->";
    }

    $strdialogue = get_string("modulename", "dialogue");
    $strdialogues = get_string("modulenameplural", "dialogue");
    $stredit = get_string("edit");
    $strview = get_string("view");
    $strweek = get_string("week");
    $strtopic = get_string("topic");
    $strquestion = get_string("question");
    $stranswer = get_string("answer");

    print_header("$course->shortname: $strdialogues", "$course->fullname", "$navigation $strdialogues", 
                 "", "", true, "", navmenu($course));


    if (! $dialogues = get_all_instances_in_course("dialogue", $course)) {
        notice("There are no dialogues", "../../course/view.php?id=$course->id");
        die;
    }

    $timenow = time();

    if ($course->format == "weeks") {
        $table->head  = array ($strweek, $strquestion, $stranswer);
        $table->align = array ("CENTER", "LEFT", "LEFT");
    } else if ($course->format == "topics") {
        $table->head  = array ($strtopic, $strquestion, $stranswer);
        $table->align = array ("CENTER", "LEFT", "LEFT");
    } else {
        $table->head  = array ($strquestion, $stranswer);
        $table->align = array ("LEFT", "LEFT");
    }

    foreach ($dialogues as $dialogue) {

        $dialogue->timestart  = $course->startdate + (($dialogue->section - 1) * 608400);
        if (!empty($dialogue->daysopen)) {
            $dialogue->timefinish = $dialogue->timestart + (3600 * 24 * $dialogue->daysopen);
        } else {
            $dialogue->timefinish = 9999999999;
        }
        $dialogueopen = ($dialogue->timestart < $timenow && $timenow < $dialogue->timefinish);

        $entrytext = get_field("dialogue_entries", "text", "userid", $USER->id, "dialogue", $dialogue->id);

        $text = text_to_html($entrytext)."<p align=right><a href=\"view.php?id=$dialogue->coursemodule\">";

        if ($dialogueopen) {
            $text .= "$stredit</a></p>";
        } else {
            $text .= "$strview</a></p>";
        }
        if (!empty($dialogue->section)) {
            $section = "$dialogue->section";
        } else {
            $section = "";
        }
        if ($course->format == "weeks" or $course->format == "topics") {
            $table->data[] = array ($section,
                                    text_to_html($dialogue->intro),
                                    $text);
        } else {
            $table->data[] = array (text_to_html($dialogue->intro),
                                    $text);
        }
    }

    echo "<br />";

    print_table($table);

    print_footer($course);
 
?>

