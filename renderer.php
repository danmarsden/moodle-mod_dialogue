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

        $date = (object) dialogue_getdate($conversation->timemodified);
        $date->fullname = fullname($conversation->author);
        if ($date->today) {
            $openedbyheader = get_string('openedbytoday', 'dialogue', $date);
        } else if ($date->currentyear) {
            $openedbyheader = get_string('openedbyshortyear', 'dialogue', $date);
        } else {
            $openedbyheader = get_string('openedbyfullyear', 'dialogue', $date);
        }

        $html .= html_writer::tag('h5', $openedbyheader, array('class' => 'conversation-heading'));
        $html .= html_writer::empty_tag('hr');
        $html .= $conversation->bodyhtml;
        $html .= $this->render_attachments($conversation->attachments);
        $html .= html_writer::end_div();
        //$html .= html_writer::end_div();
        //if () automated/open
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

    /**
     * Render a reply related to conversation.
     *
     * @param dialogue_reply $reply
     * @return string
     */
    public function render_dialogue_reply(dialogue_reply $reply) {
        global $OUTPUT;

        $html = '';

        $html .= html_writer::start_div('conversation');
        $messageid = 'm' . $reply->messageid;
        $html .= html_writer::tag('a', '', array('id' => $messageid));

        $avatar = $OUTPUT->user_picture($reply->author, array('size' => true, 'class' => 'userpicture img-rounded'));
        $html .= html_writer::div($avatar, 'conversation-object pull-left');

        $html .= html_writer::start_div('conversation-body');

        $date = (object) dialogue_getdate($reply->timemodified);
        $date->fullname = fullname($reply->author);
        if ($date->today) {
            $repliedbyheader = get_string('repliedbytoday', 'dialogue', $date);
        } else if ($date->currentyear) {
            $repliedbyheader = get_string('repliedbyshortyear', 'dialogue', $date);
        } else {
            $repliedbyheader = get_string('repliedbyfullyear', 'dialogue', $date);
        }

        $html .= html_writer::tag('h5', $repliedbyheader, array('class' => 'conversation-heading'));
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

    public function conversation_list_sortby() {
        global $PAGE;

        $strings = array();
        $strings['latest'] = get_string('latest', 'dialogue');
        $strings['unread'] = get_string('unread', 'dialogue');
        $strings['oldest'] = get_string('oldest', 'dialogue');
        $strings['authoraz'] = get_string('authoraz', 'dialogue');
        $strings['authorza'] = get_string('authorza', 'dialogue');

        $url  = $PAGE->url;
        // reset page
        $url->param('page', 0);
        $sort = $url->get_param('sort');

        $html = '';
        $html .= html_writer::start_div('js-control btn-group pull-right'); // btn-group required for js
        $html .= html_writer::start_tag('button', array('data-toggle' => 'dropdown',
                                                        'class' =>'btn btn-small dropdown-toggle'));

        $html .= get_string('sortedby', 'dialogue', $strings[$sort]);
        $html .= html_writer::tag('tag', null, array('class' => 'caret'));
        $html .= html_writer::end_tag('button');
        $html .= html_writer::start_tag('ul', array('class' => 'dropdown-menu'));

        $html .= html_writer::start_tag('li');
        $unreadurl = clone($url);
        $unreadurl->param('sort', 'unread');
        $html .= html_writer::link($unreadurl, ucfirst(get_string('unread', 'dialogue')));
        $html .= html_writer::end_tag('li');

        $html .= html_writer::start_tag('li');
        $latesturl = clone($url);
        $latesturl->param('sort', 'latest');
        $html .= html_writer::link($latesturl, ucfirst(get_string('latest', 'dialogue')));
        $html .= html_writer::end_tag('li');

        $html .= html_writer::start_tag('li');
        $oldesturl = clone($url);
        $oldesturl->param('sort', 'oldest');
        $html .= html_writer::link($oldesturl, ucfirst(get_string('oldest', 'dialogue')));
        $html .= html_writer::end_tag('li');

        $html .= html_writer::start_tag('li');
        $authorazurl = clone($url);
        $authorazurl->param('sort', 'authoraz');
        $html .= html_writer::link($authorazurl, ucfirst(get_string('authoraz', 'dialogue')));
        $html .= html_writer::end_tag('li');

        $html .= html_writer::start_tag('li');
        $authorzaurl = clone($url);
        $authorzaurl->param('sort', 'authorza');
        $html .= html_writer::link($authorzaurl, ucfirst(get_string('authorza', 'dialogue')));
        $html .= html_writer::end_tag('li');

        $html .= html_writer::end_tag('ul');
        $html .= html_writer::end_div();

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

    public function listing_tab_navigation() {}

    public function role_selector() {
        global $PAGE, $OUTPUT;

        $html = '';

        $context = $PAGE->context;
        
        $pageurl = clone($PAGE->url);

        $roleid = $pageurl->get_param('roleid');

        $rolenames = role_fix_names(get_assignable_roles($context), $context, ROLENAME_ALIAS, true);
        $html .= html_writer::start_div('dropdown-group');

        $html .= html_writer::span(get_string('role'));

        $html .= html_writer::start_div('js-control btn-group'); // btn-group required for js
        $attributes = array('data-toggle' => 'dropdown',
                            'class' =>'btn btn-small dropdown-toggle');
        $html .= html_writer::start_tag('button', $attributes);
        $html .= $rolenames[$roleid] . ' ' . html_writer::tag('span', null, array('class' => 'caret'));
        $html .= html_writer::end_tag('button');
        $html .= html_writer::start_tag('ul', array('class' => 'dropdown-menu'));
        foreach ($rolenames as $roleid => $rolename) {
            $pageurl->param('roleid', $roleid);
            $html .= html_writer::start_tag('li');
            $html .= html_writer::link($pageurl, $rolename);
            $html .= html_writer::end_tag('li');
        }
        $html .= html_writer::end_tag('ul');
        $html .= html_writer::end_div(); // end of js-control

        // Important: non javascript control must be after javascript control else layout borked in chrome.
        $select = new single_select($pageurl, 'roleid', $rolenames, $roleid, null, 'rolesform');
        //$select->method = 'post';
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
    
    public function tab_navigation() {
        global $PAGE;

        $html = '';
        $currentpage = basename($PAGE->url->out_omit_querystring(), '.php');

        $html .= html_writer::start_tag('ul', array('class'=>'nav nav-tabs'));
        // Main view
        $active = ($currentpage == 'view') ? array('class'=>'active') : array();
        $html .= html_writer::start_tag('li', $active);
        $viewurl = new moodle_url('view.php', array('id'=>$PAGE->cm->id));
        $html .= html_writer::link($viewurl, get_string('viewconversations', 'dialogue'));
        $html .= html_writer::end_tag('li');
        
        if (has_capability('mod/dialogue:viewany', $PAGE->context)) {
            $active = ($currentpage == 'viewbyrole') ? array('class'=>'active') : array();
            $html .= html_writer::start_tag('li', $active);
            $viewurl = new moodle_url('viewbyrole.php', array('id'=>$PAGE->cm->id));
            $html .= html_writer::link($viewurl, get_string('viewbyrole', 'dialogue'));
            $html .= html_writer::end_tag('li');
        }
        // Drafts
        $active = ($currentpage == 'drafts') ? array('class'=>'active') : array();
        $html .= html_writer::start_tag('li', $active);
        $draftsurl = new moodle_url('drafts.php', array('id'=>$PAGE->cm->id));
        $html .= html_writer::link($draftsurl, get_string('drafts', 'dialogue'));
        $html .= html_writer::end_tag('li');
        // 
        if (has_capability('mod/dialogue:bulkopenruleeditany', $PAGE->context)) { //@TODO better named cap
            $active = ($currentpage == 'bulkopenrules') ? array('class'=>'active') : array();
            $html .= html_writer::start_tag('li', $active);
            $bulkopenrulesurl = new moodle_url('bulkopenrules.php', array('id'=>$PAGE->cm->id));
            $html .= html_writer::link($bulkopenrulesurl, get_string('bulkopenrules', 'dialogue'));
            $html .= html_writer::end_tag('li');
        }

        if (has_capability('mod/dialogue:open', $PAGE->context)) {
            $createurl = new moodle_url('conversation.php', array('id'=>$PAGE->cm->id, 'action'=>'create'));
            $html .= html_writer::link($createurl, get_string('create'), array('class'=>'btn-create pull-right'));//array('class'=>'btn btn-primary pull-right')
        }
        $html .= html_writer::end_tag('ul');

        return $html;
    }


}
