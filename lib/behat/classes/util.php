<?php
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

/**
 * Utils for behat-related stuff
 *
 * @package    core
 * @category   test
 * @copyright  2012 David Monllaó
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/../../testing/classes/util.php');
require_once(__DIR__ . '/behat_command.php');
require_once(__DIR__ . '/behat_config_manager.php');

require_once(__DIR__ . '/../../filelib.php');
require_once(__DIR__ . '/../../clilib.php');
require_once(__DIR__ . '/../../csslib.php');

use Behat\Mink\Session;
use Behat\Mink\Exception\ExpectationException;

/**
 * Init/reset utilities for Behat database and dataroot
 *
 * @package   core
 * @category  test
 * @copyright 2013 David Monllaó
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_util extends testing_util {

    /**
     * The behat test site fullname and shortname.
     */
    const BEHATSITENAME = "Acceptance test site";

    /**
     * @var array Files to skip when resetting dataroot folder
     */
    protected static $datarootskiponreset = array('.', '..', 'behat', 'behattestdir.txt');

    /**
     * @var array Files to skip when dropping dataroot folder
     */
    protected static $datarootskipondrop = array('.', '..', 'lock');

    /**
     * Installs a site using $CFG->dataroot and $CFG->prefix
     * @throws coding_exception
     * @return void
     */
    public static function install_site() {
        global $DB, $CFG;
        require_once($CFG->dirroot.'/user/lib.php');
        if (!defined('BEHAT_UTIL')) {
            throw new coding_exception('This method can be only used by Behat CLI tool');
        }

        $tables = $DB->get_tables(false);
        if (!empty($tables)) {
            behat_error(BEHAT_EXITCODE_INSTALLED);
        }

        // New dataroot.
        self::reset_dataroot();

        $options = array();
        $options['adminuser'] = 'admin';
        $options['adminpass'] = 'admin';
        $options['fullname'] = self::BEHATSITENAME;
        $options['shortname'] = self::BEHATSITENAME;

        install_cli_database($options, false);

        // We need to keep the installed dataroot filedir files.
        // So each time we reset the dataroot before running a test, the default files are still installed.
        self::save_original_data_files();

        $frontpagesummary = new admin_setting_special_frontpagedesc();
        $frontpagesummary->write_setting(self::BEHATSITENAME);

        // Update admin user info.
        $user = $DB->get_record('user', array('username' => 'admin'));
        $user->email = 'moodle@example.com';
        $user->firstname = 'Admin';
        $user->lastname = 'User';
        $user->city = 'Perth';
        $user->country = 'AU';
        user_update_user($user, false);

        // Disable email message processor.
        $DB->set_field('message_processors', 'enabled', '0', array('name' => 'email'));

        // Sets maximum debug level.
        set_config('debug', DEBUG_DEVELOPER);
        set_config('debugdisplay', 1);

        // Disable some settings that are not wanted on test sites.
        set_config('noemailever', 1);

        // Enable web cron.
        set_config('cronclionly', 0);

        // Set noreplyaddress to an example domain, as it should be valid email address and test site can be a localhost.
        set_config('noreplyaddress', 'noreply@example.com');

        // Set the support email address.
        set_config('supportemail', 'email@example.com');

        // Remove any default blocked hosts and port restrictions, to avoid blocking tests (eg those using local files).
        set_config('curlsecurityblockedhosts', '');
        set_config('curlsecurityallowedport', '');

        // Execute all the adhoc tasks.
        while ($task = \core\task\manager::get_next_adhoc_task(time())) {
            $task->execute();
            \core\task\manager::adhoc_task_complete($task);
        }

        // Keeps the current version of database and dataroot.
        self::store_versions_hash();

        // Stores the database contents for fast reset.
        self::store_database_state();
    }

    /**
     * Build theme CSS.
     */
    public static function build_themes($mtraceprogress = false) {
        global $CFG;
        require_once("{$CFG->libdir}/outputlib.php");

        $themenames = array_keys(\core_component::get_plugin_list('theme'));

        // Load the theme configs.
        $themeconfigs = array_map(function($themename) {
            return \theme_config::load($themename);
        }, $themenames);

        // Build the list of themes and cache them in local cache.
        $themes = theme_build_css_for_themes($themeconfigs, ['ltr'], true, $mtraceprogress);

        $framework = self::get_framework();
        $storageroot = self::get_dataroot() . "/{$framework}/themedata";

        foreach ($themes as $themename => $themedata) {
            $dirname = "{$storageroot}/{$themename}";
            check_dir_exists($dirname);
            foreach ($themedata as $direction => $css) {
                file_put_contents("{$dirname}/{$direction}.css", $css);
            }
        }
    }

    /**
     * Drops dataroot and remove test database tables
     * @throws coding_exception
     * @return void
     */
    public static function drop_site() {

        if (!defined('BEHAT_UTIL')) {
            throw new coding_exception('This method can be only used by Behat CLI tool');
        }

        self::reset_dataroot();
        self::drop_database(true);
        self::drop_dataroot();
    }

    /**
     * Delete files and directories under dataroot.
     */
    public static function drop_dataroot() {
        global $CFG;

        // As behat directory is now created under default $CFG->behat_dataroot_parent, so remove the whole dir.
        if ($CFG->behat_dataroot !== $CFG->behat_dataroot_parent) {
            remove_dir($CFG->behat_dataroot, false);
        } else {
            // It should never come here.
            throw new moodle_exception("Behat dataroot should not be same as parent behat data root.");
        }
    }

    /**
     * Checks if $CFG->behat_wwwroot is available and using same versions for cli and web.
     *
     * @return void
     */
    public static function check_server_status() {
        global $CFG;

        $url = $CFG->behat_wwwroot . '/admin/tool/behat/tests/behat/fixtures/environment.php';

        // Get web versions used by behat site.
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $statuscode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statuscode !== 200 || empty($result) || (!$result = json_decode($result, true))) {

            behat_error (BEHAT_EXITCODE_REQUIREMENT, $CFG->behat_wwwroot . ' is not available, ensure you specified ' .
                'correct url and that the server is set up and started.' . PHP_EOL . ' More info in ' .
                behat_command::DOCS_URL . PHP_EOL . parent::get_site_info());
        }

        // Check if cli version is same as web version.
        $clienv = self::get_environment();
        if ($result != $clienv) {
            $output = 'Differences detected between cli and webserver...'.PHP_EOL;
            foreach ($result as $key => $version) {
                if ($clienv[$key] != $version) {
                    $output .= ' ' . $key . ': ' . PHP_EOL;
                    $output .= ' - web server: ' . $version . PHP_EOL;
                    $output .= ' - cli: ' . $clienv[$key] . PHP_EOL;
                }
            }
            echo $output;
            ob_flush();
        }
    }

    /**
     * Checks whether the test database and dataroot is ready
     * Stops execution if something went wrong
     * @throws coding_exception
     * @return void
     */
    protected static function test_environment_problem() {
        global $CFG, $DB;

        if (!defined('BEHAT_UTIL')) {
            throw new coding_exception('This method can be only used by Behat CLI tool');
        }

        if (!self::is_test_site()) {
            behat_error(1, 'This is not a behat test site!');
        }

        $tables = $DB->get_tables(false);
        if (empty($tables)) {
            behat_error(BEHAT_EXITCODE_INSTALL, '');
        }

        if (!self::is_test_data_updated()) {
            behat_error(BEHAT_EXITCODE_REINSTALL, 'The test environment was initialised for a different version');
        }
    }

    /**
     * Enables test mode
     *
     * It uses CFG->behat_dataroot
     *
     * Starts the test mode checking the composer installation and
     * the test environment and updating the available
     * features and steps definitions.
     *
     * Stores a file in dataroot/behat to allow Moodle to switch
     * to the test environment when using cli-server.
     * @param bool $themesuitewithallfeatures List themes to include core features.
     * @param string $tags comma separated tag, which will be given preference while distributing features in parallel run.
     * @param int $parallelruns number of parallel runs.
     * @param int $run current run.
     * @throws coding_exception
     * @return void
     */
    public static function start_test_mode($themesuitewithallfeatures = false, $tags = '', $parallelruns = 0, $run = 0) {

        if (!defined('BEHAT_UTIL')) {
            throw new coding_exception('This method can be only used by Behat CLI tool');
        }

        // Checks the behat set up and the PHP version.
        if ($errorcode = behat_command::behat_setup_problem()) {
            exit($errorcode);
        }

        // Check that test environment is correctly set up.
        self::test_environment_problem();

        // Updates all the Moodle features and steps definitions.
        behat_config_manager::update_config_file('', true, $tags, $themesuitewithallfeatures, $parallelruns, $run);

        if (self::is_test_mode_enabled()) {
            return;
        }

        $contents = '$CFG->behat_wwwroot, $CFG->behat_prefix and $CFG->behat_dataroot' .
            ' are currently used as $CFG->wwwroot, $CFG->prefix and $CFG->dataroot';
        $filepath = self::get_test_file_path();
        if (!file_put_contents($filepath, $contents)) {
            behat_error(BEHAT_EXITCODE_PERMISSIONS, 'File ' . $filepath . ' can not be created');
        }
    }

    /**
     * Returns the status of the behat test environment
     *
     * @return int Error code
     */
    public static function get_behat_status(): int {
        if (!defined('BEHAT_UTIL')) {
            throw new coding_exception('This method can be only used by Behat CLI tool');
        }

        // Checks the behat set up and the PHP version, returning an error code if something went wrong.
        $errorcode = behat_command::behat_setup_problem();

        if ($errorcode !== 0) {
            return $errorcode;
        }

        // Check that test environment is correctly set up, stops execution.
        self::test_environment_problem();

        return $errorcode;
    }

    /**
     * Disables test mode
     * @throws coding_exception
     * @return void
     */
    public static function stop_test_mode() {

        if (!defined('BEHAT_UTIL')) {
            throw new coding_exception('This method can be only used by Behat CLI tool');
        }

        $testenvfile = self::get_test_file_path();
        behat_config_manager::set_behat_run_config_value('behatsiteenabled', 0);

        if (!self::is_test_mode_enabled()) {
            echo "Test environment was already disabled\n";
        } else {
            if (!unlink($testenvfile)) {
                behat_error(BEHAT_EXITCODE_PERMISSIONS, 'Can not delete test environment file');
            }
        }
    }

    /**
     * Checks whether test environment is enabled or disabled
     *
     * To check is the current script is running in the test
     * environment
     *
     * @return bool
     */
    public static function is_test_mode_enabled() {

        $testenvfile = self::get_test_file_path();
        if (file_exists($testenvfile)) {
            return true;
        }

        return false;
    }

    /**
     * Returns the path to the file which specifies if test environment is enabled
     * @return string
     */
    final public static function get_test_file_path() {
        return behat_command::get_parent_behat_dir() . '/test_environment_enabled.txt';
    }

    /**
     * Removes config settings that were added to the main $CFG config within the Behat CLI
     * run.
     *
     * Database storage is already handled by reset_database and existing config values will
     * be reset automatically by initialise_cfg(), so we only need to remove added ones.
     */
    public static function remove_added_config() {
        global $CFG;
        if (!empty($CFG->behat_cli_added_config)) {
            foreach ($CFG->behat_cli_added_config as $key => $value) {
                unset($CFG->{$key});
            }
            unset($CFG->behat_cli_added_config);
        }
    }

    /**
     * Reset contents of all database tables to initial values, reset caches, etc.
     */
    public static function reset_all_data() {
        // Reset database.
        self::reset_database();

        // Purge dataroot directory.
        self::reset_dataroot();

        // Reset all static caches.
        accesslib_clear_all_caches(true);
        accesslib_reset_role_cache();
        // Reset the nasty strings list used during the last test.
        nasty_strings::reset_used_strings();

        filter_manager::reset_caches();

        \core_reportbuilder\manager::reset_caches();

        // Reset course and module caches.
        core_courseformat\base::reset_course_cache(0);
        get_fast_modinfo(0, 0, true);

        // Reset the DI container.
        \core\di::reset_container();

        // Inform data generator.
        self::get_data_generator()->reset();

        // Reset the task manager.
        \core\task\manager::reset_state();

        // Initialise $CFG with default values. This is needed for behat cli process, so we don't have modified
        // $CFG values from the old run. @see set_config.
        self::remove_added_config();
        initialise_cfg();
    }

    /**
     * Restore theme CSS stored during behat setup.
     */
    public static function restore_saved_themes(): void {
        global $CFG;

        $themerev = theme_get_revision();

        $framework = self::get_framework();
        $storageroot = self::get_dataroot() . "/{$framework}/themedata";
        $themenames = array_keys(\core_component::get_plugin_list('theme'));
        $directions = ['ltr', 'rtl'];

        $themeconfigs = array_map(function($themename) {
            return \theme_config::load($themename);
        }, $themenames);

        foreach ($themeconfigs as $themeconfig) {
            $themename = $themeconfig->name;
            $themesubrev = theme_get_sub_revision_for_theme($themename);

            $dirname = "{$storageroot}/{$themename}";
            foreach ($directions as $direction) {
                $cssfile = "{$dirname}/{$direction}.css";
                if (file_exists($cssfile)) {
                    $themeconfig->set_css_content_cache(file_get_contents($cssfile));
                }
            }
        }
    }

    /**
     * Pause execution immediately.
     *
     * @param Session $session
     * @param string $message The message to show when pausing.
     * This will be passed through cli_ansi_format so appropriate ANSI formatting and features are available.
     */
    public static function pause(Session $session, string $message): void {
        $posixexists = function_exists('posix_isatty');

        // Make sure this step is only used with interactive terminal (if detected).
        if ($posixexists && !@posix_isatty(STDOUT)) {
            throw new ExpectationException('Break point should only be used with interactive terminal.', $session);
        }

        // Save the cursor position, ring the bell, and add a new line.
        fwrite(STDOUT, cli_ansi_format("<cursor:save><bell><newline>"));

        // Output the formatted message and reset colour back to normal.
        $formattedmessage = cli_ansi_format("{$message}<colour:normal>");
        fwrite(STDOUT, $formattedmessage);

        // Wait for input.
        fread(STDIN, 1024);

        // Move the cursor back up to the previous position, then restore the original position stored earlier, and move
        // it back down again.
        fwrite(STDOUT, cli_ansi_format("<cursor:up><cursor:up><cursor:restore><cursor:down><cursor:down>"));

        // Add any extra lines back if the provided message was spread over multiple lines.
        $linecount = count(explode("\n", $formattedmessage));
        fwrite(STDOUT, str_repeat(cli_ansi_format("<cursor:down>"), $linecount - 1));
    }

    /**
     * Gets a text-based site version description.
     *
     * @return string The site info
     */
    public static function get_site_info() {
        $siteinfo = parent::get_site_info();

        $accessibility = empty(behat_config_manager::get_behat_run_config_value('axe')) ? 'No' : 'Yes';
        $scssdeprecations = empty(behat_config_manager::get_behat_run_config_value('scss-deprecations')) ? 'No' : 'Yes';
        $icondeprecations = empty(behat_config_manager::get_behat_run_config_value('no-icon-deprecations')) ? 'Yes' : 'No';

        $siteinfo .= <<<EOF
Run optional tests:
- Accessibility: {$accessibility}
- SCSS deprecations: {$scssdeprecations}
- Icon deprecations: {$icondeprecations}

EOF;

        return $siteinfo;
    }
}
