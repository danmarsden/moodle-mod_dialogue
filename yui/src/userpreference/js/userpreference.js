M.mod_dialogue = M.mod_dialogue || {};
M.mod_dialogue.userpreference = {
    name : null,
    init : function(name) {
        Y.one('#'+name).on('click', this.set_user_preference, this, name);
    },
    set_user_preference : function(e, name) {
        value = 0;
        if (Y.one('#'+name).get('checked')) {
            value = 1;
        }
        M.util.set_user_preference(name, value);
    }
};
