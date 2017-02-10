YUI.add('moodle-mod_dialogue-clickredirector', function (Y, NAME) {

M.mod_dialogue = M.mod_dialogue || {};
M.mod_dialogue.clickredirector = {
    /** params **/
    cmid          : null,
    modroot       : M.cfg.wwwroot + '/mod/dialogue/',
    init: function(cmid) {
        this.cmid = cmid; /* todo - check cmid*/
        var redirects = Y.all('.conversation-list tr');
        redirects.on('click', Y.bind(this.handle, this));

    },
    handle: function(e) {
        var params = [],
            page,
            action,
            conversationid,
            messageid,
            redirect;

        params.id = this.cmid;

        // need page i.e: view, conversation, reply
        page = e.currentTarget.getAttribute('data-redirect');
        if (!page) {
            return new M.core.exception({
                name : 'data-redirect not defined as attribute'});
        }
        action = e.currentTarget.getAttribute('data-action');
        if (action) {
            params.action = action;
        }
        conversationid = e.currentTarget.getAttribute('data-conversationid');
        if (conversationid) {
            params.conversationid = conversationid;
        }
        messageid = e.currentTarget.getAttribute('data-messageid');
        if (messageid) {
            params.messageid = messageid;
        }
        // build array of url params
        var urlparams = [];
        for (var param in params) {
            urlparams.push(param + '=' + params[param]);
        }
        // build redirect url
        redirect = this.modroot + page + '.php?' + urlparams.join('&');
        if (e.ctrlKey || e.metaKey) {
            // ugly hack for FF, FU FF
            if (window.getSelection) {
                window.getSelection().removeAllRanges();
            }
            return window.open(redirect, '_blank');
        }
        return window.location.href = redirect;
    }
};


}, '@VERSION@', {
    "requires": [
        "base",
        "node",
        "json-parse",
        "clickredirector",
        "clickredirector-filters",
        "clickredirector-highlighters",
        "event",
        "event-key"
    ]
});
