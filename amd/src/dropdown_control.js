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
        'core/log',
        'mod_dialogue/user_preferences',
        'mod_dialogue/pubsub'

    ],
    function(
        $,
        customEvents,
        log,
        userPreferences,
        pubSub
    ) {
        var SELECTORS = {
            DESCENDANTS: '[data-filter]',
            PREFERENCE: 'data-preference',
            VALUE: 'data-value'
        };

        var preferences = [];

        /**
         * Borrowed from Ryan Wyllie's JavaScript work in 3.6.
         *
         * Add an event handler for dropdown menus that wish to show their active item
         * in the dropdown toggle element.
         *
         * By default the handler will add the "active" class to the selected dropdown
         * item and set it's text as the HTML for the dropdown toggle.
         *
         * The behaviour of this handler is controlled by adding data attributes to
         * the HTML and requires the typically Bootstrap dropdown markup.
         *
         * data-show-active-item - Add to the .dropdown-menu element to enable default
         *                         functionality.
         * data-skip-active-class - Add to the .dropdown-menu to prevent this code from
         *                          adding the active class to the dropdown items
         * data-active-item-text - Add to an element within the data-toggle="dropdown" element
         *                         to use it as the active option text placeholder otherwise the
         *                         data-toggle="dropdown" element itself will be used.
         */
        var registerSelector = function(selector) {
            customEvents.define(selector, [customEvents.events.activate]);
            selector.on(customEvents.events.activate, SELECTORS.DESCENDANTS, function(e, data) {
                var option = $(e.target);
                var menuContainer = option.closest('[data-show-active-item]');
                var value = option.attr(SELECTORS.VALUE);
                // Ignore non Bootstrap dropdowns.
                if (!option.hasClass('dropdown-item')) {
                    return;
                }
                // If it's already active then we don't need to do anything.
                if (option.hasClass('active')) {
                    return;
                }
                // Clear the active class from all other options.
                var dropdownItems = menuContainer.find('.dropdown-item');
                dropdownItems.removeClass('active');
                dropdownItems.removeAttr('aria-current');
                if (!menuContainer.attr('data-skip-active-class')) {
                    // Make this option active unless configured to ignore it.
                    // Some code, for example the Bootstrap tabs, may want to handle
                    // adding the active class itself.
                    option.addClass('active');
                }
                // Update aria attribute for active item.
                option.attr('aria-current', true);

                var activeOptionText = option.text();
                var dropdownToggle = menuContainer.parent().find('[data-toggle="dropdown"]');
                var dropdownToggleText = dropdownToggle.find('[data-active-item-text]');

                if (dropdownToggleText.length) {
                    // We have a specific placeholder for the active item text so
                    // use that.
                    dropdownToggleText.html(activeOptionText);
                } else {
                    // Otherwise just replace all of the toggle text with the active item.
                    dropdownToggle.html(activeOptionText);
                }
                // Set user preference
                if (preferences.length > 0) {
                    var type = selector.attr(SELECTORS.PREFERENCE);
                    userPreferences.update({
                        preferences: [
                            {
                                type: type,
                                value: value
                            }
                        ]
                    });
                    pubSub.publish(type, value);
                }
                data.originalEvent.preventDefault();

            });
        };

        /**
         *
         */
        var init = function(selector, preferenceName) {
            log.debug('Initialising filter ' + selector);
            selector = $(selector);
            registerSelector(selector);
            if (preferenceName !== undefined) {
                log.debug(preferenceName);
                selector.attr(SELECTORS.PREFERENCE, preferenceName);
                preferences.push(preferenceName);
            }

        };

        /**
         *
         */
        return {
            init: init
        };
});
