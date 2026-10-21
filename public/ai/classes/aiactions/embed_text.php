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

namespace core_ai\aiactions;

use core_ai\aiactions\responses\response_base;

/**
 * Embed text action.
 *
 * Unlike the other core actions this one is not user facing and has no placement. It exists for
 * other components (for example a semantic search engine) to turn text into an embedding vector.
 *
 * Embeddings are only comparable when produced by the same provider instance and model, so a
 * consumer that stores vectors must record the provider instance id, model and vector dimensions
 * it indexed with and always process this action through that provider instance, using
 * {@see \core_ai\manager::process_action()} with the $providerid argument. Changing either means
 * re-embedding everything previously stored.
 *
 * The vector itself is returned to the caller and belongs to the consumer. The action table only
 * keeps a hash and length of the input plus the vector dimensions for auditing and usage reporting.
 *
 * @package    core_ai
 * @copyright  2026 Asaf Ohayon <asaf@sysbind.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class embed_text extends base {
    /**
     * Create a new instance of the embed_text action.
     *
     * @param int $contextid The context id the action was created in.
     * @param int $userid The user id requesting the action.
     * @param string $text The text to embed.
     */
    public function __construct(
        int $contextid,
        /** @var int The user id requesting the action. */
        protected int $userid,
        /** @var string The text to embed. */
        protected string $text,
    ) {
        parent::__construct($contextid);
    }

    #[\Override]
    public function store(response_base $response): int {
        global $DB;

        $responsearr = $response->get_response_data();

        // The vector belongs to the consumer and is not stored here. The input text is not stored
        // either: embedding is a bulk, system driven operation and the text already lives in the
        // component that asked for it. Keep only what is needed for auditing and usage reporting.
        $record = new \stdClass();
        $record->inputhash = hash('sha256', $this->text);
        $record->inputlength = \core_text::strlen($this->text);
        $record->embeddingdims = count($responsearr['embedding'] ?? []);

        return $DB->insert_record($this->get_tablename(), $record);
    }
}
