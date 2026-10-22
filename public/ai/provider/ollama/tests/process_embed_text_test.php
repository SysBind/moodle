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

use GuzzleHttp\Psr7\Response;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/testcase_helper_trait.php');

/**
 * Test embed text provider class for Ollama provider methods.
 *
 * @package    aiprovider_ollama
 * @copyright  2026 Asaf Ohayon <asaf@sysbind.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\aiprovider_ollama\process_embed_text::class)]
final class process_embed_text_test extends \advanced_testcase {
    use testcase_helper_trait;

    /** @var \core_ai\provider The provider that will process the action. */
    protected \core_ai\provider $provider;

    /** @var \core_ai\aiactions\embed_text The action to process. */
    protected \core_ai\aiactions\embed_text $action;

    /**
     * Set up the test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->provider = $this->create_provider(
            actionclass: \core_ai\aiactions\embed_text::class,
        );
        $this->action = new \core_ai\aiactions\embed_text(
            contextid: 1,
            userid: 1,
            text: 'This is a test text',
        );
    }

    /**
     * Call the protected handle_api_success method.
     *
     * @param array $body Response body to encode as JSON.
     * @return array
     */
    private function handle_api_success(array $body): array {
        $processor = new process_embed_text($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'handle_api_success');
        return $method->invoke($processor, new Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode($body),
        ));
    }

    /**
     * Test get_endpoint.
     */
    public function test_get_endpoint(): void {
        $processor = new process_embed_text($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'get_endpoint');

        $this->assertEquals('http://localhost:11434/api/embed', (string) $method->invoke($processor));
    }

    /**
     * Test create_request_object.
     */
    public function test_create_request_object(): void {
        $processor = new process_embed_text($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'create_request_object');
        $request = $method->invoke($processor, 1);

        $body = json_decode($request->getBody()->getContents());

        $this->assertEquals('llama3.2', $body->model);
        $this->assertEquals('This is a test text', $body->input);
        $this->assertObjectNotHasProperty('options', $body);
        $this->assertObjectNotHasProperty('system', $body);
    }

    /**
     * Test create_request_object with extra model settings from a custom model.
     */
    public function test_create_request_object_with_model_settings(): void {
        $this->provider = $this->create_provider(
            actionclass: \core_ai\aiactions\embed_text::class,
            actionconfig: [
                'model' => 'my-custom-embedder',
                'modelextraparams' => '{"num_ctx": 2048}',
            ],
        );
        $processor = new process_embed_text($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'create_request_object');
        $request = $method->invoke($processor, 1);

        $body = json_decode($request->getBody()->getContents());

        $this->assertEquals('my-custom-embedder', $body->model);
        $this->assertEquals(2048, $body->options->num_ctx);
    }

    /**
     * Test handle_api_success with the /api/embed response shape.
     */
    public function test_handle_api_success(): void {
        $result = $this->handle_api_success([
            'model' => 'llama3.2',
            'embeddings' => [[0.1, 0.2, 0.3]],
            'prompt_eval_count' => 5,
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals([0.1, 0.2, 0.3], $result['embedding']);
        $this->assertEquals(5, $result['prompttokens']);
        $this->assertNull($result['completiontokens']);
        $this->assertEquals('llama3.2', $result['model']);
    }

    /**
     * Test handle_api_success with the legacy /api/embeddings response shape.
     */
    public function test_handle_api_success_legacy_embedding(): void {
        $result = $this->handle_api_success([
            'model' => 'llama3.2',
            'embedding' => [0.4, 0.5],
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals([0.4, 0.5], $result['embedding']);
        $this->assertNull($result['prompttokens']);
    }

    /**
     * Test handle_api_success falls back to the configured model name.
     */
    public function test_handle_api_success_without_model_falls_back(): void {
        $result = $this->handle_api_success([
            'embeddings' => [[0.1]],
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('llama3.2', $result['model']);
    }

    /**
     * Test handle_api_success rejects a response without a vector.
     */
    public function test_handle_api_success_without_embedding_fails(): void {
        $result = $this->handle_api_success([
            'model' => 'llama3.2',
            'embeddings' => [[]],
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals(500, $result['errorcode']);
    }

    /**
     * Test handle_api_success rejects a vector with non-numeric components.
     */
    public function test_handle_api_success_with_non_numeric_embedding_fails(): void {
        $result = $this->handle_api_success([
            'model' => 'llama3.2',
            'embeddings' => [['not-a-number']],
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals(500, $result['errorcode']);
    }

    /**
     * Test the full process() flow against a mocked Ollama endpoint.
     */
    public function test_process(): void {
        $this->setUser($this->getDataGenerator()->create_user());

        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append(new Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode([
                'model' => 'llama3.2',
                'embeddings' => [[0.1, 0.2, 0.3]],
                'prompt_eval_count' => 5,
            ]),
        ));

        $processor = new process_embed_text($this->provider, $this->action);
        $result = $processor->process();

        $this->assertInstanceOf(\core_ai\aiactions\responses\response_embed_text::class, $result);
        $this->assertTrue($result->get_success());
        $this->assertEquals('embed_text', $result->get_actionname());
        $this->assertEquals([0.1, 0.2, 0.3], $result->get_response_data()['embedding']);
        $this->assertEquals('llama3.2', $result->get_model_used());
    }

    /**
     * Test the full process() flow when Ollama returns an error.
     */
    public function test_process_error(): void {
        $this->setUser($this->getDataGenerator()->create_user());

        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append(new Response(
            404,
            ['Content-Type' => 'application/json'],
            json_encode(['error' => 'model "nomic-embed-text" not found, try pulling it first']),
        ));

        $processor = new process_embed_text($this->provider, $this->action);
        $result = $processor->process();

        $this->assertFalse($result->get_success());
        $this->assertEquals('embed_text', $result->get_actionname());
        $this->assertEquals(404, $result->get_errorcode());
        $this->assertStringContainsString('not found', $result->get_errormessage());
    }
}
