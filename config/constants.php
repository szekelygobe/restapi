<?php

// DB tables
define("TBL_TASKS",             'tbltasks');
define("TBL_USERS",             'tblusers');
define("TBL_SESSIONS",          'tblsessions');

// Token expiration time in seconds
define("CONST_ACCESS_TOKEN_EXPIRY",     1200); // 20 minutes
define("CONST_REFRESH_TOKEN_EXPIRY",    3456000); // 40 days

// Diff. settings
define("CONST_MYSQL_DATE_FORMAT",       '%Y-%m-%d %H:%i');
define("CONST_PHP_DATE_FORMAT",         'Y-m-d H:i');
define("CONST_DEFAULT_LANGUAGE",        'EN');
define("CONST_LOGIN_ATTEMPT_LIMIT",     5);
define("CONST_ELEMENTS_PER_PAGE",       20);

