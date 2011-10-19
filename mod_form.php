<?php
/**
 * Form to define a new instance of Dialogue or edit an instance.
 * It is used from /course/modedit.php.
 *
 * @package dialogue
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once ($CFG->dirroot.'/course/moodleform_mod.php');

class mod_dialogue_mod_form extends moodleform_mod {

    function definition() {
        global $CFG, $COURSE, $DB;

        $mform    =& $this->_form;

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('dialoguename', 'dialogue'), array('size'=>'64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $this->add_intro_editor(true, get_string('dialogueintro', 'dialogue'));

        $options = array();
        $options[0] =  get_string('no'); 
        $options[1] = get_string('yes');
        
        $mform->addElement('select', 'multipleconversations', get_string('allowmultiple', 'dialogue'), $options);
        //$mform->setHelpButton('multipleconversations', array('multiple', get_string('allowmultiple', 'dialogue'), 'dialogue', 'allowmultiple'));
        $mform->addHelpButton('multipleconversations', 'multipleconversations', 'dialogue');

        $options = array (0 => 0, 7 => 7, 14 => 14, 30 => 30, 150 => 150, 365 => 365 );
        $mform->addElement('select', 'deleteafter', get_string('deleteafter', 'dialogue'), $options);
        //$mform->setHelpButton('deleteafter', array('deleteafter', get_string('deleteafter', 'dialogue'), 'dialogue', 'deleteafter'));
        $mform->addHelpButton('deleteafter', 'deleteafter', 'dialogue');
        $mform->setAdvanced('deleteafter');

        $options = array();
        $options[0] =  get_string('no'); 
        $options[1] = get_string('yes');
        
        $mform->addElement('select', 'maildefault', get_string('mailnotification', 'dialogue'), $options);
        //$mform->setHelpButton('maildefault', array('maildefault', get_string('mailnotification', 'dialogue'), 'dialogue', 'maildefault'));
        $mform->addHelpButton('maildefault', 'maildefault', 'dialogue');
        $mform->setAdvanced('maildefault');

        $options = array();
        $options[0] =  get_string('teachertostudent', 'dialogue'); 
        $options[1] =  get_string('studenttostudent', 'dialogue'); 
        $options[2] =  get_string('everybody', 'dialogue'); 
        $mform->addElement('select', 'dialoguetype', get_string('typeofdialogue', 'dialogue'), $options);
        $mform->setType('dialoguetype', PARAM_INT);
        $mform->setDefault('dialoguetype', 0);
        //$mform->setHelpButton('dialoguetype', array('dialoguetype', get_string('typeofdialogue', 'dialogue'), 'dialogue', 'dialoguetype'));
        $mform->addHelpButton('dialoguetype', 'dialoguetype', 'dialogue');
        $mform->setAdvanced('dialoguetype');

        $mform->addElement('text', 'edittime', get_string('edittime', 'dialogue'), array('size'=>'4'));
        $mform->setType('edittime', PARAM_INT);
        $mform->setDefault('edittime', 30);
        $mform->addRule('edittime', null, 'maxlength', 5, 'client');
        $mform->addRule('edittime', null, 'numeric', null, 'client');
        //$mform->setHelpButton('edittime', array('edittime', get_string('edittime', 'dialogue'), 'dialogue', 'edittime'));
        $mform->addHelpButton('edittime', 'edittime', 'dialogue');
        $mform->setAdvanced('edittime');
        


//-------------------------------------------------------------------------------
        $features = new stdClass;
        $features->groups = true;
        $features->groupings = true;
        $features->groupmembersonly = true;
        $this->standard_coursemodule_elements($features);
//-------------------------------------------------------------------------------
        $this->add_action_buttons();
    }
}
?>
