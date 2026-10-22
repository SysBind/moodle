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

namespace core_ai\aiactions\responses;

/**
 * Embed text action response class.
 *
 * @package    core_ai
 * @copyright  2026 Asaf Ohayon <asaf@sysbind.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class response_embed_text extends response_base {
    /** @var string|null Provider response id, when available. */
    private ?string $id = null;

    /** @var float[] Embedding vector. */
    private array $embedding = [];

    /** @var int|null Number of tokens in the prompt/input. */
    private ?int $prompttokens = null;

    /** @var int|null Number of completion tokens, usually null for embeddings. */
    private ?int $completiontokens = null;

    /**
     * Constructor.
     *
     * @param bool $success The success status of the action.
     * @param int $errorcode Error code. Must exist if success is false.
     * @param string $error Error name. Must exist if success is false.
     * @param string $errormessage Error message.
     */
    public function __construct(
        bool $success,
        int $errorcode = 0,
        string $error = '',
        string $errormessage = '',
    ) {
        parent::__construct(
            success: $success,
            actionname: 'embed_text',
            errorcode: $errorcode,
            error: $error,
            errormessage: $errormessage,
        );
    }

    #[\Override]
    public function set_response_data(array $response): void {
        $this->id = $response['id'] ?? null;
        $this->embedding = $response['embedding'] ?? [];
        $this->prompttokens = isset($response['prompttokens']) ? (int)$response['prompttokens'] : null;
        $this->completiontokens = isset($response['completiontokens']) ? (int)$response['completiontokens'] : null;
        $this->model = $response['model'] ?? null;
    }

    #[\Override]
    public function get_response_data(): array {
        return [
            'id' => $this->id,
            'embedding' => $this->embedding,
            'prompttokens' => $this->prompttokens,
            'completiontokens' => $this->completiontokens,
            'model' => $this->model,
        ];
    }
}
