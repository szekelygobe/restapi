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

if(array_key_exists("taskid", $_GET)){
    // init vars
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
                WHERE id = :p_taskId
            ');
            $query->bindParam(':p_taskId', $taskId);
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

        } catch (TaskException $e){
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage($e->getMessage());
            $response->send();
            exit();
        } catch (PDOException $e){
            // logging error message to standard php error log file
            error_log("Database query error - ".$e, 0);

            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage('Faild to get task');
            $response->send();
            exit();
        }

    } else if($_SERVER['REQUEST_METHOD'] === 'DELETE'){
    } else if($_SERVER['REQUEST_METHOD'] === 'PATCH'){
    } else {
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage("Request method not allowed - ".$_SERVER['REQUEST_METHOD']);
        $response->send();
        exit();
    }



} // if