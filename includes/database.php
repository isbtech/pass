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

function db_connect() {
    static $connection;
    
    if (!isset($connection)) {
        $connection = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if (!$connection) {
            die('Database Connection Failed: ' . mysqli_connect_error());
        }
        
        mysqli_set_charset($connection, 'utf8mb4');
    }
    
    return $connection;
}

function db_query($query) {
    $connection = db_connect();
    $result = mysqli_query($connection, $query);
    
    if (!$result) {
        die('Query Error: ' . mysqli_error($connection));
    }
    
    return $result;
}

function db_select($query) {
    $rows = array();
    $result = db_query($query);
    
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
    }
    
    return $rows;
}

function db_select_one($query) {
    $result = db_query($query);
    
    if (mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    
    return false;
}

function db_insert($table, $data) {
    $connection = db_connect();
    
    $fields = array_keys($data);
    $values = array();
    
    foreach ($data as $value) {
        if ($value === NULL) {
            $values[] = "NULL";
        } else {
            $values[] = "'" . mysqli_real_escape_string($connection, $value) . "'";
        }
    }
    
    $query = "INSERT INTO $table (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $values) . ")";
    
    if (db_query($query)) {
        return mysqli_insert_id($connection);
    }
    
    return false;
}

function db_update($table, $data, $where) {
    $connection = db_connect();
    
    $set = array();
    
    foreach ($data as $field => $value) {
        if ($value === NULL) {
            $set[] = "$field = NULL";
        } else {
            $set[] = "$field = '" . mysqli_real_escape_string($connection, $value) . "'";
        }
    }
    
    $query = "UPDATE $table SET " . implode(", ", $set) . " WHERE $where";
    
    return db_query($query);
}

function db_delete($table, $where) {
    $query = "DELETE FROM $table WHERE $where";
    return db_query($query);
}
?>