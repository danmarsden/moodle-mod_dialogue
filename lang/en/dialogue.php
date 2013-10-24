<?php
$string['pluginname'] = 'Dialogue';
$string['modulename'] = 'Dialogue';
$string['modulenameplural'] = 'Dialogues';
$string['modulename_help'] = 'Dialogues allow students or teachers to start two-way dialogues with another person. They are course activities that can be useful when the teacher wants a place to give private feedback to a student on their online activity. For example, if a student is participating in a language forum and made a grammatical error that the teacher wants to point out without embarassing the student, a dialogue is the perfect place. A dialogue activity would also be an excellent way for counsellors within an institution to interact with students - all activities are logged and email is not necessarily required';
$string['dialogue:addinstance'] = 'Add a Dialogue';
// Capabilities
$string['dialogue:open'] = 'Open a conversation';
$string['dialogue:receive'] = 'Receive, who can be the recipient when opening a conversation';
$string['dialogue:close'] = 'Close a conversation';
$string['dialogue:reply'] = 'Reply';
$string['dialogue:closeany'] = 'Close any';
$string['dialogue:deleteany'] = 'Delete any';
$string['dialogue:replyany'] = 'Reply any';
$string['dialogue:bulkopenrulecreate'] = 'Create a bulk opener rule';
$string['dialogue:bulkopenruleeditany'] = 'Allows user to edit any rule, useful for admin\'s etc';
$string['dialogue:viewany'] = 'View any conversation in a dialogue course module instance';
// Cache
$string['cachedef_userdetails'] = 'User brief details, all enrolled users';
$string['cachedef_participants'] = 'Participants id\'s (basic information)';
$string['cachedef_unreadcounts'] = 'Users unread message counts in conversations';
$string['cachedef_params'] = 'Params - user interface';

$string['configtrackunread'] = 'Track unread dialogue messages on course homepage';
$string['configmaxattachments'] = 'Default maximum number of attachments allowed per post.';
$string['configmaxbytes'] = 'Default maximum size for all dialogue attachments on the site (subject to course limits and other local settings)';
$string['maxattachments'] = 'Maximum number of attachments';
$string['maxattachments_help'] = 'This setting specifies the maximum number of files that can be attached to a dialogue post.';
$string['maxattachmentsize'] = 'Maximum attachment size';
$string['maxattachmentsize_help'] = 'This setting specifies the largest size of file that can be attached to a dialogue post.';

$string['dialoguename'] = 'Dialogue name';
$string['dialogueintro'] = 'Dialogue Introduction';
$string['pluginadministration'] = 'Dialogue administration';


$string['bulkopenrules'] = 'Bulk open rules';
$string['conversations'] = 'Conversations';
$string['drafts'] = 'Drafts';
$string['draft'] = 'Draft';

$string['onlydraftscanbetrashed'] = 'Only drafts can be trashed';
$string['draftconversationtrashed'] = 'Draft conversation trashed';
$string['draftreplytrashed'] = 'Draft reply trashed';


$string['open'] = 'Open';
$string['closed'] = 'Closed';
$string['nosubject'] = '[no subject]';

//* need check from fields
$string['message'] = 'Message';
$string['openwith'] = 'Open with';
$string['includefuturemembers'] = 'Include future members';
$string['cutoffdate'] = 'Cut off date';
$string['people'] = 'People';
$string['usesearch'] = 'Use search to find people to start a dialogue with';
$string['nomatchingpeople'] = 'No people match \'{$a}\'';
$string['matchingpeople'] = 'Matching people ({$a})';
$string['subject'] = 'Subject';
$string['send'] = 'Send';
$string['savedraft'] = 'Save draft';
$string['trashdraft'] = 'Trash draft';
$string['attachments'] = 'Attachments';
$string['searchpotentials'] = ' Search potentials...';

$string['messages'] = 'messages';
$string['unread'] = 'Unread';
$string['unreadmessages'] = 'Unread messages';
$string['unreadmessagesnumber'] = '{$a} unread messages';
$string['unreadmessagesone'] = '1 unread message';

$string['erroremptysubject'] = 'Subject cannot be empty.';
$string['erroremptymessage'] = 'Message cannot be empty';
$string['errornoparticipant'] = 'You must open a dialogue with somebody...';

$string['commentedago'] = '{$a->fullname} commented {$a->timeago} ago';
$string['repliedby'] = '{$a->fullname} <small>replied</small> {$a->timeago}';


// time strings

//$string['datetoday'] = '{$a->time}';
$string['dateshortyear'] = '{$a->dateshort} <small>({$a->time})</small>';
$string['datefullyear'] = '{$a->date} <small>({$a->time})</small>';

$string['openedbytoday'] = '<small>Opened by</small> {$a->fullname} <small>at</small> {$a->time} <small>({$a->unitstring}) ago</small>';
$string['openedbyshortyear'] = '<small>Opened by</small> {$a->fullname} <small>on</small> {$a->dateshort} <small>({$a->time})</small>';
$string['openedbyfullyear'] = '<small>Opened by</small> {$a->fullname} <small>on</small> {$a->date} <small>({$a->time})</small>';

$string['repliedbytoday'] = '{$a->fullname} <small>replied at</small> {$a->time} <small>({$a->unitstring}) ago</small>';
$string['repliedbyshortyear'] = '{$a->fullname} <small>replied on</small> {$a->dateshort} <small>({$a->time})</small>';
$string['repliedbyfullyear'] = '{$a->fullname} <small>replied on</small> {$a->date} <small>({$a->time})</small>';

// States
$string['bulkopener'] = 'Bulk opener';

$string['numberattachments'] = '{$a} attachments';
$string['attachment'] = 'Attachment';

$string['mine'] = 'Mine';
$string['everyone'] = 'Everyone';
$string['reply'] = 'Reply';

$string['numberunread'] = '{$a} unread';
$string['messageprovider:post'] = 'Dialogue notifications';


$string['noconversationsfound'] = 'No conversations found!';
$string['nodraftsfound'] = 'No drafts found!';
$string['nobulkrulesfound'] = 'No bulk rules found!';

$string['justmy'] = 'just my';
$string['everyones'] = 'everyone\'s';
$string['ingroup'] = 'in group {$a}';


$string['conversationlistdisplayheader'] = 'Displaying {$a->show} {$a->state} conversations {$a->groupname}';
$string['draftlistdisplayheader'] = 'Displaying my drafts';



$string['listpaginationheader'] = '{$a->start}-{$a->end} of {$a->total}';


$string['groupmodenotifymessage'] = 'This activity is running in groupmode, this will affect who you can start a conversation with and what conversations are displayed.';

$string['draftconversation'] = 'Draft conversation';
$string['draftreply'] = 'Draft reply';

$string['closeconversation'] = 'Close conversation';
$string['conversationclosed'] = 'Conversation is closed';
$string['conversationdiscarded'] = 'Conversation discarded';
$string['bulkopenrule'] = 'Bulk open rule';
$string['actions'] = 'Actions';

$string['conversationcloseconfirm'] = 'Are you sure you want to close conversation {$a} ?';
$string['conversationclosed'] = 'Conversation {$a} has been closed';

$string['conversationdeleteconfirm'] = 'Are you sure you want to delete conversation {$a} ?';
$string['conversationdeleted'] = 'Conversation {$a} has been deleted';

$string['replydeleteconfirm'] = 'Are you sure you want to this draft reply?';
$string['replydeleted'] = 'Draft reply has been deleted';

$string['replysent'] = 'Your reply has been sent';
$string['conversationopened'] = 'Conversation has been opened';
$string['conversationopenedcron'] = 'Conversations will be opened automatically';

$string['upgrademessage'] = 'This Dialogue needs to be upgraded! Please contact your Moodle administrator';

// Errors
$string['cannotclosedraftconversation'] = 'You cannot close a conversation that hasn\'t started!';
$string['nopermissiontoclose'] = 'You do not have permission to close this conversation!';
$string['nopermissiontodelete'] = 'You do not have permission to delete!';
$string['cannotdeleteopenconversation'] = 'You cannot delete a open conversation';

$string['viewconversations'] = 'View conversations';
$string['viewbyrole'] = 'View by role';
$string['ago'] = 'ago';

$string['participants'] = 'participants';


// dropdowns
$string['sortedby'] = 'Sorted by: {$a} ';
$string['latest'] = 'latest';
$string['unread'] = 'unread';
$string['oldest'] = 'oldest';
$string['authoraz'] = 'author (a&raquo;z)';
$string['authorza'] = 'author (z&raquo;a)';
$string['lastnameaz'] = 'lastname (a&raquo;z)';
$string['lastnameza'] = 'lastname (z&raquo;a)';
$string['firstnameaz'] = 'firstname (a&raquo;z)';
$string['firstnameza'] = 'firstname (z&raquo;a)';

$string['lastranon'] = 'Last ran on ';
$string['hasnotrun'] = 'Has not run yet';
$string['completed'] = 'Completed';
$string['runsuntil'] = 'Runs until ';

$string['displaying'] = 'Displaying';

$string['messageapismallmessage'] = '{$a} posted a new message to a conversation you are participating in';
$string['messageapibasicmessage'] = '
<p>{$a->userfrom} posted a new message to a conversation you are participanting
in with subject: <i>{$a->subject}</i>
<br/><br/><a href="{$a->url}">View in Moodle</a></p>';

$string['dialogueupgradehelper'] = 'Dialogue upgrade helper';
$string['upgrade'] = 'Upgrade';
$string['upgradecheck'] = 'Upgrade dialogue {$a}?';
$string['upgradeiscompleted'] = 'Upgrade is completed';
$string['upgradenoneedupgrade'] = '{$a} dialogues need to be upgraded';
$string['upgradereport'] = 'List dialogues still to upgrade';
$string['upgradereportdescription'] = 'This will show a report of all the dialogues on the system that still need to be upgraded to schema';
