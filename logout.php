<?php
/******************************************************************************
* Software: Pass    - Private Audio Streaming Service                         *
* Version:  0.1 Alpha                                                         *
* Date:     2025-03-15                                                        *
* Author:   Sandeep - sans                                                    *
* License:  Free for All Church and Ministry Usage                            *
*                                                                             *
*  You may use and modify this software as you wish.   						  *
*  But Give Credits  and Feedback                                             *
*******************************************************************************/
require_once 'includes/auth.php';

// Log out the user
logout();

// Redirect to the login page
header('Location: login.php');
exit;