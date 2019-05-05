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
 * Renderer class for Dialogue plugin
 *
 * @package   mod_dialogue
 * @copyright 2018 Troy Williams
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_dialogue\output;

defined('MOODLE_INTERNAL') || die();

use html_writer;
use moodle_url;
use plugin_renderer_base;
use renderable;
use stdClass;

/**
 * Renderer class for Dialogue plugin
 *
 * @package   mod_dialogue
 * @copyright 2018 Troy Williams
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {

    /**
     * @param $heading
     * @param null $intro
     * @return bool|string
     * @throws \moodle_exception
     */
    public function render_dialogue_header($heading, $intro = null) {
        $data = new stdClass();
        $data->heading = $heading;
        $data->intro = $intro;
        return parent::render_from_template("mod_dialogue/dialogue_header", $data);
    }

    public function render_new_dialogue_button($data) {
        return parent::render_from_template("mod_dialogue/new_dialogue_button", $data);
    }
    
    public function render_list_filter_selector() {
        $data = new stdClass();
        $data->preferencename = 'mod_dialogue:conversation_state_filter';
        $userpreference = mod_dialogue_user_preferences()[$data->preferencename];
        $selected = get_user_preferences($data->preferencename, $userpreference['default']);
        foreach ($userpreference['choices'] as $name) {
            $data->{$name} = ($name == $selected) ? true : false;
        }
        return parent::render_from_template("mod_dialogue/conversation_state_filter", $data);
    }
    
    public function render_list_sort_selector() {
        $preferencename = 'mod_dialogue_list_sort';
        $userpreference = mod_dialogue_user_preferences()[$preferencename];
        $selected = get_user_preferences($preferencename, $userpreference['default']);
        $data = new stdClass();
        foreach ($userpreference['choices'] as $name) {
            $data->{$name} = ($name == $selected) ? true : false;
        }
        return parent::render_from_template("mod_dialogue/list_sort_selector", $data);
    }
    
    /**
     * Render a list of conversations in a dialogue for a particular user.
     *
     * @global global $OUTPUT
     * @global global $PAGE
     * @param mod_dialogue_conversations $conversations
     * @return string
     */
    public function conversation_listing(\mod_dialogue\conversations $conversations) {
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
                    $html .= html_writer::link($downloadurl, html_writer::empty_tag('img', array('src' => $OUTPUT->image_url(file_mimetype_icon($mimetype)), 'class' => 'icon', 'alt' => $mimetype)));
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
    
}
