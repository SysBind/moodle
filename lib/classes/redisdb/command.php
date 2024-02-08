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

/**
 * Redis command object.
 *
 * @since      Moodle 3.1
 * @package    core_redisdb
 * @copyright  5784(2024) Asaf Ohayon (https://sysbind.co.il)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

namespace core\redisdb;

class keyval {
    public string $key = null;
    public string $val = [];
}

/**
 * represents modification to the database 
 */
class command {
    /**
     * @var keyval - key / value(s) to set / add to the key
     */
    protected keyval $value;
    
    public function __construct(keyval $keyval) {
        $this->keyval = keyval;
    }

    /** execute the command on the given Redis instance
     *
     * @param Redis $redis instance.
     * @return Redis $redis instance. (for chain commands)
     */
    public abstract function exec(Redis $redis);
}

class set {
    public function exec(Redis $redis) {
        error_log('redisdb:command:set -> ' . $this->keyval->key);
        // return redis->set();
    }
}

class sadd {
    public function exec(Redis $redis) {
        error_log('redisdb:command:sadd -> ' . $this->keyval->key);
        // return redis->set();
    }
}
