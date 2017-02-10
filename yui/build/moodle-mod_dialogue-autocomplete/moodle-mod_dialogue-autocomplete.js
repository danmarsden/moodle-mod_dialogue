YUI.add('moodle-mod_dialogue-autocomplete', function (Y, NAME) {

M.mod_dialogue = M.mod_dialogue || {};
M.mod_dialogue.autocomplete = {
    /** selectors **/
    SELECTORS: {
        CONTAINER: '#participant_autocomplete_field',
        INPUT: '#participant_autocomplete_input'
    },

    
    RESULTTEXTLOCATOR : 'fullname',
    RESULTLISTLOCATOR : 'results',

    containernode : null,
    inputnode     : null,
    listnode      : null,
    /** params **/
    cmid          : null,
    participants  : null,
    

    init: function(cmid, participants) {

        this.cmid = cmid;
        this.participants = participants;
        

        this.removeimageurl = M.util.image_url('remove', 'dialogue');

        this.containernode =  Y.one(this.SELECTORS.CONTAINER);
        this.inputnode = Y.one(this.SELECTORS.INPUT);
        this.listnode = Y.Node.create('<ul id="'+Y.guid()+'"></ul>');

        this.inputnode.plug(Y.Plugin.AutoComplete);
        this.inputnode.ac.set('resultHighlighter', 'phraseMatch');
        this.inputnode.ac.set('source', M.cfg.wwwroot + '/mod/dialogue/searchpotentials.json.php?q={query}&id=' +
            cmid+'&sesskey='+M.cfg.sesskey);
        this.inputnode.ac.set('resultTextLocator', this.RESULTTEXTLOCATOR);
        this.inputnode.ac.set('resultListLocator', this.RESULTLISTLOCATOR);
        this.inputnode.ac.set('resultFormatter', this.result_formatter);
        this.inputnode.ac.set('minQueryLength', 0);
        this.inputnode.ac.set('maxResults', 10);
        this.containernode.prepend(this.listnode);
        // add footer, this will be used for displaying record information, found counts etc
        this.listfooter = Y.Node.create('<div class="yui3-aclist-footer"></div>');
        Y.one('.yui3-aclist-content').append(this.listfooter);

        try {
            participants = Y.JSON.parse(participants);
            for (var idx in participants){
              this.add_participant(participants[idx]);
            }
        }
        catch (e) {
        }

        // remove all non-js controls they will interfere with data elsewise
        Y.all('.nonjs-control').remove();

        // bind handle_select function on the autocomplete object
        this.inputnode.ac.after('select', Y.bind(this.handle_select, this));
        this.containernode.one('.drop-down-arrow').on('click', Y.bind(this.drop_the_list, this));
        this.inputnode.on('key', Y.bind(this.handle_backspace, this), 'backspace');
        this.inputnode.ac.on('results', Y.bind(this.enforce_participant_limit, this));
        this.inputnode.ac.on('results', Y.bind(this.refresh_footer, this));
     
    },

    enforce_participant_limit : function(e) {
        if (this.listnode.get('children').size() >= 1) {
            e.preventDefault();
            e.stopPropagation();
        }
    },

    refresh_footer : function(e) {
        var displayingto = (e.data.matches < e.data.pagesize) ? e.data.matches : e.data.pagesize;
        this.listfooter.setHTML('<strong><small>Displaying 1-'+ displayingto +' of ' + e.data.matches + '</small></strong>');
    },

    handle_select    : function(e) {
        var person = e.result.raw;
        this.add_participant(person);
        this.inputnode.set('value', '');
    },
            
    add_participant : function(participant) {
        var participantitem = this.make_participant_item(participant);
        this.listnode.append(participantitem);
        // attachremove listener after append
        Y.all('li div img.remove').on('click', Y.bind(this.remove_participant_onclick, this));
        // force safari to repaint, done by adding class. only safari, safari you suck!
        this.containernode.toggleClass('force-safari-repaint');
    },

    remove_participant_onclick : function(e) {
        var participant = Y.one(e.currentTarget).ancestor('li');
        if (participant){
            participant.remove();
        }
    },

    remove_last_participant : function() {
        var lastparticipant = this.listnode.one('> li:last-of-type');
        if (lastparticipant && this.inputnode.get('value') === '') {
            lastparticipant.remove();
        }
    },

    handle_backspace : function() {
        this.remove_last_participant();
        this.inputnode.focus();
    },

    drop_the_list : function() {
        this.inputnode.ac.sendRequest('');
        this.inputnode.focus();
    },

    make_participant_item : function(person) {

        var template = '<li>' +
                       '<div class="aclist-participant-item">' +
                       '<img src="{personimageurl}" alt="{personimagealt}" title="{personimagealt}" class="userpicture" />' +
                       '<span>{personfullname}</span>' +
                       '<img class="remove" src="{removeimageurl}"/>' +
                       '<input type="hidden" name="p[]" value="{personid}"/>' +
                       '</div>' +
                       '</li>';

         return Y.Lang.sub(template, {personimageurl : person.imageurl,
                                      personimagealt : person.imagealt,
                                      personfullname : person.fullname,
                                      personid       : person.id,
                                      removeimageurl : this.removeimageurl});

    },
    result_formatter : function (query, results) {

        var r =  Y.Array.map(results, function (result) {
               var person = result.raw;
               return '<div class="">' +
                      '<img src="'+person.imageurl+'" alt="'+person.imagealt+'" title="'+person.imagealt+
                        '" class="userpicture" />' +
                      '<span class="participant-name">' + result.highlighted + '</span>' +
                      '</div>';
        
        });
        return r;
    }
};


}, '@VERSION@', {
    "requires": [
        "base",
        "node",
        "json-parse",
        "autocomplete",
        "autocomplete-filters",
        "autocomplete-highlighters",
        "event",
        "event-key"
    ]
});
