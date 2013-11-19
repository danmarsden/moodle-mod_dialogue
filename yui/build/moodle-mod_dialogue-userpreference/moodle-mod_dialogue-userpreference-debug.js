YUI.add('moodle-mod_dialogue-userpreference', function (Y, NAME) {

M.mod_dialogue = M.mod_dialogue || {};
M.mod_dialogue.userpreference = {
    init: function() {
        // registered preferences that can be set.
        var settings = [
                'dialogue_displaybystudent'
            ];
            for (var s in settings) {
                var setting = settings[s];
                Y.one('#'+setting).on('click', this.set_user_preference, this, setting);
            }
    },
    set_user_preference : function(e, name) {
            M.util.set_user_preference(name, Y.one('#'+name).get('checked'));
            // reload the page
            window.location.reload(true)
    }

}


}, '@VERSION@', {
    "requires": [
        "base",
        "node",
        "json-parse",
        "userpreference",
        "userpreference-filters",
        "userpreference-highlighters",
        "event",
        "event-key"
    ]
});
