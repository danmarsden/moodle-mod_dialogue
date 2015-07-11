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

defined('MOODLE_INTERNAL') || die();

/**
 * A custom renderer class that extends the plugin_renderer_base and is used by
 * the dialogue module.
 *
 * @package   mod_dialogue
 * @copyright 2013 Troy Williams
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_dialogue_renderer extends plugin_renderer_base {
    public function open_rules_listing(mod_dialogue\open_rules_list $list) {
        global $USER, $PAGE, $OUTPUT;


        $dialogue = $list->dialogue;
        $course = $dialogue->course;
        $groupcache = groups_cache_groupdata($course->id);
        $records = $list->records();
        $rowsmatched = $list->rows_matched();
        $rowsreturned = count($records);

        $page = $list->page;
        $pageurl = $PAGE->url;

        $html = '';
        $html .= html_writer::start_div('listing-meta');
        $html .= html_writer::tag('span', get_string('displaying', 'dialogue'));

        $a         = new stdClass();
        $a->start  = ($list->page) ? $list->page * $list->limit : 1;
        $a->end    = $list->page * $list->limit + $rowsreturned;
        $a->total  = $rowsmatched;
        $html .= html_writer::start_div('dropdown pull-right');
        $html .= html_writer::tag('span', get_string('listpaginationheader', 'dialogue', $a));
        /* Caret
        $html .= html_writer::start_tag('a', array('href'=>'#',
                                                   'class'=>'dropdown-toggle',
                                                   'data-toggle'=>'dropdown',
                                                   'role'=>'button',
                                                   'aria-haspopup'=>'true',
                                                   'aria-expanded'=>'false'));

        $html .= html_writer::tag('span', '', array('class'=>'caret'));
        $html .= html_writer::end_tag('a');
        */
        $html .= html_writer::start_tag('ul', array('class'=>'dropdown-menu'));
        $html .= html_writer::tag('div', 'some text...');
        $html .= html_writer::end_tag('ul');
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();

        $html .= html_writer::start_tag('table', array('class'=>'conversation-list table table-hover table-condensed'));
        $html .= html_writer::start_tag('tbody');
        foreach ($records as $record) {
            $html .= html_writer::start_tag('tr'); // start the row
            if ($record->lastrun) {
                $lastrun = get_string('lastranon', 'dialogue') . userdate($record->lastrun);
            } else {
                $lastrun = get_string('hasnotrun', 'dialogue');
            }
            $html .= html_writer::tag('td', $lastrun);

            if ($record->includefuturemembers) {
                if ($record->cutoffdate > time()) {
                    $runsuntil = html_writer::tag('i', get_string('runsuntil', 'dialogue') . userdate($record->cutoffdate));
                    $html .= html_writer::tag('td', $runsuntil);
                } else {
                    $html .= html_writer::tag('td', get_string('completed', 'dialogue'));
                }
            } else {
                if (!$record->lastrun) {
                    $html .= html_writer::tag('td', '');
                } else {
                    $html .= html_writer::tag('td', get_string('completed', 'dialogue'));
                }
            }
            if ($record->type == 'group') {
                $html .= html_writer::tag('td', $groupcache->groups[$record->sourceid]->name);

            } else {
                $html .= html_writer::tag('td', get_string('allparticipants'));
            }

            $subject = empty($record->subject) ? get_string('nosubject', 'dialogue') : $record->subject;
            $subject = html_writer::tag('strong', $subject);
            $html .= html_writer::tag('td', $subject);

            $statelabel = '';
            if ($record->state == \mod_dialogue\dialogue::STATE_CLOSED) {
                $statelabel = html_writer::tag('span', get_string('closed', 'dialogue'),
                    array('class'=>'state-indicator state-closed'));
            } else {
                $statelabel = html_writer::tag('span', get_string('open', 'dialogue'),
                    array('class'=>'state-indicator state-open'));
            }
            $html .= html_writer::tag('td', $statelabel);

            $params = array('id' => $record->conversationid);
            if ($record->state == \mod_dialogue\dialogue::STATE_OPEN) {
                $icon   = $OUTPUT->pix_icon('i/edit', get_string('edit'));
                $url    = new moodle_url('conversation/openrule/edit.php', $params);
                $link   = html_writer::link($url, $icon);
                $html .= html_writer::tag('td', $link);
            } else {
                $html .= html_writer::tag('td', '');
            }
            $icon   = $OUTPUT->pix_icon('i/info', get_string('info'));
            $url    = new moodle_url('conversation/openrule/view.php', $params);
            $link   = html_writer::link($url, $icon);
            $html .= html_writer::tag('td', $link);

            $html .= html_writer::end_tag('tr');
        }
        $html .= html_writer::end_tag('tbody');
        $html .= html_writer::end_tag('table');

        $pagination = new paging_bar($rowsmatched, $page, \mod_dialogue\dialogue::PAGINATION_PAGE_SIZE, $pageurl);
        $html .= $this->render($pagination);

        return $html;

    }
    public function conversation_listing(mod_dialogue\conversations_list $list) {
        global $USER, $PAGE, $OUTPUT;


        $dialogue = $list->dialogue;
        $records = $list->records();
        $rowsmatched = $list->rows_matched();
        $rowsreturned = count($records);

        $page = $list->page;
        $pageurl = $PAGE->url;

        $html = '';
        $html .= html_writer::start_div('listing-meta');
        $html .= html_writer::tag('span', get_string('displaying', 'dialogue'));

        $a         = new stdClass();
        $a->start  = ($list->page) ? $list->page * $list->limit : 1;
        $a->end    = $list->page * $list->limit + $rowsreturned;
        $a->total  = $rowsmatched;


        $html .= html_writer::start_div('dropdown pull-right');

        $html .= html_writer::tag('span', get_string('listpaginationheader', 'dialogue', $a));
        /* Caret
        $html .= html_writer::start_tag('a', array('href'=>'#',
                                                   'class'=>'dropdown-toggle',
                                                   'data-toggle'=>'dropdown',
                                                   'role'=>'button',
                                                   'aria-haspopup'=>'true',
                                                   'aria-expanded'=>'false'));

        $html .= html_writer::tag('span', '', array('class'=>'caret'));
        $html .= html_writer::end_tag('a');
        */
        $html .= html_writer::start_tag('ul', array('class'=>'dropdown-menu'));
        $html .= html_writer::tag('div', 'some text...');
        $html .= html_writer::end_tag('ul');
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();

        $html .= html_writer::start_tag('table', array('class'=>'conversation-list table table-hover table-condensed'));
        $html .= html_writer::start_tag('tbody');
        foreach ($records as $record) {

            $conversationid = $record['id'];
            $html .= html_writer::start_tag('tr'); // start the row

            //$html .= html_writer::tag('td', $record['id']);

            $badge = '';
            $unreadcount = $record['messagecount'] - $record['seencount'];
            if ($unreadcount > 0) {
                $badgeclass = 'badge label-info';
                $badge = html_writer::span($unreadcount, $badgeclass, array('title'=>get_string('numberunread', 'dialogue', $unreadcount)));
            }
            $html .= html_writer::tag('td', $badge);



            if ($USER->id == $record['recipient']['id']) {
                $displayuser = (object) $record['organizer'];
                $avatar = $OUTPUT->user_picture($displayuser, array('class'=> 'userpicture img-rounded', 'size' => 48));
                $fullname = fullname($displayuser);
            } else {
                $displayuser = (object) $record['recipient'];
                $avatar = $OUTPUT->user_picture($displayuser, array('class'=> 'userpicture img-rounded', 'size' => 48));
                $fullname = fullname($displayuser);
            }


            $html .= html_writer::start_tag('td');
            $participants = dialogue_get_conversation_participants($dialogue, $record['id']);
            if ($participants) {

                $html .= html_writer::start_div('participants');
                foreach($participants as $participant) {
                    $participant = (object) $participant;
                    $class = 'userpicture img-rounded';
                    if ($participant->id == $record['organizer']['id']) {
                        $class .= ' organizer';
                        $participant->imagealt = 'Organised by ' . fullname($participant);
                    }
                    $html .= $OUTPUT->user_picture($participant, array('class'=>$class, 'size'=>16));
                }
                $html .= html_writer::end_div();
            }
            $html .= html_writer::end_tag('td');

            //$html .= html_writer::start_tag('td');
            //$html .= $avatar;
            //$html .= html_writer::tag('span', fullname($displayuser), array('class'=>'fullname'));
            //$html .= html_writer::end_tag('td');

            $html .= html_writer::tag('td', $avatar);
            $html .= html_writer::tag('td', fullname($displayuser));

            $html .= html_writer::start_tag('td');
            $subject = empty($record['subject']) ? get_string('nosubject', 'dialogue') : $record['subject'];
            $subject = html_to_text($subject, 0, false);
            $url = new moodle_url('/mod/dialogue/conversation/view.php', array('id'=>$conversationid));
            $html .= html_writer::start_tag('a', array('href'=>$url->out(false)));
            $html .= html_writer::tag('strong', shorten_text($subject, 60));
            $html .= html_writer::start_div();
            $body = format_text($record['lastmessage']['body'], $record['lastmessage']['bodyformat']);
            $body = html_to_text($body, 0, false);
            $body = shorten_text($body, 75);
            $html .= html_writer::tag('span', $body);
            $html .= html_writer::end_tag('a');
            $html .= html_writer::end_div();
            $html .= html_writer::end_tag('td');

            $html .= html_writer::start_tag('td');
            $author = (object) $record['lastmessage']['author'];
            $author->imagealt = 'Last post by ' . fullname($author);
            $picture = $OUTPUT->user_picture($author, array('class'=>'userpicture img-rounded', 'size'=>16));
            $html .= html_writer::tag('span', $picture,
                array('class' => 'participant', 'title' => fullname($author)));


            $timemodified = $record['lastmessage']['postdate'];
            $datestrings = (object) dialogue_get_humanfriendly_dates($timemodified);
            if ($timemodified >= strtotime("today")) {
                $datetime = $datestrings->timepast;
            } else if ($timemodified >= strtotime("-1 year")) {
                $datetime = get_string('dateshortyear', 'dialogue', $datestrings);
            } else {
                $datetime = get_string('datefullyear', 'dialogue', $datestrings);
            }
            $html .= html_writer::tag('span', $datetime, array('title' => userdate($timemodified)));
            $html .= html_writer::end_tag('td');

            $statelabel = '';
            if ($record['lastmessage']['state'] == \mod_dialogue\dialogue::STATE_CLOSED) {
                $statelabel = html_writer::tag('span', get_string('closed', 'dialogue'),
                    array('class'=>'state-indicator state-closed'));
            }
            $html .= html_writer::tag('td', $statelabel);

            $icon   = $OUTPUT->pix_icon('t/message', get_string('view'));
            $url    = new moodle_url('conversation/view.php', array('id' => $conversationid));
            $link   = html_writer::link($url, $icon);
            $messagecount = html_writer::span($record['messagecount'], 'messagecount');
            $html .= html_writer::tag('td', $link . $messagecount);

            $html .= html_writer::end_tag('tr');
        }
        $html .= html_writer::end_tag('tbody');
        $html .= html_writer::end_tag('table');

        $pagination = new paging_bar($rowsmatched, $page, \mod_dialogue\dialogue::PAGINATION_PAGE_SIZE, $pageurl);

        $html .= $this->render($pagination);

        return $html;
    }



     public function dialogue_search_form($q = '', $url = null) {
         global $PAGE;

         $pageurl   = $PAGE->url;
         $cmid      = $PAGE->cm->id;

         $inputid = 'dialogue-search-input';
         $inputsize = 30;
         $formid = 'dialogue-searcher';
         $form = array('id' => $formid, 'action' => $pageurl, 'method' => 'get', 'class' => "form-inline", 'role' => 'form');
         $searchlabel= get_string("filterbyname", "dialogue");

         $output = '';
         $output .= html_writer::start_tag('form', $form);
         $output .= html_writer::start_div('input-group');
         $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'id', 'value' => $cmid));
         $output .= html_writer::tag('label', $searchlabel, array('for' => $inputid, 'class' => 'sr-only'));
         $search = array('type' => 'text', 'id' => $inputid,  'name' => 'q',
             'class' => 'form-control', 'size' => $inputsize, 'value' => s($q), 'placeholder' => $searchlabel);
         $output .= html_writer::empty_tag('input', $search);
         $button = array('type' => 'submit', 'class' => 'btn btn-search');
         $output .= html_writer::start_span('input-group-btn');
         $output .= html_writer::tag('button', get_string('go'), $button);
         $output .= html_writer::end_span();
         $output .= html_writer::end_div(); // Close form-group.
         $output .= html_writer::end_tag('form');

         return $output;
     }


    /**
     * Render conversation, just the conversation
     *
     * @global type $PAGE
     * @global type $OUTPUT
     * @global type $USER
     * @param dialogue_conversation $conversation
     * @return string
     */
    public function render_conversation(mod_dialogue\conversation $conversation) {
        global $PAGE, $OUTPUT, $USER;

        $context = $conversation->dialogue->context; // fetch context from parent dialogue
        $cm      = $conversation->dialogue->cm; // fetch course module from parent dialogue

        $today    = strtotime("today");
        $yearago  = strtotime("-1 year");

        $html = '';

        $html .= html_writer::start_div('conversation-heading');
        $html .= html_writer::tag('h3', $conversation->subject, array('class' => 'heading'));

        if ($conversation->state == \mod_dialogue\dialogue::STATE_OPEN) {
            $span = html_writer::tag('span', get_string('open', 'dialogue'), array('class' => "state-indicator state-open"));
            $html .= html_writer::tag('h3', $span, array('class' => 'heading pull-right'));
        }

        if ($conversation->state == \mod_dialogue\dialogue::STATE_CLOSED) {
            $span = html_writer::tag('span', get_string('closed', 'dialogue'), array('class' => "state-indicator state-closed"));
            $html .= html_writer::tag('h3', $span, array('class' => 'heading pull-right'));
        }

        if ($conversation->state == \mod_dialogue\dialogue::STATE_BULK_AUTOMATED) {
            $span = html_writer::tag('span', get_string('bulkopener', 'dialogue'), array('class' => "state-indicator state-bulk"));
            $html .= html_writer::tag('h3', $span, array('class' => 'heading pull-right'));
        }

        $html .= html_writer::end_div(); // close header

        $html .= html_writer::start_div('conversation');
        $messageid = 'm' . $conversation->messageid;

        $html .= html_writer::tag('a', '', array('id' => $messageid));
        $avatar = $OUTPUT->user_picture($conversation->author, array('size' => true, 'class' => 'userpicture img-rounded'));

        $html .= html_writer::div($avatar, 'conversation-object pull-left');

        $html .= html_writer::start_div('conversation-body');

        $datestrings = (object) dialogue_get_humanfriendly_dates($conversation->timemodified);
        $datestrings->fullname = fullname($conversation->author); //sneaky
        if ($conversation->timemodified >= $today) {
            $openedbyheader = get_string('openedbytoday', 'dialogue', $datestrings);
        } else if ($conversation->timemodified >= $yearago) {
            $openedbyheader = get_string('openedbyshortyear', 'dialogue', $datestrings);
        } else {
            $openedbyheader = get_string('openedbyfullyear', 'dialogue', $datestrings);
        }

        $html .= html_writer::start_div('conversation-header');
        $html .= html_writer::tag('span', $openedbyheader, array('class' => 'conversation-openedby pull-left'));

        $html .= html_writer::start_tag('ul', array('class' => "message-actions pull-right"));

        if ($conversation->state == \mod_dialogue\dialogue::STATE_OPEN) {
            $canclose = ((has_capability('mod/dialogue:close', $context) and $USER->id == $conversation->author->id) or
                          has_capability('mod/dialogue:closeany', $context));


            if ($canclose) {
                $lockicon = html_writer::tag('i', '', array('class' => "fa fa-lock"));
                $html .= html_writer::start_tag('li');
                $closeurl = new moodle_url('/mod/dialogue/conversation/close.php');

                $closeurl->param('id', $conversation->conversationid);

                $html .= html_writer::link($closeurl,  get_string('closeconversation', 'dialogue') . $lockicon);
                $html .= html_writer::end_tag('li');
            }
        }

        $candelete = ((has_capability('mod/dialogue:delete', $context) and $USER->id == $conversation->author->id) or
                       has_capability('mod/dialogue:deleteany', $context));

        if ($candelete) {
            $html .= html_writer::start_tag('li');
            $trashicon = html_writer::tag('i', '', array('class' => "fa fa-trash-o"));
            $deleteurl = new moodle_url('/mod/dialogue/conversation/delete.php');
            $deleteurl->param('id', $conversation->conversationid);
            $html .= html_writer::link($deleteurl,  get_string('deleteconversation', 'dialogue') . $trashicon);
            $html .= html_writer::end_tag('li');
        }

        $html .= html_writer::end_tag('ul');
        $html .= html_writer::empty_tag('br');
        $html .= html_writer::end_div();

        $html .= html_writer::empty_tag('hr');
        $html .= $conversation->bodyhtml;
        $html .= $this->render_attachments($conversation->attachments);
        $html .= html_writer::end_div();
        //$html .= html_writer::end_div();

        // Display list of people who have received this conversation.
        // @todo - display rest of information, which group, has completed? etc
        if ($conversation->state == \mod_dialogue\dialogue::STATE_BULK_AUTOMATED) {
            $receivers = $conversation->receivedby;
            if ($receivers) {
                $html .= html_writer::start_div('participants receivedby');
                $count = count($receivers);
                if ($count == 1) {
                    $openedwithstring = get_string('conversationopenedwith', 'dialogue');
                } else {
                    $openedwithstring = get_string('conversationsopenedwith', 'dialogue', $count);
                }
                $html .= html_writer::span($openedwithstring);
                $html .= html_writer::start_tag('table', array('class'=>'table')); //table-condensed
                $html .= html_writer::start_tag('tbody');
                $sentonstring = new lang_string('senton', 'dialogue');
                foreach ($receivers as $receivedby) {
                    $person = dialogue_get_user_details($conversation->dialogue, $receivedby->userid);
                    $html .= html_writer::start_tag('tr');
                    $picture = $OUTPUT->user_picture($person, array('class' => 'userpicture img-rounded', 'size' => 20));
                    $html .= html_writer::tag('td', $picture);
                    $html .= html_writer::tag('td', fullname($person));
                    $html .= html_writer::tag('td', $sentonstring . userdate($receivedby->timemodified));
                    $html .= html_writer::end_tag('tr');
                }
                $html .= html_writer::end_tag('tbody');
                $html .= html_writer::end_tag('table');
                $html .= html_writer::end_div();
            }
        }
        // This should only display on open and closed conversations @todo - tidy + css
        $participants = $conversation->get_participants();
        if ($participants) {
            $html .= html_writer::start_div('participants');
            $html .= html_writer::tag('strong', count($participants));
            $html .= '&nbsp;' . get_string('participants', 'dialogue');
            foreach ($participants as $participant) {
                $picture = $OUTPUT->user_picture($participant, array('class' => 'userpicture img-rounded', 'size' => 20));
                $html .= html_writer::tag('span', $picture . '&nbsp;' . fullname($participant), array('class' => 'participant'));
            }
            $html .= html_writer::end_div();
        }
        $html .= html_writer::end_div(); // end of main conversation
        $html .= html_writer::empty_tag('hr');

        return $html;
    }

    /**
     * Render a list of conversations in a dialogue for a particular user.
     *
     * @global global $OUTPUT
     * @global global $PAGE
     * @param mod_dialogue_conversations $conversations
     * @return string
     */
    public function conversation_listing1(\mod_dialogue\conversations $conversations) {
        global $OUTPUT, $PAGE;

        $dialogue = $conversations->dialogue;
        $cm       = $conversations->dialogue->cm;

        $list = array();

        $html = '';

        $rowsmatched = $conversations->rows_matched();
        if ($rowsmatched) {
            $list = $conversations->records();
        }

        if (empty($list)) {
            $html .= '<br/><br/>';
            $html .= $OUTPUT->notification(get_string('noconversationsfound', 'dialogue'), 'notifyproblem');
        } else {
            $today    = strtotime("today");
            $yearago  = strtotime("-1 year");

            $rowsreturned = count($list);

            $html .= html_writer::start_div('listing-meta');
            $html .= html_writer::tag('h6', get_string('displaying', 'dialogue'));
            $a         = new stdClass();
            $a->start  = ($conversations->page) ? $conversations->page * $conversations->limit : 1;
            $a->end    = $conversations->page * $conversations->limit + $rowsreturned;
            $a->total  = $rowsmatched;
            $html .= html_writer::tag('h6', get_string('listpaginationheader', 'dialogue', $a), array('class'=>'pull-right'));
            $html .= html_writer::end_div();

            $html .= html_writer::start_tag('table', array('class'=>'conversation-list table table-hover table-condensed'));
            $html .= html_writer::start_tag('tbody');
            foreach ($list as $record) {

                $datattributes = array('data-redirect' => 'conversation',
                                       'data-action'   => 'view',
                                       'data-conversationid' => $record->conversationid);

                $html .= html_writer::start_tag('tr', $datattributes);

                $statelabel = '';
                if ($record->state == \mod_dialogue\dialogue::STATE_CLOSED) {
                    $statelabel = html_writer::tag('span', get_string('closed', 'dialogue'),
                                                   array('class'=>'state-indicator state-closed'));
                }
                $html .= html_writer::tag('td', $statelabel);

                if (isset($record->unread)) {
                    $badge = '';
                    $unreadcount = $record->unread;
                    if ($unreadcount > 0) {
                        $badgeclass = 'badge label-info';
                        $badge = html_writer::span($unreadcount, $badgeclass, array('title'=>get_string('numberunread', 'dialogue', $unreadcount)));
                    }
                    $html .= html_writer::tag('td', $badge);
                }

                if (isset($record->userid)) {
                    $displayuser = dialogue_get_user_details($dialogue, $record->userid);
                    $avatar = $OUTPUT->user_picture($displayuser, array('class'=> 'userpicture img-rounded', 'size' => 48));
                    $html .= html_writer::tag('td', $avatar);
                    $html .= html_writer::tag('td', fullname($displayuser));
                }

                if (isset($record->subject) and isset($record->body)) {
                    $subject = empty($record->subject) ? get_string('nosubject', 'dialogue') : $record->subject;
                    $summaryline = dialogue_generate_summary_line($subject, $record->body, $record->bodyformat);
                    $html .= html_writer::start_tag('td');
                    $html .= html_writer::start_div();
                    $html .= $summaryline;

                    $participants = dialogue_get_conversation_participants($dialogue, $record->conversationid);
                    $html .= html_writer::start_div();
                    foreach($participants as $participantid) {
                        //if ($participantid == $USER->id) {
                        //    continue;
                        //}
                        $participant = dialogue_get_user_details($dialogue, $participantid);
                        $picture = $OUTPUT->user_picture($participant, array('class'=>'userpicture img-rounded', 'size'=>16));
                        $html .= html_writer::tag('span', $picture.' '.fullname($participant),
                                                    array('class' => 'participant'));

                    }
                    $html .= html_writer::start_div();

                    $html .= html_writer::end_div();
                    $html .= html_writer::end_tag('td');
                }

                if (isset($record->timemodified)) {
                    $datestrings = (object) dialogue_get_humanfriendly_dates($record->timemodified);
                    if ($record->timemodified >= $today) {
                        $datetime = $datestrings->timepast;
                    } else if ($record->timemodified >= $yearago) {
                        $datetime = get_string('dateshortyear', 'dialogue', $datestrings);
                    } else {
                        $datetime = get_string('datefullyear', 'dialogue', $datestrings);
                    }
                    $html .= html_writer::tag('td', $datetime, array('title' => userdate($record->timemodified)));
                }

                $viewurlparams = array('id' => $cm->id, 'conversationid' => $record->conversationid, 'action' => 'view');
                $viewlink = html_writer::link(new moodle_url('conversation.php', $viewurlparams),
                                              get_string('view'));

                $html .= html_writer::tag('td', $viewlink, array('class'=>'nonjs-control'));

                $html .= html_writer::end_tag('tr');
            }

            $html .= html_writer::end_tag('tbody');
            $html .= html_writer::end_tag('table');

            $pagination = new paging_bar($rowsmatched, $conversations->page, $conversations->limit, $PAGE->url);

            $html .= $OUTPUT->render($pagination);
        }

        return $html;
    }

    /**
     * Render a reply related to conversation.
     *
     * @param dialogue_reply $reply
     * @return string
     */
    public function render_reply(\mod_dialogue\reply $reply) {
        global $OUTPUT, $USER;

        $context        = $reply->dialogue->context; // fetch context from parent dialogue
        $cm             = $reply->dialogue->cm; // fetch course module from parent dialogue
        $conversation   = $reply->conversation; // fetch parent conversation

        $today    = strtotime("today");
        $yearago  = strtotime("-1 year");

        $html = '';

        $html .= html_writer::start_div('conversation');
        $messageid = 'm' . $reply->messageid;
        $html .= html_writer::tag('a', '', array('id' => $messageid));

        $avatar = $OUTPUT->user_picture($reply->author, array('size' => true, 'class' => 'userpicture img-rounded'));
        $html .= html_writer::div($avatar, 'conversation-object pull-left');

        $html .= html_writer::start_div('conversation-body');

        $datestrings = (object) dialogue_get_humanfriendly_dates($reply->timemodified);
        $datestrings->fullname = fullname($reply->author); //sneaky
        if ($reply->timemodified >= $today) {
            $repliedbyheader = get_string('repliedbytoday', 'dialogue', $datestrings);
        } else if ($reply->timemodified >= $yearago) {
            $repliedbyheader = get_string('repliedbyshortyear', 'dialogue', $datestrings);
        } else {
            $repliedbyheader = get_string('repliedbyfullyear', 'dialogue', $datestrings);
        }
        $html .= html_writer::start_div('reply-header');
        $html .= html_writer::tag('span', $repliedbyheader, array('class' => 'reply-openedby pull-left'));
        $html .= html_writer::empty_tag('br');
        $html .= html_writer::end_div();
        $html .= html_writer::empty_tag('hr');
        $html .= $reply->bodyhtml;
        $html .= $this->render_attachments($reply->attachments);
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();

        return $html;
    }

    /**
     * Render attachments associated with a message - conversation or reply.
     *
     * @global type $OUTPUT
     * @param array $attachments
     * @return string
     */
    public function render_attachments(array $attachments) {
        global $OUTPUT;

        $html = '';

        if ($attachments) {

            $numattachments = count($attachments);
            $attachmentheader = ($numattachments > 1) ? get_string('numberattachments', 'dialogue', $numattachments) :
                                                        get_string('attachment', 'dialogue');

            $html .= html_writer::start_div('attachments');
            $html .= html_writer::tag('h5', $attachmentheader);
            foreach ($attachments as $file) {
                $contextid = $file->get_contextid();
                $itemid = $file->get_itemid();
                $filename = $file->get_filename();
                $filesize = $file->get_filesize();
                $mimetype = $file->get_mimetype();

                $viewurl = new moodle_url('/pluginfile.php/' . $contextid . '/mod_dialogue/attachment/' . $itemid . '/' . $filename);
                $previewurl = clone($viewurl);
                $previewurl->param('preview', 'thumb');
                $downloadurl = clone($viewurl);
                $downloadurl->param('forcedownload', 'true');

                if ($file->is_valid_image()) {
                    $html .= html_writer::start_tag('table');
                    $html .= html_writer::start_tag('tbody');
                    $html .= html_writer::start_tag('tr');
                    $html .= html_writer::start_tag('td');
                    $html .= html_writer::link($viewurl, html_writer::empty_tag('img', array('src' => $previewurl->out(), 'class' => 'thumbnail', 'alt' => $mimetype)));
                    $html .= html_writer::end_tag('td');
                    $html .= html_writer::start_tag('td');
                    $html .= html_writer::tag('b', $filename);
                    $html .= html_writer::empty_tag('br');
                    $html .= html_writer::tag('span', display_size($filesize), array('class' => 'meta-filesize'));
                    $html .= html_writer::link($viewurl, html_writer::tag('span', get_string('view')));
                    $html .= html_writer::link($downloadurl, html_writer::tag('span', get_string('download')));
                    $html .= html_writer::end_tag('td');
                    $html .= html_writer::end_tag('tr');
                    $html .= html_writer::end_tag('tbody');
                    $html .= html_writer::end_tag('table');
                } else {
                    $html .= html_writer::start_tag('table');
                    $html .= html_writer::start_tag('tbody');
                    $html .= html_writer::start_tag('tr');
                    $html .= html_writer::start_tag('td');
                    $html .= html_writer::link($downloadurl, html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url(file_mimetype_icon($mimetype)), 'class' => 'icon', 'alt' => $mimetype)));
                    $html .= html_writer::end_tag('td');
                    $html .= html_writer::start_tag('td');
                    $html .= html_writer::tag('i', $filename);
                    $html .= html_writer::empty_tag('br');
                    $html .= html_writer::tag('span', display_size($filesize), array('class' => 'meta-filesize'));
                    $html .= html_writer::link($downloadurl, html_writer::tag('span', get_string('download')));
                    $html .= html_writer::end_tag('td');
                    $html .= html_writer::end_tag('tr');
                    $html .= html_writer::end_tag('tbody');
                    $html .= html_writer::end_tag('table');
                }
                $html .= html_writer::empty_tag('br'); // break up attachments spacing
            }
            $html .= html_writer::end_div();
        }
        return $html;
    }


    public function state_button_group() {
        global $PAGE;

        $stateurl = clone($PAGE->url);
        $html = '';
        $openlink = '';
        $closedlink = '';

        // get state from url param
        $state = $stateurl->get_param('state');
        // state open, disable and enable closed button.
        if ($state == \mod_dialogue\dialogue::STATE_OPEN) {
            $stateurl->param('state', \mod_dialogue\dialogue::STATE_CLOSED);

            $openlink = html_writer::link('#', html_writer::tag('span', get_string('open', 'dialogue')),
                                                                  array('class'=>'btn btn-small disabled'));

            $closedlink = html_writer::link($stateurl, html_writer::tag('span', get_string('closed', 'dialogue')),
                                                                    array('class'=>'btn btn-small'));

        }
        // state closed, disable and enable open button.
        if ($state == \mod_dialogue\dialogue::STATE_CLOSED) {
            $stateurl->param('state', \mod_dialogue\dialogue::STATE_OPEN);

            $openlink = html_writer::link($stateurl, html_writer::tag('span', get_string('open', 'dialogue')),
                                                                 array('class'=>'btn btn-small'));

            $closedlink = html_writer::link('#', html_writer::tag('span', get_string('closed', 'dialogue')),
                                                                  array('class'=>'btn btn-small disabled'));

        }
        $html .= html_writer::start_div('btn-group');
        $html .= $openlink; // open state link
        $html .= $closedlink; // close state link
        $html .= html_writer::end_div();

        return $html;
    }

    /**
     * Builds and returns HTML needed to render the sort by drop down for conversation
     * lists.
     *
     * @global stdClass $PAGE
     * @global stdClass $OUTPUT
     * @param array $options
     * @param string $sort
     * @param string $direction
     * @return string $html
     * @throws moodle_exception
     */
    public function list_sortby($options, $sort, $direction) {
        global $PAGE, $OUTPUT;

        $html = '';
        $nonjsoptions = array();

        if (!in_array($sort, array_keys($options))) {
            throw new moodle_exception("Not a sort option");
        }


        $pageurl = clone($PAGE->url);
        $PAGE->url->param('page', 0); // reset pagination

        $html .= html_writer::start_div('dropdown-group pull-right'); //
        $html .= html_writer::start_div('js-control btn-group pull-right');

        $html .= html_writer::start_tag('button', array('data-toggle' => 'dropdown',
                                                        'class' =>'btn btn-small dropdown-toggle'));

        $html .= get_string('sortedby', 'dialogue', get_string($sort, 'dialogue'));
        $html .= html_writer::tag('tag', null, array('class' => 'caret'));
        $html .= html_writer::end_tag('button');
        $html .= html_writer::start_tag('ul', array('class' => 'dropdown-menu'));
        foreach ($options as $option => $settings) {
            $string = get_string($option, 'dialogue');
            $nonjsoptions[$option] = $string;
            if ($settings['directional'] == false) {
                $url = clone($PAGE->url);
                $url->param('sort', $option);
                $html .= html_writer::start_tag('li');
                $html .= html_writer::link($url, $string);
                $html .= html_writer::end_tag('li');
                continue;
            }
            if ($option == $sort) {
                $sortdirection = ($direction == 'desc') ? 'asc' : 'desc';
            } else {
                $sortdirection = \core_text::strtolower($settings['default']);
            }
            $url = clone($PAGE->url);
            $url->param('sort', $option);
            $url->param('direction', $sortdirection);
            // font awesome icon
            $faclass = "fa fa-sort-{$settings['type']}-{$sortdirection} pull-right";
            $faicon = html_writer::tag('i', '', array('class' => $faclass));
            $html .= html_writer::start_tag('li');
            $html .= html_writer::link($url, $faicon . $string);
            $html .= html_writer::end_tag('li');
        }
        $html .= html_writer::end_tag('ul');
        $html .= html_writer::end_div(); // end of js-control

        // Important: non javascript control must be after javascript control else layout borked in chrome.
        $select = new single_select($pageurl, 'sort', $nonjsoptions, $sort, null, 'orderbyform');
        $select->method = 'post';
        $nonjscontrol = $OUTPUT->render($select);
        $html .= html_writer::div($nonjscontrol, 'nonjs-control');

        $html .= html_writer::end_div(); // end of container
        return $html;

    }

    public function sort_by_dropdown($options) {
        global $PAGE, $OUTPUT;
        $html = '';


        $strings = array();
        foreach ($options as $option) {
            $strings[$option] = get_string($option, 'dialogue');
        }

        $pageurl = clone($PAGE->url);

        $PAGE->url->param('page', 0); // reset pagination

        $sort = $PAGE->url->get_param('sort');
        if (!in_array($sort, $options)) {
            throw new coding_exception('$PAGE sort param is not in options');
        }

        $html .= html_writer::start_div('dropdown-group pull-right'); //
        $html .= html_writer::start_div('js-control btn-group pull-right');

        $html .= html_writer::start_tag('button', array('data-toggle' => 'dropdown',
                                                        'class' =>'btn btn-small dropdown-toggle'));

        $html .= get_string('sortedby', 'dialogue', get_string($sort, 'dialogue'));
        $html .= html_writer::tag('tag', null, array('class' => 'caret'));
        $html .= html_writer::end_tag('button');
        $html .= html_writer::start_tag('ul', array('class' => 'dropdown-menu'));

        foreach ($options as $option) {
            $url = clone($PAGE->url);
            $url->param('sort', $option);
            $html .= html_writer::start_tag('li');
            $html .= html_writer::link($url, ucfirst(get_string($option, 'dialogue')));
            $html .= html_writer::end_tag('li');
        }

        $html .= html_writer::end_tag('ul');
        $html .= html_writer::end_div(); // end of js-control

        // Important: non javascript control must be after javascript control else layout borked in chrome.
        $select = new single_select($pageurl, 'sort', $strings, $sort, null, 'orderbyform');
        $select->method = 'post';
        $nonjscontrol = $OUTPUT->render($select);
        $html .= html_writer::div($nonjscontrol, 'nonjs-control');

        $html .= html_writer::end_div(); // end of container
        return $html;

    }

    public function tab_navigation(\mod_dialogue\dialogue $dialogue) {
        global $PAGE;

        $config  = $dialogue->config;
        $context = $dialogue->context;
        $cm      = $dialogue->cm;

        $html = '';
        $currentpage = basename($PAGE->url->out_omit_querystring(), '.php');

        $html .= html_writer::start_tag('ul', array('class'=>'nav nav-tabs'));
        // link main conversation listing
        $active = ($currentpage == 'view') ? array('class'=>'active') : array();
        $html .= html_writer::start_tag('li', $active);
        $viewurl = new moodle_url('view.php', array('id'=>$cm->id));
        $html .= html_writer::link($viewurl, get_string('viewconversations', 'dialogue'));
        $html .= html_writer::end_tag('li');
        // experimental: link conversation by role listing
        if (!empty($config->viewconversationsbyrole) and has_capability('mod/dialogue:viewbyrole', $context)) {
            $active = ($currentpage == 'viewconversationsbyrole') ? array('class'=>'active') : array();
            $html .= html_writer::start_tag('li', $active);
            $viewurl = new moodle_url('viewconversationsbyrole.php', array('id'=>$cm->id));
            $html .= html_writer::link($viewurl, get_string('viewconversationsbyrole', 'dialogue'));
            $html .= html_writer::end_tag('li');
        }
        // link to users draft listing
        $active = ($currentpage == 'drafts') ? array('class'=>'active') : array();
        $html .= html_writer::start_tag('li', $active);
        $draftsurl = new moodle_url('drafts.php', array('id'=>$cm->id));
        $html .= html_writer::link($draftsurl, get_string('drafts', 'dialogue'));
        $html .= html_writer::end_tag('li');
        // link to bulk open rules listing
        if (has_any_capability(array('mod/dialogue:bulkopenrulecreate', 'mod/dialogue:bulkopenruleeditany'), $context)) { // @todo better named capabilities
            $active = ($currentpage == 'bulkopenrules') ? array('class'=>'active') : array();
            $html .= html_writer::start_tag('li', $active);
            $bulkopenrulesurl = new moodle_url('bulkopenrules.php', array('id'=>$cm->id));
            $html .= html_writer::link($bulkopenrulesurl, get_string('bulkopenrules', 'dialogue'));
            $html .= html_writer::end_tag('li');
        }
        // open discussion button
        if (has_capability('mod/dialogue:open', $context)) {
            $createurl = new moodle_url('conversation.php', array('id'=>$cm->id, 'action'=>'create'));
            $html .= html_writer::link($createurl, get_string('create'), array('class'=>'btn-create pull-right'));//array('class'=>'btn btn-primary pull-right')
        }
        $html .= html_writer::end_tag('ul');

        return $html;
    }

} // end of renderer class
