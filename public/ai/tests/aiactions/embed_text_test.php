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

use core_ai\aiactions\responses\response_embed_text;

/**
 * Test embed_text action methods.
 *
 * @package    core_ai
 * @copyright  2026 Asaf Ohayon <asaf@sysbind.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\core_ai\aiactions\embed_text::class)]
final class embed_text_test extends \advanced_testcase {
    public function test_configure(): void {
        $action = new embed_text(
            contextid: 1,
            userid: 7,
            text: 'Example text',
        );
        $this->assertEquals(7, $action->get_configuration('userid'));
        $this->assertEquals('Example text', $action->get_configuration('text'));
    }

    public function test_store(): void {
        $this->resetAfterTest();
        global $DB;

        $action = new embed_text(
            contextid: 1,
            userid: 7,
            text: 'Vector input text',
        );
        $response = new response_embed_text(success: true);
        $response->set_response_data([
            'id' => 'emb_123',
            'embedding' => [0.1, 0.2, 0.3],
            'prompttokens' => 12,
            'model' => 'text-embedding-3-large',
        ]);

        $storeid = $action->store($response);
        $record = $DB->get_record('ai_action_embed_text', ['id' => $storeid], '*', MUST_EXIST);

        $this->assertEquals(hash('sha256', 'Vector input text'), $record->inputhash);
        $this->assertEquals(strlen('Vector input text'), (int)$record->inputlength);
        $this->assertEquals(3, (int)$record->embeddingdims);
        // Neither the input text nor the vector is persisted.
        $this->assertObjectNotHasProperty('inputtext', $record);
        $this->assertObjectNotHasProperty('embedding', $record);
    }
}
