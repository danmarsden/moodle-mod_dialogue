<?php  //$Id: upgrade.php,v 1.2 2009/08/20 02:23:21 deeknow Exp $

function xmldb_dialogue_upgrade($oldversion=0) {

    global $CFG, $THEME, $db;

    $result = true;
    
    if ($result && $oldversion < 2007100300) {

    /// Define field recipientid to be added to dialogue_entries
        $table = new XMLDBTable('dialogue_entries');
        $field = new XMLDBField('recipientid');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, '0', 'userid');

    /// Launch add field recipientid
        $result = $result && add_field($table, $field);
        
        $index = new XMLDBIndex('dialogue_entries_recipientid_idx');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('recipientid'));

    /// Launch add index dialogue_entries_recipientid_idx
        $result = $result && add_index($table, $index);
    }
    
    if ($result && $oldversion < 2007100301) {

    /// Define field lastrecipientid to be added to dialogue_conversations
        $table = new XMLDBTable('dialogue_conversations');
        $field = new XMLDBField('lastrecipientid');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, '0', 'lastid');

    /// Launch add field lastrecipientid
        $result = $result && add_field($table, $field);
    }
    
    if ($result && $oldversion < 2007100400) {

    /// Define field attachment to be added to dialogue_entries
        $table = new XMLDBTable('dialogue_entries');
        $field = new XMLDBField('attachment');
        $field->setAttributes(XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null, null, null, 'text');

    /// Launch add field attachment
        $result = $result && add_field($table, $field);
    }
    
    if ($result && $oldversion < 2007100800) {

    /// Define field edittime to be added to dialogue
        $table = new XMLDBTable('dialogue');
        $field = new XMLDBField('edittime');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'intro');

    /// Launch add field edittime
        $result = $result && add_field($table, $field);
    }
    
    if ($result && $oldversion < 2007110700) {

    /// Define field groupid to be added to dialogue_conversations
        $table = new XMLDBTable('dialogue_conversations');
        $field = new XMLDBField('groupid');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'subject');

    /// Launch add field groupid
        $result = $result && add_field($table, $field);
        
    /// Define index dialogue_conversations_groupid_idx (not unique) to be added to dialogue_conversations
        $index = new XMLDBIndex('dialogue_conversations_groupid_idx');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('groupid'));

    /// Launch add index dialogue_conversations_groupid_idx
        $result = $result && add_index($table, $index);
    }
    
    if ($result && $oldversion < 2007110800) {

    /// Define field grouping to be added to dialogue_conversations
        $table = new XMLDBTable('dialogue_conversations');
        $field = new XMLDBField('grouping');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null, null, '0', 'groupid');

    /// Launch add field grouping
        $result = $result && add_field($table, $field);

    /// Define index dialogue_conversations_grouping_idx (not unique) to be added to dialogue_conversations
        $table = new XMLDBTable('dialogue_conversations');
        $index = new XMLDBIndex('dialogue_conversations_grouping_idx');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('grouping'));

    /// Launch add index dialogue_conversations_grouping_idx
        $result = $result && add_index($table, $index);
        
    }
    
    if ($result && $oldversion < 2007111401) {

    /// Define field timemodified to be added to dialogue_entries
        $table = new XMLDBTable('dialogue_entries');
        $field = new XMLDBField('timemodified');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', null, null, null, null, null, '0', 'timecreated');

    /// Launch add field timemodified
        $result = $result && add_field($table, $field);
        
        $result = $result && $result = execute_sql('UPDATE '.$CFG->prefix.'dialogue_entries SET timemodified = timecreated');
    }
    
    if ($result && $oldversion < 2007112200) {

    /// Define table dialogue_read to be created
        $table = new XMLDBTable('dialogue_read');

    /// Adding fields to table dialogue_read
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('entryid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('firstread', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('lastread', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, null);

    /// Adding keys to table dialogue_read
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->addKeyInfo('dialogueread_entryid_userid_uk', XMLDB_KEY_UNIQUE, array('entryid', 'userid'));

    /// Launch create table for dialogue_read
        $result = $result && create_table($table);
    }
    
    if ($result && $oldversion < 2007112201) {

    /// Define field conversationid to be added to dialogue_read
        $table = new XMLDBTable('dialogue_read');
        $field = new XMLDBField('conversationid');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, null, 'lastread');

    /// Launch add field conversationid
        $result = $result && add_field($table, $field);

    /// Define key dialogueread_conversation_fk (foreign) to be added to dialogue_read
        $table = new XMLDBTable('dialogue_read');
        $key = new XMLDBKey('dialogueread_conversation_fk');
        $key->setAttributes(XMLDB_KEY_FOREIGN, array('conversationid'), 'dialogue_conversations', array('id'));

    /// Launch add key dialogueread_conversation_fk
        $result = $result && add_key($table, $key);
        
    }
    
    if ($result && $oldversion < 2007121701) {
        $logdisplay = new stdClass;
        $logdisplay->module = 'assignment';
        $logdisplay->mtable = 'assignment';
        $logdisplay->field  = 'name';
        
        $logdisplay->action = 'delete';
        $result = $result && insert_record('log_display', $logdisplay);
        
        $logdisplay->action = 'view receipt';
        $result = $result && insert_record('log_display', $logdisplay);
    }
    return($result);
}

?>