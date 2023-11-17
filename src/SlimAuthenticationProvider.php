<?php

namespace src;

use Exception;

class SlimAuthenticationProvider
{
    public static function getUserIdByToken($token): int {
        # check if token is valid
        $db_connection = DatabaseSingleton::getInstance();
        # get user id from token
        $idnull = $db_connection->perform_query("SELECT session_user FROM t4_sessions WHERE session_token = ?", [$token])->fetch_assoc()["session_user"];
        if ($idnull == null) {
            return 0;
        }
        return $idnull;
    }

    /**
     * @throws Exception
     * Checks if a token is valid, for every request. Also provide the answer directly.
     */
    public static function validatetoken($token): bool
    {
        $database_connection = DatabaseSingleton::getInstance();
        # Check if token exists
        $db_fetch = $database_connection->perform_query("SELECT COUNT(session_id) as sessioncount, session_expires as sessionexpiry FROM t4_sessions WHERE session_token = ?", [$token]);
        $data = $db_fetch->fetch_assoc();
        $sessioncount = $data["sessioncount"];
        $session_expire = $data["sessionexpiry"];
        if ($sessioncount > 0) {
            # check if token is expired
            if ($session_expire < date("Y-m-d H:i:s")) {
                # if expired return false
                return false;
            }
            # if not expired just return.
            return true;
        } else {
            # if not exists
            return false;
        }
    }
}