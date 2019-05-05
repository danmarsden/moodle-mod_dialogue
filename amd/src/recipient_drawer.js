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

define(
    [
        'jquery',
        'core/custom_interaction_events',
        'core/pubsub'
    ],
    function(
        $,
        CustomEvents,
        PubSub
    ) {

        /**
         * Show the drawer.
         *
         * @param {Object} root The recipient drawer container.
         */
        var show = function(root) {
            if (!root.attr('data-shown')) {
                root.attr('data-shown', true);
            }
            root.removeClass('hidden');
            root.attr('aria-expanded', true);
            root.attr('aria-hidden', false);
        };

        /**
         * Hide the drawer.
         *
         * @param {Object} root The recipient drawer container.
         */
        var hide = function(root) {
            root.addClass('hidden');
            root.attr('aria-expanded', false);
            root.attr('aria-hidden', true);
        };

        /**
         * Check if the drawer is visible.
         *
         * @param {Object} root The message drawer container.
         * @return {bool}
         */
        var isVisible = function(root) {
            return !root.hasClass('hidden');
        };

        /**
         * Listen to and handle events for routing, showing and hiding the message drawer.
         *
         * @param {Object} root The message drawer container.
         */
        var registerEventListeners = function(root) {
            CustomEvents.define(root, [CustomEvents.events.activate]);



            var paramRegex = /^data-route-param-?(\d*)$/;

            root.on(CustomEvents.events.activate, SELECTORS.ROUTES, function(e, data) {
                var element = $(e.target).closest(SELECTORS.ROUTES);
                var route = element.attr('data-route');
                var attributes = [];

                for (var i = 0; i < element[0].attributes.length; i++) {
                    attributes.push(element[0].attributes[i]);
                }

                var paramAttributes = attributes.filter(function(attribute) {
                    var name = attribute.nodeName;
                    var match = paramRegex.test(name);
                    return match;
                });
                paramAttributes.sort(function(a, b) {
                    var aParts = paramRegex.exec(a.nodeName);
                    var bParts = paramRegex.exec(b.nodeName);
                    var aIndex = aParts.length > 1 ? aParts[1] : 0;
                    var bIndex = bParts.length > 1 ? bParts[1] : 0;

                    if (aIndex < bIndex) {
                        return -1;
                    } else if (bIndex < aIndex) {
                        return 1;
                    } else {
                        return 0;
                    }
                });

                var params = paramAttributes.map(function(attribute) {
                    return attribute.nodeValue;
                });
                var routeParams = [route].concat(params);

                Router.go.apply(null, routeParams);

                data.originalEvent.preventDefault();
            });

            root.on(CustomEvents.events.activate, SELECTORS.ROUTES_BACK, function(e, data) {
                Router.back();

                data.originalEvent.preventDefault();
            });

            PubSub.subscribe(Events.SHOW, function() {
                show(root);
            });

            PubSub.subscribe(Events.HIDE, function() {
                hide(root);
            });

            PubSub.subscribe(Events.TOGGLE_VISIBILITY, function() {
                if (isVisible(root)) {
                    hide(root);
                } else {
                    show(root);
                }
            });

            PubSub.subscribe(Events.SHOW_CONVERSATION, function(conversationId) {
                show(root);
                Router.go(Routes.VIEW_CONVERSATION, conversationId);
            });

            PubSub.subscribe(Events.CREATE_CONVERSATION_WITH_USER, function(userId) {
                show(root);
                Router.go(Routes.VIEW_CONVERSATION, null, 'create', userId);
            });

            PubSub.subscribe(Events.SHOW_SETTINGS, function() {
                show(root);
                Router.go(Routes.VIEW_SETTINGS);
            });

            PubSub.subscribe(Events.PREFERENCES_UPDATED, function(preferences) {
                var filteredPreferences = preferences.filter(function(preference) {
                    return preference.type == 'message_entertosend';
                });
                var enterToSendPreference = filteredPreferences.length ? filteredPreferences[0] : null;

                if (enterToSendPreference) {
                    var viewConversationFooter = root.find(SELECTORS.FOOTER_CONTAINER).find(SELECTORS.VIEW_CONVERSATION);
                    viewConversationFooter.attr('data-enter-to-send', enterToSendPreference.value);
                }
            });
        };

        /**
         * Initialise the message drawer.
         *
         * @param {Object} root The message drawer container.
         */
        var init = function(root) {
            root = $(root);
            registerEventListeners(root);
        };

        return {
            init: init,
        };
    });
