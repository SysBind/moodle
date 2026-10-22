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

namespace aiprovider_ollama\aimodel;

use core_ai\aimodel\base;

/**
 * Nomic Embed Text embedding model.
 *
 * @package    aiprovider_ollama
 * @copyright  2026 Asaf Ohayon <asaf@sysbind.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class nomic_embed_text extends base implements ollama_base {
    #[\Override]
    public function get_model_name(): string {
        return 'nomic-embed-text';
    }

    #[\Override]
    public function get_model_display_name(): string {
        return 'Nomic Embed Text';
    }

    #[\Override]
    public function model_type(): int {
        return self::MODEL_TYPE_EMBEDDING;
    }
}
