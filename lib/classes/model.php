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

namespace core;

/**
 * User Alert notifications.
 *
 * @package    core
 * @copyright  2016 Avi Levy <avi@sysbind.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

abstract class model
{
    /**
     * The relative table in Database
     * @var string $table
     */
    protected $table;

    /**
     * Integer number represent the uniqe id in Database
     * @var int $id
     */
    protected $id;

    /**
     */
    public function __construct(string $table = '', int $id = null) {
        $this->id = $id;
        $this->set_table($table);
    }

    /**
     * Return the table name
     * @return string
     */
    public function get_table():string {
        return $this->table;
    }

    /**
     * Set the table and check if table exist if yes set the table
     * and return treu else return false
     * @param string $table
     * @return bool
     */
    public function set_table($table):bool {
        global $DB;

        $dbman = $DB->get_manager();
        if ($dbman->table_exists($table)) {
            $this->table = $table;
            return true;
        }
        return false;
    }

    /**
     * Initialize object properties wite database correlaction fileds
     */
    public function load_from_db() {
        global $DB;

        if (!empty($this->id)) {
            $vars = get_object_vars($this);
            $obj = $DB->get_record($this->get_table(), array('id' => $this->id));
            foreach ($obj as $key => $value) {
                if (key_exists($key, $vars)) {
                    $this->{$key} = $value;
                }
            }
        }
    }

    protected function to_std():\stdClass {
        $obj = new \stdClass();
        $vars = get_object_vars($this);
        foreach ($vars as $key => $value) {
            $obj->{$key} = $value;
        }
        return $obj;
    }

    public abstract function add_entry();
}

