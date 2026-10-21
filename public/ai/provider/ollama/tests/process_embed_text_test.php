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

    public function test_handle_api_success(): void {
        $result = $this->handle_api_success([
            'model' => 'llama3.2',
            'embeddings' => [[0.1, 0.2, 0.3]],
            'prompt_eval_count' => 5,
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals([0.1, 0.2, 0.3], $result['embedding']);
        $this->assertEquals(5, $result['prompttokens']);
        $this->assertEquals('llama3.2', $result['model']);
    }

    public function test_handle_api_success_legacy_embedding(): void {
        $result = $this->handle_api_success([
            'model' => 'llama3.2',
            'embedding' => [0.4, 0.5],
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals([0.4, 0.5], $result['embedding']);
    }

    public function test_handle_api_success_without_embedding_fails(): void {
        $result = $this->handle_api_success([
            'model' => 'llama3.2',
            'embeddings' => [[]],
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals(500, $result['errorcode']);
    }

    public function test_handle_api_success_with_non_numeric_embedding_fails(): void {
        $result = $this->handle_api_success([
            'model' => 'llama3.2',
            'embeddings' => [['not-a-number']],
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals(500, $result['errorcode']);
    }
}
