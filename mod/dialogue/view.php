<?PHP  // $Id: view.php,v 1.1 2003/10/02 15:53:20 moodler Exp $

    require_once("../../config.php");
    require_once("lib.php");

    require_variable($id);    // Course Module ID

    if (! $cm = get_record("course_modules", "id", $id)) {
        error("Course Module ID was incorrect");
    }

    if (! $course = get_record("course", "id", $cm->course)) {
        error("Course is misconfigured");
    }

    require_login($course->id);

    if (! $dialogue = get_record("dialogue", "id", $cm->instance)) {
        error("Course module is incorrect");
    }

    add_to_log($course->id, "dialogue", "view", "view.php?id=$cm->id", "$dialogue->id");

    if (! $cw = get_record("course_sections", "id", $cm->section)) {
        error("Course module is incorrect");
    }

    if ($course->category) {
        $navigation = "<a href=\"../../course/view.php?id=$course->id\">$course->shortname</a> ->";
    }

    $strdialogue = get_string("modulename", "dialogue");
    $strdialogues = get_string("modulenameplural", "dialogue");

    print_header("$course->shortname: $dialogue->name", "$course->fullname",
                 "$navigation <a href=\"index.php?id=$course->id\">$strdialogues</a> -> $dialogue->name",
                 "", "", true,
                  update_module_button($cm->id, $course->id, $strdialogue), navmenu($course, $cm));

	// ...and if necessary set default action 
	
	optional_variable($action);
	
	if (!isguest()) { // it's a teacher or student
		if (!$cm->visible and isstudent($course->id)) {
			$action = 'notavailable';
		}
		if (empty($action)) {
			$action = 'view';
		}
	}
	else { // it's a guest, oh no!
		$action = 'notavailable';
	}
	


/*********************** dialogue not available (for gusets mainly)***********************/
	if ($action == 'notavailable') {
		print_heading(get_string("notavailable", "dialogue"));
	}


	/************ view **************************************************/
	elseif ($action == 'view') {
	
		echo "<center>\n";
		print_simple_box( text_to_html($dialogue->intro) , "center");
		echo "<br />";
		if ($names = dialogue_get_available_users($dialogue)) {
			print_simple_box_start("center");
			echo "<center>";
			echo "<form name=\"startform\" method=\"post\" action=\"dialogues.php\">\n";
			echo "<input type=\"hidden\" name=\"id\"value=\"$cm->id\">\n";
			echo "<input type=\"hidden\" name=\"action\" value=\"opendialogue\">\n";
			echo "<b>".get_string("startadialoguewith", "dialogue")." : </b>";
			choose_from_menu($names, "recipientid");
			echo " <input type=\"submit\" value=\"".get_string("startdialogue","dialogue")."\">\n";
			echo "</form>\n";
			echo "</center>";
			print_simple_box_end();
		}
		
		// print active conversations, first those requiring a reply, then the others.
		dialogue_list_conversations($dialogue, $USER);
		if (dialogue_count_closed($dialogue, $USER)) {
			$options = array ("id" => "$cm->id", "action" => "listclosed");
            echo "<center><br />";
			print_single_button("dialogues.php", $options, get_string("listcloseddialogues","dialogue"));
            echo "</center>";
		}
	}
		
	/*************** no man's land **************************************/
	else {
		error("Fatal Error: Unknown Action: ".$action."\n");
	}

    print_footer($course);

?>
