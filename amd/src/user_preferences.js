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

define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    /**
     * Update a users preferences.
     *
     * @param {Object} args Arguments send to the webservice.
     *
     * Sample args:
     * {
     *     preferences: [
     *         {
     *             type: 'block_example_user_sort_preference'
     *             value: 'title'
     *         }
     *     ]
     * }
     */
    var update = function(preferences) {
        var request = {
            methodname: 'core_user_update_user_preferences',
            args: preferences
        };
        Ajax.call([request])[0]
            .fail(Notification.exception);
    };

    return {
        update: update
    };
});
