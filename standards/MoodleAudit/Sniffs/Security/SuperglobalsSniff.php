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
 * Audit S3: no direct access to request superglobals.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace MoodleAudit\Sniffs\Security;

use MoodleAudit\Util\Audit;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Audit S3: $_GET, $_POST, $_REQUEST, $_COOKIE and $_SESSION are never accessed directly.
 *
 * Use optional_param() / required_param(), $SESSION and the Moodle cookie APIs instead.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class SuperglobalsSniff implements Sniff {
    /** @var string[] Superglobals that must not be read or written directly, with the replacement. */
    const SUPERGLOBALS = [
        '$_COOKIE' => 'cookie',
        '$_GET' => 'param',
        '$_POST' => 'param',
        '$_REQUEST' => 'param',
        '$_SESSION' => 'session',
    ];

    /**
     * Register the tokens this sniff listens for.
     *
     * @return int[] token codes.
     */
    public function register() {
        return [T_VARIABLE];
    }

    /**
     * Report direct superglobal access.
     *
     * @param File $file the file being checked.
     * @param int $pointer position of the token.
     */
    public function process(File $file, $pointer) {
        $name = $file->getTokens()[$pointer]['content'];
        if (isset(self::SUPERGLOBALS[$name])) {
            $file->addError(Audit::message('superglobal_' . self::SUPERGLOBALS[$name], $name), $pointer, 'S3');
        }
    }
}
