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

namespace aiprovider_openai;

use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Process text embedding requests for OpenAI.
 *
 * @package    aiprovider_openai
 * @copyright  2026 Asaf Ohayon <asaf@sysbind.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_embed_text extends abstract_processor {
    #[\Override]
    protected function get_system_instruction(): string {
        return '';
    }

    #[\Override]
    protected function create_request_object(string $userid): RequestInterface {
        $requestobj = new \stdClass();
        $requestobj->model = $this->get_model();
        $requestobj->input = $this->action->get_configuration('text');
        $requestobj->user = $userid;

        return new Request(
            method: 'POST',
            uri: '',
            headers: [
                'Content-Type' => 'application/json',
            ],
            body: json_encode($requestobj, JSON_UNESCAPED_SLASHES),
        );
    }

    #[\Override]
    protected function handle_api_success(ResponseInterface $response): array {
        $bodyobj = json_decode($response->getBody()->getContents());
        $embedding = $bodyobj->data[0]->embedding ?? [];

        return [
            'success' => true,
            'id' => $bodyobj->id ?? null,
            'embedding' => $embedding,
            'prompttokens' => $bodyobj->usage->prompt_tokens ?? null,
            'completiontokens' => null,
            'model' => $bodyobj->model ?? $this->get_model(),
        ];
    }
}
