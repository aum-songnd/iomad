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

namespace thquizaccess_seb;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/test_helper_trait.php');

/**
 * PHPUnit tests for thquiz_settings class.
 *
 * @package   thquizaccess_seb
 * @author    Andrew Madden <andrewmadden@catalyst-au.net>
 * @copyright 2020 Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class thquiz_settings_test extends \advanced_testcase {
    use \thquizaccess_seb_test_helper_trait;

    /** @var context_module $context Test context. */
    protected $context;

    /** @var moodle_url $url Test thquiz URL. */
    protected $url;

    /**
     * Called before every test.
     */
    public function setUp(): void {
        parent::setUp();

        $this->resetAfterTest();

        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
        $this->thquiz = $this->getDataGenerator()->create_module('thquiz', [
            'course' => $this->course->id,
            'seb_requiresafeexambrowser' => settings_provider::USE_SEB_CONFIG_MANUALLY,
        ]);
        $this->context = \context_module::instance($this->thquiz->cmid);
        $this->url = new \moodle_url("/mod/thquiz/view.php", ['id' => $this->thquiz->cmid]);
    }

    /**
     * Test that config is generated immediately prior to saving thquiz settings.
     */
    public function test_config_is_created_from_thquiz_settings() {
        // Test settings to populate the in the object.
        $settings = $this->get_test_settings([
            'thquizid' => $this->thquiz->id,
            'cmid' => $this->thquiz->cmid,
        ]);

        // Obtain the existing record that is created when using a generator.
        $thquizsettings = thquiz_settings::get_record(['thquizid' => $this->thquiz->id]);

        // Update the settings with values from the test function.
        $thquizsettings->from_record($settings);
        $thquizsettings->save();

        $config = $thquizsettings->get_config();
        $this->assertEquals(
            "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<!DOCTYPE plist PUBLIC \"-//Apple//DTD PLIST 1.0//EN\" \"http://www.apple.com/DTDs/PropertyList-1.0.dtd\">
<plist version=\"1.0\"><dict><key>showTaskBar</key><true/><key>allowWlan</key><false/><key>showReloadButton</key><true/>"
                . "<key>showTime</key><false/><key>showInputLanguage</key><true/><key>allowQuit</key><true/>"
                . "<key>quitURLConfirm</key><true/><key>audioControlEnabled</key><true/><key>audioMute</key><false/>"
                . "<key>allowSpellCheck</key><false/><key>browserWindowAllowReload</key><true/><key>URLFilterEnable</key><true/>"
                . "<key>URLFilterEnableContentFilter</key><false/><key>hashedQuitPassword</key>"
                . "<string>9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08</string><key>URLFilterRules</key>"
                . "<array><dict><key>action</key><integer>1</integer><key>active</key><true/><key>expression</key>"
                . "<string>test.com</string><key>regex</key><false/></dict></array><key>startURL</key><string>$this->url</string>"
                . "<key>sendBrowserExamKey</key><true/><key>browserWindowWebView</key><integer>3</integer>"
                . "<key>examSessionClearCookiesOnStart</key><false/><key>allowPreferencesWindow</key><false/></dict></plist>\n",
            $config);
    }

    /**
     * Test that config string gets updated from thquiz settings.
     */
    public function test_config_is_updated_from_thquiz_settings() {
        // Test settings to populate the in the object.
        $settings = $this->get_test_settings([
            'thquizid' => $this->thquiz->id,
            'cmid' => $this->thquiz->cmid,
        ]);

        // Obtain the existing record that is created when using a generator.
        $thquizsettings = thquiz_settings::get_record(['thquizid' => $this->thquiz->id]);

        // Update the settings with values from the test function.
        $thquizsettings->from_record($settings);
        $thquizsettings->save();

        $config = $thquizsettings->get_config();
        $this->assertEquals("<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<!DOCTYPE plist PUBLIC \"-//Apple//DTD PLIST 1.0//EN\" \"http://www.apple.com/DTDs/PropertyList-1.0.dtd\">
<plist version=\"1.0\"><dict><key>showTaskBar</key><true/><key>allowWlan</key><false/><key>showReloadButton</key><true/>"
            . "<key>showTime</key><false/><key>showInputLanguage</key><true/><key>allowQuit</key><true/>"
            . "<key>quitURLConfirm</key><true/><key>audioControlEnabled</key><true/><key>audioMute</key><false/>"
            . "<key>allowSpellCheck</key><false/><key>browserWindowAllowReload</key><true/><key>URLFilterEnable</key><true/>"
            . "<key>URLFilterEnableContentFilter</key><false/><key>hashedQuitPassword</key>"
            . "<string>9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08</string><key>URLFilterRules</key>"
            . "<array><dict><key>action</key><integer>1</integer><key>active</key><true/><key>expression</key>"
            . "<string>test.com</string><key>regex</key><false/></dict></array><key>startURL</key><string>$this->url</string>"
            . "<key>sendBrowserExamKey</key><true/><key>browserWindowWebView</key><integer>3</integer>"
            . "<key>examSessionClearCookiesOnStart</key><false/>"
            . "<key>allowPreferencesWindow</key><false/></dict></plist>\n", $config);

        $thquizsettings->set('filterembeddedcontent', 1); // Alter the settings.
        $thquizsettings->save();
        $config = $thquizsettings->get_config();
        $this->assertEquals("<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<!DOCTYPE plist PUBLIC \"-//Apple//DTD PLIST 1.0//EN\" \"http://www.apple.com/DTDs/PropertyList-1.0.dtd\">
<plist version=\"1.0\"><dict><key>showTaskBar</key><true/><key>allowWlan</key><false/><key>showReloadButton</key><true/>"
            . "<key>showTime</key><false/><key>showInputLanguage</key><true/><key>allowQuit</key><true/>"
            . "<key>quitURLConfirm</key><true/><key>audioControlEnabled</key><true/><key>audioMute</key><false/>"
            . "<key>allowSpellCheck</key><false/><key>browserWindowAllowReload</key><true/><key>URLFilterEnable</key><true/>"
            . "<key>URLFilterEnableContentFilter</key><true/><key>hashedQuitPassword</key>"
            . "<string>9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08</string><key>URLFilterRules</key>"
            . "<array><dict><key>action</key><integer>1</integer><key>active</key><true/><key>expression</key>"
            . "<string>test.com</string><key>regex</key><false/></dict></array><key>startURL</key><string>$this->url</string>"
            . "<key>sendBrowserExamKey</key><true/><key>browserWindowWebView</key><integer>3</integer>"
            . "<key>examSessionClearCookiesOnStart</key><false/>"
            . "<key>allowPreferencesWindow</key><false/></dict></plist>\n", $config);
    }

    /**
     * Test that config key is generated immediately prior to saving thquiz settings.
     */
    public function test_config_key_is_created_from_thquiz_settings() {
        $settings = $this->get_test_settings();

        $thquizsettings = new thquiz_settings(0, $settings);
        $configkey = $thquizsettings->get_config_key();
        $this->assertEquals("65ff7a3b8aec80e58fbe2e7968826c33cbf0ac444a748055ebe665829cbf4201",
            $configkey
        );
    }

    /**
     * Test that config key is generated immediately prior to saving thquiz settings.
     */
    public function test_config_key_is_updated_from_thquiz_settings() {
        $settings = $this->get_test_settings();

        $thquizsettings = new thquiz_settings(0, $settings);
        $configkey = $thquizsettings->get_config_key();
        $this->assertEquals("65ff7a3b8aec80e58fbe2e7968826c33cbf0ac444a748055ebe665829cbf4201",
                $configkey);

        $thquizsettings->set('filterembeddedcontent', 1); // Alter the settings.
        $configkey = $thquizsettings->get_config_key();
        $this->assertEquals("d975b8a2ec4472495a8be7c64d7c8cc960dbb62472d5e88a8847ac0e5d77e533",
            $configkey);
    }

    /**
     * Test that different URL filter expressions are turned into config XML.
     *
     * @param \stdClass $settings Thquiz settings
     * @param string $expectedxml SEB Config XML.
     *
     * @dataProvider filter_rules_provider
     */
    public function test_filter_rules_added_to_config(\stdClass $settings, string $expectedxml) {
        $thquizsettings = new thquiz_settings(0, $settings);
        $config = $thquizsettings->get_config();
        $this->assertEquals($expectedxml, $config);
    }

    /**
     * Test that browser keys are validated and retrieved as an array instead of string.
     */
    public function test_browser_exam_keys_are_retrieved_as_array() {
        $thquizsettings = new thquiz_settings();
        $thquizsettings->set('allowedbrowserexamkeys', "one two,three\nfour");
        $retrievedkeys = $thquizsettings->get('allowedbrowserexamkeys');
        $this->assertEquals(['one', 'two', 'three', 'four'], $retrievedkeys);
    }

    /**
     * Test validation of Browser Exam Keys.
     *
     * @param string $bek Browser Exam Key.
     * @param string $expectederrorstring Expected error.
     *
     * @dataProvider bad_browser_exam_key_provider
     */
    public function test_browser_exam_keys_validation_errors($bek, $expectederrorstring) {
        $thquizsettings = new thquiz_settings();
        $thquizsettings->set('allowedbrowserexamkeys', $bek);
        $thquizsettings->validate();
        $errors = $thquizsettings->get_errors();
        $this->assertContainsEquals($expectederrorstring, $errors);
    }

    /**
     * Test that uploaded seb file gets converted to config string.
     */
    public function test_config_file_uploaded_converted_to_config() {
        $url = new \moodle_url("/mod/thquiz/view.php", ['id' => $this->thquiz->cmid]);
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
                . "<!DOCTYPE plist PUBLIC \"-//Apple//DTD PLIST 1.0//EN\" \"http://www.apple.com/DTDs/PropertyList-1.0.dtd\">\n"
                . "<plist version=\"1.0\"><dict><key>hashedQuitPassword</key><string>hashedpassword</string>"
                . "<key>allowWlan</key><false/><key>startURL</key><string>$url</string>"
                . "<key>sendBrowserExamKey</key><true/><key>browserWindowWebView</key><integer>3</integer></dict></plist>\n";
        $itemid = $this->create_module_test_file($xml, $this->thquiz->cmid);
        $thquizsettings = thquiz_settings::get_record(['thquizid' => $this->thquiz->id]);
        $thquizsettings->set('requiresafeexambrowser', settings_provider::USE_SEB_UPLOAD_CONFIG);
        $thquizsettings->save();
        $config = $thquizsettings->get_config();
        $this->assertEquals($xml, $config);
    }

    /**
     * Test test_no_config_file_uploaded
     */
    public function test_no_config_file_uploaded() {
        $thquizsettings = thquiz_settings::get_record(['thquizid' => $this->thquiz->id]);
        $thquizsettings->set('requiresafeexambrowser', settings_provider::USE_SEB_UPLOAD_CONFIG);
        $cmid = $thquizsettings->get('cmid');
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage("No uploaded SEB config file could be found for thquiz with cmid: {$cmid}");
        $thquizsettings->get_config();
    }

    /**
     * A helper function to build a config file.
     *
     * @param mixed $allowuserquitseb Required allowQuit setting.
     * @param mixed $quitpassword Required hashedQuitPassword setting.
     *
     * @return string
     */
    protected function get_config_xml($allowuserquitseb = null, $quitpassword = null) {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<!DOCTYPE plist PUBLIC \"-//Apple//DTD PLIST 1.0//EN\" \"http://www.apple.com/DTDs/PropertyList-1.0.dtd\">\n"
            . "<plist version=\"1.0\"><dict><key>allowWlan</key><false/><key>startURL</key>"
            . "<string>https://safeexambrowser.org/start</string>"
            . "<key>sendBrowserExamKey</key><true/>";

        if (!is_null($allowuserquitseb)) {
            $allowuserquitseb = empty($allowuserquitseb) ? 'false' : 'true';
            $xml .= "<key>allowQuit</key><{$allowuserquitseb}/>";
        }

        if (!is_null($quitpassword)) {
            $xml .= "<key>hashedQuitPassword</key><string>{$quitpassword}</string>";
        }

        $xml .= "</dict></plist>\n";

        return $xml;
    }

    /**
     * Test using USE_SEB_TEMPLATE and have it override settings from the template when they are set.
     */
    public function test_using_seb_template_override_settings_when_they_set_in_template() {
        $xml = $this->get_config_xml(true, 'password');
        $template = $this->create_template($xml);

        $this->assertStringContainsString("<key>startURL</key><string>https://safeexambrowser.org/start</string>", $template->get('content'));
        $this->assertStringContainsString("<key>allowQuit</key><true/>", $template->get('content'));
        $this->assertStringContainsString("<key>hashedQuitPassword</key><string>password</string>", $template->get('content'));

        $thquizsettings = thquiz_settings::get_record(['thquizid' => $this->thquiz->id]);
        $thquizsettings->set('requiresafeexambrowser', settings_provider::USE_SEB_TEMPLATE);
        $thquizsettings->set('templateid', $template->get('id'));
        $thquizsettings->set('allowuserquitseb', 1);
        $thquizsettings->save();

        $this->assertStringContainsString(
            "<key>startURL</key><string>https://www.example.com/moodle/mod/thquiz/view.php?id={$this->thquiz->cmid}</string>",
            $thquizsettings->get_config()
        );

        $this->assertStringContainsString("<key>allowQuit</key><true/>", $thquizsettings->get_config());
        $this->assertStringNotContainsString("hashedQuitPassword", $thquizsettings->get_config());

        $thquizsettings->set('quitpassword', 'new password');
        $thquizsettings->save();
        $hashedpassword = hash('SHA256', 'new password');
        $this->assertStringContainsString("<key>allowQuit</key><true/>", $thquizsettings->get_config());
        $this->assertStringNotContainsString("<key>hashedQuitPassword</key><string>password</string>", $thquizsettings->get_config());
        $this->assertStringContainsString("<key>hashedQuitPassword</key><string>{$hashedpassword}</string>", $thquizsettings->get_config());

        $thquizsettings->set('allowuserquitseb', 0);
        $thquizsettings->set('quitpassword', '');
        $thquizsettings->save();
        $this->assertStringContainsString("<key>allowQuit</key><false/>", $thquizsettings->get_config());
        $this->assertStringNotContainsString("hashedQuitPassword", $thquizsettings->get_config());
    }

    /**
     * Test using USE_SEB_TEMPLATE and have it override settings from the template when they are not set.
     */
    public function test_using_seb_template_override_settings_when_not_set_in_template() {
        $xml = $this->get_config_xml();
        $template = $this->create_template($xml);

        $this->assertStringContainsString("<key>startURL</key><string>https://safeexambrowser.org/start</string>", $template->get('content'));
        $this->assertStringNotContainsString("<key>allowQuit</key><true/>", $template->get('content'));
        $this->assertStringNotContainsString("<key>hashedQuitPassword</key><string>password</string>", $template->get('content'));

        $thquizsettings = thquiz_settings::get_record(['thquizid' => $this->thquiz->id]);
        $thquizsettings->set('requiresafeexambrowser', settings_provider::USE_SEB_TEMPLATE);
        $thquizsettings->set('templateid', $template->get('id'));
        $thquizsettings->set('allowuserquitseb', 1);
        $thquizsettings->save();

        $this->assertStringContainsString("<key>allowQuit</key><true/>", $thquizsettings->get_config());
        $this->assertStringNotContainsString("hashedQuitPassword", $thquizsettings->get_config());

        $thquizsettings->set('quitpassword', 'new password');
        $thquizsettings->save();
        $hashedpassword = hash('SHA256', 'new password');
        $this->assertStringContainsString("<key>allowQuit</key><true/>", $thquizsettings->get_config());
        $this->assertStringContainsString("<key>hashedQuitPassword</key><string>{$hashedpassword}</string>", $thquizsettings->get_config());

        $thquizsettings->set('allowuserquitseb', 0);
        $thquizsettings->set('quitpassword', '');
        $thquizsettings->save();
        $this->assertStringContainsString("<key>allowQuit</key><false/>", $thquizsettings->get_config());
        $this->assertStringNotContainsString("hashedQuitPassword", $thquizsettings->get_config());
    }

    /**
     * Test using USE_SEB_UPLOAD_CONFIG and use settings from the file if they are set.
     */
    public function test_using_own_config_settings_are_not_overridden_if_set() {
        $xml = $this->get_config_xml(true, 'password');
        $this->create_module_test_file($xml, $this->thquiz->cmid);

        $thquizsettings = thquiz_settings::get_record(['thquizid' => $this->thquiz->id]);
        $thquizsettings->set('requiresafeexambrowser', settings_provider::USE_SEB_UPLOAD_CONFIG);
        $thquizsettings->set('allowuserquitseb', 0);
        $thquizsettings->set('quitpassword', '');
        $thquizsettings->save();

        $this->assertStringContainsString(
            "<key>startURL</key><string>https://www.example.com/moodle/mod/thquiz/view.php?id={$this->thquiz->cmid}</string>",
            $thquizsettings->get_config()
        );

        $this->assertStringContainsString("<key>allowQuit</key><true/>", $thquizsettings->get_config());
        $this->assertStringContainsString("<key>hashedQuitPassword</key><string>password</string>", $thquizsettings->get_config());

        $thquizsettings->set('quitpassword', 'new password');
        $thquizsettings->save();
        $hashedpassword = hash('SHA256', 'new password');

        $this->assertStringNotContainsString("<key>hashedQuitPassword</key><string>{$hashedpassword}</string>", $thquizsettings->get_config());
        $this->assertStringContainsString("<key>allowQuit</key><true/>", $thquizsettings->get_config());
        $this->assertStringContainsString("<key>hashedQuitPassword</key><string>password</string>", $thquizsettings->get_config());

        $thquizsettings->set('allowuserquitseb', 0);
        $thquizsettings->set('quitpassword', '');
        $thquizsettings->save();

        $this->assertStringContainsString("<key>allowQuit</key><true/>", $thquizsettings->get_config());
        $this->assertStringContainsString("<key>hashedQuitPassword</key><string>password</string>", $thquizsettings->get_config());
    }

    /**
     * Test using USE_SEB_UPLOAD_CONFIG and use settings from the file if they are not set.
     */
    public function test_using_own_config_settings_are_not_overridden_if_not_set() {
        $xml = $this->get_config_xml();
        $this->create_module_test_file($xml, $this->thquiz->cmid);

        $thquizsettings = thquiz_settings::get_record(['thquizid' => $this->thquiz->id]);
        $thquizsettings->set('requiresafeexambrowser', settings_provider::USE_SEB_UPLOAD_CONFIG);
        $thquizsettings->set('allowuserquitseb', 1);
        $thquizsettings->set('quitpassword', '');
        $thquizsettings->save();

        $this->assertStringContainsString(
            "<key>startURL</key><string>https://www.example.com/moodle/mod/thquiz/view.php?id={$this->thquiz->cmid}</string>",
            $thquizsettings->get_config()
        );

        $this->assertStringNotContainsString("allowQuit", $thquizsettings->get_config());
        $this->assertStringNotContainsString("hashedQuitPassword", $thquizsettings->get_config());

        $thquizsettings->set('quitpassword', 'new password');
        $thquizsettings->save();

        $this->assertStringNotContainsString("allowQuit", $thquizsettings->get_config());
        $this->assertStringNotContainsString("hashedQuitPassword", $thquizsettings->get_config());

        $thquizsettings->set('allowuserquitseb', 0);
        $thquizsettings->set('quitpassword', '');
        $thquizsettings->save();

        $this->assertStringNotContainsString("allowQuit", $thquizsettings->get_config());
        $this->assertStringNotContainsString("hashedQuitPassword", $thquizsettings->get_config());
    }

    /**
     * Test using USE_SEB_TEMPLATE populates the linkquitseb setting if a quitURL is found.
     */
    public function test_template_has_quit_url_set() {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<!DOCTYPE plist PUBLIC \"-//Apple//DTD PLIST 1.0//EN\" \"http://www.apple.com/DTDs/PropertyList-1.0.dtd\">\n"
            . "<plist version=\"1.0\"><dict><key>hashedQuitPassword</key><string>hashedpassword</string>"
            . "<key>allowWlan</key><false/><key>quitURL</key><string>http://seb.quit.url</string>"
            . "<key>sendBrowserExamKey</key><true/></dict></plist>\n";

        $template = $this->create_template($xml);

        $thquizsettings = thquiz_settings::get_record(['thquizid' => $this->thquiz->id]);
        $thquizsettings->set('requiresafeexambrowser', settings_provider::USE_SEB_TEMPLATE);
        $thquizsettings->set('templateid', $template->get('id'));

        $this->assertEmpty($thquizsettings->get('linkquitseb'));
        $thquizsettings->save();

        $this->assertNotEmpty($thquizsettings->get('linkquitseb'));
        $this->assertEquals('http://seb.quit.url', $thquizsettings->get('linkquitseb'));
    }

    /**
     * Test using USE_SEB_UPLOAD_CONFIG populates the linkquitseb setting if a quitURL is found.
     */
    public function test_config_file_uploaded_has_quit_url_set() {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<!DOCTYPE plist PUBLIC \"-//Apple//DTD PLIST 1.0//EN\" \"http://www.apple.com/DTDs/PropertyList-1.0.dtd\">\n"
            . "<plist version=\"1.0\"><dict><key>hashedQuitPassword</key><string>hashedpassword</string>"
            . "<key>allowWlan</key><false/><key>quitURL</key><string>http://seb.quit.url</string>"
            . "<key>sendBrowserExamKey</key><true/></dict></plist>\n";

        $itemid = $this->create_module_test_file($xml, $this->thquiz->cmid);
        $thquizsettings = thquiz_settings::get_record(['thquizid' => $this->thquiz->id]);
        $thquizsettings->set('requiresafeexambrowser', settings_provider::USE_SEB_UPLOAD_CONFIG);

        $this->assertEmpty($thquizsettings->get('linkquitseb'));
        $thquizsettings->save();

        $this->assertNotEmpty($thquizsettings->get('linkquitseb'));
        $this->assertEquals('http://seb.quit.url', $thquizsettings->get('linkquitseb'));
    }

    /**
     * Test template id set correctly.
     */
    public function test_templateid_set_correctly_when_save_settings() {
        $thquizsettings = thquiz_settings::get_record(['thquizid' => $this->thquiz->id]);
        $this->assertEquals(0, $thquizsettings->get('templateid'));

        $template = $this->create_template();
        $templateid = $template->get('id');

        // Initially set to USE_SEB_TEMPLATE with a template id.
        $this->save_settings_with_optional_template($thquizsettings, settings_provider::USE_SEB_TEMPLATE, $templateid);
        $thquizsettings = thquiz_settings::get_record(['thquizid' => $this->thquiz->id]);
        $this->assertEquals($templateid, $thquizsettings->get('templateid'));

        // Case for USE_SEB_NO, ensure template id reverts to 0.
        $this->save_settings_with_optional_template($thquizsettings, settings_provider::USE_SEB_NO);
        $thquizsettings = thquiz_settings::get_record(['thquizid' => $this->thquiz->id]);
        $this->assertEquals(0, $thquizsettings->get('templateid'));

        // Reverting back to USE_SEB_TEMPLATE.
        $this->save_settings_with_optional_template($thquizsettings, settings_provider::USE_SEB_TEMPLATE, $templateid);

        // Case for USE_SEB_CONFIG_MANUALLY, ensure template id reverts to 0.
        $this->save_settings_with_optional_template($thquizsettings, settings_provider::USE_SEB_CONFIG_MANUALLY);
        $thquizsettings = thquiz_settings::get_record(['thquizid' => $this->thquiz->id]);
        $this->assertEquals(0, $thquizsettings->get('templateid'));

        // Reverting back to USE_SEB_TEMPLATE.
        $this->save_settings_with_optional_template($thquizsettings, settings_provider::USE_SEB_TEMPLATE, $templateid);

        // Case for USE_SEB_CLIENT_CONFIG, ensure template id reverts to 0.
        $this->save_settings_with_optional_template($thquizsettings, settings_provider::USE_SEB_CLIENT_CONFIG);
        $thquizsettings = thquiz_settings::get_record(['thquizid' => $this->thquiz->id]);
        $this->assertEquals(0, $thquizsettings->get('templateid'));

        // Reverting back to USE_SEB_TEMPLATE.
        $this->save_settings_with_optional_template($thquizsettings, settings_provider::USE_SEB_TEMPLATE, $templateid);

        // Case for USE_SEB_UPLOAD_CONFIG, ensure template id reverts to 0.
        $xml = file_get_contents(__DIR__ . '/fixtures/unencrypted.seb');
        $this->create_module_test_file($xml, $this->thquiz->cmid);
        $this->save_settings_with_optional_template($thquizsettings, settings_provider::USE_SEB_UPLOAD_CONFIG);
        $thquizsettings = thquiz_settings::get_record(['thquizid' => $this->thquiz->id]);
        $this->assertEquals(0, $thquizsettings->get('templateid'));

        // Case for USE_SEB_TEMPLATE, ensure template id is correct.
        $this->save_settings_with_optional_template($thquizsettings, settings_provider::USE_SEB_TEMPLATE, $templateid);
        $thquizsettings = thquiz_settings::get_record(['thquizid' => $this->thquiz->id]);
        $this->assertEquals($templateid, $thquizsettings->get('templateid'));
    }

    /**
     * Helper function in tests to set USE_SEB_TEMPLATE and a template id on the thquiz settings.
     *
     * @param thquiz_settings $thquizsettings Given thquiz settings instance.
     * @param int $savetype Type of SEB usage.
     * @param int $templateid Template ID.
     */
    public function save_settings_with_optional_template($thquizsettings, $savetype, $templateid = 0) {
        $thquizsettings->set('requiresafeexambrowser', $savetype);
        if (!empty($templateid)) {
            $thquizsettings->set('templateid', $templateid);
        }
        $thquizsettings->save();
    }

    /**
     * Bad browser exam key data provider.
     *
     * @return array
     */
    public function bad_browser_exam_key_provider() : array {
        return [
            'Short string' => ['fdsf434r',
                    'A key should be a 64-character hex string.'],
            'Non hex string' => ['aadf6799aadf6789aadf6789aadf6789aadf6789aadf6789aadf6789aadf678!',
                    'A key should be a 64-character hex string.'],
            'Non unique' => ["aadf6799aadf6789aadf6789aadf6789aadf6789aadf6789aadf6789aadf6789"
                    . "\naadf6799aadf6789aadf6789aadf6789aadf6789aadf6789aadf6789aadf6789", 'The keys must all be different.'],
        ];
    }

    /**
     * Provide settings for different filter rules.
     *
     * @return array Test data.
     */
    public function filter_rules_provider() : array {
        return [
            'enabled simple expessions' => [
                (object) [
                    'requiresafeexambrowser' => settings_provider::USE_SEB_CONFIG_MANUALLY,
                    'thquizid' => 1,
                    'cmid' => 1,
                    'expressionsallowed' => "test.com\r\nsecond.hello",
                    'regexallowed' => '',
                    'expressionsblocked' => '',
                    'regexblocked' => '',
                ],
                "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
                . "<!DOCTYPE plist PUBLIC \"-//Apple//DTD PLIST 1.0//EN\" \"http://www.apple.com/DTDs/PropertyList-1.0.dtd\">\n"
                . "<plist version=\"1.0\"><dict><key>showTaskBar</key><true/>"
                . "<key>allowWlan</key><false/><key>showReloadButton</key>"
                . "<true/><key>showTime</key><true/><key>showInputLanguage</key><true/><key>allowQuit</key><true/>"
                . "<key>quitURLConfirm</key><true/><key>audioControlEnabled</key><false/><key>audioMute</key><false/>"
                . "<key>allowSpellCheck</key><false/><key>browserWindowAllowReload</key><true/><key>URLFilterEnable</key><false/>"
                . "<key>URLFilterEnableContentFilter</key><false/><key>URLFilterRules</key><array>"
                . "<dict><key>action</key><integer>1</integer><key>active</key><true/>"
                . "<key>expression</key><string>test.com</string>"
                . "<key>regex</key><false/></dict><dict><key>action</key><integer>1</integer>"
                . "<key>active</key><true/><key>expression</key>"
                . "<string>second.hello</string><key>regex</key><false/></dict></array>"
                . "<key>startURL</key><string>https://www.example.com/moodle/mod/thquiz/view.php?id=1</string>"
                . "<key>sendBrowserExamKey</key><true/><key>browserWindowWebView</key><integer>3</integer>"
                . "<key>examSessionClearCookiesOnStart</key><false/>"
                . "<key>allowPreferencesWindow</key><false/></dict></plist>\n",
            ],
            'blocked simple expessions' => [
                (object) [
                    'requiresafeexambrowser' => settings_provider::USE_SEB_CONFIG_MANUALLY,
                    'thquizid' => 1,
                    'cmid' => 1,
                    'expressionsallowed' => '',
                    'regexallowed' => '',
                    'expressionsblocked' => "test.com\r\nsecond.hello",
                    'regexblocked' => '',
                ],
                "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
                . "<!DOCTYPE plist PUBLIC \"-//Apple//DTD PLIST 1.0//EN\" \"http://www.apple.com/DTDs/PropertyList-1.0.dtd\">\n"
                . "<plist version=\"1.0\"><dict><key>showTaskBar</key><true/>"
                . "<key>allowWlan</key><false/><key>showReloadButton</key>"
                . "<true/><key>showTime</key><true/><key>showInputLanguage</key><true/><key>allowQuit</key><true/>"
                . "<key>quitURLConfirm</key><true/><key>audioControlEnabled</key><false/><key>audioMute</key><false/>"
                . "<key>allowSpellCheck</key><false/><key>browserWindowAllowReload</key><true/><key>URLFilterEnable</key><false/>"
                . "<key>URLFilterEnableContentFilter</key><false/><key>URLFilterRules</key><array>"
                . "<dict><key>action</key><integer>0</integer><key>active</key><true/>"
                . "<key>expression</key><string>test.com</string>"
                . "<key>regex</key><false/></dict><dict><key>action</key><integer>0</integer>"
                . "<key>active</key><true/><key>expression</key>"
                . "<string>second.hello</string><key>regex</key><false/></dict></array>"
                . "<key>startURL</key><string>https://www.example.com/moodle/mod/thquiz/view.php?id=1</string>"
                . "<key>sendBrowserExamKey</key><true/><key>browserWindowWebView</key><integer>3</integer>"
                . "<key>examSessionClearCookiesOnStart</key><false/>"
                . "<key>allowPreferencesWindow</key><false/></dict></plist>\n",
            ],
            'enabled regex expessions' => [
                (object) [
                    'requiresafeexambrowser' => settings_provider::USE_SEB_CONFIG_MANUALLY,
                    'thquizid' => 1,
                    'cmid' => 1,
                    'expressionsallowed' => '',
                    'regexallowed' => "test.com\r\nsecond.hello",
                    'expressionsblocked' => '',
                    'regexblocked' => '',
                ],
                "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
                . "<!DOCTYPE plist PUBLIC \"-//Apple//DTD PLIST 1.0//EN\" \"http://www.apple.com/DTDs/PropertyList-1.0.dtd\">\n"
                . "<plist version=\"1.0\"><dict><key>showTaskBar</key><true/>"
                . "<key>allowWlan</key><false/><key>showReloadButton</key>"
                . "<true/><key>showTime</key><true/><key>showInputLanguage</key><true/><key>allowQuit</key><true/>"
                . "<key>quitURLConfirm</key><true/><key>audioControlEnabled</key><false/><key>audioMute</key><false/>"
                . "<key>allowSpellCheck</key><false/><key>browserWindowAllowReload</key><true/><key>URLFilterEnable</key><false/>"
                . "<key>URLFilterEnableContentFilter</key><false/><key>URLFilterRules</key><array>"
                . "<dict><key>action</key><integer>1</integer><key>active</key><true/>"
                . "<key>expression</key><string>test.com</string>"
                . "<key>regex</key><true/></dict><dict><key>action</key><integer>1</integer>"
                . "<key>active</key><true/><key>expression</key>"
                . "<string>second.hello</string><key>regex</key><true/></dict></array>"
                . "<key>startURL</key><string>https://www.example.com/moodle/mod/thquiz/view.php?id=1</string>"
                . "<key>sendBrowserExamKey</key><true/><key>browserWindowWebView</key><integer>3</integer>"
                . "<key>examSessionClearCookiesOnStart</key><false/>"
                . "<key>allowPreferencesWindow</key><false/></dict></plist>\n",
            ],
            'blocked regex expessions' => [
                (object) [
                    'requiresafeexambrowser' => settings_provider::USE_SEB_CONFIG_MANUALLY,
                    'thquizid' => 1,
                    'cmid' => 1,
                    'expressionsallowed' => '',
                    'regexallowed' => '',
                    'expressionsblocked' => '',
                    'regexblocked' => "test.com\r\nsecond.hello",
                ],
                "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
                . "<!DOCTYPE plist PUBLIC \"-//Apple//DTD PLIST 1.0//EN\" \"http://www.apple.com/DTDs/PropertyList-1.0.dtd\">\n"
                . "<plist version=\"1.0\"><dict><key>showTaskBar</key><true/>"
                . "<key>allowWlan</key><false/><key>showReloadButton</key>"
                . "<true/><key>showTime</key><true/><key>showInputLanguage</key><true/><key>allowQuit</key><true/>"
                . "<key>quitURLConfirm</key><true/><key>audioControlEnabled</key><false/><key>audioMute</key><false/>"
                . "<key>allowSpellCheck</key><false/><key>browserWindowAllowReload</key><true/><key>URLFilterEnable</key><false/>"
                . "<key>URLFilterEnableContentFilter</key><false/><key>URLFilterRules</key><array>"
                . "<dict><key>action</key><integer>0</integer><key>active</key><true/>"
                . "<key>expression</key><string>test.com</string>"
                . "<key>regex</key><true/></dict><dict><key>action</key><integer>0</integer>"
                . "<key>active</key><true/><key>expression</key>"
                . "<string>second.hello</string><key>regex</key><true/></dict></array>"
                . "<key>startURL</key><string>https://www.example.com/moodle/mod/thquiz/view.php?id=1</string>"
                . "<key>sendBrowserExamKey</key><true/><key>browserWindowWebView</key><integer>3</integer>"
                . "<key>examSessionClearCookiesOnStart</key><false/>"
                . "<key>allowPreferencesWindow</key><false/></dict></plist>\n",
            ],
            'multiple simple expessions' => [
                (object) [
                    'requiresafeexambrowser' => settings_provider::USE_SEB_CONFIG_MANUALLY,
                    'thquizid' => 1,
                    'cmid' => 1,
                    'expressionsallowed' => "*",
                    'regexallowed' => '',
                    'expressionsblocked' => '',
                    'regexblocked' => "test.com\r\nsecond.hello",
                ],
                "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
                . "<!DOCTYPE plist PUBLIC \"-//Apple//DTD PLIST 1.0//EN\" \"http://www.apple.com/DTDs/PropertyList-1.0.dtd\">\n"
                . "<plist version=\"1.0\"><dict><key>showTaskBar</key><true/>"
                . "<key>allowWlan</key><false/><key>showReloadButton</key>"
                . "<true/><key>showTime</key><true/><key>showInputLanguage</key><true/><key>allowQuit</key><true/>"
                . "<key>quitURLConfirm</key><true/><key>audioControlEnabled</key><false/><key>audioMute</key><false/>"
                . "<key>allowSpellCheck</key><false/><key>browserWindowAllowReload</key><true/><key>URLFilterEnable</key><false/>"
                . "<key>URLFilterEnableContentFilter</key><false/><key>URLFilterRules</key><array><dict><key>action</key>"
                . "<integer>1</integer><key>active</key><true/><key>expression</key><string>*</string>"
                . "<key>regex</key><false/></dict>"
                . "<dict><key>action</key><integer>0</integer><key>active</key><true/>"
                . "<key>expression</key><string>test.com</string>"
                . "<key>regex</key><true/></dict><dict><key>action</key><integer>0</integer>"
                . "<key>active</key><true/><key>expression</key>"
                . "<string>second.hello</string><key>regex</key><true/></dict></array>"
                . "<key>startURL</key><string>https://www.example.com/moodle/mod/thquiz/view.php?id=1</string>"
                . "<key>sendBrowserExamKey</key><true/><key>browserWindowWebView</key><integer>3</integer>"
                . "<key>examSessionClearCookiesOnStart</key><false/>"
                . "<key>allowPreferencesWindow</key><false/></dict></plist>\n",
            ],
        ];
    }

    /**
     * Test that config and config key are null when expected.
     */
    public function test_generates_config_values_as_null_when_expected() {
        $thquizsettings = thquiz_settings::get_record(['thquizid' => $this->thquiz->id]);
        $this->assertNotNull($thquizsettings->get_config());
        $this->assertNotNull($thquizsettings->get_config_key());

        $thquizsettings->set('requiresafeexambrowser', settings_provider::USE_SEB_NO);
        $thquizsettings->save();
        $thquizsettings = thquiz_settings::get_record(['thquizid' => $this->thquiz->id]);
        $this->assertNull($thquizsettings->get_config());
        $this->assertNull($thquizsettings->get_config());

        $thquizsettings->set('requiresafeexambrowser', settings_provider::USE_SEB_UPLOAD_CONFIG);
        $xml = file_get_contents(__DIR__ . '/fixtures/unencrypted.seb');
        $this->create_module_test_file($xml, $this->thquiz->cmid);
        $thquizsettings->save();
        $thquizsettings = thquiz_settings::get_record(['thquizid' => $this->thquiz->id]);
        $this->assertNotNull($thquizsettings->get_config());
        $this->assertNotNull($thquizsettings->get_config_key());

        $thquizsettings->set('requiresafeexambrowser', settings_provider::USE_SEB_CLIENT_CONFIG);
        $thquizsettings->save();
        $thquizsettings = thquiz_settings::get_record(['thquizid' => $this->thquiz->id]);
        $this->assertNull($thquizsettings->get_config());
        $this->assertNull($thquizsettings->get_config_key());

        $template = $this->create_template();
        $templateid = $template->get('id');
        $this->save_settings_with_optional_template($thquizsettings, settings_provider::USE_SEB_TEMPLATE, $templateid);
        $thquizsettings = thquiz_settings::get_record(['thquizid' => $this->thquiz->id]);
        $this->assertNotNull($thquizsettings->get_config());
        $this->assertNotNull($thquizsettings->get_config_key());
    }

    /**
     * Test that thquizsettings cache exists after creation.
     */
    public function test_thquizsettings_cache_exists_after_creation() {
        $expected = thquiz_settings::get_record(['thquizid' => $this->thquiz->id]);
        $this->assertEquals($expected->to_record(), \cache::make('thquizaccess_seb', 'thquizsettings')->get($this->thquiz->id));
    }

    /**
     * Test that thquizsettings cache gets deleted after deletion.
     */
    public function test_thquizsettings_cache_purged_after_deletion() {
        $this->assertNotEmpty(\cache::make('thquizaccess_seb', 'thquizsettings')->get($this->thquiz->id));

        $thquizsettings = thquiz_settings::get_record(['thquizid' => $this->thquiz->id]);
        $thquizsettings->delete();

        $this->assertFalse(\cache::make('thquizaccess_seb', 'thquizsettings')->get($this->thquiz->id));
    }

    /**
     * Test that we can get thquiz_settings by thquiz id.
     */
    public function test_get_thquiz_settings_by_thquiz_id() {
        $expected = thquiz_settings::get_record(['thquizid' => $this->thquiz->id]);

        $this->assertEquals($expected->to_record(), thquiz_settings::get_by_thquiz_id($this->thquiz->id)->to_record());

        // Check that data is getting from cache.
        $expected->set('showsebtaskbar', 0);
        $this->assertNotEquals($expected->to_record(), thquiz_settings::get_by_thquiz_id($this->thquiz->id)->to_record());

        // Now save and check that cached as been updated.
        $expected->save();
        $this->assertEquals($expected->to_record(), thquiz_settings::get_by_thquiz_id($this->thquiz->id)->to_record());

        // Returns false for non existing thquiz.
        $this->assertFalse(thquiz_settings::get_by_thquiz_id(7777777));
    }

    /**
     * Test that SEB config cache exists after creation of the thquiz.
     */
    public function test_config_cache_exists_after_creation() {
        $this->assertNotEmpty(\cache::make('thquizaccess_seb', 'config')->get($this->thquiz->id));
    }

    /**
     * Test that SEB config cache gets deleted after deletion.
     */
    public function test_config_cache_purged_after_deletion() {
        $this->assertNotEmpty(\cache::make('thquizaccess_seb', 'config')->get($this->thquiz->id));

        $thquizsettings = thquiz_settings::get_record(['thquizid' => $this->thquiz->id]);
        $thquizsettings->delete();

        $this->assertFalse(\cache::make('thquizaccess_seb', 'config')->get($this->thquiz->id));
    }

    /**
     * Test that we can get SEB config by thquiz id.
     */
    public function test_get_config_by_thquiz_id() {
        $thquizsettings = thquiz_settings::get_record(['thquizid' => $this->thquiz->id]);
        $expected = $thquizsettings->get_config();

        $this->assertEquals($expected, thquiz_settings::get_config_by_thquiz_id($this->thquiz->id));

        // Check that data is getting from cache.
        $thquizsettings->set('showsebtaskbar', 0);
        $this->assertNotEquals($thquizsettings->get_config(), thquiz_settings::get_config_by_thquiz_id($this->thquiz->id));

        // Now save and check that cached as been updated.
        $thquizsettings->save();
        $this->assertEquals($thquizsettings->get_config(), thquiz_settings::get_config_by_thquiz_id($this->thquiz->id));

        // Returns null for non existing thquiz.
        $this->assertNull(thquiz_settings::get_config_by_thquiz_id(7777777));
    }

    /**
     * Test that SEB config key cache exists after creation of the thquiz.
     */
    public function test_config_key_cache_exists_after_creation() {
        $this->assertNotEmpty(\cache::make('thquizaccess_seb', 'configkey')->get($this->thquiz->id));
    }

    /**
     * Test that SEB config key cache gets deleted after deletion.
     */
    public function test_config_key_cache_purged_after_deletion() {
        $this->assertNotEmpty(\cache::make('thquizaccess_seb', 'configkey')->get($this->thquiz->id));

        $thquizsettings = thquiz_settings::get_record(['thquizid' => $this->thquiz->id]);
        $thquizsettings->delete();

        $this->assertFalse(\cache::make('thquizaccess_seb', 'configkey')->get($this->thquiz->id));
    }

    /**
     * Test that we can get SEB config key by thquiz id.
     */
    public function test_get_config_key_by_thquiz_id() {
        $thquizsettings = thquiz_settings::get_record(['thquizid' => $this->thquiz->id]);
        $expected = $thquizsettings->get_config_key();

        $this->assertEquals($expected, thquiz_settings::get_config_key_by_thquiz_id($this->thquiz->id));

        // Check that data is getting from cache.
        $thquizsettings->set('showsebtaskbar', 0);
        $this->assertNotEquals($thquizsettings->get_config_key(), thquiz_settings::get_config_key_by_thquiz_id($this->thquiz->id));

        // Now save and check that cached as been updated.
        $thquizsettings->save();
        $this->assertEquals($thquizsettings->get_config_key(), thquiz_settings::get_config_key_by_thquiz_id($this->thquiz->id));

        // Returns null for non existing thquiz.
        $this->assertNull(thquiz_settings::get_config_key_by_thquiz_id(7777777));
    }

}
