<?php
require_once ('db.php');
require_once ('../config/constants.php');
require_once ('../model/Response.php');
require_once ('../utils/debug_functions.php');
require_once ('../languages/lng_'.CONST_DEFAULT_LANGUAGE.'.php');

global $language_array;

// connecting to DB
try {
    $writeDB = DB::connectWriteDB();
}
catch ( PDOException $ex){
    error_log($language_array['LNG_DB_CONNECTION_ERROR'].' - '.$ex, 0);
    // build and return error response
    Response::returnErrorResponse(500, [$language_array['LNG_DB_CONNECTION_ERROR']]);
} // end try

// checking for method
if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    Response::returnErrorResponse(405, [$language_array['LNG_REQUEST_NOT_ALLOWED']]);
} // if not POST

// checking for content header
if(isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] !== 'application/json'){
    Response::returnErrorResponse(400, [$language_array['LNG_HEADER_NOT_JSON']]);
} // if not json

// getting sent data
$rawPostData = file_get_contents('php://input');

// if invalid json
if(!$jsonData = json_decode($rawPostData)){
    Response::returnErrorResponse(400, [$language_array['LNG_BODY_NOT_VALID_JSON']]);
} // if not valid JSON

// checking if data supplied
if(!isset($jsonData->fullname) || !isset($jsonData->username) || !isset($jsonData->password)){
    // init var
    $message = [];
    // building list of error messages
    !isset($jsonData->fullname) ? $message[] = $language_array['LNG_FULLNAME_NOT_SUPPLIED'] : null;
    !isset($jsonData->username) ? $message[] = $language_array['LNG_USERNAME_NOT_SUPPLIED'] : null;
    !isset($jsonData->password) ? $message[] = $language_array['LNG_PASSWORD_NOT_SUPPLIED'] : null;
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
    !strlen($jsonData->fullname) < 1 ? $message[] = $language_array['LNG_FULLNAME_BLANK_ERROR'] : null;
    !strlen($jsonData->fullname) > 255 ? $message[] = $language_array['LNG_FULLNAME_TO_LONG'] : null;
    !strlen($jsonData->username) < 1 ? $message[] = $language_array['LNG_USERNAME_BLANK_ERROR'] : null;
    !strlen($jsonData->username) > 255 ? $message[] = $language_array['LNG_USERNAME_TO_LONG'] : null;
    !strlen($jsonData->password) < 1 ? $message[] = $language_array['LNG_PASSWORD_BLANK_ERROR'] : null;
    !strlen($jsonData->password) > 255 ? $message[] = $language_array['LNG_PASSWORD_TO_LONG'] : null;
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
        Response::returnErrorResponse(409, [$language_array['LNG_USERNAME_TAKEN']]);
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
        Response::returnErrorResponse(500, [$language_array['LNG_USER_CREATION_ERROR']]);
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
    Response::returnSuccessResponse(201, $newUser['data'][0], $language_array['LNG_USER_CREATED']);
}
catch (PDOException $ex){
    error_log($language_array['LNG_DB_QUERY_ERROR'].' - '.$ex, 0);
    Response::returnErrorResponse(500, [$language_array['LNG_USER_CREATION_ERROR']]);
} // try