<?php

require_once ('db.php');
require_once ('../model/Response.php');
require_once ('../model/Task.php');
require_once ('../debug_functions.php');

// DB connection
try {
    // connecting to databases
    $writeDB = DB::connectWriteDB();
    $readDB = DB::connectReadDB();

} catch (PDOException $e){
    // logging error message to standard php error log file
    error_log("Connection error - ".$e, 0);
    // build and return error response
    returnErrorResponse(500, ["Database connection error"]);
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
        returnErrorResponse(400, ["Task ID cannot be blank or must be numeric"]);
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
                returnErrorResponse(404, ["Task not found"]);
            } // row count
            // building and returning response
            returnSuccessResponse(200, $dbData);
        }
        catch (TaskException $e){
            // build and return error response
            returnErrorResponse(500, [$e->getMessage()]);
        }
        catch (PDOException $e){
            // logging error message to standard php error log file
            error_log("Database query error - ".$e, 0);
            // build and return error response
            returnErrorResponse(500, ["Failed to get task"]);
        }
    }
    // handling delete request
    else if($_SERVER['REQUEST_METHOD'] === 'DELETE'){
        try {
            // deleting task from DB
            $taskDelete = DB::deleteTableRow($writeDB, 'tbltasks', ['id'=>$taskId]);

            // if deletion failed
            if($taskDelete === 0){
                // build and return error response
                returnErrorResponse(404, ["Task not found"]);
            } // if no task fund

            // building and returning response
            returnSuccessResponse(200, null, "Task deleted");

        } catch (PDOException $e){
            // build and return error response
            returnErrorResponse(500, ["Failed to delete task"]);
        } // end try catch

    }
    // handling update requests
    else if($_SERVER['REQUEST_METHOD'] === 'PATCH'){
        try {
            // if not valid json header
            if(isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] !== "application/json"){
                returnErrorResponse(400, ["Content type header not set to JSON"]);
            }
            // passed data
            $rawPATCHData = file_get_contents('php://input');
            // trying to decode sent data
            if(!$jsonData = json_decode($rawPATCHData)){
                returnErrorResponse(400, ["Request body is not valid JSON"]);
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
            $queryFields .= $deadline_updated    ? " deadline = STR_TO_DATE(:p_deadline, '%d/%m/%Y %H:%i'), " : null;
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
                returnErrorResponse(400, ["No task fields provided"]);
            }

            // requesting existing DB task
            $originalTask = DB::requestDBTask($readDB, ['id'=>$taskId]);

            // if no task found
            if($originalTask['rows_returned'] === 0){
                returnErrorResponse(404, ["No task found to update"]);
            }

            // the found task
            $task = Task::createFromArray($originalTask['tasks'][0]);

            // building update query
            $queryString = 'UPDATE tbltasks SET '.$queryFields.' WHERE id = :p_id';
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
                returnErrorResponse(400, ["Task not updated"]);
            }

            // requesting existing DB task
            $updatedTask = DB::requestDBTask($writeDB, ['id'=>$taskId]);
            $returnData['rows_returned'] = $rowCount;
            $returnData['tasks'] = $updatedTask['tasks'];

            // return success response
            returnSuccessResponse(200, $returnData, "Task updated");
        }
        catch (TaskException $ex){
            // build and sed error response
            returnErrorResponse(400, [$ex->getMessage()]);
        }
        catch (PDOException $ex){
            // logging error to php error logs
            error_log("Database query error - ".$ex, 0);
            // build and sed error response
            returnErrorResponse(500, ["Failed to update task - check your data for errors - ".$ex->getMessage()]);
        } // end try catch
    }
    // on unsupported request
    else {
        // build and return error response
        returnErrorResponse(405, ["Request method not allowed - ".$_SERVER['REQUEST_METHOD']]);
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
        returnErrorResponse(400, ["Completed filter must be Y or N - ".$completed]);
    } // invalid completed flag

    // on completed GET request
    if($_SERVER['REQUEST_METHOD'] === 'GET') {
        try {
            // getting task data from db
            $dbData = DB::requestDBTask($readDB, ['completed'=>$completed]);
            // building and returning success response
            returnSuccessResponse(200, $dbData);
        }
        catch (TaskException $e){
            // build and return error response
            returnErrorResponse(500, [$e->getMessage()]);
        }
        catch (PDOException $e){
            error_log("Database query error - ".$e, 0);
            // build and return error response
            returnErrorResponse(500, ["Failed to get tasks"]);
        } // end try catch
    }
    // invalid method
    else {
        // build and return error response
        returnErrorResponse(405, ["Request method not allowed - ".$_SERVER['REQUEST_METHOD']]);
    }

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
            returnErrorResponse(400, ["Page number cannot be blank and must be numeric - ".$page]);
        } // if invalid page number

        try {
            // counting tasks in DB
            $query = $readDB->prepare('SELECT count(id) as totalNoOfTasks from tbltasks');
            $query->execute();
            $row = $query->fetch(PDO::FETCH_ASSOC);
            $tasksCount = intval($row['totalNoOfTasks']);
            // calculating number of pages we need (rounded up), minimum of 1 page
            $numOfPages = $tasksCount > 0
                ? ceil($tasksCount / $limitPerPage)
                : 1;

            // handling page range errors
            if($page > $numOfPages || $page == 0){
                // build and return error response
                returnErrorResponse(400, ["Page not found - ".$page]);
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
            returnSuccessResponse(200, $returnData);

        } catch (TaskException $ex){
            // build and return error response
            returnErrorResponse(500, [$ex->getMessage()]);
        } catch (PDOException $ex) {
            error_log("Database query error - ".$ex, 0);
            // build and return error response
            returnErrorResponse(500, ["Failed to get tasks"]);
        } // try catch
    }
    // if not GET method
    else {
        // build and return error response
        returnErrorResponse(405, ["Request method not allowed - ".$_SERVER['REQUEST_METHOD']]);
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
            returnSuccessResponse(200, $dbData);

        } catch (PDOException $ex){
            // build and return error response
            returnErrorResponse(500, [$ex->getMessage()]);
        } catch (TaskException $ex){
            error_log("Database query error - ". $ex, 0);
            // build and return error response
            returnErrorResponse(404, ["Failed to get tasks"]);
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
                returnErrorResponse(400, ["Content type header is not set to JSON"]);
            } // if json sent

            // getting raw posted data into variable
            $rawPOSTData = file_get_contents('php://input');

            // if valid json data and decoded successfully
            if(!$jsonData = json_decode($rawPOSTData)){
                // build and return error response
                returnErrorResponse(400, ["Request body is not valid JSON"]);
            } // end if valid json

            // checking for mandatory fields
            if( (!isset($jsonData->title) || !strlen($jsonData->title)) ||
                (!isset($jsonData->completed) || strlen($jsonData->completed) !== 1)
            ){
                // adding error message if missing required field
                !isset($jsonData->title) ? $returnMessage[] = "Title field is mandatory and must be provided" : null;
                !isset($jsonData->completed) ? $returnMessage[] = "Completed field is mandatory and must be provided" : null;
                // building and returning error response
                returnErrorResponse(400, $returnMessage);
            } // end if missing fields

            // building new task object and validating data
            $newTask = new Task(
                null,
                $jsonData->title,
                isset($jsonData->description) ? $jsonData->description : null,
                isset($jsonData->deadline) ? $jsonData->deadline : null,
                $jsonData->completed
            );  // new task

            // extracting data from task object after validation
            $title       = $newTask->getTitle();
            $description = $newTask->getDescription();
            $deadline    = $newTask->getDeadline();
            $completed   = $newTask->getComplited();

            $query = $writeDB->prepare( '
                INSERT into tbltasks 
                    ( title, description, deadline, completed )
                VALUES
                    ( :p_title, :p_description, STR_TO_DATE(:p_deadline, \'%d/%m/%Y %H:%i\'), :p_completed)
            ');
            $query->bindParam(':p_title', $title);
            $query->bindParam(':p_description', $description);
            $query->bindParam(':p_deadline', $deadline);
            $query->bindParam(':p_completed', $completed);
            $query->execute();
            $rowCount = $query->rowCount();

            // if no row effected return error
            if($rowCount === 0){
                returnErrorResponse(500,["Failed to create task"]);
            }

            // getting last inserted task's ID
            $lastTaskID = $writeDB->lastInsertId();
            // getting newly inserted task data
            $dbData = DB::requestDBTask($writeDB, ['id'=>$lastTaskID]);

            // if task not found
            if($dbData['rows_returned'] === 0){
                // build and return error response
                returnErrorResponse(500, ["Failed to return task after creation - id:".$lastTaskID]);
            } // row count

            // building and returning response
            returnSuccessResponse(201, $dbData, "Task created", false);
        }
        catch (TaskException $ex){
            // build and return error response
            returnErrorResponse(400, [$ex->getMessage()]);
        }
        catch (PDOException $ex){
            error_log("Database query error - ".$ex, 0);
            // build and return error response
            returnErrorResponse(500, ["Failed to insert task into database - check submitted data for errors"]);
        } // end try catch
    }
    // if invalid request method
    else {
        // build and return error response
        returnErrorResponse(405, ["Request method not allowed - ". $_SERVER['REQUEST_METHOD']]);
    } // end if else GET / POST
}
// on invalid url
else {
    // build and return error response
    returnErrorResponse(404, ["Endpoint not found"]);
} // end if else if...



function returnSuccessResponse(
    int $p_code,
    array $p_return_data = null,
    string $p_message = null,
    bool $p_cache = true
){
    // building and returning response
    $response = new Response();
    $response->setHttpStatusCode($p_code);
    $response->setSuccess(true);

    // cache response if needed
    $p_cache ? $response->toCache(true) : null;
    // returning data if provided
    is_array($p_return_data) && !empty($p_return_data)
        ?  $response->setData($p_return_data)
        : null;
    // returning message if provided
    $p_message && strlen($p_message)
        ? $response->addMessage($p_message)
        : null;

    $response->send();
    exit();
} // end func err message

function returnErrorResponse(int $p_err_code, array $p_message){
    $response = new Response();
    $response->setHttpStatusCode($p_err_code);
    $response->setSuccess(false);
    // adding one or multiple messages
    foreach ($p_message as $message){
        $response->addMessage($message);
    } // foreach
    $response->send();
    exit();
} // end func err message