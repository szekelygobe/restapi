<?php
require_once ('db.php');
require_once ('../config/constants.php');
require_once ('../model/Response.php');
require_once ('../model/Task.php');
require_once ('../utils/debug_functions.php');
require_once ('../languages/lng_'.CONST_DEFAULT_LANGUAGE.'.php');

global $language_array;

// DB connection
try {
    // connecting to databases
    $writeDB = DB::connectWriteDB();
    $readDB = DB::connectReadDB();

} catch (PDOException $e){
    // logging error message to standard php error log file
    error_log($language_array['LNG_DB_CONNECTION_ERROR'].' - '.$e, 0);
    // build and return error response
    Response::returnErrorResponse(500, [$language_array['LNG_DB_CONNECTION_ERROR']]);
}

// if task id provided
if(array_key_exists("taskid", $_GET)){
    // init vars
    $taskArray = [];
    $tasksArray = [];
    $returnData = [];

    $taskId = $_GET['taskid'];

    // on taskId error
    if($taskId == '' || !is_numeric($taskId)){
        // build and return error response
        Response::returnErrorResponse(400, [$language_array['LNG_MISSING_TASK_ID']]);
    }

    // handling GET request
    if($_SERVER['REQUEST_METHOD'] === 'GET')
    {
        try {
            // getting task data from db
            $dbData = DB::requestDBTask( $readDB, ['id'=>$taskId]);
            // if no task found
            if($dbData['rows_returned'] === 0){
                // build and return error response
                Response::returnErrorResponse(404, [$language_array['LNG_TASK_NOT_FOUND']]);
            } // row count
            // building and returning response
            Response::returnSuccessResponse(200, $dbData);
        }
        catch (TaskException $e){
            // build and return error response
            Response::returnErrorResponse(500, [$e->getMessage()]);
        }
        catch (PDOException $e){
            // logging error message to standard php error log file
            error_log($language_array['LNG_DB_QUERY_ERROR'].' - '.$e, 0);
            // build and return error response
            Response::returnErrorResponse(500, [$language_array['LNG_FAILED_TO_GET_TASK']]);
        }
    }
    // handling delete request
    else if($_SERVER['REQUEST_METHOD'] === 'DELETE'){
        try {
            // deleting task from DB
            $taskDelete = DB::deleteTableRow($writeDB, TBL_TASKS, ['id'=>$taskId]);

            // if deletion failed
            if($taskDelete === 0){
                // build and return error response
                Response::returnErrorResponse(404, [$language_array['LNG_TASK_NOT_FOUND']]);
            } // if no task fund

            // building and returning response
            Response::returnSuccessResponse(200, null, $language_array['LNG_TASK_DELETED']);

        } catch (PDOException $e){
            // build and return error response
            Response::returnErrorResponse(500, [$language_array['LNG_FAILED_TO_DELETE_TASK']]);
        } // end try catch

    }
    // handling update requests
    else if($_SERVER['REQUEST_METHOD'] === 'PATCH'){
        try {
            // if not valid json header
            if(isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] !== "application/json"){
                Response::returnErrorResponse(400, [$language_array['LNG_HEADER_NOT_JSON']]);
            }
            // passed data
            $rawPATCHData = file_get_contents('php://input');
            // trying to decode sent data
            if(!$jsonData = json_decode($rawPATCHData)){
                Response::returnErrorResponse(400, [$language_array['LNG_BODY_NOT_VALID_JSON']]);
            }

            // init var
            $queryFields         = "";
            // setting flags to determine fields to update
            $title_updated       = isset($jsonData->title);
            $description_updated = isset($jsonData->description);
            $deadline_updated    = isset($jsonData->deadline);
            $completed_updated   = isset($jsonData->completed);
            
            // building update query
            $queryFields .= $title_updated       ? " title = :p_title, " : null;
            $queryFields .= $description_updated ? " description = :p_description, " : null;
            $queryFields .= $deadline_updated    ? " deadline = STR_TO_DATE(:p_deadline, '".CONST_MYSQL_DATE_FORMAT."'), " : null;
            $queryFields .= $completed_updated   ? " completed = :p_completed, " : null;
            // trimming trailing comma
            $queryFields         = rtrim($queryFields, ', ');

            // if no field is provided for update
            if( $title_updated          === false &&
                $description_updated    === false &&
                $deadline_updated       === false &&
                $completed_updated      === false
            ){
                // build and return error response
                Response::returnErrorResponse(400, [$language_array['LNG_NO_TASK_FIELD']]);
            }

            // requesting existing DB task
            $originalTask = DB::requestDBTask($readDB, ['id'=>$taskId]);

            // if no task found
            if($originalTask['rows_returned'] === 0){
                Response::returnErrorResponse(404, [$language_array['LNG_NO_TASK_TO_UPDATE']]);
            }

            // the found task
            $task = Task::createFromArray($originalTask['tasks'][0]);

            // building update query
            $queryString = 'UPDATE '.TBL_TASKS.' SET '.$queryFields.' WHERE id = :p_id';
            $query = $writeDB->prepare($queryString);

            // if updating title
            if($title_updated === true){
                $task->setTitle($jsonData->title);
                $up_title = $task->getTitle();
                $query->bindParam(':p_title', $up_title);
            } // end if title
            // if updating description
            if($description_updated === true){
                $task->setDescription($jsonData->description);
                $up_description = $task->getDescription();
                $query->bindParam(':p_description', $up_description);
            } // end if description
            // if updating deadline
            if($deadline_updated === true){
                $task->setDeadline($jsonData->deadline);
                $up_deadline = $task->getDeadline();
                $query->bindParam(':p_deadline', $up_deadline);
            } // end deadline
            // if update completed
            if($completed_updated === true){
                $task->setCompleted($jsonData->completed);
                $up_completed = $task->getComplited();
                $query->bindParam(':p_completed', $up_completed);
            } // end if completed

            $query->bindParam(':p_id', $taskId, PDO::PARAM_INT);
            $query->execute();
            $rowCount = $query->rowCount();
            // if no task updated
            if($rowCount === 0){
                Response::returnErrorResponse(400, [$language_array['LNG_TASK_NOT_UPDATED']]);
            }

            // requesting existing DB task
            $updatedTask = DB::requestDBTask($writeDB, ['id'=>$taskId]);
            $returnData['rows_returned'] = $rowCount;
            $returnData['tasks'] = $updatedTask['tasks'];

            // return success response
            Response::returnSuccessResponse(200, $returnData, $language_array['LNG_TASK_UPDATED']);
        }
        catch (TaskException $ex){
            // build and sed error response
            Response::returnErrorResponse(400, [$ex->getMessage()]);
        }
        catch (PDOException $ex){
            // logging error to php error logs
            error_log($language_array['LNG_DB_QUERY_ERROR'].' - '.$ex, 0);
            // build and sed error response
            Response::returnErrorResponse(500, [$language_array['LNG_TASK_UPDATED_FAILED']]
            );
        } // end try catch
    }
    // on unsupported request
    else {
        // build and return error response
        Response::returnErrorResponse(405, [$language_array['LNG_REQUEST_METHOD_ERROR'].' - ' . $_SERVER['REQUEST_METHOD']]);
    } // end if else verbs

}
// setting completed status of a task
else if (array_key_exists('completed', $_GET)){
    // init variable
    $taskArray = [];
    $completed = $_GET['completed'];
    // if invalid parameter
    if($completed !== 'Y' && $completed !== 'N'){
        // build and return error response
        Response::returnErrorResponse(400, [$language_array['LNG_TASK_COMPLETED_ERROR'].' - '.$completed]);
    } // invalid completed flag

    // on completed GET request
    if($_SERVER['REQUEST_METHOD'] === 'GET') {
        try {
            // getting task data from db
            $dbData = DB::requestDBTask($readDB, ['completed'=>$completed]);
            // building and returning success response
            Response::returnSuccessResponse(200, $dbData);
        }
        catch (TaskException $e){
            // build and return error response
            Response::returnErrorResponse(500, [$e->getMessage()]);
        }
        catch (PDOException $e){
            error_log($language_array['LNG_DB_QUERY_ERROR'].' - '.$e, 0);
            // build and return error response
            Response::returnErrorResponse(500, [$language_array['LNG_FAILED_TO_GET_TASK']]);
        } // end try catch
    }
    // invalid method
    else {
        // build and return error response
        Response::returnErrorResponse(405, [$language_array['LNG_REQUEST_METHOD_ERROR'].' - '. $_SERVER['REQUEST_METHOD']]);
    } // if else GET

}
// if tasks with pagination
else if (array_key_exists('page', $_GET)) {
    // pagination only on GET request method
    if($_SERVER['REQUEST_METHOD'] === 'GET'){
        $page = $_GET['page'];
        $limitPerPage = 2;
        // error handling ofr page number
        if($page == '' || !is_numeric($page)){
            // build and return error response
            Response::returnErrorResponse(400, [$language_array['LNG_PAGE_NUMBER_ERROR'].' - '.$page]);
        } // if invalid page number

        try {
            // counting tasks in DB
            $nrTasks    = DB::requestDBData($readDB, TBL_TASKS, "count(id) as totalNoOfTasks", [1=>1]);
            $tasksCount = intval($nrTasks['data'][0]['totalNoOfTasks']);
            // calculating number of pages we need (rounded up), minimum of 1 page
            $numOfPages = $tasksCount > 0
                ? ceil($tasksCount / $limitPerPage)
                : 1;

            // handling page range errors
            if($page > $numOfPages || $page == 0){
                // build and return error response
                Response::returnErrorResponse(400, [$language_array['LNG_PAGE_NOT_FOUND'].' - '. $page]);
            } // if invalid page range

            // calculating tasks to return
            $offset = $page == 1 ? 0 : $limitPerPage*($page-1);

            $dbData = DB::requestDBTask($readDB,[1=>1], " LIMIT $limitPerPage OFFSET $offset");

            $returnData = [];
            $returnData['rows_returned'] = $dbData['rows_returned'];
            $returnData['total_rows'] = $tasksCount;
            $returnData['total_pages'] = $numOfPages;
            // returning pagination info
            $page < $numOfPages ? $returnData['has_next_page'] = true : $returnData['has_next_page'] =  false;
            $page > 1 ? $returnData['has_previous_page'] = true : $returnData['has_previous_page'] =  false;
            $returnData['tasks'] = $dbData['tasks'];

            // build and return success response
            Response::returnSuccessResponse(200, $returnData);

        } catch (TaskException $ex){
            // build and return error response
            Response::returnErrorResponse(500, [$ex->getMessage()]);
        } catch (PDOException $ex) {
            error_log($language_array['LNG_DB_QUERY_ERROR'].' - '.$ex, 0);
            // build and return error response
            Response::returnErrorResponse(500, [$language_array['LNG_FAILED_TO_GET_TASK']]);
        } // try catch
    }
    // if not GET method
    else {
        // build and return error response
        Response::returnErrorResponse(405, [$language_array['LNG_REQUEST_METHOD_ERROR'].' - '.$_SERVER['REQUEST_METHOD']]);
    } // end if not GET method
}
// if $_GET is empty for all tasks
else if (empty($_GET)){
    // if requesting list of tasks
    if($_SERVER['REQUEST_METHOD'] === 'GET'){
        try {

            // getting task data from db
            $dbData = DB::requestDBTask( $readDB, [1=>1]);

            // build and return success response
            Response::returnSuccessResponse(200, $dbData);

        } catch (PDOException $ex){
            // build and return error response
            Response::returnErrorResponse(500, [$ex->getMessage()]);
        } catch (TaskException $ex){
            error_log($language_array['LNG_DB_QUERY_ERROR'].' - '. $ex, 0);
            // build and return error response
            Response::returnErrorResponse(404, [$language_array['LNG_FAILED_TO_GET_TASK']]);
        } // end try catch
    }
    // if posting a new task
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST'){
        // insert new task into DB
        try {
            // init var
            $returnMessage = [];
            $taskArray     = [];
            $returnData    = [];

            // check if valid json data
            if(isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] !== 'application/json'){
                // build and return error response
                Response::returnErrorResponse(400, [$language_array['LNG_HEADER_NOT_JSON']]);
            } // if json sent

            // getting raw posted data into variable
            $rawPOSTData = file_get_contents('php://input');

            // if valid json data and decoded successfully
            if(!$jsonData = json_decode($rawPOSTData)){
                // build and return error response
                Response::returnErrorResponse(400, [$language_array['LNG_BODY_NOT_VALID_JSON']]);
            } // end if valid json

            // checking for mandatory fields
            if( (!isset($jsonData->title) || !strlen($jsonData->title)) ||
                (!isset($jsonData->completed) || strlen($jsonData->completed) !== 1)
            ){
                // adding error message if missing required field
                !isset($jsonData->title) ? $returnMessage[] = $language_array['LNG_TASK_TITLE_MANDATORY'] : null;
                !isset($jsonData->completed) ? $returnMessage[] = $language_array['LNG_TASK_COMPLETED_MANDATORY'] : null;
                // building and returning error response
                Response::returnErrorResponse(400, $returnMessage);
            } // end if missing fields

            // building new task object and validating data
            $newTask = new Task(
                null,
                $jsonData->title,
                isset($jsonData->description) ? $jsonData->description : null,
                isset($jsonData->deadline) ? $jsonData->deadline : null,
                $jsonData->completed
            );  // new task

            // building values to insert
            $insertValues = [
              'title'       => $newTask->getTitle(),
              'description' => $newTask->getDescription(),
              'deadline'    => $newTask->getDeadline(),
              'completed'   => $newTask->getComplited()
            ];
            // inserting task into DB
            $taskInsert = DB::insertDB($writeDB, TBL_TASKS, $insertValues);
            // if task not inserted return error
            if((int)$taskInsert === 0){
                Response::returnErrorResponse(500, [$language_array['LNG_TASK_CREATION_FAILED']]);
            }

            // getting newly inserted task data
            $dbData = DB::requestDBTask($writeDB, ['id'=>$taskInsert]);

            // if task not found
            if($dbData['rows_returned'] === 0){
                // build and return error response
                Response::returnErrorResponse(500, [$language_array['LNG_TASK_C_RETURNED_FAILED'].' - '. $taskInsert]);
            } // row count

            // building and returning response
            Response::returnSuccessResponse(201, $dbData, $language_array['LNG_TASK_CREATED'], false);
        }
        catch (TaskException $ex){
            // build and return error response
            Response::returnErrorResponse(400, [$ex->getMessage()]);
        }
        catch (PDOException $ex){
            error_log($language_array['LNG_DB_QUERY_ERROR'].' - '.$ex, 0);
            // build and return error response
            Response::returnErrorResponse(500, [$language_array['LNG_TASK_INSERT_ERROR']]
            );
        } // end try catch
    }
    // if invalid request method
    else {
        // build and return error response
        Response::returnErrorResponse(405, [$language_array['LNG_REQUEST_METHOD_ERROR'].' - '. $_SERVER['REQUEST_METHOD']]);
    } // end if else GET / POST
}
// on invalid url
else {
    // build and return error response
    Response::returnErrorResponse(404, [$language_array['LNG_ENDPOINT_ERROR']]);
} // end if else if...