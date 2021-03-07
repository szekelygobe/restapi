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

// if sessionid provided in url
if(array_key_exists("sessionid", $_GET)){
    $sessionid = $_GET['sessionid'];

    // checking valid sessionid
    if($sessionid === '' || !is_numeric($sessionid)){
        // building array of messages
        $message = [];
        $sessionid === ''       ? $message[] = $language_array['LNG_BLANK_SESSIONID_ERROR'] : null;
        !is_numeric($sessionid) ? $message[] = $language_array['LNG_NOT_NUMERIC_SESSIONID_ERROR'] : null;
        // building failed response
        Response::returnErrorResponse(400, $message);
    } // if invalid session ID

    // if no or invalid authorization
    if(!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1){
        // building array of messages
        $message = [];
        !isset($_SERVER['HTTP_AUTHORIZATION'])
            ? $message[] = $language_array['LNG_ACCESS_TOKEN_MISSING']
            : null;
        isset($_SERVER['HTTP_AUTHORIZATION']) && strlen($_SERVER['HTTP_AUTHORIZATION']) < 1
            ? $message[] = $language_array['LNG_ACCESS_TOKEN_TO_SHORT']
            : null;
        // building failed response
        Response::returnErrorResponse(401, $message);
    } // end if no or invalid authorization

    // the accesstoken sent in the header
    $accesstoken = $_SERVER['HTTP_AUTHORIZATION'];

    // if log out
    if($_SERVER['REQUEST_METHOD'] === 'DELETE' ){
        try {
            // trying to delete existing session
            $deletedSession = DB::deleteTableRow(
                $writeDB,
                TBL_SESSIONS,
                ['id'=>$sessionid, 'accesstoken'=>$accesstoken ]
            );

            // if no session deleted
            if((int)$deletedSession === 0){
                // build and send error response
                Response::returnErrorResponse(400, [$language_array['LNG_TOKEN_DELETE_ERROR']]);
            } // end if else

            // building response data
            $returnData = [];
            $returnData['session_id'] = (int)$sessionid;
            // building and sending success response
            Response::returnSuccessResponse(200, $returnData, $language_array['LNG_LOGGED_OUT_SUCCESS']);

        }
        catch (PDOException $ex){
            // building error response
            Response::returnErrorResponse(500, [$language_array['LNG_LOGOUT_ISSUE']]);
        } // try catch
    } // if update token
    // if refreshing access token
    elseif($_SERVER['REQUEST_METHOD'] === 'PATCH' ){
        //checking if correct header
        if(isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] !== 'application/json'){
            // building and sending error response
            Response::returnErrorResponse(400, [$language_array['LNG_HEADER_NOT_JSON']]);
        } // end if correct header

        // the sent data
        $rawPostedData = file_get_contents("php://input");

        // check if valid JSON format
        if(!$jsonData = json_decode($rawPostedData)){
            // building and sending error response
            Response::returnErrorResponse(400, [$language_array['LNG_BODY_NOT_VALID_JSON']]);
        } // end if invalid JSON

        // verifying valid refreshtoken format
        if(!isset($jsonData->refresh_token) || strlen($jsonData->refresh_token) < 1){
            // building multiple messages
            $message = [];
            !isset($jsonData->refresh_token)
                ? $message[] = $language_array['LNG_REFRESH_TOKEN_MISSING']
                : null;
            isset($jsonData->refresh_token) && strlen($jsonData->refresh_token) < 1
                ? $message[] = $language_array['LNG_REFRESH_TOKEN_BLANK']
                : null;
            // building and sending error response
            Response::returnErrorResponse(400, $message);
        } // end if valid refreshtoken format

        try {
            // saving to variable
            $refreshtoken = $jsonData->refresh_token;
            // searching for session in DB
            $session = DB::requestSession($writeDB, $sessionid, $accesstoken, $refreshtoken);

            // if session not found
            if($session['rowCount'] === 0){
                Response::returnErrorResponse(401, [$language_array['LNG_INCORRECT_TOKEN_TO_SID']]);
            } // if session not found

            // extracting returned values from DB
            $returned_sessionid           = $session['data'][0]['sessionid'];
            $returned_userid              = $session['data'][0]['userid'];
            $returned_accesstoken         = $session['data'][0]['accesstoken'];
            $returned_accesstoken_expiry  = $session['data'][0]['accesstokenexpiry'];
            $returned_refreshtoken        = $session['data'][0]['refreshtoken'];
            $returned_refreshtoken_expiry = $session['data'][0]['refreshtokenexpiry'];
            $returned_useractive          = $session['data'][0]['useractive'];
            $returned_loginattempts       = $session['data'][0]['loginattempts'];

            // if not active user account
            if($returned_useractive !== 'Y'){
                // build and return error response
                Response::returnErrorResponse(401, [$language_array['LNG_USER_ACCOUNT_INACTIVE']]);
            } // if not active

            // if login attempt exceeded
            if($returned_loginattempts >= CONST_LOGIN_ATTEMPT_LIMIT){
                // build and return error response
                Response::returnErrorResponse(401, [$language_array['LNG_USER_ACCOUNT_LOCKED_OUT']]);
            } // if login attempt exceeded

            // if refresh token not expired
            if( strtotime($returned_refreshtoken_expiry) < time()){
                // build and return error response
                Response::returnErrorResponse(401, [$language_array['LNG_REFRESH_TOKEN_EXPIRED']]);
            } // if refresh token expired

            // generating new tokens with expiration dates
            $new_tokens = generateTokens();

            // updating session tokens
            $updateSession = DB::updateDB(
                $writeDB,
                TBL_SESSIONS,
                [ 'accesstoken'         => $new_tokens['accesstoken'],
                  'accesstokenexpiry'   => $new_tokens['accesstokenexpiry'],
                  'refreshtoken'        => $new_tokens['refreshtoken'],
                  'refreshtokenexpiry'  => $new_tokens['refreshtokenexpiry'] ],
                [ 'id'           => $returned_sessionid,
                  'userid'       => $returned_userid,
                  'accesstoken'  => $returned_accesstoken,
                  'refreshtoken' => $returned_refreshtoken ]
            ); // update

            // if no session updated
            if($updateSession['rowCount'] === 0){
                // build and return response
                Response::returnErrorResponse(401, [$language_array['LNG_TOKEN_REFRESH_UPDATE_ERROR']]);
            } // if no update

            // building return data
            $updatedSession                             = [];
            $updatedSession['session_id']               = $returned_sessionid;
            $updatedSession['access_token']             = $new_tokens['accesstoken'];
            $updatedSession['access_token_expires_in']  = CONST_ACCESS_TOKEN_EXPIRY;
            $updatedSession['refresh_token']            = $new_tokens['refreshtoken'];
            $updatedSession['refresh_token_expires_in'] = CONST_REFRESH_TOKEN_EXPIRY;

            // returning success result
            Response::returnSuccessResponse(200, $updatedSession, $language_array['LNG_TOKEN_REFRESH_SUCCESS']);

        } catch (PDOException $ex){
            // bulding error message
            Response::returnErrorResponse(500, [$language_array['LNG_TOKEN_REFRESH_ERROR']]);
        } // try catch
    } // invalid method
    else {
        // building error response
        Response::returnErrorResponse(405, [$language_array['LNG_REQUEST_METHOD_ERROR']]);
    } // end if else valid method
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
        $new_tokens = generateTokens();

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
                  'accesstoken'         => $new_tokens['accesstoken'],
                  'accesstokenexpiry'   => $new_tokens['accesstokenexpiry'],
                  'refreshtoken'        => $new_tokens['refreshtoken'],
                  'refreshtokenexpiry'  => $new_tokens['refreshtokenexpiry']
                ]
            ); // end insert
            // commit and close transaction
            $writeDB->commit();

            // building return data
            $returnData                             = [];
            $returnData['session_id']               = $insertedUser;
            $returnData['access_token']             = $new_tokens['accesstoken'];
            $returnData['access_token_expires_in']  = CONST_ACCESS_TOKEN_EXPIRY;
            $returnData['refresh_token']            = $new_tokens['refreshtoken'];
            $returnData['refresh_token_expires_in'] = CONST_REFRESH_TOKEN_EXPIRY;

            // returning success result
            Response::returnSuccessResponse(201, $returnData);
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




/**
 * Generate new access and refresh tokens with expiration dates
 * @access public
 * @param -
 * @return array - of generated data
 */
function generateTokens():array {
    // init var
    $result = [];
    // generating unique tokens
    $result['accesstoken']        = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)) . time());
    $result['accesstokenexpiry']  = date(CONST_PHP_DATE_FORMAT, strtotime("+" . CONST_ACCESS_TOKEN_EXPIRY . " sec"));
    $result['refreshtoken']       = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)) . time());
    $result['refreshtokenexpiry'] = date(CONST_PHP_DATE_FORMAT, strtotime('+'.CONST_REFRESH_TOKEN_EXPIRY.' sec'));

    return $result;
} // end new tokens

