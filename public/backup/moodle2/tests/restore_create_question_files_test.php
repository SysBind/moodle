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

namespace core_backup;

use advanced_testcase;
use backup;
use backup_controller;
use context_course;
use context_module;
use core\progress\none;
use file_exception;
use PHPUnit\Framework\Attributes\CoversClass;
use Random\RandomException;
use restore_controller;
use restore_controller_dbops;
use restore_create_question_files;
use restore_dbops;
use restore_task;
use stored_file_creation_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/backup/moodle2/restore_stepslib.php');

/**
 * Tests for the question-file restore step.
 *
 * @package core_backup
 * @copyright Eli Zard <eli@sysbind.co.il>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(restore_create_question_files::class)]
final class restore_create_question_files_test extends advanced_testcase {
    /** @var string Restore id for the direct step test. */
    private $restoreid;

    /** @var bool Whether this test created the shared restore temp tables. */
    private $createdtemptables = false;

    /**
     * Drop temporary restore mappings created by the direct step test.
     */
    protected function tearDown(): void {
        if ($this->restoreid) {
            if ($this->createdtemptables) {
                restore_controller_dbops::drop_restore_temp_tables($this->restoreid);
            } else {
                global $DB;
                $DB->delete_records('backup_ids_temp', ['backupid' => $this->restoreid]);
                $DB->delete_records('backup_files_temp', ['backupid' => $this->restoreid]);
            }
            restore_dbops::reset_backup_ids_cached();
            $this->restoreid = null;
        }
        parent::tearDown();
    }

    /**
     * Create the restore temp tables used by a direct restore-step test.
     *
     * @return void Restore id.
     * @throws RandomException
     */
    private function create_restoreid(): void {
        $this->restoreid = 'questionfiles' . bin2hex(random_bytes(8));
        $this->createdtemptables = !restore_controller_dbops::create_restore_temp_tables($this->restoreid);
    }

    /**
     * Create a task double for direct execution of the restore step.
     *
     * @param string $restoreid Restore id.
     * @param string $basepath Backup base path.
     * @return restore_task
     */
    private function create_task_mock(string $restoreid, string $basepath = ''): restore_task {
        global $USER;

        $task = $this->getMockBuilder(restore_task::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['build', 'define_settings', 'get_restoreid', 'get_progress', 'get_userid', 'get_basepath'])
            ->getMock();
        $task->method('get_restoreid')->willReturn($restoreid);
        $task->method('get_progress')->willReturn(new none());
        $task->method('get_userid')->willReturn($USER->id);
        $task->method('get_basepath')->willReturn($basepath);

        return $task;
    }

    /**
     * Create a PHPUnit double of the restore step.
     *
     * @param restore_task $task Restore task double.
     * @param string[] $methods Methods to mock.
     * @return restore_create_question_files
     */
    private function create_step_mock(restore_task $task, array $methods): restore_create_question_files {
        return $this->getMockBuilder(restore_create_question_files::class)
            ->setConstructorArgs(['question_files', $task])
            ->onlyMethods($methods)
            ->getMock();
    }

    /**
     * Create a category with questions and optionally add its restore mappings.
     *
     * @param object $questiongenerator Question data generator.
     * @param string $restoreid Restore id.
     * @param int $oldcontextid Source context id.
     * @param int $newcontextid Destination context id.
     * @param string[] $qtypes Question types to add to the category.
     * @param bool $addcategorymapping Whether to add the category mapping.
     * @return void Created question category.
     * @throws RandomException
     */
    private function create_category_with_questions(
        $questiongenerator,
        string $restoreid,
        int $oldcontextid,
        int $newcontextid,
        array $qtypes,
        bool $addcategorymapping = true
    ): void {
        $category = $questiongenerator->create_question_category([
            'contextid' => $oldcontextid,
            'name' => 'Question file restore category ' . bin2hex(random_bytes(5)),
        ]);

        if ($addcategorymapping) {
            restore_dbops::set_backup_ids_record(
                $restoreid,
                'question_category',
                $category->id,
                0,
                $newcontextid,
                (object)['contextid' => $oldcontextid],
            );
        }

        foreach ($qtypes as $qtype) {
            $question = $questiongenerator->create_question($qtype, null, [
                'category' => $category->id,
                'name' => 'Question file restore question ' . bin2hex(random_bytes(5)),
            ]);
            restore_dbops::set_backup_ids_record(
                $restoreid,
                'question_created',
                $question->id,
                $question->id,
                $category->id,
            );
        }
    }

    /**
     * Create a file in a question file area and return its restore assertions.
     *
     * @param int $contextid Context id.
     * @param string $component File component.
     * @param string $filearea File area.
     * @param int $itemid Source item id.
     * @param string $questionname Question name used to find the restored item.
     * @param string $itemtype Item type used to find the restored item id.
     * @param string $filename File name.
     * @param string $content File contents.
     * @return array File restore assertions.
     * @throws file_exception
     * @throws stored_file_creation_exception
     */
    private function create_question_file(
        int $contextid,
        string $component,
        string $filearea,
        int $itemid,
        string $questionname,
        string $itemtype,
        string $filename,
        string $content
    ): array {
        global $USER;

        get_file_storage()->create_file_from_string([
            'contextid' => $contextid,
            'component' => $component,
            'filearea' => $filearea,
            'itemid' => $itemid,
            'filepath' => '/',
            'filename' => $filename,
            'userid' => $USER->id,
        ], $content);

        return [
            'questionname' => $questionname,
            'component' => $component,
            'filearea' => $filearea,
            'itemtype' => $itemtype,
            'filename' => $filename,
            'content' => $content,
        ];
    }

    /**
     * Check common files and each question type are processed once per context pair.
     */
    public function test_file_areas_are_processed_once_per_context_pair(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $questiongenerator = $generator->get_plugin_generator('core_question');
        $sourcecoursea = $generator->create_course();
        $sourcecourseb = $generator->create_course();
        $targetcoursex = $generator->create_course();
        $targetcoursey = $generator->create_course();
        $targetcoursez = $generator->create_course();
        $sourcecontexta = context_course::instance($sourcecoursea->id);
        $sourcecontextb = context_course::instance($sourcecourseb->id);
        $targetcontextx = context_course::instance($targetcoursex->id);
        $targetcontexty = context_course::instance($targetcoursey->id);
        $targetcontextz = context_course::instance($targetcoursez->id);

        $this->create_restoreid();

        // The first category has no mapping and must not suppress the valid categories after it.
        $this->create_category_with_questions(
            $questiongenerator,
            $this->restoreid,
            $sourcecontexta->id,
            0,
            ['essay'],
            false,
        );

        // Two question types in one category ensure a later type isn't skipped with the common files.
        $this->create_category_with_questions(
            $questiongenerator,
            $this->restoreid,
            $sourcecontexta->id,
            $targetcontextx->id,
            ['shortanswer', 'essay'],
        );
        $this->create_category_with_questions(
            $questiongenerator,
            $this->restoreid,
            $sourcecontextb->id,
            $targetcontexty->id,
            ['essay'],
        );
        // Non-adjacent repeat of A/X, then same source context with a different destination.
        $this->create_category_with_questions(
            $questiongenerator,
            $this->restoreid,
            $sourcecontexta->id,
            $targetcontextx->id,
            ['shortanswer'],
        );
        $this->create_category_with_questions(
            $questiongenerator,
            $this->restoreid,
            $sourcecontexta->id,
            $targetcontextz->id,
            ['shortanswer'],
        );

        $task = $this->create_task_mock($this->restoreid);
        $step = $this->create_step_mock($task, ['send_common_files', 'send_qtype_files']);
        $commonfilescalls = [];
        $qtypefilescalls = [];
        $step->expects($this->exactly(3))
            ->method('send_common_files')
            ->willReturnCallback(static function ($oldcontextid, $newcontextid) use (&$commonfilescalls) {
                $commonfilescalls[] = [$oldcontextid, $newcontextid];
            });
        $step->expects($this->exactly(4))
            ->method('send_qtype_files')
            ->willReturnCallback(static function ($qtype, $oldcontextid, $newcontextid) use (&$qtypefilescalls) {
                $qtypefilescalls[] = [$qtype, $oldcontextid, $newcontextid];
            });

        $step->execute();
        $this->assertDebuggingCalled(null, DEBUG_DEVELOPER);

        $this->assertSame([
            [$sourcecontexta->id, $targetcontextx->id],
            [$sourcecontextb->id, $targetcontexty->id],
            [$sourcecontexta->id, $targetcontextz->id],
        ], $commonfilescalls);

        $this->assertEqualsCanonicalizing([
            ['shortanswer', $sourcecontexta->id, $targetcontextx->id],
            ['essay', $sourcecontextb->id, $targetcontexty->id],
            ['essay', $sourcecontexta->id, $targetcontextx->id],
            ['shortanswer', $sourcecontexta->id, $targetcontextz->id],
        ], $qtypefilescalls);
    }

    /**
     * Check an empty question bank completes without sending any file areas.
     */
    public function test_empty_question_bank_sends_no_files(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->create_restoreid();
        $task = $this->create_task_mock($this->restoreid);
        $step = $this->create_step_mock($task, ['send_common_files', 'send_qtype_files']);
        $step->expects($this->never())->method('send_common_files');
        $step->expects($this->never())->method('send_qtype_files');

        $step->execute();
    }

    /**
     * Check completion markers are local to one step execution.
     */
    public function test_context_pair_is_processed_again_on_a_new_execution(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $questiongenerator = $generator->get_plugin_generator('core_question');
        $sourcecourse = $generator->create_course();
        $targetcourse = $generator->create_course();
        $sourcecontext = context_course::instance($sourcecourse->id);
        $targetcontext = context_course::instance($targetcourse->id);

        $this->create_restoreid();
        $this->create_category_with_questions(
            $questiongenerator,
            $this->restoreid,
            $sourcecontext->id,
            $targetcontext->id,
            ['shortanswer'],
        );

        $task = $this->create_task_mock($this->restoreid);
        $step = $this->create_step_mock($task, ['send_common_files', 'send_qtype_files']);
        $step->expects($this->exactly(2))->method('send_common_files');
        $step->expects($this->exactly(2))->method('send_qtype_files');

        $step->execute();
        $step->execute();
    }

    /**
     * Check the real qtype sender uses the cached component map for later context pairs.
     */
    public function test_qtype_sender_handles_repeated_type_in_different_context_pairs(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $questiongenerator = $generator->get_plugin_generator('core_question');
        $sourcecoursea = $generator->create_course();
        $sourcecourseb = $generator->create_course();
        $targetcoursea = $generator->create_course();
        $targetcourseb = $generator->create_course();
        $sourcecontexta = context_course::instance($sourcecoursea->id);
        $sourcecontextb = context_course::instance($sourcecourseb->id);
        $targetcontexta = context_course::instance($targetcoursea->id);
        $targetcontextb = context_course::instance($targetcourseb->id);

        $this->create_restoreid();
        $this->create_category_with_questions(
            $questiongenerator,
            $this->restoreid,
            $sourcecontexta->id,
            $targetcontexta->id,
            ['essay'],
        );
        $this->create_category_with_questions(
            $questiongenerator,
            $this->restoreid,
            $sourcecontextb->id,
            $targetcontextb->id,
            ['essay'],
        );

        $task = $this->create_task_mock($this->restoreid, $CFG->tempdir);
        $step = $this->create_step_mock($task, ['send_common_files']);
        $step->expects($this->exactly(2))->method('send_common_files');

        $step->execute();
    }

    /**
     * Check common and qtype files are restored with their mapped item IDs.
     */
    public function test_restores_question_files_from_categories_sharing_a_context(): void {
        global $CFG, $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $questiongenerator = $generator->get_plugin_generator('core_question');
        $course = $generator->create_course();
        $qbank = $generator->get_plugin_generator('mod_qbank')->create_instance(['course' => $course->id]);
        $context = context_module::instance($qbank->cmid);
        $files = [];

        $categoryone = $questiongenerator->create_question_category([
            'contextid' => $context->id,
            'name' => 'Question file restore integration category one',
        ]);
        $questionone = $questiongenerator->create_question('shortanswer', null, [
            'category' => $categoryone->id,
            'name' => 'Question file common areas',
        ]);
        $questiontextfilename = 'question-text.txt';
        $generalfeedbackfilename = 'general-feedback.txt';
        $DB->set_field(
            'question',
            'questiontext',
            '<p>@@PLUGINFILE@@/' . $questiontextfilename . '</p>',
            ['id' => $questionone->id]
        );
        $DB->set_field(
            'question',
            'generalfeedback',
            '<p>@@PLUGINFILE@@/' . $generalfeedbackfilename . '</p>',
            ['id' => $questionone->id],
        );
        $files[] = $this->create_question_file(
            $context->id,
            'question',
            'questiontext',
            $questionone->id,
            $questionone->name,
            'question',
            $questiontextfilename,
            'question text content',
        );
        $files[] = $this->create_question_file(
            $context->id,
            'question',
            'generalfeedback',
            $questionone->id,
            $questionone->name,
            'question',
            $generalfeedbackfilename,
            'general feedback content',
        );

        $answerid = $DB->get_field_sql(
            'SELECT MIN(id) FROM {question_answers} WHERE question = ?',
            [$questionone->id],
            MUST_EXIST,
        );
        $files[] = $this->create_question_file(
            $context->id,
            'question',
            'answer',
            $answerid,
            $questionone->name,
            'answer',
            'answer.txt',
            'answer content',
        );
        $files[] = $this->create_question_file(
            $context->id,
            'question',
            'answerfeedback',
            $answerid,
            $questionone->name,
            'answer',
            'answer-feedback.txt',
            'answer feedback content',
        );

        $hintid = $DB->insert_record('question_hints', (object)[
            'questionid' => $questionone->id,
            'hint' => 'Question hint',
            'hintformat' => FORMAT_HTML,
        ]);
        $files[] = $this->create_question_file(
            $context->id,
            'question',
            'hint',
            $hintid,
            $questionone->name,
            'hint',
            'hint.txt',
            'hint content',
        );

        foreach (['correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback'] as $filearea) {
            $files[] = $this->create_question_file(
                $context->id,
                'question',
                $filearea,
                $questionone->id,
                $questionone->name,
                'question',
                $filearea . '.txt',
                $filearea . ' content',
            );
        }

        // A second category in the same context ensures a single common-file pass includes later questions.
        $categorytwo = $questiongenerator->create_question_category([
            'contextid' => $context->id,
            'name' => 'Question file restore integration category two',
        ]);
        $questiontwo = $questiongenerator->create_question('shortanswer', null, [
            'category' => $categorytwo->id,
            'name' => 'Question file second category',
        ]);
        $questiontwofilename = 'second-question-text.txt';
        $DB->set_field(
            'question',
            'questiontext',
            '<p>@@PLUGINFILE@@/' . $questiontwofilename . '</p>',
            ['id' => $questiontwo->id],
        );
        $files[] = $this->create_question_file(
            $context->id,
            'question',
            'questiontext',
            $questiontwo->id,
            $questiontwo->name,
            'question',
            $questiontwofilename,
            'second question content',
        );

        $categoryessay = $questiongenerator->create_question_category([
            'contextid' => $context->id,
            'name' => 'Question file restore essay category',
        ]);
        $essay = $questiongenerator->create_question('essay', null, [
            'category' => $categoryessay->id,
            'name' => 'Question file essay grader info',
        ]);
        $graderinfofilename = 'grader-info.txt';
        $DB->set_field(
            'qtype_essay_options',
            'graderinfo',
            '<p>@@PLUGINFILE@@/' . $graderinfofilename . '</p>',
            ['questionid' => $essay->id],
        );
        $files[] = $this->create_question_file(
            $context->id,
            'qtype_essay',
            'graderinfo',
            $essay->id,
            $essay->name,
            'question',
            $graderinfofilename,
            'grader info content',
        );

        $CFG->backup_file_logger_level = backup::LOG_NONE;
        $backupcontroller = new backup_controller(
            backup::TYPE_1ACTIVITY,
            $qbank->cmid,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id,
        );
        $backupid = $backupcontroller->get_backupid();
        $backupcontroller->execute_plan();
        $backupcontroller->destroy();

        $targetcourse = $generator->create_course();
        $restorecontroller = new restore_controller(
            $backupid,
            $targetcourse->id,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id,
            backup::TARGET_CURRENT_ADDING,
        );
        $this->assertTrue($restorecontroller->execute_precheck());
        $restorecontroller->get_plan()->get_setting('role_assignments')->set_value(true);
        $restorecontroller->get_plan()->get_setting('permissions')->set_value(true);
        $restorecontroller->execute_plan();
        $restorecontroller->destroy();

        $targetqbanks = get_fast_modinfo($targetcourse->id)->get_instances_of('qbank');
        $this->assertCount(1, $targetqbanks);
        $targetqbank = reset($targetqbanks);
        $targetcontext = context_module::instance($targetqbank->id);
        foreach ($files as $fileassertion) {
            $questionid = $DB->get_field_sql(
                "SELECT q.id
                   FROM {question} q
                   JOIN {question_versions} qv ON qv.questionid = q.id
                   JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                   JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                  WHERE qc.contextid = ? AND q.name = ?",
                [$targetcontext->id, $fileassertion['questionname']],
                MUST_EXIST,
            );

            if ($fileassertion['itemtype'] === 'answer') {
                $itemid = $DB->get_field_sql(
                    'SELECT MIN(id) FROM {question_answers} WHERE question = ?',
                    [$questionid],
                    MUST_EXIST,
                );
            } else if ($fileassertion['itemtype'] === 'hint') {
                $itemid = $DB->get_field_sql(
                    'SELECT id FROM {question_hints} WHERE questionid = ? AND ' . $DB->sql_compare_text('hint') . ' = ?',
                    [$questionid, 'Question hint'],
                    MUST_EXIST,
                );
            } else {
                $itemid = $questionid;
            }

            $file = get_file_storage()->get_file(
                $targetcontext->id,
                $fileassertion['component'],
                $fileassertion['filearea'],
                $itemid,
                '/',
                $fileassertion['filename'],
            );
            $this->assertNotFalse($file);
            $this->assertSame($itemid, $file->get_itemid());
            $this->assertSame($fileassertion['content'], $file->get_content());
        }
    }
}
