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
 * Audit Q8 and S12: no deprecated core APIs.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace MoodleAudit\Sniffs\Quality;

use MoodleAudit\Util\Audit;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Audit Q8 and S12: no deprecated core APIs.
 *
 * Reports calls to deprecated or removed core functions (add_to_log() as S12, the
 * others as Q8) and lib.php output callbacks that have been replaced by hooks.
 * print_error() is already reported by the Moodle standard. The messages name the
 * replacement only; check the deprecation and removal versions against the Moodle
 * upgrade notes for the plugin's supported versions.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class DeprecatedApiSniff implements Sniff {
    /** @var string[] Deprecated functions and their replacements. */
    const FUNCTIONS = [
        'add_to_log' => '\core\event\... events',
        'cron_setup_user' => '\core\cron::setup_user()',
        'events_trigger' => '\core\event\... events',
        'get_context_instance' => 'context_system::instance(), context_course::instance() and the other context_*::instance()',
        'get_referer' => 'get_local_referer()',
    ];

    /** @var string[] lib.php callback suffixes replaced by hooks, with the hook to use. */
    const CALLBACKS = [
        '_add_htmlattributes' => '\core\hook\output\before_html_attributes',
        '_before_footer' => '\core\hook\output\before_footer_html_generation',
        '_before_http_headers' => '\core\hook\output\before_http_headers',
        '_before_standard_html_head' => '\core\hook\output\before_standard_head_html_generation',
        '_before_standard_top_of_body_html' => '\core\hook\output\before_standard_top_of_body_html_generation',
    ];

    /**
     * Register the tokens this sniff listens for.
     *
     * @return int[] token codes.
     */
    public function register() {
        return [T_STRING, T_FUNCTION];
    }

    /**
     * Report deprecated calls and callbacks.
     *
     * @param File $file the file being checked.
     * @param int $pointer position of the token.
     */
    public function process(File $file, $pointer) {
        $tokens = $file->getTokens();
        if ($tokens[$pointer]['code'] === T_FUNCTION) {
            $this->process_callback($file, $pointer);
            return;
        }
        $function = strtolower($tokens[$pointer]['content']);
        if (!isset(self::FUNCTIONS[$function]) || !Audit::is_function_call($file, $pointer)) {
            return;
        }
        $id = $function === 'add_to_log' ? 'S12' : 'Q8';
        $a = ['id' => $id, 'function' => $function, 'replacement' => self::FUNCTIONS[$function]];
        $file->addWarning(Audit::message('deprecated', $a), $pointer, $id);
    }

    /**
     * Report a lib.php callback that has been replaced by a hook.
     *
     * @param File $file the file being checked.
     * @param int $pointer position of the function keyword.
     */
    protected function process_callback(File $file, int $pointer): void {
        if (basename(Audit::path($file)) !== 'lib.php' || !empty($file->getTokens()[$pointer]['conditions'])) {
            return; // Only top-level functions in lib.php are callbacks.
        }
        $name = strtolower((string) $file->getDeclarationName($pointer));
        foreach (self::CALLBACKS as $suffix => $hook) {
            if (substr($name, -strlen($suffix)) === $suffix) {
                $file->addWarning(Audit::message('callbackhook', ['function' => $name, 'hook' => $hook]), $pointer, 'Q8');
                return;
            }
        }
    }
}
