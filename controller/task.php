<?php

require_once ('db.php');
require_once ('../model/Response.php');
require_once ('../model/Task.php');

try {

    $writeDB = DB::connectWriteDB();
    $readDB = DB::connectReadDB();

} catch (PDOException $e){
    // logging error message to standard php error log file
    error_log("Connection error - ".$e, 0);

    $response = new Response();
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->addMessage('Database connection error');
    $response->send();
    exit();
}
// if task id provided
if(array_key_exists("taskid", $_GET)){
    // init vars
    $taskArray = [];
    $tasksArray = [];
    $returnData = [];

    $taskId = $_GET['taskid'];

    if($taskId == '' || !is_numeric($taskId)){
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("Task ID cannot be blank or must be numeric");
        $response->send();
        exit();
    }

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
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage("Task not found");
                $response->send();
                exit();
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
            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->toCache(true);
            $response->setData($returnData);
            $response->send();
            exit();

        }
        catch (TaskException $e){
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage($e->getMessage());
            $response->send();
            exit();

        }
        catch (PDOException $e){
            // logging error message to standard php error log file
            error_log("Database query error - ".$e, 0);

            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage('Faild to get task');
            $response->send();
            exit();
        }
    }
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
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage('Task not found');
                $response->send();
                exit();
            }

            // on success build and send response
            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->addMessage('Task deleted');
            $response->send();
            exit();

        } catch (PDOException $e){
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage('Faild to delete task');
            $response->send();
            exit();
        }

    }
    else if($_SERVER['REQUEST_METHOD'] === 'PATCH'){}
    else {
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage("Request method not allowed - ".$_SERVER['REQUEST_METHOD']);
        $response->send();
        exit();
    }

}
// setting completed status of a task
else if (array_key_exists('completed', $_GET)){
    $completed = $_GET['completed'];
    // if invalid parameter
    if($completed !== 'Y' && $completed !== 'N'){
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("Completed filter must be Y or N");
        $response->send();
        exit();
    }

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
            }

            $returnData['rows_returned'] = $rowCount;
            $returnData['tasks'] = $taskArray;

            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->toCache(true);
            $response->setData($returnData);
            $response->send();
            exit();
        }
        catch (TaskException $e){
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage($e->getMessage());
            $response->send();
            exit();
        }
        catch (PDOException $e){
            error_log("Database query error - ".$e, 0);
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Faild to get tasks");
            $response->send();
            exit();
        }
    }
    // invalid method
    else {
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage("Request method not allowed - ".$_SERVER['REQUEST_METHOD']);
        $response->send();
        exit();
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
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage('Page number cannot be blank and must be numeric - '.$page);
            $response->send();
            exit();
        }

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

            // handling page errors
            if($page > $numOfPages || $page == 0){
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage('Page not found');
                $response->send();
                exit();
            }

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

            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->toCache(true);
            $response->setData($returnData);
            $response->send();
            exit();

        } catch (TaskException $ex){
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit();
        } catch (PDOException $ex) {
            error_log("Database query error - ".$ex, 0);
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage('Failed to get tasks' );
            $response->send();
            exit();
        } // try catch
    }
    // if not GET method
    else {
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage('Request method not allowed -'.$_SERVER['REQUEST_METHOD']);
        $response->send();
        exit();
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

            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->toCache(true);
            $response->setData($returnData);
            $response->send();
            exit();
        } catch (PDOException $ex){
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit();
        } catch (TaskException $ex){
            error_log("Database query error - ". $ex, 0);
            $response = new Response();
            $response->setHttpStatusCode(404);
            $response->setSuccess(false);
            $response->addMessage("Failed to get tasks");
            $response->send();
            exit();

        }
    }
    // if posting a new task
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST'){


    }
    // if invalid request method
    else {
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage('Request method not allowed - '. $_SERVER['REQUEST_METHOD']);
        $response->send();
        exit();
    } // end if else GET / POST




}
// on invalid url
else {
    $response = new Response();
    $response->setHttpStatusCode(404);
    $response->setSuccess(false);
    $response->addMessage('Endpoint not found');
    $response->send();
    exit();
} // end if else if...