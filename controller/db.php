<?php declare(strict_types=1);

require_once ('../debug_functions.php');

class DB {
    private static $writeDBConnection;
    private static $readDBConnection;



    /**
     * Connecting to write database
     * @access public
     * @param none
     * @return connection reference
     */
    public static function connectWriteDB(){
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
     * @param none
     * @return connection reference
     */
    public static function connectReadDB(){
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
     * @param $p_db_connection  - the db connection to query
     * @param string $p_query   - query
     * @param array $p_params   - the PDO parameters for the prepare statement
     * @return array - array of [rowCount] and [data]
     */
    public static function dbQuery(object $p_db_connection, string $p_query, array $p_params):array
    {
        // init vars
        $result = [];

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
        }
    } // end func. query



    /**
     * Select and return DB data
     * @access public
     * @param $p_db                 - the database connection
     * @param string $p_table       - the table to query
     * @param string $p_fields      - the fields to return
     * @param array $p_where        - array of where clause values [key will be the column name, value the matched value]
     * @param string|null $p_limit  - the limit part of query
     * @return array - array of [rowCount] and [data]
     */
    public static function requestDBData(object $p_db, string $p_table, string $p_fields, array $p_where, string $p_limit = null): array
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

        // psql($query, $params,'db.php - 81',0,1);

        try {
            return self::dbQuery($p_db, $query, $params);
        } catch (PDOException $ex){
            throw  $ex;
        }
    } // end return



    /**
     * Search task data in DB
     * @access public
     * @param $p_db                 - the database connection
     * @param array $p_where        - array of where clause values [key will be the column name, value the matched value]
     * @param string|null $p_limit  - the limit part of query
     * @return array - array of [rows_returned] and [tasks]
     */
    public static function requestDBTask(object $p_db, array $p_where, string $p_limit = null): array
    {
        try {
            // init vars
            $task               = null;
            $tasksArray         = [];
            $result             = [];

            $dbTasks = self::requestDBData(
                $p_db,
                'tbltasks',
                'id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed',
                $p_where,
                $p_limit
            );
            // if tasks found
            if(count($dbTasks['data'])>0){
                foreach ($dbTasks['data'] as $task){
                    $task = new Task(
                        $task['id'],
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
     * @param $p_db             - the database connection
     * @param string $p_table   - table name to delete from
     * @param array $p_where    - array of values to identify deletable row, the key is the field name the value is the field value
     *         ex.: ['trademark_id'=>some_value]
     * @return array - array of [rowCount] and [data]
     */
    public static function deleteTableRow (object $p_db, string $p_table, array $p_where): int
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

        // delete trailing AND
        $del_where = rtrim($custom_where, ' AND ');
        // trimming trailing AND
        $custom_where = rtrim($custom_where, ' AND ');
        // building query
        $query = 'DELETE FROM '.$p_table.' WHERE '.$custom_where.' ';

        try {
            $deletedRow = self::dbQuery($p_db, $query,$params);
            // psql($query,$params,' DELETE ', 0, 1);

            return $deletedRow['rowCount'];
        } catch (PDOException $ex){
            throw $ex;
        }
    } // end delete



} // class