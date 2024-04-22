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

/**
 * A custom renderer class used by the dialogue module.
 *
 * @package   mod_dialogue
 * @copyright 2013 Troy Williams
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_dialogue_renderer extends plugin_renderer_base {

    /**
     * Render conversation, just the conversation
     *
     * @param mod_dialogue\conversation $conversation
     * @return string
     */
    public function render_conversation(mod_dialogue\conversation $conversation) {
        global $USER;

        $context = $conversation->dialogue->context; // Fetch context from parent dialogue.
        $cm      = $conversation->dialogue->cm; // Fetch course module from parent dialogue.

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

        $html .= html_writer::end_div(); // Close header.

        $html .= html_writer::start_div('conversation');
        $messageid = 'm' . $conversation->messageid;

        $html .= html_writer::tag('a', '', array('id' => $messageid));
        $avatar = $this->output->user_picture($conversation->author, array('size' => true, 'class' => 'userpicture img-rounded'));

        $html .= html_writer::div($avatar, 'conversation-object pull-left');

        $html .= html_writer::start_div('conversation-body');

        $datestrings = (object) dialogue_get_humanfriendly_dates($conversation->timemodified);
        $datestrings->fullname = fullname($conversation->author);
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
            $canclose = ((has_capability('mod/dialogue:close', $context) && $USER->id == $conversation->author->id) ||
                          has_capability('mod/dialogue:closeany', $context));

            if ($canclose) {
                $lockicon = html_writer::tag('i', '', array('class' => "fa fa-lock"));
                $html .= html_writer::start_tag('li');
                $closeurl = new moodle_url('/mod/dialogue/conversation.php');
                $closeurl->param('id', $cm->id);
                $closeurl->param('conversationid', $conversation->conversationid);
                $closeurl->param('action', 'close');
                $html .= html_writer::link($closeurl,  get_string('closeconversation', 'dialogue') . $lockicon);
                $html .= html_writer::end_tag('li');
            }
        }

        $candelete = ((has_capability('mod/dialogue:delete', $context) && $USER->id == $conversation->author->id) ||
                       has_capability('mod/dialogue:deleteany', $context));

        if ($candelete) {
            $html .= html_writer::start_tag('li');
            $trashicon = html_writer::tag('i', '', array('class' => "fa fa-trash-o"));
            $deleteurl = new moodle_url('/mod/dialogue/conversation.php');
            $deleteurl->param('id', $cm->id);
            $deleteurl->param('conversationid', $conversation->conversationid);
            $deleteurl->param('action', 'delete');
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

        // Display list of people who have received this conversation
        // Todo - display rest of information, which group, has completed? etc.
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
                $html .= html_writer::start_tag('table', array('class' => 'table'));
                $html .= html_writer::start_tag('tbody');
                $sentonstring = new lang_string('senton', 'dialogue');
                foreach ($receivers as $receivedby) {
                    $person = dialogue_get_user_details($conversation->dialogue, $receivedby->userid);
                    $html .= html_writer::start_tag('tr');
                    $picture = $this->output->user_picture($person, array('class' => 'userpicture img-rounded', 'size' => 20));
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
        // This should only display on open and closed conversations todo - tidy + css.
        $participants = $conversation->participants;
        if ($participants) {
            $html .= html_writer::start_div('participants');
            $html .= html_writer::tag('strong', count($participants));
            $html .= '&nbsp;' . get_string('participants', 'dialogue');
            foreach ($participants as $participant) {
                $picture = $this->output->user_picture($participant, array('class' => 'userpicture img-rounded', 'size' => 20));
                $html .= html_writer::tag('span', $picture . '&nbsp;' . fullname($participant), array('class' => 'participant'));
            }
            $html .= html_writer::end_div();
        }
        $html .= html_writer::end_div(); // End of main conversation.
        $html .= html_writer::empty_tag('hr');

        return $html;
    }

    /**
     * Render a list of conversations in a dialogue for a particular user.
     *
     * @param \mod_dialogue\conversations $conversations
     * @return string
     */
    public function conversation_listing(\mod_dialogue\conversations $conversations) {
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
            $html .= $this->output->notification(get_string('noconversationsfound', 'dialogue'), 'notifyproblem');
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
            $html .= html_writer::tag('h6', get_string('listpaginationheader', 'dialogue', $a), array('class' => 'pull-right'));
            $html .= html_writer::end_div();

            $html .= html_writer::start_tag('table', array('class' => 'conversation-list table table-hover table-condensed'));
            $html .= html_writer::start_tag('tbody');
            foreach ($list as $record) {

                $datattributes = array('data-redirect' => 'conversation',
                                       'data-action'   => 'view',
                                       'data-conversationid' => $record->conversationid);

                $html .= html_writer::start_tag('tr', $datattributes);

                $statelabel = '';
                if ($record->state == \mod_dialogue\dialogue::STATE_CLOSED) {
                    $statelabel = html_writer::tag('span', get_string('closed', 'dialogue'),
                                                   array('class' => 'state-indicator state-closed'));
                }
                $html .= html_writer::tag('td', $statelabel);

                if (isset($record->unread)) {
                    $badge = '';
                    $unreadcount = $record->unread;
                    if ($unreadcount > 0) {
                        $badgeclass = 'badge label-info';
                        $badge = html_writer::span($unreadcount, $badgeclass,
                            array('title' => get_string('numberunread', 'dialogue', $unreadcount)));
                    }
                    $html .= html_writer::tag('td', $badge);
                }

                if (isset($record->userid)) {
                    $displayuser = dialogue_get_user_details($dialogue, $record->userid);
                    $avatar = $this->output->user_picture($displayuser, array('class' => 'userpicture img-rounded', 'size' => 48));
                    $html .= html_writer::tag('td', $avatar);
                    $html .= html_writer::tag('td', fullname($displayuser));
                }

                if (isset($record->subject) && isset($record->body)) {
                    $subject = empty($record->subject) ? get_string('nosubject', 'dialogue') : $record->subject;
                    $summaryline = dialogue_generate_summary_line($subject, $record->body, $record->bodyformat);
                    $html .= html_writer::start_tag('td');
                    $html .= html_writer::start_div();
                    $html .= $summaryline;

                    $participants = dialogue_get_conversation_participants($dialogue, $record->conversationid);
                    $html .= html_writer::start_div();
                    foreach ($participants as $participantid) {
                        $participant = dialogue_get_user_details($dialogue, $participantid);
                        $picture = $this->output->user_picture($participant,
                            array('class' => 'userpicture img-rounded', 'size' => 16));
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

                $html .= html_writer::tag('td', $viewlink, array('class' => 'nonjs-control'));

                $html .= html_writer::end_tag('tr');
            }

            $html .= html_writer::end_tag('tbody');
            $html .= html_writer::end_tag('table');

            $pagination = new paging_bar($rowsmatched, $conversations->page, $conversations->limit, $this->page->url);

            $html .= $this->output->render($pagination);
        }

        return $html;
    }

    /**
     * Render a reply related to conversation.
     *
     * @param \mod_dialogue\reply $reply
     * @return string
     */
    public function render_reply(\mod_dialogue\reply $reply) {

        $context        = $reply->dialogue->context; // Fetch context from parent dialogue.
        $cm             = $reply->dialogue->cm; // Fetch course module from parent dialogue.
        $conversation   = $reply->conversation; // Fetch parent conversation.

        $today    = strtotime("today");
        $yearago  = strtotime("-1 year");

        $html = '';

        $html .= html_writer::start_div('conversation');
        $messageid = 'm' . $reply->messageid;
        $html .= html_writer::tag('a', '', array('id' => $messageid));

        $avatar = $this->output->user_picture($reply->author, array('size' => true, 'class' => 'userpicture img-rounded'));
        $html .= html_writer::div($avatar, 'conversation-object pull-left');

        $html .= html_writer::start_div('conversation-body');

        $datestrings = (object) dialogue_get_humanfriendly_dates($reply->timemodified);
        $datestrings->fullname = fullname($reply->author);
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
     * @param array $attachments
     * @return string
     */
    public function render_attachments(array $attachments) {
        global $CFG;

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

                $viewurl = \moodle_url::make_file_url($CFG->wwwroot, '/pluginfile.php/' . $contextid .
                    '/mod_dialogue/attachment/' . $itemid . '/' . $filename);
                $previewurl = clone($viewurl);
                $previewurl->param('preview', 'thumb');
                $downloadurl = clone($viewurl);
                $downloadurl->param('forcedownload', 'true');

                if ($file->is_valid_image()) {
                    $html .= html_writer::start_tag('table');
                    $html .= html_writer::start_tag('tbody');
                    $html .= html_writer::start_tag('tr');
                    $html .= html_writer::start_tag('td');
                    $html .= html_writer::link($viewurl, html_writer::empty_tag('img',
                        array('src' => $previewurl->out(), 'class' => 'thumbnail', 'alt' => $mimetype)));
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
                    $html .= html_writer::link($downloadurl, html_writer::empty_tag('img',
                        array('src' => $this->output->image_url(file_mimetype_icon($mimetype)),
                            'class' => 'icon', 'alt' => $mimetype)));
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
                $html .= html_writer::empty_tag('br'); // Break up attachments spacing.
            }
            $html .= html_writer::end_div();
        }
        return $html;
    }

    /**
     * State button group
     * @return string
     * @throws coding_exception
     */
    public function state_button_group() {
        $stateurl = clone($this->page->url);
        $html = '';
        $openlink = '';
        $closedlink = '';

        // Get state from url param.
        $state = $stateurl->get_param('state');
        // State open, disable and enable closed button.
        if ($state == \mod_dialogue\dialogue::STATE_OPEN) {
            $stateurl->param('state', \mod_dialogue\dialogue::STATE_CLOSED);

            $openlink = html_writer::link('#', html_writer::tag('span', get_string('open', 'dialogue')),
                                                                  array('class' => 'btn btn-small disabled'));

            $closedlink = html_writer::link($stateurl, html_writer::tag('span', get_string('closed', 'dialogue')),
                                                                    array('class' => 'btn btn-small'));

        }
        // State closed, disable and enable open button.
        if ($state == \mod_dialogue\dialogue::STATE_CLOSED) {
            $stateurl->param('state', \mod_dialogue\dialogue::STATE_OPEN);

            $openlink = html_writer::link($stateurl, html_writer::tag('span', get_string('open', 'dialogue')),
                                                                 array('class' => 'btn btn-small'));

            $closedlink = html_writer::link('#', html_writer::tag('span', get_string('closed', 'dialogue')),
                                                                  array('class' => 'btn btn-small disabled'));

        }
        $html .= html_writer::start_div('btn-group');
        $html .= $openlink; // Open state link.
        $html .= $closedlink; // Close state link.
        $html .= html_writer::end_div();

        return $html;
    }

    /**
     * Builds and returns HTML needed to render the sort by drop down for conversation
     * lists.
     *
     * @param array $options
     * @param string $sort
     * @param string $direction
     * @return string $html
     * @throws moodle_exception
     */
    public function list_sortby($options, $sort, $direction) {
        $html = '';
        $nonjsoptions = array();

        if (!in_array($sort, array_keys($options))) {
            throw new moodle_exception("Not a sort option");
        }

        $pageurl = clone($this->page->url);
        $this->page->url->param('page', 0); // Reset pagination.

        $html .= html_writer::start_div('dropdown-group pull-right');
        $html .= html_writer::start_div('js-control btn-group pull-right');

        $html .= html_writer::start_tag('button', array('data-toggle' => 'dropdown',
                                                        'class' => 'btn btn-small dropdown-toggle'));

        $html .= get_string('sortedby', 'dialogue', get_string($sort, 'dialogue'));
        $html .= html_writer::end_tag('button');
        $html .= html_writer::start_tag('ul', array('class' => 'dropdown-menu'));
        foreach ($options as $option => $settings) {
            $string = get_string($option, 'dialogue');
            $nonjsoptions[$option] = $string;
            if ($settings['directional'] == false) {
                $url = clone($this->page->url);
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
            $url = clone($this->page->url);
            $url->param('sort', $option);
            $url->param('direction', $sortdirection);
            // Font awesome icon.
            $faclass = "fa fa-sort-{$settings['type']}-{$sortdirection} pull-right";
            $faicon = html_writer::tag('i', '', array('class' => $faclass));
            $html .= html_writer::start_tag('li');
            $html .= html_writer::link($url, $faicon . $string);
            $html .= html_writer::end_tag('li');
        }
        $html .= html_writer::end_tag('ul');
        $html .= html_writer::end_div(); // End of js-control.

        // Important: non javascript control must be after javascript control else layout borked in chrome.
        $select = new single_select($pageurl, 'sort', $nonjsoptions, $sort, null, 'orderbyform');
        $select->method = 'post';
        $nonjscontrol = $this->output->render($select);
        $html .= html_writer::div($nonjscontrol, 'nonjs-control');

        $html .= html_writer::end_div(); // End of container.
        return $html;

    }

    /**
     * sort by dropdown.
     * @param array $options
     * @return string
     * @throws coding_exception
     */
    public function sort_by_dropdown($options) {
        $html = '';

        $strings = array();
        foreach ($options as $option) {
            $strings[$option] = get_string($option, 'dialogue');
        }

        $pageurl = clone($this->page->url);

        $this->page->url->param('page', 0); // Reset pagination.

        $sort = $this->page->url->get_param('sort');
        if (!in_array($sort, $options)) {
            throw new coding_exception('$this->page sort param is not in options');
        }

        $html .= html_writer::start_div('dropdown-group pull-right');
        $html .= html_writer::start_div('js-control btn-group pull-right');

        $html .= html_writer::start_tag('button', array('data-toggle' => 'dropdown',
                                                        'class' => 'btn btn-small dropdown-toggle'));

        $html .= get_string('sortedby', 'dialogue', get_string($sort, 'dialogue'));
        $html .= html_writer::tag('tag', null, array('class' => 'caret'));
        $html .= html_writer::end_tag('button');
        $html .= html_writer::start_tag('ul', array('class' => 'dropdown-menu'));

        foreach ($options as $option) {
            $url = clone($this->page->url);
            $url->param('sort', $option);
            $html .= html_writer::start_tag('li');
            $html .= html_writer::link($url, ucfirst(get_string($option, 'dialogue')));
            $html .= html_writer::end_tag('li');
        }

        $html .= html_writer::end_tag('ul');
        $html .= html_writer::end_div(); // End of js-control.

        // Important: non javascript control must be after javascript control else layout borked in chrome.
        $select = new single_select($pageurl, 'sort', $strings, $sort, null, 'orderbyform');
        $select->method = 'post';
        $nonjscontrol = $this->output->render($select);
        $html .= html_writer::div($nonjscontrol, 'nonjs-control');

        $html .= html_writer::end_div(); // End of container.
        return $html;

    }

    /**
     * Tab navigation
     * @param \mod_dialogue\dialogue $dialogue
     * @return string
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function tab_navigation(\mod_dialogue\dialogue $dialogue) {
        $config  = $dialogue->config;
        $context = $dialogue->context;
        $cm      = $dialogue->cm;

        $html = '';
        $currentpage = basename($this->page->url->out_omit_querystring(), '.php');

        $html .= html_writer::start_tag('ul', array('class' => 'nav nav-tabs'));
        // Link main conversation listing.
        $active = ($currentpage == 'view') ? array('class' => 'active') : array();
        $html .= html_writer::start_tag('li', $active);
        $viewurl = new moodle_url('view.php', array('id' => $cm->id));
        $html .= html_writer::link($viewurl, get_string('viewconversations', 'dialogue'));
        $html .= html_writer::end_tag('li');
        // Experimental: link conversation by role listing.
        if (!empty($config->viewconversationsbyrole) && has_capability('mod/dialogue:viewbyrole', $context)) {
            $active = ($currentpage == 'viewconversationsbyrole') ? array('class' => 'active') : array();
            $html .= html_writer::start_tag('li', $active);
            $viewurl = new moodle_url('viewconversationsbyrole.php', array('id' => $cm->id));
            $html .= html_writer::link($viewurl, get_string('viewconversationsbyrole', 'dialogue'));
            $html .= html_writer::end_tag('li');
        }
        // Link to users draft listing.
        $active = ($currentpage == 'drafts') ? array('class' => 'active') : array();
        $html .= html_writer::start_tag('li', $active);
        $draftsurl = new moodle_url('drafts.php', array('id' => $cm->id));
        $html .= html_writer::link($draftsurl, get_string('drafts', 'dialogue'));
        $html .= html_writer::end_tag('li');
        // Link to bulk open rules listing.
        if (has_any_capability(array('mod/dialogue:bulkopenrulecreate', 'mod/dialogue:bulkopenruleeditany'), $context)) {
            $active = ($currentpage == 'bulkopenrules') ? array('class' => 'active') : array();
            $html .= html_writer::start_tag('li', $active);
            $bulkopenrulesurl = new moodle_url('bulkopenrules.php', array('id' => $cm->id));
            $html .= html_writer::link($bulkopenrulesurl, get_string('bulkopenrules', 'dialogue'));
            $html .= html_writer::end_tag('li');
        }
        // Open discussion button.
        if (has_capability('mod/dialogue:open', $context)) {
            $createurl = new moodle_url('conversation.php', array('id' => $cm->id, 'action' => 'create'));
            $html .= html_writer::link($createurl, get_string('create'), array('class' => 'btn-create pull-right'));
        }
        $html .= html_writer::end_tag('ul');

        return $html;
    }

}
