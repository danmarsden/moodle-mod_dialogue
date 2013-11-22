M.mod_dialogue = M.mod_dialogue || {};
M.mod_dialogue.userpreference = {
    name : null,
    redirect : null,
    init : function(name, redirect) {
        Y.one('#'+name).on('click', this.set_user_preference, this, name, redirect);
    },
    set_user_preference : function(e, name, redirect) {
        value = 0;
        if (Y.one('#'+name).get('checked')) {
            value = 1;
        }
        M.util.set_user_preference(name, value);
    }
};
