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
catch (PDOException $ex){
    error_log($language_array['LNG_DB_CONNECTION_ERROR'].' - '.$ex, 0);
    Response::returnErrorResponse(500, [$language_array['LNG_DB_CONNECTION_ERROR']]);
}

if(array_key_exists("sessionid", $_GET)){

}
// creating new session
elseif (empty($_GET)){
    // if not a POST request method
    if($_SERVER['REQUEST_METHOD'] !==  "POST"){
        Response::returnErrorResponse(505, [$language_array['LNG_REQUEST_METHOD_ERROR']]);
    } // if wrong request method

    // delay bruteforce attacks
    sleep(1);

    // check for JSON header
    if(isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] !== 'application/json'){
        Response::returnErrorResponse(400, [$language_array['LNG_HEADER_NOT_JSON']]);
    } // if header check

    // getting posted data
    $rawPostedData = file_get_contents('php://input');
    // try to decode posted data
    if(!$jsonData = json_decode($rawPostedData)){
        Response::returnErrorResponse(400, [$language_array['LNG_BODY_NOT_VALID_JSON']]);
    } // decode json

    // check for missing data
    if(!isset($jsonData->username) || !isset($jsonData->password)){
        // init var
        $message = [];
        // checking for missing data
        !isset($jsonData->username) ? $message[] = $language_array['LNG_USERNAME_MISSING'] : null;
        !isset($jsonData->password) ? $message[] = $language_array['LNG_PASSWORD_MISSING'] : null;
        // build and return response
        Response::returnErrorResponse(400,$message);
    } // end if missing data

    // checking for valid data
    if( strlen($jsonData->username) < 1 || strlen($jsonData->username) > 255 ||
        strlen($jsonData->password) < 1 || strlen($jsonData->password) > 255
    ){
        // init var
        $message = [];
        // checking for valid supplied data
        strlen($jsonData->username) < 1     ? $message[] = $language_array['LNG_USERNAME_BLANK_ERROR'] : null;
        strlen($jsonData->username) > 255   ? $message[] = $language_array['LNG_USERNAME_TO_LONG'] : null;
        strlen($jsonData->password) < 1     ? $message[] = $language_array['LNG_PASSWORD_BLANK_ERROR'] : null;
        strlen($jsonData->password) > 255   ? $message[] = $language_array['LNG_PASSWORD_TO_LONG'] : null;
        // build and return response
        Response::returnErrorResponse(400, $message);
    } // if valid data

    // getting user data
    try {
        $username = $jsonData->username;
        $password = $jsonData->password;

        // getting user data
        $dbUser = DB::requestDBData(
            $writeDB,
            TBL_USERS,
            "id, fullname, username, password, useractive, loginattempts",
            ['username'=>$jsonData->username]
        );

        // if no user found
        if($dbUser['rowCount'] === 0){
            Response::returnErrorResponse(401,[$language_array['LNG_LOGIN_ERROR']]);
        } // end if no user

        // the found user data
        $userData = $dbUser['data'][0];

        $returned_id            = $userData['id'];
        $returned_fullname      = $userData['fullname'];
        $returned_username      = $userData['username'];
        $returned_password      = $userData['password'];
        $returned_useractive    = $userData['useractive'];
        $returned_loginattempts = $userData['loginattempts'];

        // check for inactive user account
        if($returned_useractive !== 'Y'){
            Response::returnErrorResponse(401, [$language_array['LNG_USER_ACCOUNT_INACTIVE']]);
        } // if inactive

        // check for login attempt limit
        if($returned_loginattempts >= CONST_LOGIN_ATTEMPT_LIMIT){
            Response::returnErrorResponse(401, [$language_array['LNG_USER_ACCOUNT_LOCKED_OUT']]);
        } // if login attempts

        // checking for matching password
        if(!password_verify($password, $returned_password)){
            // updating login failure count
            $updateTry = DB::dbQuery(
                $writeDB,
                ' UPDATE '.TBL_USERS.' SET loginattempts=loginattempts+1 WHERE id=:p_id',
                [':p_id' => $returned_id]
            ); // update
            // building and returning error response
            Response::returnErrorResponse(401, [$language_array['LNG_LOGIN_ERROR']]);
        } //if wrong password

        // generating unique tokens
        $accessToken    = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)).time());
        $refreshToken   = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)).time());

        // trying login update transaction
        try {
            // string transaction
            $writeDB->beginTransaction();
            // updating login failure count
            $updateTry = DB::updateDB(
                $writeDB,
                TBL_USERS,
                ['loginattempts'=> 0],
                ['id'=>$returned_id]
            ); // update

            // inserting new session into DB
            $insertedUser = DB::insertDB(
                $writeDB,
                TBL_SESSIONS,
                [ 'userid'              => $returned_id,
                  'accesstoken'         => $accessToken,
                  'accesstokenexpiry'   => date(CONST_PHP_DATE_FORMAT, strtotime("+".CONST_ACCESS_TOKEN_EXPIRY." sec")),
                  'refreshtoken'        => $refreshToken,
                  'refreshtokenexpiry'  => date(CONST_PHP_DATE_FORMAT, strtotime('+'.CONST_REFRESH_TOKEN_EXPIRY.' sec'))
                ]
            ); // end insert
            // commit and close transaction
            $writeDB->commit();

            // building return data
            $returnData                             = [];
            $returnData['session_id']               = $insertedUser;
            $returnData['access_token']             = $accessToken;
            $returnData['access_token_expires_is']  = CONST_ACCESS_TOKEN_EXPIRY;
            $returnData['refresh_token']            = $refreshToken;
            $returnData['refresh_token_expires_id'] = CONST_REFRESH_TOKEN_EXPIRY;

            // returning success result
            Response::returnSuccessResponse(201, $returnData);


            // vid 27



        }
        catch (PDOException $ex){
            // roll back updates if transaction failed
            $writeDB->rollBack();
            Response::returnErrorResponse(500, [$language_array['LNG_LOGIN_DB_ERROR'].$ex]);
        } // try catch login
    }
    catch (PDOException $ex){
        Response::returnErrorResponse(500, [$language_array['LNG_LOGIN_ISSUE']]);
    } // try catch user data
}
// if invalid parameters
else {
    Response::returnErrorResponse(404, [$language_array['LNG_ENDPOINT_ERROR']]);
} // end if else