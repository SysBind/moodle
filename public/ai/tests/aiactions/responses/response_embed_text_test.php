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
 * Test response_embed_text action methods.
 *
 * @package    core_ai
 * @copyright  2026 Asaf Ohayon <asaf@sysbind.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \core_ai\aiactions\responses\response_embed_text
 */
final class response_embed_text_test extends \advanced_testcase {
    public function test_get_success(): void {
        $response = new response_embed_text(success: true);
        $this->assertTrue($response->get_success());
        $this->assertEquals('embed_text', $response->get_actionname());
    }

    public function test_set_response_data(): void {
        $response = new response_embed_text(success: true);
        $response->set_response_data([
            'id' => 'emb_456',
            'embedding' => [0.01, 0.02],
            'prompttokens' => 9,
            'model' => 'text-embedding-3-small',
        ]);

        $data = $response->get_response_data();
        $this->assertEquals('emb_456', $data['id']);
        $this->assertEquals([0.01, 0.02], $data['embedding']);
        $this->assertEquals(9, $data['prompttokens']);
        $this->assertEquals('text-embedding-3-small', $data['model']);
    }
}
