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
 * Audit S4: moodleform fields have a setType().
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace MoodleAudit\Sniffs\Security;

use MoodleAudit\Util\Audit;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHPCSUtils\Utils\PassedParameters;

/**
 * Audit S4: forms use moodleform with setType() for every field.
 *
 * In a class extending moodleform (or \core_form\dynamic_form), every text, textarea,
 * hidden, password or passwordunmask element added with a literal name needs a
 * setType() for that name. When any setType() name is not a literal (for example a
 * loop) the class is skipped, to avoid false reports.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class FormSetTypeSniff implements Sniff {
    /** @var string[] Element types that return user text and therefore need a PARAM_* type. */
    const TYPED_ELEMENTS = ['hidden', 'password', 'passwordunmask', 'text', 'textarea'];

    /** @var string[] Parent classes that make a class a Moodle form. */
    const FORM_CLASSES = ['dynamic_form', 'moodleform'];

    /**
     * Register the tokens this sniff listens for.
     *
     * @return int[] token codes.
     */
    public function register() {
        return [T_CLASS];
    }

    /**
     * Check one form class.
     *
     * @param File $file the file being checked.
     * @param int $pointer position of the class keyword.
     */
    public function process(File $file, $pointer) {
        $tokens = $file->getTokens();
        $parent = $file->findExtendedClassName($pointer);
        if ($parent === false || !isset($tokens[$pointer]['scope_closer'])) {
            return;
        }
        $parts = explode('\\', $parent);
        if (!in_array(strtolower(end($parts)), self::FORM_CLASSES, true)) {
            return;
        }
        $elements = [];
        $typed = [];
        for ($i = $tokens[$pointer]['scope_opener']; $i < $tokens[$pointer]['scope_closer']; $i++) {
            if ($tokens[$i]['code'] !== T_STRING || $tokens[$i - 1]['code'] !== T_OBJECT_OPERATOR) {
                continue;
            }
            $method = strtolower($tokens[$i]['content']);
            if ($method === 'addelement') {
                $type = PassedParameters::getParameter($file, $i, 1, 'elementname');
                $name = PassedParameters::getParameter($file, $i, 2, 'name');
                if ($type !== false && $name !== false && $this->is_literal($file, $name) && $this->is_literal($file, $type)) {
                    $elementtype = strtolower(trim($type['clean'], '\'"'));
                    $elementname = trim($name['clean'], '\'"');
                    if (in_array($elementtype, self::TYPED_ELEMENTS, true) && !isset($elements[$elementname])) {
                        $elements[$elementname] = $i;
                    }
                }
            } else if ($method === 'settype') {
                $name = PassedParameters::getParameter($file, $i, 1, 'elementname');
                if ($name === false || !$this->is_literal($file, $name)) {
                    return; // Names built at run time; this check cannot tell which fields are typed.
                }
                $typed[trim($name['clean'], '\'"')] = true;
            } else if ($method === 'settypes') {
                return; // Types set in bulk from an array; left to manual review.
            }
        }
        foreach ($elements as $elementname => $position) {
            if (!isset($typed[$elementname])) {
                $file->addWarning(Audit::message('formsettype', $elementname), $position, 'S4');
            }
        }
    }

    /**
     * Whether a call argument is a single string literal.
     *
     * @param File $file the file being checked.
     * @param array $parameter parameter details from PassedParameters.
     * @return bool true for a plain quoted string.
     */
    protected function is_literal(File $file, array $parameter): bool {
        $tokens = $file->getTokens();
        $found = 0;
        for ($i = $parameter['start']; $i <= $parameter['end']; $i++) {
            if (in_array($tokens[$i]['code'], [T_WHITESPACE, T_COMMENT], true)) {
                continue;
            }
            if ($tokens[$i]['code'] !== T_CONSTANT_ENCAPSED_STRING) {
                return false;
            }
            $found++;
        }
        return $found === 1;
    }
}
