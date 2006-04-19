<?php // $Id: migrate2utf8.php,v 1.1 2006/04/19 10:51:52 thepurpleblob Exp $
function migrate2utf8_dialogue_name($recordid){
    global $CFG, $globallang;

    $result = false;

/// Some trivial checks
    if (empty($recordid)) {
        log_the_problem_somewhere();
        return false;
    }

    if (!$dialogue = get_record('dialogue','id',$recordid)) {
        log_the_problem_somewhere();
        return false;
    }
    if ($globallang) {
        $fromenc = $globallang;
    } else {
        $sitelang   = $CFG->lang;
        $courselang = get_course_lang($dialogue->course);  //Non existing!
        $userlang   = get_main_teacher_lang($dialogue->course); //N.E.!!

        $fromenc = get_original_encoding($sitelang, $courselang, $userlang);
    }

/// We are going to use textlib facilities
    
/// Convert the text
    if (($fromenc != 'utf-8') && ($fromenc != 'UTF-8')) {
        $result = utfconvert($dialogue->name, $fromenc);

        $newdialogue = new object;
        $newdialogue->id = $recordid;
        $newdialogue->name = $result;
        migrate2utf8_update_record('dialogue',$newdialogue);
    }
/// And finally, just return the converted field
    return $result;
}

function migrate2utf8_forum_intro($recordid){
    global $CFG, $globallang;

    $result = false;

/// Some trivial checks
    if (empty($recordid)) {
        log_the_problem_somewhere();
        return false;
    }

    if (!$dialogue = get_record('dialogue','id',$recordid)) {
        log_the_problem_somewhere();
        return false;
    }

    if ($globallang) {
        $fromenc = $globallang;
    } else {
        $sitelang   = $CFG->lang;
        $courselang = get_course_lang($dialogue->course);  //Non existing!
        $userlang   = get_main_teacher_lang($dialogue->course); //N.E.!!

        $fromenc = get_original_encoding($sitelang, $courselang, $userlang);
    }

/// We are going to use textlib facilities
    
/// Convert the text
    if (($fromenc != 'utf-8') && ($fromenc != 'UTF-8')) {
        $result = utfconvert($dialogue->intro, $fromenc);

        $newdialogue = new object;
        $newdialogue->id = $recordid;
        $newdialogue->intro = $result;
        migrate2utf8_update_record('dialogue',$newdialogue);
    }
/// And finally, just return the converted field
    return $result;
}
?>
