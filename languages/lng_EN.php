<?php


// init var
static $language_array = [];

// general
$language_array['LNG_REQUEST_METHOD_ERROR']         = 'Request method not allowed';
$language_array['LNG_REQUEST_NOT_ALLOWED']          = 'Request not allowed';
$language_array['LNG_ENDPOINT_ERROR']               = 'Endpoint not found';


// pagination
$language_array['LNG_PAGE_NUMBER_ERROR']            = 'Page number cannot be blank and must be numeric';
$language_array['LNG_PAGE_NOT_FOUND']               = 'Page not found';

// database
$language_array['LNG_DB_CONNECTION_ERROR'] 		    = 'Database connection error';
$language_array['LNG_DB_QUERY_ERROR'] 		        = 'Database query error';

// json
$language_array['LNG_HEADER_NOT_JSON'] 		        = 'Content type header not set to JSON';
$language_array['LNG_BODY_NOT_VALID_JSON'] 		    = 'Request body is not valid JSON';

// users
$language_array['LNG_FULLNAME_NOT_SUPPLIED']        = 'Full name not supplied';
$language_array['LNG_USERNAME_NOT_SUPPLIED']        = 'Username not supplied';
$language_array['LNG_PASSWORD_NOT_SUPPLIED']        = 'Password not supplied';
$language_array['LNG_FULLNAME_BLANK_ERROR']         = 'Full name cannot be blank';
$language_array['LNG_FULLNAME_TO_LONG']             = 'Full name cannot be greater than 255 characters';
$language_array['LNG_USERNAME_BLANK_ERROR']         = 'Username cannot be blank';
$language_array['LNG_USERNAME_TO_LONG']             = 'Username cannot be greater than 255 characters';
$language_array['LNG_PASSWORD_BLANK_ERROR']         = 'Password cannot be blank';
$language_array['LNG_PASSWORD_TO_LONG']             = 'Password cannot be greater than 255 characters';
$language_array['LNG_USERNAME_TAKEN']               = 'Username already taken';
$language_array['LNG_USER_CREATION_ERROR']          = 'There was an issue creating a user account - please try again';
$language_array['LNG_USER_CREATED']                 = 'User created';
$language_array['LNG_USERNAME_MISSING']             = 'Username not supplied';
$language_array['LNG_PASSWORD_MISSING']             = 'Password not supplied';
$language_array['LNG_LOGIN_ERROR']                  = 'Username or password is incorrect';
$language_array['LNG_LOGIN_DB_ERROR']               = 'There was an issue logging in - please try again';
$language_array['LNG_USER_ACCOUNT_INACTIVE']        = 'User account not active';
$language_array['LNG_USER_ACCOUNT_LOCKED_OUT']      = 'User account is currently locked out';
$language_array['LNG_LOGIN_ISSUE']                  = 'There was an issue logging in';
$language_array['LNG_LOGOUT_ISSUE']                 = 'There was an issue logging in';
$language_array['LNG_BLANK_SESSIONID_ERROR']        = 'Session ID cannot be blank';
$language_array['LNG_NOT_NUMERIC_SESSIONID_ERROR']  = 'Session ID must be numeric';
$language_array['LNG_ACCESS_TOKEN_MISSING']         = 'Access token is missing from the header';
$language_array['LNG_ACCESS_TOKEN_TO_SHORT']        = 'Access token cannot be blank';
$language_array['LNG_TOKEN_DELETE_ERROR']           = 'Failed to log out of this session using access token provided';
$language_array['LNG_LOGGED_OUT_SUCCESS']           = 'Logged out';
$language_array['LNG_REFRESH_TOKEN_MISSING']        = 'Refresh token not supplied';
$language_array['LNG_REFRESH_TOKEN_BLANK']          = 'Refresh token cannot be blank';
$language_array['LNG_REFRESH_TOKEN_EXPIRED']        = 'Refresh token has expired - please log in again';
$language_array['LNG_TOKEN_REFRESH_ERROR']          = 'There was an issue refreshing access token - please login again';
$language_array['LNG_INCORRECT_TOKEN_TO_SID']       = 'Access token or refresh token is incorrect for session id';
$language_array['LNG_TOKEN_REFRESH_UPDATE_ERROR']   = 'Access token could not be refreshed - please log in again';
$language_array['LNG_TOKEN_REFRESH_SUCCESS']        = 'Token refreshed';


// task
$language_array['LNG_MISSING_TASK_ID'] 			    = 'Task ID cannot be blank or must be numeric';
$language_array['LNG_TASK_NOT_FOUND'] 			    = 'Task not found';
$language_array['LNG_FAILED_TO_GET_TASK']		    = 'Failed to get task';
$language_array['LNG_TASK_DELETED']		            = 'Task deleted';
$language_array['LNG_FAILED_TO_DELETE_TASK']	    = 'Failed to deleted task';
$language_array['LNG_NO_TASK_FIELD']	            = 'No task fields provided';
$language_array['LNG_NO_TASK_TO_UPDATE']	        = 'No task found to update';
$language_array['LNG_TASK_NOT_UPDATED']	            = 'Task not updated';
$language_array['LNG_TASK_UPDATED']	                = 'Task updated';
$language_array['LNG_TASK_UPDATED_FAILED']	        = 'Failed to update task - check your data for errors';
$language_array['LNG_TASK_COMPLETED_ERROR']	        = 'Completed filter must be Y or N';
$language_array['LNG_TASK_TITLE_MANDATORY']	        = 'Title field is mandatory and must be provided';
$language_array['LNG_TASK_COMPLETED_MANDATORY']	    = 'Completed field is mandatory and must be provided';
$language_array['LNG_TASK_CREATION_FAILED']	        = 'Failed to create task';
$language_array['LNG_TASK_C_RETURNED_FAILED']	    = 'Failed to return task after creation';
$language_array['LNG_TASK_CREATED']	                = 'Task created';
$language_array['LNG_TASK_INSERT_ERROR']	        = 'Failed to insert task into database - check submitted data for errors';

