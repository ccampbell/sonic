<?php
namespace Sonic;

/**
 * Exception class
 *
 * @category Sonic
 * @package Exception
 * @author Craig Campbell
 */
class Exception extends \Exception
{
    const INTERNAL_SERVER_ERROR = 0;
    const NOT_FOUND = 1;
    const FORBIDDEN = 2;
    const UNAUTHORIZED = 3;

    /**
     * gets display message to show to the user
     *
     * @return string
     */
    public function getDisplayMessage()
    {
        switch ($this->code) {
            case self::NOT_FOUND:
                return 'The page you were looking for could not be found.';
                break;
            case self::FORBIDDEN:
                return 'You do not have permission to view this page.';
                break;
            case self::UNAUTHORIZED:
                return 'This page requires login.';
                break;
            default:
                return 'Some kind of error occured.';
                break;
        }
    }

    /**
     * gets http code to set to header
     *
     * @return string
     */
    public function getHttpCode()
    {
        switch ($this->code) {
            case self::NOT_FOUND:
                return 'HTTP/1.1 404 Not Found';
                break;
            case self::FORBIDDEN:
                return 'HTTP/1.1 403 Forbidden';
                break;
            case self::UNAUTHORIZED:
                return 'HTTP/1.1 401 Unauthorized';
                break;
            default:
                return 'HTTP/1.1 500 Internal Server Error';
                break;
        }
    }
}
