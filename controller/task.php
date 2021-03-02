<?php

require_once ('db.php');
require_once ('../model/Response.php');
require_once ('../model/Task.php');

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

    // on taskid error
    if($taskId == '' || !is_numeric($taskId)){
        // build and return error response
        returnErrorResponse(400, ["Task ID cannot be blank or must be numeric"]);
    }

    // handling GET request
    if($_SERVER['REQUEST_METHOD'] === 'GET')
    {
        try {
            $query = $readDB->prepare('
                SELECT 
                    id, 
                    title, 
                    description, 
                    DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, 
                    completed
                FROM tbltasks
                WHERE id = :p_taskId
            ');
            $query->bindParam(':p_taskId', $taskId, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            // if task not found
            if($rowCount === 0){
                // build and return error response
                returnErrorResponse(404, ["Task not found"]);
            } // row count

            // if task found
            while($row = $query->fetch(PDO::FETCH_ASSOC)){
                $task = new Task(
                    $row['id'],
                    $row['title'],
                    $row['description'],
                    $row['deadline'],
                    $row['completed']
                );
                $tasksArray[] = $task->returnTaskAsArray();
            } // while

            // building task array for returning
            $returnData['rows_returned'] = $rowCount;
            $returnData['tasks'] = $tasksArray;

            // building and returning response
            returnSuccessResponse(200, $returnData);
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
            $query = $writeDB->prepare('
                DELETE 
                FROM tbltasks 
                WHERE id = :p_taskId
            ');
            $query->bindParam(':p_taskId', $taskId, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();
            // if deletion failed
            if($rowCount === 0){
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
    else if($_SERVER['REQUEST_METHOD'] === 'PATCH'){}
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
            $query = $readDB->prepare('
                SELECT 
                    id, 
                    title, 
                    description, 
                    DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, 
                    completed
                FROM tbltasks
                WHERE  completed = :p_completed
            ');
            $query->bindParam(':p_completed', $completed, PDO::PARAM_STR);
            $query->execute();
            $rowCount = $query->rowCount();

            while($row = $query->fetch(PDO::FETCH_ASSOC)){
                $task = new Task(
                    $row['id'],
                    $row['title'],
                    $row['description'],
                    $row['deadline'],
                    $row['completed']
                );
                $taskArray[] = $task->returnTaskAsArray();
            } // end while

            $returnData['rows_returned'] = $rowCount;
            $returnData['tasks'] = $taskArray;

            // building and returning success response
            returnSuccessResponse(200, $returnData);

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

            $query = $readDB->prepare('
                SELECT 
                    id, 
                    title, 
                    description, 
                    DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, 
                    completed
                FROM tbltasks
                LIMIT :p_pagelimit offset :p_offset
            ');
            $query->bindParam(':p_pagelimit', $limitPerPage, PDO::PARAM_INT);
            $query->bindParam(':p_offset', $offset, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();
            $taskArray = [];

            while($row = $query->fetch(PDO::FETCH_ASSOC)){
                $task = new Task(
                    $row['id'],
                    $row['title'],
                    $row['description'],
                    $row['deadline'],
                    $row['completed']
                );
                $taskArray[] = $task->returnTaskAsArray();
            }

            $returnData = [];
            $returnData['rows_returned'] = $rowCount;
            $returnData['total_rows'] = $tasksCount;
            $returnData['total_pages'] = $numOfPages;
            // returning pagination info
            $page < $numOfPages ? $returnData['has_next_page'] = true : $returnData['has_next_page'] =  false;
            $page > 1 ? $returnData['has_previous_page'] = true : $returnData['has_previous_page'] =  false;

            $returnData['tasks'] = $taskArray;

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
            $query = $readDB->prepare('
                SELECT 
                    id, 
                    title, 
                    description, 
                    DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, 
                    completed
                FROM tbltasks
                WHERE  1
            ');
            $query->execute();
            $rowCount  = $query->rowCount();
            $taskArray = []; // reset array

            while($row = $query->fetch(PDO::FETCH_ASSOC)){
                $task = new Task(
                    $row['id'],
                    $row['title'],
                    $row['description'],
                    $row['deadline'],
                    $row['completed']
                );
                $taskArray[] = $task->returnTaskAsArray();
            }

            $returnData = [];
            $returnData['rows_returned'] = $rowCount;
            $returnData['tasks'] = $taskArray;

            // build and return success response
            returnSuccessResponse(200, $returnData);

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
            $query->bindParam(':p_title', $title, PDO::PARAM_STR);
            $query->bindParam(':p_description', $description, PDO::PARAM_STR);
            $query->bindParam(':p_deadline', $deadline, PDO::PARAM_STR);
            $query->bindParam(':p_completed', $completed, PDO::PARAM_STR);
            $query->execute();
            $rowCount = $query->rowCount();

            // if no row effected return error
            if($rowCount === 0){
                returnErrorResponse(500,"Failed to create task");
            }

            // getting last inserted task's ID
            $lastTaskID = $writeDB->lastInsertId();

            $query = $writeDB->prepare('
                SELECT 
                    id, 
                    title, 
                    description, 
                    DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, 
                    completed
                FROM tbltasks
                WHERE id = :p_taskID 
            ');
            $query->bindParam(':p_taskID', $lastTaskID);
            $query->execute();
            $rowCount = $query->rowCount();

            // if task not found
            if($rowCount === 0){
                // build and return error response
                returnErrorResponse(500, ["Failed to return task after creation - id:".$lastTaskID]);
            } // row count

            // if task found
            while($row = $query->fetch(PDO::FETCH_ASSOC)){
                $task = new Task(
                    $row['id'],
                    $row['title'],
                    $row['description'],
                    $row['deadline'],
                    $row['completed']
                );
                $taskArray[] = $task->returnTaskAsArray();
            } // while

            // building task array for returning
            $returnData['rows_returned'] = $rowCount;
            $returnData['tasks'] = $taskArray;

            // building and returning response
            returnSuccessResponse(201, $returnData, "Task created", false);
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
    $response->toCache(true);

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