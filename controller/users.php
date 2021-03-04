<?php
require_once ('db.php');
require_once ('../config/constants.php');
require_once ('../model/Response.php');

// connecting to DB
try {
    $writeDB = DB::connectWriteDB();
}
catch ( PDOException $ex){
    error_log("Connection error - ".$ex, 0);
    Response::returnErrorResponse(500, ["Unable to connect to DB"]);
} // end try

// checking for method
if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    Response::returnErrorResponse(405, ["Request not allowed"]);
} // if not POST

// checking for content header
if(isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] !== 'application/json'){
    Response::returnErrorResponse(400, ["Content type header not set to JSON"]);
} // if not json

// getting sent data
$rawPostData = file_get_contents('php://input');

// if invalid json
if(!$jsonData = json_decode($rawPostData)){
    Response::returnErrorResponse(400, ["Request body is not valid JSON"]);
} // if not valid JSON

// checking if data supplied
if(!isset($jsonData->fullname) || !isset($jsonData->username) || !isset($jsonData->password)){
    // init var
    $message = [];
    // building list of error messages
    !isset($jsonData->fullname) ? $message[] = "Full name not supplied" : null;
    !isset($jsonData->username) ? $message[] = "Username not supplied" : null;
    !isset($jsonData->password) ? $message[] = "Password not supplied" : null;
    // building and sending response
    Response::returnErrorResponse(400, $message);
} // if all data supplied

// checking valid supplied data
if( strlen($jsonData->fullname) < 1 ||
    strlen($jsonData->fullname) > 255 ||
    strlen($jsonData->username) < 1 ||
    strlen($jsonData->username) > 255 ||
    strlen($jsonData->password) < 1 ||
    strlen($jsonData->password) > 255
){
    // init var
    $message = [];
    // building list of error messages
    !strlen($jsonData->fullname) < 1 ? $message[] = "Full name cannot be blank" : null;
    !strlen($jsonData->fullname) > 255 ? $message[] = "Full name cannot be greater than 255 characters" : null;
    !strlen($jsonData->username) < 1 ? $message[] = "Username cannot be blank" : null;
    !strlen($jsonData->username) > 255 ? $message[] = "Username cannot be greater than 255 characters" : null;
    !strlen($jsonData->password) < 1 ? $message[] = "Password cannot be blank" : null;
    !strlen($jsonData->password) > 255 ? $message[] = "Password cannot be greater than 255 characters" : null;
    // building and sending response
    Response::returnErrorResponse(400, $message);
} // if valid data supplied

// init variables
$fullName = trim($jsonData->fullname);
$userName = trim($jsonData->username);
$password = $jsonData->password;

try {
    // check if username already taken
    $dbUser = DB::requestDBData(
        $writeDB,
        TBL_USERS,
        "id",
        ['username'=>$userName],
        " LIMIT 1 "
    );

    // if username already taken
    if($dbUser['rowCount'] !== 0){
        Response::returnErrorResponse(409, ["Username already taken"]);
    } // if taken

    // hashing password
    $hashed_password = password_hash($jsonData->password, PASSWORD_DEFAULT);

    // inserting new user into DB
    $insertedUser = DB::insertDB(
        $writeDB,
        TBL_USERS,
        [ 'fullname'=>$fullName,
          'username'=>$userName,
          'password'=>$hashed_password
        ]
    ); // end insert

    // if error inserting user
    if((int)$insertedUser === 0 ){
        Response::returnErrorResponse(500, ["There was an issue creating a user account - please try again"]);
    }

    // returning inserted user data
    $newUser = DB::requestDBData(
        $writeDB,
        TBL_USERS,
        "id AS user_id, fullname, username",
        ['id'=>$insertedUser],
        " LIMIT 1 "
    );

    // building and returning created user
    Response::returnSuccessResponse(201, $newUser['data'][0], "User created");
}
catch (PDOException $ex){
    error_log("Database query error - ".$ex, 0);
    Response::returnErrorResponse(500, ["There was an issue creating a user account - please try again - ".$ex]);
} // try