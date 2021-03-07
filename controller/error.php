<?php
require_once('db.php');
require_once('../config/constants.php');
require_once('../model/Response.php');
require_once('../utils/debug_functions.php');
require_once ('../languages/lng_'.CONST_DEFAULT_LANGUAGE.'.php');

global $language_array;

// building 404 error response
Response::returnErrorResponse(404, [$language_array['LNG_ENDPOINT_ERROR']]);
