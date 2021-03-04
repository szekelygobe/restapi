<?php

/**
 * Printing arrays readable
 * @access public
 * @param  $km_message_text - optional message to print on kill
 * @return string - the message object
 */
function killme($km_message_text = false)
{
    // building message
    $km_message_obj = '<div style="border:2px red solid;padding:10px;background-color:white; font-size: 12px;">' . "\n" .
        '	<b>KILLME:</b><br> >>' . $km_message_text . '<< ' . "\n" .
        '</div>';

    // displaying message
    echo $km_message_obj;
    // stopping all scripts
    die();
} //end func. kill-me


/**
 * Printing arrays readable
 * @access public
 * @param  $array_to_print - the array to print
 * @param  $message - message to print
 * @param  $return_string - to return values as string
 * @param  $killme - abort code running after th print
 * @param bool $send_mail - if set email will be sent to me :)
 * @return string - the print param content
 */
function parray($array_to_print = false, $message = false, $return_string = false, $killme = false, $send_mail = false)
{
    global $developer_ips;

    // init vars
    $current_date = date("g:i:s a - F j, Y ");

    // only display debug when admin is logged in
    // if(isset($_SESSION['user_data']['userlevel']) && $_SESSION['user_data']['userlevel'] > 8){
    // displaying calling file name
    $message_text = $message
        ? $message
        //: pathinfo(__FILE__, PATHINFO_DIRNAME) .'/'.pathinfo(__FILE__, PATHINFO_BASENAME);
        // :basename($_SERVER["PHP_SELF"]);
        : basename($_SERVER["SCRIPT_NAME"]);

    // init variable
    $parray_string = ' DEBUG [' . $message_text . '] : ' . "\n\n";
    // if parameter to print has value
    if ($array_to_print) {
        // returning value
        // $parray_string .= nl2br(print_r($array_to_print, true));
        $parray_string .= print_r($array_to_print, true);
        // if empty parameter
    } else {
        $parray_string .= '<b>No array</b> to print or <b>array empty</b> !<br> ';
    } // end if else empty parameter

    // if return results as string
    if ($return_string) {
        // returning result as string
        return $parray_string;
        // if print results
    } else {
        //display message:
        echo "\n" . "<div style='border:2px red solid;padding:5px 10px;margin:5px 0;clear:both;font-size:11px;line-height:11px; background-color: white;'>";
        echo "\n<br><span style='font-weight:bold;text-decoration: underline;'> DEBUG: $message_text</span>";
        echo "\n<br><span> - " . $current_date . " </span>";
        // if parameter passed
        if ($array_to_print) {
            // printing structureÅ
            echo "\n" . '<br><br><pre style="color:blue; font-weight:bold">' . "\n";
            print_r($array_to_print);
            echo "\n" . '</pre>';
        } else {
            echo "\n" . '	<br><b>No array</b> to print or <b>array empty</b> !<br> ';
        } // end if else parameter passed
        echo "\n</div><br>\n";
    } // end if else string or output

    // if website generation
    if ($killme) {
        // calling kill me function
        killme($message);
    } // end if kill me
    // } // end if admin
} // end function print array


/**
 * Replacing PDO array variables in query and displaying
 * @access public
 * @param  $p_sql - the SQL to print
 * @param  $p_array - the array to replace
 * @param  $p_message - message to print
 * @param  $return_string -  return values as string not print out
 * @param  $killme - stop process
 * @return string - the correct SQL
 */
function psql($p_sql, $p_array, $p_message = false, $return_string = false, $killme = false)
{
    // init vars
    $current_date = date("g:i:s a - F j, Y ");

    // formatting query
    $p_sql = str_ireplace(['select',
                           'from',
                           'where',
                           'asc',
                           ' as ',
                           ' and ',
                           'order by',
                           'group',
                           'limit ',
                           ' by',
                           'inner',
                           'left',
                           'join',
                           'insert ',
                           'into',
                           'values',
                           'update ',
                           'set ',
                           ')',
                           '(',
                           ',',
                           '  '
                          ],
                          ["\n<b>SELECT</b>\t",
                           "\n<b>FROM</b>",
                           "\n<b>WHERE</b>",
                           "<b>ASC</b>",
                           "<b> AS </b>",
                           "\n\t<b> AND </b>",
                           "\n<b>ORDER BY</b>",
                           "\n<b>GROUP</b>",
                           "\n<b>LIMIT</b>",
                           " <b>BY</b>",
                           "\n<b>INNER</b>",
                           "\n<b>LEFT</b>",
                           "<b>JOIN</b>",
                           "<b>INSERT </b>",
                           "<b>INTO</b>",
                           "<b>VALUES</b>",
                           "<b>UPDATE</b> ",
                           "\n<b>SET</b> \n\t",
                           "<b>)</b>",
                           "<b>(</b>",
                           ",\n\t",
                           " "
                          ],
                          $p_sql
    );

    // only display debug when admin is logged in
    // if(isset($_SESSION['user_data']['userlevel']) && $_SESSION['user_data']['userlevel'] > 8){
    // displaying calling file name
    $message_text = $p_message
        ? $p_message
        //: pathinfo(__FILE__, PATHINFO_DIRNAME) .'/'.pathinfo(__FILE__, PATHINFO_BASENAME);
        // : basename($_SERVER["PHP_SELF"]);
        : basename($_SERVER["SCRIPT_NAME"]);

    // if PDO param array has values
    if (!empty($p_array)) {
        // replacing values
        foreach ($p_array as $pakey => $paval) {
            // if not ingeger or float add quotation
            if (!is_int($paval) && !is_float($paval) && !is_array($paval)) {
                // $p_array[$pakey]='"'.$paval.'"';
                $paval = '"' . addslashes($paval) . '"';
            } else if (is_array($paval)) {
                parray($paval, 'ERROR PSQL PARAMETER - debug_functions.php - 134', 0, 1);
            }
            // replacing placeholders with values
            $p_sql = preg_replace("/" . $pakey . "\b/", $paval, $p_sql);
        } // end foreach
    } // end if PDO array

    // if return results as string
    if ($return_string) {
        // returning result as string
        return 'SQL DEBUG [' . $message_text . '] : ' . $p_sql;
        // if print results
    } else {
        //display message:
        echo "<div style='border:2px blue solid;padding:5px 10px;margin:5px 0;clear:both;font-size:11px;line-height:11px; background-color: white;'>
					<span style='font-weight:bold;text-decoration: underline;'>" . $current_date . " | SQL DEBUG: $message_text</span>";
        // printing structure
        echo '<br><br><pre>' . "\n";
        print_r($p_sql);
        echo '</pre>';
        echo "</div>";
    } // end if else string or output
    // if website generation
    if ($killme) {
        // calling killme function
        killme($p_message);
    } // end if killme
    // } // end if admin
} // end function print SQL


