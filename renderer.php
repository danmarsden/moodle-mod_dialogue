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

    /**
     * Render conversation, just the conversation
     *
     * @global type $PAGE
     * @global type $OUTPUT
     * @global type $USER
     * @param dialogue_conversation $conversation
     * @return string
     */
    public function render_dialogue_conversation(dialogue_conversation $conversation) {
        global $PAGE, $OUTPUT, $USER;

        // fetch context from parent dialogue
        $context = $conversation->dialogue->context;

        $today    = strtotime("today");
        $yearago  = strtotime("-1 year");

        $html = '';

        $html .= html_writer::start_div('conversation-heading');
        $html .= html_writer::tag('h3', $conversation->subject, array('class' => 'heading'));

        if ($conversation->state == dialogue::STATE_OPEN) {
            $canclose = ((has_capability('mod/dialogue:close', $context) and $USER->id == $conversation->author->id) or
                    has_capability('mod/dialogue:closeany', $context));

            if ($canclose) {
                $closeurl = clone($PAGE->url);
                $closeurl->param('conversationid', $conversation->conversationid);
                $closeurl->param('action', 'close');
                $html .= html_writer::link($closeurl, get_string('closeconversation', 'dialogue'), array('class' => "btn btn-danger pull-right"));
            }
        }

        if ($conversation->state == dialogue::STATE_CLOSED) {
            $span = html_writer::tag('span', get_string('closed', 'dialogue'), array('class' => "state-indicator state-closed"));
            $html .= html_writer::tag('h3', $span, array('class' => 'heading pull-right'));
        }

        if ($conversation->state == dialogue::STATE_BULK_AUTOMATED) {
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

        $html .= html_writer::tag('h5', $openedbyheader, array('class' => 'conversation-heading',
                                                               'title' => userdate($conversation->timemodified)));
        $html .= html_writer::empty_tag('hr');
        $html .= $conversation->bodyhtml;
        $html .= $this->render_attachments($conversation->attachments);
        $html .= html_writer::end_div();
        //$html .= html_writer::end_div();

        // Display list of people who have received this conversation.
        // @todo - display rest of information, which group, has completed? etc
        if ($conversation->state == dialogue::STATE_BULK_AUTOMATED) {
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
        $participants = $conversation->participants;
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

    public function conversations(dialogue_conversations $conversations, $page) {
        global $PAGE, $OUTPUT;

        $output = '';

        $dialogue = $conversations->dialogue;
        $cm       = $conversations->dialogue->cm;
        $state    = $conversations->state;

        $today    = strtotime("today");
        $yearago  = strtotime("-1 year");

        $listheading = get_string('displayconversationsheading', 'dialogue', textlib::strtolower($state));
        $activegroup = groups_get_activity_group($cm, true);
        if ($activegroup) {
            $groupname = groups_get_group_name($activegroup);
            $listheading .= ' ' . get_string('ingroup', 'dialogue', $groupname);
        }

        $matches = $conversations->matches();
        if (!$matches) {
            $output .= html_writer::start_div();
            $output .= html_writer::tag('h6', $listheading);
            $output .= html_writer::end_div();
            $output .= $OUTPUT->notification(get_string('noconversationsfound', 'dialogue'), 'notifyproblem');
            return $output;
        }

        $pageurl = $PAGE->url;
        $pagination = new paging_bar($matches, $page, dialogue::PAGINATION_PAGE_SIZE, $pageurl);
        $records = $conversations->fetch_page($page);

        $output .= html_writer::start_div('listing-meta');
        $output .= html_writer::tag('h6', $listheading);
        $a         = new stdClass();
        $a->start  = ($page) ? $page * dialogue::PAGINATION_PAGE_SIZE : 1;
        $a->end    = $page * dialogue::PAGINATION_PAGE_SIZE + count($records);
        $a->total  = $matches;
        $output .= html_writer::tag('h6', get_string('listpaginationheader', 'dialogue', $a), array('class'=>'pull-right'));
        $output .= html_writer::end_div();

        $output .= html_writer::start_tag('table', array('class'=>'table table-hover table-condensed'));
        $output .= html_writer::start_tag('tbody');
        foreach ($records as $record) {
            $output .= html_writer::start_tag('tr', array('id'=>'conversationid-'.$record->conversationid));

            if (isset($record->unread)) {
                $unreadcount = $record->unread;
                $badgeclass = 'hidden';
                if ($unreadcount > 0) {
                    $badgeclass = 'badge label-info';
                }
                $badge = html_writer::span($unreadcount, $badgeclass, array('title'=>get_string('numberunread', 'dialogue', $unreadcount)));
                $output .= html_writer::tag('td', $badge);
            }
/*
            if (isset($record->authorid)) {
                $author = dialogue_get_user_details($dialogue, $record->authorid);
                $avatar = $OUTPUT->user_picture($author, array('class'=> 'userpicture img-rounded', 'size' => 48));
                $output .= html_writer::tag('td', $avatar);
                $output .= html_writer::tag('td', fullname($author));
            }
*/
            if (isset($record->displayuserid)) {
                $displayuser = dialogue_get_user_details($dialogue, $record->displayuserid);
                $avatar = $OUTPUT->user_picture($displayuser, array('class'=> 'userpicture img-rounded', 'size' => 48));
                $output .= html_writer::tag('td', $avatar);
                $output .= html_writer::tag('td', fullname($displayuser));
            }

            if (isset($record->subject) and isset($record->body)) {
                $subject = empty($record->subject) ? get_string('nosubject', 'dialogue') : $record->subject;
                $summaryline = dialogue_generate_summary_line($subject, $record->body);
                $output .= html_writer::start_tag('td');
                $output .= html_writer::start_div();
                $output .= $summaryline;

                $participants = dialogue_get_conversation_participants($dialogue, $record->conversationid);
                $output .= html_writer::start_div();
                foreach($participants as $participantid) {
                    if (isset($record->authorid) and $record->authorid == $participantid) {
                        continue;
                    }
                    $participant = dialogue_get_user_details($dialogue, $participantid);
                    $picture = $OUTPUT->user_picture($participant, array('class'=>'userpicture img-rounded', 'size'=>16));
                    $output .= html_writer::tag('span', $picture.' '.fullname($participant),
                                                array('class' => 'participant'));

                }
                $output .= html_writer::start_div();

                $output .= html_writer::end_div();
                $output .= html_writer::end_tag('td');
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
                $output .= html_writer::tag('td', $datetime, array('title' => userdate($record->timemodified)));
            }

            $viewurlparams = array('id' => $cm->id, 'conversationid' => $record->conversationid, 'action' => 'view');
            $viewlink = html_writer::link(new moodle_url('conversation.php', $viewurlparams),
                                          get_string('view'), array('class'=>'nonjs-control-show'));

            $output .= html_writer::tag('td', $viewlink);

            $output .= html_writer::end_tag('tr');
        }
        $output .= html_writer::end_tag('tbody');
        $output .= html_writer::end_tag('table');

        $output .= $OUTPUT->render($pagination);

        return $output;
    }
    /**
     * Render a reply related to conversation.
     *
     * @param dialogue_reply $reply
     * @return string
     */
    public function render_dialogue_reply(dialogue_reply $reply) {
        global $OUTPUT;

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

        $html .= html_writer::tag('h5', $repliedbyheader, array('class' => 'conversation-heading',
                                                                'title' => userdate($reply->timemodified)));
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

    public function show_button_group() {
        global $PAGE;

        $showurl = clone($PAGE->url);
        $html = '';
        $minelink = '';
        $everyonelink = '';

        // return '' user doesn't have view any capability
        if (!has_capability('mod/dialogue:viewany', $PAGE->context)) {
            return $html;
        }

        // get show option from url param
        $show = $showurl->get_param('show');
        // disable show mine button, enable show everyone
        if ($show == dialogue::SHOW_MINE) {
            $showurl->param('show', dialogue::SHOW_EVERYONE);

            $minelink = html_writer::link('#', html_writer::tag('span', get_string('mine', 'dialogue')),
                                                                  array('class'=>'btn btn-small disabled'));

            $everyonelink = html_writer::link($showurl, html_writer::tag('span', get_string('everyone', 'dialogue')),
                                                                    array('class'=>'btn btn-small'));
        }
        // disable show everyone button, enable show mine
        if ($show == dialogue::SHOW_EVERYONE) {
            $showurl->param('show', dialogue::SHOW_MINE);

            $minelink = html_writer::link($showurl, html_writer::tag('span', get_string('mine', 'dialogue')),
                                                                  array('class'=>'btn btn-small'));

            $everyonelink = html_writer::link('#', html_writer::tag('span', get_string('everyone', 'dialogue')),
                                                                    array('class'=>'btn btn-small disabled'));
        }
        $html .= html_writer::start_div('btn-group');
        $html .= $minelink; // show mine link
        $html .= $everyonelink; // show everyone's link
        $html .= html_writer::end_div();

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
        if ($state == dialogue::STATE_OPEN) {
            $stateurl->param('state', dialogue::STATE_CLOSED);

            $openlink = html_writer::link('#', html_writer::tag('span', get_string('open', 'dialogue')),
                                                                  array('class'=>'btn btn-small disabled'));

            $closedlink = html_writer::link($stateurl, html_writer::tag('span', get_string('closed', 'dialogue')),
                                                                    array('class'=>'btn btn-small'));

        }
        // state closed, disable and enable open button.
        if ($state == dialogue::STATE_CLOSED) {
            $stateurl->param('state', dialogue::STATE_OPEN);

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

    public function display_by_student_checkbox() {
        global $PAGE;

        $html = '';

        user_preference_allow_ajax_update('dialogue_displaybystudent', PARAM_BOOL);

        $PAGE->requires->yui_module('moodle-mod_dialogue-userpreference',
                                    'M.mod_dialogue.userpreference.init', array());


        $displaybystudent = get_user_preferences('dialogue_displaybystudent', false);

        $attributes = array('id'=>'dialogue_displaybystudent', 'type'=>'checkbox', 'value'=> '1');
        if ($displaybystudent) {
            $attributes['checked'] = 'checked';
        }

        $html .= html_writer::start_div('display-by-student js-control');
        //$html .= html_writer::tag('label', get_string('displaybystudent', 'dialogue'));, array('class' => 'label')
        $html .= html_writer::tag('span', get_string('displaybystudent', 'dialogue'));
        $html .= html_writer::empty_tag('input', $attributes);
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
                $sortdirection = textlib::strtolower($settings['default']);
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

    public function tab_navigation(dialogue $dialogue) {
        global $PAGE;

        $config  = $dialogue->config;
        $context = $dialogue->context;
        $cm      = $dialogue->cm;

        $html = '';
        $currentpage = basename($PAGE->url->out_omit_querystring(), '.php');

        $html .= html_writer::start_tag('ul', array('class'=>'nav nav-tabs'));
        // Main view
        $active = ($currentpage == 'view') ? array('class'=>'active') : array();
        $html .= html_writer::start_tag('li', $active);
        $viewurl = new moodle_url('view.php', array('id'=>$cm->id));
        $html .= html_writer::link($viewurl, get_string('viewconversations', 'dialogue'));
        $html .= html_writer::end_tag('li');

        // Drafts
        $active = ($currentpage == 'drafts') ? array('class'=>'active') : array();
        $html .= html_writer::start_tag('li', $active);
        $draftsurl = new moodle_url('drafts.php', array('id'=>$cm->id));
        $html .= html_writer::link($draftsurl, get_string('drafts', 'dialogue'));
        $html .= html_writer::end_tag('li');
        //
        if (has_capability('mod/dialogue:bulkopenruleeditany', $context)) { //@TODO better named cap
            $active = ($currentpage == 'bulkopenrules') ? array('class'=>'active') : array();
            $html .= html_writer::start_tag('li', $active);
            $bulkopenrulesurl = new moodle_url('bulkopenrules.php', array('id'=>$cm->id));
            $html .= html_writer::link($bulkopenrulesurl, get_string('bulkopenrules', 'dialogue'));
            $html .= html_writer::end_tag('li');
        }

        if (has_capability('mod/dialogue:open', $context)) {
            $createurl = new moodle_url('conversation.php', array('id'=>$cm->id, 'action'=>'create'));
            $html .= html_writer::link($createurl, get_string('create'), array('class'=>'btn-create pull-right'));//array('class'=>'btn btn-primary pull-right')
        }
        $html .= html_writer::end_tag('ul');

        return $html;
    }

} // end of renderer class
