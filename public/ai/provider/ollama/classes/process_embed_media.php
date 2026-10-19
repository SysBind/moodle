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

namespace aiprovider_ollama;

/**
 * Ollama media embeddings are not enabled in this implementation.
 *
 * @package    aiprovider_ollama
 * @copyright  2026 Asaf Ohayon <asaf@sysbind.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_embed_media extends \core_ai\process_base {
    #[\Override]
    protected function query_ai_api(): array {
        return [
            'success' => false,
            'errorcode' => 400,
            'error' => get_string('error:400', 'core_ai'),
            'errormessage' => 'Ollama media embeddings are not enabled for this provider action yet.',
        ];
    }
}
