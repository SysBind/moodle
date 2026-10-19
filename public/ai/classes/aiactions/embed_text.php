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
 * Embed text class.
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
        $embedding = $responsearr['embedding'] ?? [];

        $record = new \stdClass();
        $record->inputtext = $this->text;
        $record->responseid = $responsearr['id'] ?? null;
        $record->embeddingdims = count($embedding);
        $record->embeddinghash = hash('sha256', json_encode($embedding));

        return $DB->insert_record($this->get_tablename(), $record);
    }
}
