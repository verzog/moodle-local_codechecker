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
 * Audit S13 and S14: capability risk bitmasks in db/access.php.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace MoodleAudit\Sniffs\Security;

use MoodleAudit\Util\Audit;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHPCSUtils\Utils\Arrays;
use PHPCSUtils\Utils\PassedParameters;

/**
 * Audit S13 and S14: capabilities in db/access.php carry the right risk bitmasks.
 *
 * Reported, per capability:
 * - S14: a 'write' capability with no riskbitmask.
 * - S14: a RISK_CONFIG, RISK_XSS or RISK_DATALOSS capability given to archetypes by
 *   default without a comment in its definition justifying it.
 * - S13: a capability whose name suggests destructive or bulk actions (delete, purge,
 *   remove, reset, bulk) without RISK_DATALOSS.
 *
 * The file is read as tokens and never executed.
 *
 * @package    local_codechecker
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class CapabilityRiskSniff implements Sniff {
    /** @var string[] Risks that should not be granted to archetypes without a justification. */
    const HIGH_RISKS = ['RISK_CONFIG', 'RISK_DATALOSS', 'RISK_XSS'];

    /** @var string Pattern for capability names that suggest destructive or bulk actions. */
    const DESTRUCTIVE_PATTERN = '~(delete|purge|remove|reset|bulk)~i';

    /**
     * Register the tokens this sniff listens for.
     *
     * @return int[] token codes.
     */
    public function register() {
        return [T_VARIABLE];
    }

    /**
     * Check the $capabilities array.
     *
     * @param File $file the file being checked.
     * @param int $pointer position of the token.
     */
    public function process(File $file, $pointer) {
        $tokens = $file->getTokens();
        if ($tokens[$pointer]['content'] !== '$capabilities' || substr(Audit::path($file), -14) !== '/db/access.php') {
            return;
        }
        $equal = $file->findNext(Audit::empty_tokens(), $pointer + 1, null, true);
        if ($equal === false || $tokens[$equal]['code'] !== T_EQUAL) {
            return;
        }
        $array = $file->findNext(Audit::empty_tokens(), $equal + 1, null, true);
        if ($array === false || !in_array($tokens[$array]['code'], [T_OPEN_SHORT_ARRAY, T_ARRAY], true)) {
            return;
        }
        foreach (PassedParameters::getParameters($file, $array) as $item) {
            $arrow = Arrays::getDoubleArrowPtr($file, $item['start'], $item['end']);
            if ($arrow === false) {
                continue;
            }
            $name = $this->key($file, $item['start'], $arrow);
            $value = $file->findNext(Audit::empty_tokens(), $arrow + 1, $item['end'] + 1, true);
            if ($value !== false && in_array($tokens[$value]['code'], [T_OPEN_SHORT_ARRAY, T_ARRAY], true)) {
                $this->check_capability($file, $name, $value, $item['end']);
            }
        }
    }

    /**
     * Check one capability definition.
     *
     * @param File $file the file being checked.
     * @param string $name the capability name.
     * @param int $array position of the definition's array opener.
     * @param int $end last token of the definition.
     */
    protected function check_capability(File $file, string $name, int $array, int $end): void {
        $tokens = $file->getTokens();
        $fields = [];
        foreach (PassedParameters::getParameters($file, $array) as $item) {
            $arrow = Arrays::getDoubleArrowPtr($file, $item['start'], $item['end']);
            if ($arrow !== false) {
                $fields[$this->key($file, $item['start'], $arrow)] = [$arrow + 1, $item['end']];
            }
        }
        $risks = [];
        if (isset($fields['riskbitmask'])) {
            for ($i = $fields['riskbitmask'][0]; $i <= $fields['riskbitmask'][1]; $i++) {
                if ($tokens[$i]['code'] === T_STRING && strpos($tokens[$i]['content'], 'RISK_') === 0) {
                    $risks[] = $tokens[$i]['content'];
                }
            }
        }
        $captype = isset($fields['captype']) ? $this->key($file, $fields['captype'][0], $fields['captype'][1] + 1) : '';

        if ($captype === 'write' && !isset($fields['riskbitmask'])) {
            $file->addWarning(Audit::message('capnorisk', $name), $array, 'S14');
        }
        $highrisks = array_intersect($risks, self::HIGH_RISKS);
        $justified = $file->findNext([T_COMMENT, T_DOC_COMMENT_OPEN_TAG], $array, $end + 1) !== false;
        $granted = isset($fields['archetypes']) && $this->grants_archetypes($file, $fields['archetypes']);
        if ($highrisks && $granted && !$justified) {
            $a = ['cap' => $name, 'risks' => implode(', ', $highrisks)];
            $file->addWarning(Audit::message('capdefaultgrant', $a), $array, 'S14');
        }
        if (preg_match(self::DESTRUCTIVE_PATTERN, $name) && !in_array('RISK_DATALOSS', $risks, true)) {
            $file->addWarning(Audit::message('capdataloss', $name), $array, 'S13');
        }
    }

    /**
     * Get the unquoted text of the first code token in a range (an array key or a simple value).
     *
     * @param File $file the file being checked.
     * @param int $start first token of the range.
     * @param int $end token after the range.
     * @return string the text, without quotes.
     */
    protected function key(File $file, int $start, int $end): string {
        $first = $file->findNext(Audit::empty_tokens(), $start, $end, true);
        return $first === false ? '' : trim($file->getTokens()[$first]['content'], '\'"');
    }

    /**
     * Whether an archetypes value allows the capability for at least one archetype.
     *
     * @param File $file the file being checked.
     * @param int[] $range first and last token of the archetypes value.
     * @return bool true if CAP_ALLOW is found.
     */
    protected function grants_archetypes(File $file, array $range): bool {
        $tokens = $file->getTokens();
        for ($i = $range[0]; $i <= $range[1]; $i++) {
            if ($tokens[$i]['code'] === T_STRING && $tokens[$i]['content'] === 'CAP_ALLOW') {
                return true;
            }
        }
        return false;
    }
}
