YUI.add('moodle-mod_dialogue-recipientpicker', function (Y, NAME) {


var RecipientPicker = function() {
    RecipientPicker.superclass.constructor.apply(this, arguments);
};
RecipientPicker.NAME    = "recipientpicker";
RecipientPicker.ATTRS   = {};

Y.extend(RecipientPicker, Y.Base, {
    AutoComplete: {},
    // Custom field. More Info
    resultRequiredFields: ['id', 'fullname', 'picture', 'imagealt'],
    initializer: function (config) {
        // inputNode required, if we don't have it we can't build
        // the AutoComplete class.
        this.inputNode  = config.inputNode || null;
        if (! Y.one(this.inputNode)) {
            return false;
        }
        this.source     = config.source || null;

        // Result config
        this.resultTextLocator = config.resultTextLocator || 'fullname';
        this.resultListLocator = config.resultListLocator || 'results';
        this.resultHighlighter = 'phraseMatch';
        this.maxResults = parseInt(config.maxResults, 10) || 10;

        this.resultFormatter = this.formatResultList;

        // Setup AutoComplete
        this.AutoComplete = new Y.AutoComplete(this.getAutoCompleteConfig());

        // Custom field.
        this.selectedNode       = config.selectedNode || null;
        this.selectedHiddenName = config.selectedHiddenName || 'rp-data';

        this.AutoComplete.on('query', Y.bind(this.onQuery, this));

        if (Y.one(this.selectedNode)) {
            Y.one(this.selectedNode).addClass('rp-selected-group'); // Add class for CSS styling.
            this.AutoComplete.after('select', Y.bind(this.onListItemSelected, this));
        } else {
            return new M.core.exception(new Error('selectedNode not found, must be defined!'));
        }
        // Setup input text focus event.
        Y.one(this.inputNode).on('focus', Y.bind(this.onInputFocus, this));
        Y.one(this.inputNode).on('key', Y.bind(this.onInputBackspace, this), 'backspace');

        // Now load any data
        if (config.selectedItems) {
            for (var i = 0; i < config.selectedItems.length; i++) {
                this.addSelectedItem(config.selectedItems[i]);
            }
        }

        this.AutoComplete.render(); // Need this.
    },
    onQuery: function(e) {
        var selectedItems = Y.all('.rp-selected-item');
        if (selectedItems.size() === 1) {
            e.preventDefault();
            e.stopPropagation();

        }
    },
    onItemRemove: function(event) {
        var item = event.currentTarget.ancestor('.rp-selected-item');
        if (!item) {
            return false;
        }
        return item.remove();
    },
    onInputFocus: function() {
        var inputNode = Y.one(this.inputNode),
            selectedItems = Y.all('.rp-selected-item');
        if (selectedItems.size() === 1) {
            return;
        }
        // Drop the list.
        if (inputNode.get('value') === '') {
            this.AutoComplete.sendRequest('');
        }
    },
    onInputBackspace: function() {
        var inputNode = Y.one(this.inputNode),
            selectedNode = Y.one(this.selectedNode),
            last;

        last = selectedNode.one('> .rp-selected-item:last-of-type');
        if (last && inputNode.get('value') === '') {
            last.remove();
        }
    },
    onListItemSelected: function(event) {
        var record  = event.result.raw;
        this.addSelectedItem(record);
    },
    addSelectedItem: function(record) {
        var content,
            select,
            input;

        select  = Y.one(this.selectedNode);
        input   = Y.one(this.inputNode);

        // Not a object, then leave.
        if (!(record instanceof Object)) {
            return;
        }

        // Check we have all the required fields.
        Y.Array.each(this.resultRequiredFields, function(name) {
            if (!record.hasOwnProperty(name)) {
                return new M.core.exception(new Error('Required field ' + name + ' not found!'));
            }
        });

        if (select) {
            content =  Y.Node.create('<div class="rp-selected-item"></div>')
                .append('<input type="hidden" name="'+this.selectedHiddenName+'[]" value="'+record.id+'"/>')
                .append(Y.Node.create('<img class="userpicture" src="' + record.imageurl +
                '" alt="' + record.imagealt + '" title="' + record.imagealt + '"/>'))
                .append(Y.Node.create('<span class="fullname">'+ record.fullname+'</span>'))
                .append(Y.Node.create('<img class="rp-remove-action" src="'+M.util.image_url('remove', 'dialogue')+'"/>'));

            // Append item HTML to selected item container.
            select.append(content);
            // Bind handler.
            Y.on('click', Y.bind(this.onItemRemove, this),'.rp-selected-group .rp-selected-item .rp-remove-action');
            input.set('value', '');
        }
    },
    getAutoCompleteConfig: function(name) {
        var attributes = ['inputNode',
                          'source',
                          'resultTextLocator',
                          'resultListLocator',
                          'resultHighlighter',
                          'maxResults',
                          'resultFormatter'];

        var config = {},
            idx;
        if (name) {
            idx = attributes.indexOf(name);
            config =  this[attributes[idx]];
        } else {
            for (var i in attributes) {
                config[attributes[i]] = this[attributes[i]];
            }
        }
        return config;
    },
    formatResultList: function (query, results) {
        return Y.Array.map(results, function(result) {
            var record = result.raw;
            var content = Y.Node.create('<div class="rp-result-item"></div>')
                .append(Y.Node.create('<img class="userpicture" src="' + record.imageurl +
                '" alt="' + record.imagealt + '" title="' + record.imagealt + '"/>'))
                .append(Y.Node.create('<span class="fullname">'+result.highlighted+'</span>'));
            return content;
        });
    }
});

M.mod_dialogue = M.mod_dialogue || {};
M.mod_dialogue.recipientpicker = M.mod_dialogue.recipientpicker || {};
M.mod_dialogue.recipientpicker.init = function(config) {
    return new RecipientPicker(config);
};




}, '@VERSION@', {
    "requires": [
        "base",
        "node",
        "node-event-delegate",
        "json-parse",
        "autocomplete",
        "autocomplete-filters",
        "autocomplete-highlighters",
        "event",
        "event-key",
        "moodle-core-notification-exception"
    ]
});
