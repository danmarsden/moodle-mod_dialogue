/**
 * Gruntfile for compiling theme_bootstrap .less files.
 *
 * This file configures tasks to be run by Grunt
 * http://gruntjs.com/ for the current theme.
 *
 * Requirements:
 * nodejs, npm, grunt-cli.
 *
 * Installation:
 * node and npm: instructions at http://nodejs.org/
 * grunt-cli: `[sudo] npm install -g grunt-cli`
 * node dependencies: run `npm install` in the root directory.
 */

module.exports = function(grunt) {

    // PHP strings for exec task.
    var moodleroot = 'dirname(dirname(__DIR__))',
        configfile = moodleroot + ' . "/config.php"',
    // PHP reset theme cache code
    phpresetcache = '';
    phpresetcache += "define(\"CLI_SCRIPT\", true);";
    phpresetcache += "require(" + configfile  + ");";
    phpresetcache += "theme_reset_all_caches();";

    grunt.initConfig({
        less: {
            // Compile main theme styles.
            dialogue: {
                options: {
                    compress: false,
                    sourceMap: false,
                    outputSourceFiles: true
                },
                files: {
                    "styles.css": "less/dialogue.less"
                }
            }
        },
        exec: {
            resetcache: {
                cmd: "php -r '" + phpresetcache + "'",
                callback: function(error, stdout, stderror) {
                    // exec will output error messages
                    // just add one to confirm success.
                    if (!error) {
                        grunt.log.writeln("Moodle theme cache reset.");
                    }
                }
            },
            shifter: {
                cmd: "shifter --walk --recursive"
            }
        },

        watch: {
            // Watch for any changes to less files and compile. , "exec:shifter"
            files: ["less/**/*.less", "yui/src/**/js/*.js"],
            tasks: ["exec:shifter", "exec:resetcache", "less:dialogue"],
            options: {
                livereload: true,
                compress: false,
                spawn: false
            }
        }
    });

    // Load contrib tasks.
    grunt.loadNpmTasks("grunt-contrib-less");
    grunt.loadNpmTasks("grunt-contrib-watch");
    grunt.loadNpmTasks("grunt-exec");

    // Register tasks.
    grunt.registerTask("default", ["watch"]);
};
