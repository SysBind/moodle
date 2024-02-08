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
 * Redis specific (NO) SQL code generator.
 *
 * @package    core_ddl
 * @copyright  5784(2024) Asaf Ohayon (https://sysbind.co.il)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/classes/redisdb/command.php'); // autoload?
use core\redisdb\sadd;

require_once($CFG->libdir.'/ddl/sql_generator.php');

/**
 * TODO - Document
 */

class redis_sql_generator extends sql_generator {

 /**
     * Given one correct xmldb_table, returns the REDIS("SQL") statements
     * to create it (inside one array).
     *
     * @param xmldb_table $xmldb_table An xmldb_table instance.
     * @return array An array of REDIS ("SQL") statements, starting with the table creation SQL followed
     * by any of its comments, indexes and sequence creation SQL statements.
     */
    public function getCreateTableSQL($xmldb_table) {
        if ($error = $xmldb_table->validateDefinition()) {
            throw new coding_exception($error);
        }

        $results = [];  //Array where all the sentences will be stored        

        $table_name = $this->getTableName($xmldb_table);
        // Table header
        $table = new sadd('mdl:schema:tables', [$table_name]);

        if (!$xmldb_fields = $xmldb_table->getFields()) {
            return $results;
        }

        $fields = [];
        $field_names = [];
        foreach ($xmldb_fields as $xmldb_field) {
            if ($error = $xmldb_field->validateDefinition($xmldb_table)) {
                throw new coding_exception($error);
            }

            $field_names[] = $xmldb_field->getName();
        }
        $fields = new sadd('mdl:schema:columns:' . $table_name, $field_names);        
        //        $fields = new sadd('mdl:schema:', []);
        // $sequencefield = null;

        // // Add the fields, separated by commas
        // foreach ($xmldb_fields as $xmldb_field) {
        //     if ($xmldb_field->getSequence()) {
        //         $sequencefield = $xmldb_field->getName();
        //     }
        //     $table .= "\n    " . $this->getFieldSQL($xmldb_table, $xmldb_field);
        //     $table .= ',';
        // }
        // // Add the keys, separated by commas
        // if ($xmldb_keys = $xmldb_table->getKeys()) {
        //     foreach ($xmldb_keys as $xmldb_key) {
        //         if ($keytext = $this->getKeySQL($xmldb_table, $xmldb_key)) {
        //             $table .= "\nCONSTRAINT " . $keytext . ',';
        //         }
        //         // If the key is XMLDB_KEY_FOREIGN_UNIQUE, create it as UNIQUE too
        //         if ($xmldb_key->getType() == XMLDB_KEY_FOREIGN_UNIQUE) {
        //             //Duplicate the key
        //             $xmldb_key->setType(XMLDB_KEY_UNIQUE);
        //             if ($keytext = $this->getKeySQL($xmldb_table, $xmldb_key)) {
        //                 $table .= "\nCONSTRAINT " . $keytext . ',';
        //             }
        //         }
        //         // make sure sequence field is unique
        //         if ($sequencefield and $xmldb_key->getType() == XMLDB_KEY_PRIMARY) {
        //             $fields = $xmldb_key->getFields();
        //             $field = reset($fields);
        //             if ($sequencefield === $field) {
        //                 $sequencefield = null;
        //             }
        //         }
        //     }
        // }
        // // throw error if sequence field does not have unique key defined
        // if ($sequencefield) {
        //     throw new ddl_exception('ddsequenceerror', $xmldb_table->getName());
        // }

        // // Table footer, trim the latest comma
        // $table = trim($table,',');
        // $table .= "\n)";

        // Add the CREATE TABLE to results
        $results[] = $table;
        $results[] = $fields;

        //!!!!
        return $results;

        // Add comments if specified and it exists
        if ($this->add_table_comments && $xmldb_table->getComment()) {
            $comment = $this->getCommentSQL($xmldb_table);
            // Add the COMMENT to results
            $results = array_merge($results, $comment);
        }

        // Add the indexes (each one, one statement)
        if ($xmldb_indexes = $xmldb_table->getIndexes()) {
            foreach ($xmldb_indexes as $xmldb_index) {
                //tables do not exist yet, which means indexed can not exist yet
                if ($indextext = $this->getCreateIndexSQL($xmldb_table, $xmldb_index)) {
                    $results = array_merge($results, $indextext);
                }
            }
        }

        // Also, add the indexes needed from keys, based on configuration (each one, one statement)
        if ($xmldb_keys = $xmldb_table->getKeys()) {
            foreach ($xmldb_keys as $xmldb_key) {
                // If we aren't creating the keys OR if the key is XMLDB_KEY_FOREIGN (not underlying index generated
                // automatically by the RDBMS) create the underlying (created by us) index (if doesn't exists)
                if (!$this->getKeySQL($xmldb_table, $xmldb_key) || $xmldb_key->getType() == XMLDB_KEY_FOREIGN) {
                    // Create the interim index
                    $index = new xmldb_index('anyname');
                    $index->setFields($xmldb_key->getFields());
                    //tables do not exist yet, which means indexed can not exist yet
                    $createindex = false; //By default
                    switch ($xmldb_key->getType()) {
                        case XMLDB_KEY_UNIQUE:
                        case XMLDB_KEY_FOREIGN_UNIQUE:
                            $index->setUnique(true);
                            $createindex = true;
                            break;
                        case XMLDB_KEY_FOREIGN:
                            $index->setUnique(false);
                            $createindex = true;
                            break;
                    }
                    if ($createindex) {
                        if ($indextext = $this->getCreateIndexSQL($xmldb_table, $index)) {
                            // Add the INDEX to the array
                            $results = array_merge($results, $indextext);
                        }
                    }
                }
            }
        }

        // Add sequence extra code if needed
        if ($this->sequence_extra_code) {
            // Iterate over fields looking for sequences
            foreach ($xmldb_fields as $xmldb_field) {
                if ($xmldb_field->getSequence()) {
                    // returns an array of statements needed to create one sequence
                    $sequence_sentences = $this->getCreateSequenceSQL($xmldb_table, $xmldb_field);
                    // Add the SEQUENCE to the array
                    $results = array_merge($results, $sequence_sentences);
                }
            }
        }

        return $results;
    }

    /**
     * Reset a sequence to the id field of a table.
     *
     * @param xmldb_table|string $table name of table or the table object.
     * @return array of sql statements
     */
    public function getResetSequenceSQL($table) {
        throw new coding_exception("Redis SQL Generator -> getResetSequenceSQL not yet implemented");
    }

    /**
     * Given one correct xmldb_table, returns the SQL statements
     * to create temporary table (inside one array).
     *
     * @param xmldb_table $xmldb_table The xmldb_table object instance.
     * @return array of sql statements
     */
    public function getCreateTempTableSQL($xmldb_table) {
        error_log('Redis Generator -> getCreateTempTableSQL' .  $xmldb_table);
        throw new coding_exception("Redis SQL Generator -> getCreateTempTableSQL not yet implemented");        
    }

    /**
     * Given one correct xmldb_index, returns the SQL statements
     * needed to create it (in array).
     *
     * @param xmldb_table $xmldb_table The xmldb_table instance to create the index on.
     * @param xmldb_index $xmldb_index The xmldb_index to create.
     * @return array An array of SQL statements to create the index.
     * @throws coding_exception Thrown if the xmldb_index does not validate with the xmldb_table.
     */
    public function getCreateIndexSQL($xmldb_table, $xmldb_index) {
        error_log('Redis Generator -> getCreateTempTableSQL' .  $xmldb_table->getName() . ' : ' . $xmldb_index->getName());
        return ['sql-create-index ' . $xmldb_table->getName() . ' ' . $xmldb_index->getName() ];
        // throw new coding_exception("Redis SQL Generator -> getCreateIndexSQL not yet implemented : " . $xmldb_table->getName() . ' : ' . $xmldb_index->getName());
    }

    /**
     * Given one XMLDB Type, length and decimals, returns the DB proper SQL type.
     *
     * @param int $xmldb_type The xmldb_type defined constant. XMLDB_TYPE_INTEGER and other XMLDB_TYPE_* constants.
     * @param int $xmldb_length The length of that data type.
     * @param int $xmldb_decimals The decimal places of precision of the data type.
     * @return string The DB defined data type.
     */
    public function getTypeSQL($xmldb_type, $xmldb_length=null, $xmldb_decimals=null) {

        switch ($xmldb_type) {
            case XMLDB_TYPE_INTEGER:    // From http://www.postgresql.org/docs/7.4/interactive/datatype.html
                if (empty($xmldb_length)) {
                    $xmldb_length = 10;
                }
                if ($xmldb_length > 9) {
                    $dbtype = 'BIGINT';
                } else if ($xmldb_length > 4) {
                    $dbtype = 'INTEGER';
                } else {
                    $dbtype = 'SMALLINT';
                }
                break;
            case XMLDB_TYPE_NUMBER:
                $dbtype = $this->number_type;
                if (!empty($xmldb_length)) {
                    $dbtype .= '(' . $xmldb_length;
                    if (!empty($xmldb_decimals)) {
                        $dbtype .= ',' . $xmldb_decimals;
                    }
                    $dbtype .= ')';
                }
                break;
            case XMLDB_TYPE_FLOAT:
                $dbtype = 'DOUBLE PRECISION';
                if (!empty($xmldb_decimals)) {
                    if ($xmldb_decimals < 6) {
                        $dbtype = 'REAL';
                    }
                }
                break;
            case XMLDB_TYPE_CHAR:
                $dbtype = 'VARCHAR';
                if (empty($xmldb_length)) {
                    $xmldb_length='255';
                }
                $dbtype .= '(' . $xmldb_length . ')';
                break;
            case XMLDB_TYPE_TEXT:
                $dbtype = 'TEXT';
                break;
            case XMLDB_TYPE_BINARY:
                $dbtype = 'BYTEA';
                break;
            case XMLDB_TYPE_DATETIME:
                $dbtype = 'TIMESTAMP';
                break;
        }
        return $dbtype;
    }

    /**
     * Returns the code (array of statements) needed to add one comment to the table.
     *
     * @param xmldb_table $xmldb_table The xmldb_table object instance.
     * @return array Array of SQL statements to add one comment to the table.
     */
    function getCommentSQL ($xmldb_table) {

        $comment = "COMMENT ON TABLE " . $this->getTableName($xmldb_table);
        $comment.= " IS '" . $this->addslashes(substr($xmldb_table->getComment(), 0, 250)) . "'";

        return array($comment);
    }



    /**
     * Given one xmldb_table and one xmldb_field, return the SQL statements needed to add its default
     * (usually invoked from getModifyDefaultSQL()
     *
     * @param xmldb_table $xmldb_table The xmldb_table object instance.
     * @param xmldb_field $xmldb_field The xmldb_field object instance.
     * @return array Array of SQL statements to create a field's default.
     */
    public function getCreateDefaultSQL($xmldb_table, $xmldb_field) {
        // Just a wrapper over the getAlterFieldSQL() function for PostgreSQL that
        // is capable of handling defaults
        return $this->getAlterFieldSQL($xmldb_table, $xmldb_field);
    }

    /**
     * Given one xmldb_table and one xmldb_field, return the SQL statements needed to drop its default
     * (usually invoked from getModifyDefaultSQL()
     *
     * Note that this method may be dropped in future.
     *
     * @param xmldb_table $xmldb_table The xmldb_table object instance.
     * @param xmldb_field $xmldb_field The xmldb_field object instance.
     * @return array Array of SQL statements to create a field's default.
     *
     * @todo MDL-31147 Moodle 2.1 - Drop getDropDefaultSQL()
     */
    public function getDropDefaultSQL($xmldb_table, $xmldb_field) {
        // Just a wrapper over the getAlterFieldSQL() function for PostgreSQL that
        // is capable of handling defaults
        return $this->getAlterFieldSQL($xmldb_table, $xmldb_field);
    }

    /**
     * Given one object name and it's type (pk, uk, fk, ck, ix, uix, seq, trg).
     *
     * (MySQL requires the whole xmldb_table object to be specified, so we add it always)
     *
     * This is invoked from getNameForObject().
     * Only some DB have this implemented.
     *
     * @param string $object_name The object's name to check for.
     * @param string $type The object's type (pk, uk, fk, ck, ix, uix, seq, trg).
     * @param string $table_name The table's name to check in
     * @return bool If such name is currently in use (true) or no (false)
     */
    public function isNameInUse($object_name, $type, $table_name) {
        error_log('Redis Generator -> isNameInUse ' . $object_name . ' : ' . $type . ':' . $table_name);
        return false;
        // throw new coding_exception("Redis SQL Generator -> isNameInUse not yet implemented");
    }

    /**
     * Returns an array of reserved words (lowercase) for this DB
     * @return array An array of database specific reserved words
     */
    public static function getReservedWords() {
        // This file contains the reserved words for PostgreSQL databases
                // This file contains the reserved words for PostgreSQL databases
        // http://www.postgresql.org/docs/current/static/sql-keywords-appendix.html
        $reserved_words = array (
            'all', 'and', 'any', 'array', 'as', 'asc'
        );
        return $reserved_words;
    }
}
