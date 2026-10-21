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

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Process text embedding requests for Ollama.
 *
 * @package    aiprovider_ollama
 * @copyright  2026 Asaf Ohayon <asaf@sysbind.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_embed_text extends abstract_processor {
    #[\Override]
    protected function get_system_instruction(): string {
        return '';
    }

    #[\Override]
    protected function get_endpoint(): UriInterface {
        $url = rtrim($this->provider->config['endpoint'], '/') . '/api/embed';
        return new Uri($url);
    }

    #[\Override]
    protected function create_request_object(string $userid): RequestInterface {
        $requestobj = new \stdClass();
        $requestobj->model = $this->get_model();
        $requestobj->input = $this->action->get_configuration('text');

        return new Request(
            method: 'POST',
            uri: '',
            body: json_encode($requestobj),
            headers: [
                'Content-Type' => 'application/json',
            ],
        );
    }

    #[\Override]
    protected function handle_api_success(ResponseInterface $response): array {
        $bodyobj = json_decode($response->getBody()->getContents());

        $embedding = [];
        if (!empty($bodyobj->embedding) && is_array($bodyobj->embedding)) {
            $embedding = $bodyobj->embedding;
        } else if (!empty($bodyobj->embeddings[0]) && is_array($bodyobj->embeddings[0])) {
            $embedding = $bodyobj->embeddings[0];
        }

        foreach ($embedding as $value) {
            if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)) {
                $embedding = [];
                break;
            }
        }

        if ($embedding === []) {
            return \core_ai\error\factory::create(
                500,
                'Invalid embedding response from Ollama: no embedding vector returned.',
            )->get_error_details();
        }

        return [
            'success' => true,
            'id' => $bodyobj->id ?? null,
            'embedding' => $embedding,
            'prompttokens' => $bodyobj->prompt_eval_count ?? null,
            'completiontokens' => null,
            'model' => $bodyobj->model ?? $this->get_model(),
        ];
    }
}
