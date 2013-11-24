M.mod_dialogue = M.mod_dialogue || {};
M.mod_dialogue.upgrade = {
    init_upgrade_table: function(Y) {

        Y.use('node', function(Y) {
            checkboxes = Y.all('td.c0 input');
            checkboxes.each(function(node) {
                node.on('change', function(e) {
                    rowelement = e.currentTarget.get('parentNode').get('parentNode');
                    if (e.currentTarget.get('checked')) {
                        rowelement.setAttribute('class', 'selectedrow');
                    } else {
                        rowelement.setAttribute('class', 'unselectedrow');
                    }
                });

                rowelement = node.get('parentNode').get('parentNode');
                if (node.get('checked')) {
                    rowelement.setAttribute('class', 'selectedrow');
                } else {
                    rowelement.setAttribute('class', 'unselectedrow');
                }
            });
        });

        var selectall = Y.one('th.c0 input');
        selectall.on('change', function(e) {
            if (e.currentTarget.get('checked')) {
                checkboxes = Y.all('td.c0 input');
                checkboxes.each(function(node) {
                    rowelement = node.get('parentNode').get('parentNode');
                    node.set('checked', true);
                    rowelement.setAttribute('class', 'selectedrow');
                });
            } else {
                checkboxes = Y.all('td.c0 input');
                checkboxes.each(function(node) {
                    rowelement = node.get('parentNode').get('parentNode');
                    node.set('checked', false);
                    rowelement.setAttribute('class', 'unselectedrow');
                });
            }
        });

        var upgradeselectedbutton = Y.one('#id_upgradeselected');
        upgradeselectedbutton.on('click', function(e) {
            checkboxes = Y.all('td.c0 input');
            var selecteddialogues = [];
            checkboxes.each(function(node) {
                if (node.get('checked')) {
                    selecteddialogues[selecteddialogues.length] = node.get('value');
                }
            });

            operation = Y.one('#id_operation');
            dialoguesinput = Y.one('input.selecteddialogues');
            dialoguesinput.set('value', selecteddialogues.join(','));
            if (selecteddialogues.length == 0) {
                alert(M.str.dialogue.upgradenodialoguesselected);
                e.preventDefault();
            }
        });
    }
}
