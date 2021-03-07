<?php declare(strict_types=1);

require_once('../utils/debug_functions.php');

class DB {
    private static $writeDBConnection;
    private static $readDBConnection;



    /**
     * Connecting to write database
     * @access public
     * @param -
     * @return PDO - connection reference
     */
    public static function connectWriteDB(): PDO
    {
        if(self::$writeDBConnection === null){
            self::$writeDBConnection = new PDO('mysql:host=localhost;dbname=tasksdb;charset=utf8', 'root', 'bubbancs');
            self::$writeDBConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$writeDBConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        }
        return self::$writeDBConnection;
    } // end write db



    /**
     * Connecting to read database
     * @access public
     * @param -
     * @return PDO - connection reference
     */
    public static function connectReadDB(): PDO
    {
        if(self::$readDBConnection === null){
            self::$readDBConnection = new PDO('mysql:host=localhost;dbname=tasksdb;charset=utf8', 'root', 'bubbancs');
            self::$readDBConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$readDBConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        }
        return self::$readDBConnection;
    } // end read db



    /**
     * Runs a query on the database
     * @access public
     * @param object $p_db_connection   - the db connection to query
     * @param string $p_query           - query
     * @param array $p_params           - the PDO parameters for the prepare statement
     * @param bool|null $p_print        - flag to print built query
     * @return array - array of [rowCount] and [data]
     */
    public static function dbQuery(
        object $p_db_connection,
        string $p_query,
        array $p_params,
        bool $p_print=null):array
    {
        // printing parameter query on demand
        $p_print ? psql($p_query, $p_params,' dbQuery query ',0,1) : null;

        // init vars
        $result = [];
        // trying to run query
        try {
            // setting parameter
            $p_db_connection->setAttribute( PDO::ATTR_EMULATE_PREPARES, false );
            $p_db_connection->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            // preparing query
            $PDO_stmt = $p_db_connection->prepare($p_query);
            // inserting variables
            // $PDO_stmt->execute($prepare_params);
            $PDO_stmt->execute($p_params);
            // nr rows in result
            $result['rowCount'] = $PDO_stmt->rowCount();
            // getting result
            $result['data'] = $PDO_stmt->fetchAll(PDO::FETCH_ASSOC) ;

            return $result;
        } catch (PDOException $ex){
            throw $ex;
        } // try catch
    } // end func. query



    /**
     * Runs a insert query on the database
     * @access public
     * @param object $p_db          - the database to insert (database connection object)
     * @param string $p_table       - the name of the table to insert to
     * @param array $p_values       - array of the values to insert [the key is the column, the value is the value]
     * @param bool|null $p_print    - flag to print built query
     * @return int - last insert id
     */
    public static function insertDB(
        object $p_db,
        string $p_table,
        array $p_values,
        bool $p_print=null): int
    {

        // init vars
        $fields       = '';
        $placeholders = '';
        $params       = [];

        // looping through array
        foreach ($p_values as $v_key => $v_val) {
            $fields .= $v_key.', ';
            $placeholders .= ':p_'.$v_key.', ';
            $params[':p_'.$v_key] = $v_val;
        }// end foreach
        // removing trailing ', '
        $fields = rtrim($fields, ', ');
        $placeholders = rtrim($placeholders, ', ');

        // building insert query
        $query = ' INSERT INTO '.$p_table.' ('.$fields.') VALUES ('.$placeholders.')';

        // print built query on demand
        $p_print ? psql($query, $params,' insertDB query ',0,  1) : null;

        // building and executing PDO query
        try {
            // setting parameter
            $p_db->setAttribute( PDO::ATTR_EMULATE_PREPARES, false );
            // preparing query
            $PDO_stmt = $p_db->prepare($query);
            // inserting variables
            $PDO_stmt->execute($params);
            // returning the id of the inserted row
            return (int)$p_db->lastInsertId();
            // if error
        } catch (PDOException $ex) {
            throw $ex;
        } // end try
    } // end func. insert query



    /**
     * Update DB row
     * @access public
     * @param object $p_db      - DB connection to update data in
     * @param string $p_table   - the table to update in
     * @param array $p_values   - the table column names and values to update in array format ['table_col']=>value
     * @param array $p_where    - the sql where parameters to identify the row to update, in array format ['table_col']=>value
     * @param bool $p_print     - flag to pring generated query
     * @return array - the affected row count and data
     */
    public static function updateDB(
        object $p_db,
        string $p_table,
        array $p_values,
        array $p_where,
        bool $p_print=null): array
    {
        // init var
        $custom_where = '';
        $values       = '';
        $param        = [];

        // looping through array
        foreach ($p_values as $v_key => $v_val) {
            // building SET part of query
            $values .= $v_key.'=:pv_'.$v_key.', ';
            // adding values to PDO param array
            $param[':pv_'.$v_key]=$v_val;
        }// end foreach
        // trimming tailing ', '
        $values = rtrim($values, ', ');

        // looping through WHERE array
        foreach ($p_where as $w_key => $w_val) {
            // building WHERE part of query
            $custom_where .= $w_key.'=:p_'.$w_key.' AND ';
            // adding values to PDO param array
            $param[':p_'.$w_key]=$w_val;
        } // end foreach
        // trimming trailing 'AND '
        $custom_where = rtrim($custom_where, 'AND ');

        // building query
        $query = ' UPDATE '.$p_table.' SET '.$values.' WHERE '.$custom_where;

        // display built query on demand
        $p_print ? psql($query, $param,' updateDB query ',0,1) : null;

        // trying to update
        try {
            return self::dbQuery($p_db, $query, $param);
        } catch (PDOException $ex){
            throw $ex;
        } // try catch
    } // end func update db



    /**
     * Select and return DB data
     * @access public
     * @param object $p_db          - the database connection
     * @param string $p_table       - the table to query
     * @param string $p_fields      - the fields to return
     * @param array $p_where        - array of where clause values [key will be the column name, value the matched value]
     * @param string|null $p_limit  - the limit part of query
     * @param bool|null $p_print    - flag to print built query
     * @return array - array of [rowCount] and [data]
     */
    public static function requestDBData(
        object $p_db,
        string $p_table,
        string $p_fields,
        array $p_where,
        string $p_limit = null,
        bool $p_print = null ): array
    {
        // init var
        $params       = [];
        $custom_where = '';

        // looping through array
        foreach ($p_where as $w_key => $w_val) {
            // building the where query
            $custom_where .= $w_key.' = :p_'.$w_key.' AND ';
            // adding PDO param to array
            $params[':p_'.$w_key] = $w_val;
        }// end foreach
        // trimming trailing AND
        $custom_where = rtrim($custom_where, ' AND ');
        // building query
        $query = 'SELECT '.$p_fields.' FROM '.$p_table.' WHERE '.$custom_where.' '.$p_limit;
        // print built query on request
        $p_print ? psql($query, $params,' requestDBData query ',0,1) : null;

        try {
            return self::dbQuery($p_db, $query, $params);
        } catch (PDOException $ex){
            throw  $ex;
        }
    } // end return



    /**
     * Search task data in DB
     * @access public
     * @param object $p_db          - the database connection
     * @param array $p_where        - array of where clause values [key will be the column name, value the matched value]
     * @param string|null $p_limit  - the limit part of query
     * @param bool|null $p_print    - flag to print built query
     * @return array - array of [rows_returned] and [tasks]
     */
    public static function requestDBTask(
        object $p_db,
        array $p_where,
        string $p_limit = null,
        bool $p_print = null ): array
    {
        // init vars
        $task               = null;
        $tasksArray         = [];
        $result             = [];

        try {
            $dbTasks = self::requestDBData(
                $p_db,
                'tbltasks',
                'id, userid, title, description, DATE_FORMAT(deadline, "'.CONST_MYSQL_DATE_FORMAT.'") as deadline, completed',
                $p_where,
                $p_limit,
                $p_print
            );
            // if tasks found
            if(count($dbTasks['data'])>0){
                foreach ($dbTasks['data'] as $task){
                    $task = new Task(
                        $task['id'],
                        $task['userid'],
                        $task['title'],
                        $task['description'],
                        $task['deadline'],
                        $task['completed']
                    );
                    $tasksArray[] = $task->returnTaskAsArray();
                } // end foreach
            } // end if

            $result['rows_returned'] = $dbTasks['rowCount'];
            $result['tasks']     = $tasksArray;
            return $result;
        } catch (PDOException $ex){
            throw  $ex;
        }
    } // end return



    /**
     * Delete a row from db table
     * @access public
     * @param object $p_db          - the database connection
     * @param string $p_table       - table name to delete from
     * @param array $p_where        - array of values to identify row, [key = field name] [value = field value]
     * @param bool|null $p_print    - flag to print built query
     * @return int - the affected row count
     */
    public static function deleteTableRow (
        object $p_db,
        string $p_table,
        array $p_where,
        bool $p_print = null): int
    {
        // init var
        $params       = [];
        $custom_where = '';

        // looping through array
        foreach ($p_where as $w_key => $w_val) {
            // building the where query
            $custom_where .= $w_key.' = :p_'.$w_key.' AND ';
            // adding PDO param to array
            $params[':p_'.$w_key] = $w_val;
        }// end foreach

        // trimming trailing AND
        $custom_where = rtrim($custom_where, ' AND ');
        // building query
        $query = 'DELETE FROM '.$p_table.' WHERE '.$custom_where.' ';
        // print built query on request
        $p_print ? psql($query, $params,' deleteTableRow query ',0,1) : null;

        try {
            $deletedRow = self::dbQuery($p_db, $query,$params);
            return $deletedRow['rowCount'];
        } catch (PDOException $ex){
            throw $ex;
        }
    } // end delete





    /**
     * Search for session
     * @access public
     * @param object $p_db   - the database connection
     * @param array $p_where - array of values to identify row, [key = field name] [value = field value]
     * @param bool $p_print  - flag to print built query
     * @return array - array of session data
     */
    public static function requestSession(
        object $p_db,
        array $p_where,
        bool $p_print = null): array
    {
        // init var
        $params       = [];
        $custom_where = '';

        // looping through array
        foreach ($p_where as $w_key => $w_val) {
            // if key contains .
            if(strpos($w_key, '.') !== false){
                // separating values
                $values = explode('.', $w_key);
                $placeholder = ':p_'.end($values);
            } else {
                $placeholder = ':p_'.$w_key;
            } // end if else .

            // building the where query
            $custom_where .= $w_key.' = '.$placeholder.' AND ';
            // adding PDO param to array
            $params[$placeholder] = $w_val;
        }// end foreach
        // trimming trailing 'AND '
        $custom_where = rtrim($custom_where, 'AND ');

        // building query
        $query = "
            SELECT s.id AS sessionid,
                   s.userid AS userid,
                   s.accesstoken,
                   s.accesstokenexpiry,
                   s.refreshtoken,
                   s.refreshtokenexpiry,
                   u.useractive,
                   u.loginattempts
            FROM ".TBL_SESSIONS." AS s,
                 ".TBL_USERS." AS u
            WHERE  u.id = s.userid AND ".$custom_where;

        // display built query if needed
        $p_print ? psql($query, $params,'db.php - 388',0,1) : null ;

        // running the query
        try {
            return self::dbQuery($p_db, $query,$params);
        }
        catch (PDOException $ex){
            throw $ex;
        } // try catch
    } // end user session



} // class