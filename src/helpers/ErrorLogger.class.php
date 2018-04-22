<?php
set_error_handler(array('ErrorLogger', 'all_error_handler'));
set_exception_handler(array('ErrorLogger', 'exception_handler'));

if (!isset(ErrorLogger::$hostname)) {
    if (!isset($_SERVER) || !isset($_SERVER['HTTP_HOST']) || $_SERVER['HTTP_HOST'] == '') {
        ErrorLogger::$hostname = getenv('HOSTNAME');
    } else {
        ErrorLogger::$hostname = substr($_SERVER['HTTP_HOST'], 0, stripos($_SERVER['HTTP_HOST'], ':'));
    }
}

if (!defined('DIR_FS_CATALOG')) {
    die('Const DIR_FS_CATALOG is not defined!!!');
}
if (!defined('DIR_FS_LOGFILES')) {
    define('DIR_FS_LOGFILES', DIR_FS_CATALOG . '/logfiles/' . ErrorLogger::$hostname . '/');
}
ErrorLogger::create_path(DIR_FS_LOGFILES, DIR_FS_CATALOG);

/**
 * Class ErrorLogger
 */
class ErrorLogger
{
    static private $pattern_parts = array(
        '%DATE%',
        '%TITLE%',
        '%SESSION_ID%',
        '%HTTP_REFERER%',
        '%REQUEST_URI%',
        '%REMOTE_ADDR%',
        '%DATA_DUMP%',
        '%_POST%',
        '%_GET%'
    );
    static public $hostname;
    /** @var string  if not empty additional info will be add to report
     * Used only in fn {@link all_error_handler} now.
     */
    static private $addLogInfo = '';
    static private $send_interval_minutes = 5;
    const LAST_LOG_CHECK_FILE = 'last_log_check_file.txt';

    /**
     * @param $var
     * @param bool $full_trace
     */
    public static function dumpCommented($var, $full_trace = true)
    {
        echo '<!-- ' . "\n" . self::obj_dump($var, true, $full_trace) . '-->';
    }

    /**
     * @param $var
     * @param bool|false $return_as_string
     * @param bool|false $full_trace
     * @return string
     */
    public static function obj_dump($var, $return_as_string = false, $full_trace = false)
    {
        if (function_exists('debug_backtrace')) {
            $Tmp1 = debug_backtrace();
        } else {
            $Tmp1 = array(
                'file' => 'UNKNOWN FILE',
                'line' => 'UNKNOWN LINE',
            );
        }
        $var_value = '';
        $output = '<fieldset style="font:normal 12px helvetica,arial; margin:10px;"><legend style="font:bold 14px helvetica,arial">Dump - ' . $Tmp1[0]['file'] . " : " . $Tmp1[0]['line'] . '</legend><pre><br/>';
        if ($return_as_string) {
            $var_value = "\nDump - " . $Tmp1[0]['file'] . ' : ' . $Tmp1[0]['line'] . "\n";
        }
        if ($full_trace) {
            if ($return_as_string && $full_trace) {
                $var_value .= "\n" . self::trace_to_str($Tmp1) . "\n";
            } else {
                $output .= '<legend style="font:bold 14px helvetica,arial">' . self::trace_to_str($Tmp1) . '</legend>';
            }
        }
        if (is_bool($var)) {
            $var_value .= '(bool) ' . ($var ? 'true' : 'false');
        } elseif (is_null($var)) {
            $var_value .= '(null)';
//    } elseif (is_array($var)) {
//      $var_value .= self::obj_dump($var, true);
        } else {
            $var_value .= htmlspecialchars(print_r($var, true));
        }
        $output .= nl2br($var_value) . '</pre></fieldset><br/><br/>';

        if ($return_as_string) {
            return $var_value;
        }

        echo $output;
    }

    /**
     * @param $errno
     * @param $errstr
     * @param $errfile
     * @param $errline
     */
    public static function all_error_handler($errno, $errstr, $errfile, $errline)
    {
        if (!error_reporting()) {
            return;
        } // if Not show error

        $aErrTypeToText = array(
            E_ERROR => 'ERROR',
            E_WARNING => 'WARNING',
            E_PARSE => 'PARSE',
            E_NOTICE => 'NOTICE',
            E_CORE_ERROR => 'CORE_ERROR',
            E_CORE_WARNING => 'CORE_WARNING',
            E_COMPILE_ERROR => 'COMPILE_ERROR',
            E_COMPILE_WARNING => 'COMPILE_WARNING',
            E_USER_ERROR => 'USER_ERROR',
            E_USER_WARNING => 'USER_WARNING',
            E_USER_NOTICE => 'USER_NOTICE',
        );

        $allowedErrType = array(
            E_ERROR,
            E_WARNING,
            E_PARSE,
//    E_NOTICE,
            E_CORE_ERROR,
            E_CORE_WARNING,
            E_COMPILE_ERROR,
            E_COMPILE_WARNING,
            E_USER_ERROR,
            E_USER_WARNING,
//      E_USER_NOTICE,
        );

        if (PHP_VERSION >= 5.0) {
            $aErrTypeToText[E_STRICT] = 'STRICT';
//    $allowedErrType[] = E_STRICT;
        }
        if (PHP_VERSION >= 5.2) {
            $aErrTypeToText[E_RECOVERABLE_ERROR] = 'RECOVERABLE_ERROR ';
            $allowedErrType[] = E_RECOVERABLE_ERROR;
        }
        if (PHP_VERSION >= 5.3) {
            $aErrTypeToText[E_DEPRECATED] = 'DEPRECATED';
            $aErrTypeToText[E_USER_DEPRECATED] = 'USER_DEPRECATED';
            $allowedErrType[] = E_DEPRECATED;
            $allowedErrType[] = E_USER_DEPRECATED;
        }

        $error_string = '';
        $error_string .= '<b>' . $aErrTypeToText[$errno] . '</b>: ' . $errstr . ' in line ' . $errline . ' of file ' . $errfile . '<br/>' . "\n";
        $error_string .= self::trace();
        switch ($errno) {
            case E_STRICT: // ignore this type of error
                break;

            case E_NOTICE:
            case E_USER_NOTICE:
                if (!defined('DISPLAY_NOTICES') || !DISPLAY_NOTICES) {
                    break;
                }
            default:
                echo $error_string;
        }

        if (in_array($errno, $allowedErrType)) {
            if (self::$addLogInfo !== '') {
                $error_string .= "\n" . self::$addLogInfo;
                self::$addLogInfo = '';
            }
            ErrorLogger::send_email('Error report', $error_string);
        }
        if ($errno == E_ERROR || $errno == E_USER_ERROR) {
            die();
        }
    }

    /**
     * @param Throwable $exception
     */
    public static function exception_handler(Throwable $exception)
    {
        if (!error_reporting()) {
            return;
        } // if Not show error

        $error_string = '<b>Uncaught exception:</b>' . nl2br(str_ireplace(' ', '&nbsp;', $exception->getMessage())) . '<hr>Trace path:<br/>';
        foreach ($exception->getTrace() as $arr_error) {
//            $error_string .= '<b>' . $arr_error['file'] . '</b>, method <b>' . $arr_error['function'] . '</b> of class <b>' . $arr_error['class'] . '</b>, line <b>' . $arr_error['line'] . '</b><br/>';

            if (isset($arr_error['file'])) {
                $error_string .= '<b>' . $arr_error['file'] . '</b>, ';
            } else {
                $error_string .= 'File can not be detected, ';
            }
            if (isset($arr_error['function'])) {
                $error_string .= 'method <b>' . $arr_error['function'] . '</b> ';
            } else {
                $error_string .= 'method can not be detected ';
            }
            if (isset($arr_error['class'])) {
                $error_string .= 'of class <b>' . $arr_error['class'] . '</b>, ';
            } else {
                $error_string .= ', class can not be detected, ';
            }
            if (isset($arr_error['line'])) {
                $error_string .= 'line <b>' . $arr_error['line'] . '</b>';
            } else {
                $error_string .= 'line can not be detected';
            }
            $error_string .= '<br/>';
        }
        echo $error_string;
        Errorlogger::send_email('Exeption report', $error_string);
        exit;
    }

    /**
     * @param string $title
     * @param string $data
     * @param string $email
     * @return bool
     */
    public static function send_email($title, $data = '', $email = '')
    {
        $log_data = str_ireplace(array('<br>', '<br/>', '<hr>', '<hr/>'), "\n", $data);
        $log_data = strip_tags(str_ireplace('&nbsp;', " ", $log_data));
        if (is_null(self::add('send_email_error_log', $title, $log_data, true))) {
            return false;
        }

        $aEmailList = array();
        if ($email != '') {
            $aEmailList = array($email);
        } else {
            if (defined('EMAIL_ERROR_LOG_RECIPIENTS')) {
                $aEmailList = explode(';', EMAIL_ERROR_LOG_RECIPIENTS);
            }
        }
        $aPatternParts = self::getPatternParts($title, $data);
        foreach ($aEmailList as $sEmail) {
            if (isset($aPatternParts['log']['DATA_DUMP']) && $aPatternParts['log']['DATA_DUMP'] != '' && $sEmail != '') {
                error_log(
                    nl2br(
                        $aPatternParts['log']['DATE'] . ' ' . $aPatternParts['log']['TITLE'] . ' on ' . self::$hostname . $aPatternParts['log']['REQUEST_URI'] . "\n" . $aPatternParts['log']['HTTP_USER_AGENT'] . "\n" . $aPatternParts['log']['HTTP_REFERER'] . $aPatternParts['log']['SESSION_ID'] . "\n" . $aPatternParts['log']['DATA_DUMP'] . "\n" . $aPatternParts['log']['_POST'] . "\n" . $aPatternParts['log']['_GET'] . "\n"
                    ),
                    1,
                    $sEmail,
                    'Content-type: text/html; charset=utf-8' . "\r\n"
                );
            }
        }

        return true;
    }

    /**
     * @param $path
     * @param $base
     */
    public static function create_path($path, $base)
    {
        if (!is_dir($base)) {
            die($base . ' is not a directory!');
        }
        $cur_path = $base;
        if (substr($cur_path, -1) === '/') {
            $cur_path = substr($cur_path, 0, -1);
        }
        if (substr($path, 0, strlen($base)) === $base) {
            // a path with base includes was passed. truncate it to path relative to base
            $path = substr($path, strlen($base));
        }
        $path_parts = explode('/', $path);
        foreach ($path_parts as $dir_name) {
            if ($dir_name === '') {
                continue;
            }
            $cur_path .= '/' . $dir_name;
            if (!is_dir($cur_path)) {
                if (!@mkdir($cur_path, 0775)) {
                    trigger_error($cur_path . ' can\'t be created', E_USER_ERROR);
                }
            }
        }
    }

    /**
     * @param string $file_name
     * @param string $title
     * @param string $data
     * @param bool $check_unique
     * @return bool|null
     */
    public static function add($file_name = 'default', $title = '', $data = '', $check_unique = false)
    {
        if ($check_unique && self::check_unique_log($data)) {
            return null;
        }

        $aPatternParts = self::getPatternParts($title, $data);

        $log_string = "\n" . self::parsePattern($aPatternParts['log']) . "\n";

        $file = DIR_FS_LOGFILES . $file_name;

        self::save_last_log_check_file($aPatternParts['ers']);
        $fileOK = @error_log($log_string, 3, $file . '.log');
        if ($fileOK) {
            @chmod($file . '.log', 0775);
        }

        return $fileOK;
    }

    /**
     * @return string
     */
    public static function trace()
    {
        $tmp = debug_backtrace();
        $trace_string = 'Trace path:<br/>';
        for ($i = 2; $i < count($tmp); $i++) {
            $arr_error = $tmp[$i];
            $file = '`Can`t detect file`';
            if (isset($arr_error['file'])) {
                $file = $arr_error['file'];
            }
            $line = '`Can`t detect line`';
            if (isset($arr_error['line'])) {
                $line = $arr_error['line'];
            }
            $function = $arr_error['function'];
            $trace_string .= '<b>' . $file . '</b>, function <b>' . $function . '</b>, line <b>' . $line . '</b><br/>';
        }

        return $trace_string;
    }

    /**
     * @param string $tmp
     * @return string
     */
    private static function trace_to_str($tmp = '')
    {
        if (!$tmp) {
            $tmp = debug_backtrace();
        }
        $strLog = 'Stack tracing:' . "\n";
        for ($i = 1; $i < count($tmp); $i++) {
            $arr_error = $tmp[$i];
            $file = $arr_error['file'];
            $line = $arr_error['line'];
            $function = $arr_error['function'];
            $strLog .= $file . ', function ' . $function . ', line ' . $line . "\n";
        }
        $strLog .= '----------------------------------------' . "\n";

        return $strLog;
    }

    /**
     * @param $aParts
     * @return mixed|string
     */
    private static function parsePattern($aParts)
    {
        $sPattern = <<<TEXT_PATTERN
%DATE% %TITLE% %SESSION_ID% %HTTP_REFERER% %REMOTE_ADDR% %REQUEST_URI% %DATA_DUMP%
TEXT_PATTERN;

        foreach ($aParts as $sPart => $sValue) {
            $sPattern = str_replace('%' . strtoupper(trim($sPart, '%')) . '%', $sValue, $sPattern);
        }

        //clean default parts
        foreach (self::$pattern_parts as $sPart) {
            $sPattern = str_replace($sPart, '', $sPattern);
        }

        return $sPattern;
    }

    /**
     * @param $title
     * @param $data
     * @return array
     */
    private static function getPatternParts($title, $data)
    {
        $aPatternParts = array();

        $date = date('Y-m-d H:i:s');
        $aPatternParts['log']['DATE'] = '[' . $date . ']';
        $aPatternParts['ers']['DATE'] = $date;
        $aPatternParts['log']['TITLE'] = $title;
        $aPatternParts['ers']['TITLE'] = $title;

        //add session id
        $session_id = session_id();
        $aPatternParts['ers']['SESSION_ID'] = $aPatternParts['log']['SESSION_ID'] = '';
        if ($session_id != '') {
            $aPatternParts['ers']['SESSION_ID'] = $session_id;
            $aPatternParts['log']['SESSION_ID'] = '; SESSION_ID = ' . $session_id;
        }

        //add referer
        $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        $aPatternParts['ers']['HTTP_REFERER'] = $aPatternParts['log']['HTTP_REFERER'] = '';
        if ($referer != '') {
            $aPatternParts['ers']['HTTP_REFERER'] = $referer;
            $aPatternParts['log']['HTTP_REFERER'] = '; HTTP_REFERER = ' . $referer;
        }

        //add remote ip
        $remoteIp = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        $aPatternParts['ers']['REMOTE_ADDR'] = $aPatternParts['log']['REMOTE_ADDR'] = '';
        if ($remoteIp != '') {
            $aPatternParts['ers']['REMOTE_ADDR'] = $remoteIp;
            $aPatternParts['log']['REMOTE_ADDR'] = '; REMOTE_ADDR = ' . $remoteIp;
        }

        //add request uri
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $aPatternParts['ers']['REQUEST_URI'] = $aPatternParts['log']['REQUEST_URI'] = '';
        if ($request_uri != '') {
            $aPatternParts['ers']['REQUEST_URI'] = $request_uri;
            $aPatternParts['log']['REQUEST_URI'] = "\n" . 'REQUEST_URI = ' . $request_uri;
        }

        //add user agent
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $aPatternParts['ers']['HTTP_USER_AGENT'] = $aPatternParts['log']['HTTP_USER_AGENT'] = '';
        if ($request_uri != '') {
            $aPatternParts['ers']['HTTP_USER_AGENT'] = $user_agent;
            $aPatternParts['log']['HTTP_USER_AGENT'] = "\n" . 'HTTP_USER_AGENT = ' . $user_agent;
        }

        //add description
        $aPatternParts['ers']['DATA_DUMP'] = $aPatternParts['log']['DATA_DUMP'] = '';
        if ($data != '') {
            $aPatternParts['ers']['DATA_DUMP'] = self::prepare_log_body($data);
            $aPatternParts['log']['DATA_DUMP'] = "\n" . print_r($data, 1);
        }

        //add $_POST
        $aPatternParts['ers']['_POST'] = $aPatternParts['log']['_POST'] = '';
        if (count($_POST)) {
            $aPatternParts['ers']['_POST'] = print_r($_POST, 1);
            $aPatternParts['log']['_POST'] = "\n" . '$_POST = ' . "\n" . print_r($_POST, 1);
        }

        //add $_GET
        $aPatternParts['ers']['_GET'] = $aPatternParts['log']['_GET'] = '';
        if (count($_GET)) {
            $aPatternParts['ers']['_GET'] = print_r($_GET, 1);
            $aPatternParts['log']['_GET'] = "\n" . '$_GET = ' . "\n" . print_r($_GET, 1);
        }

        return $aPatternParts;
    }

    /**
     * register unique errors per 1 hour
     *
     * @param $log_body
     * @return bool
     */
    private static function check_unique_log($log_body)
    {
        $last_log_date = self::check_log_in_file($log_body);

        return self::is_log_in_interval($last_log_date);
    }

    /**
     * @param $log_body
     * @return string
     */
    private static function prepare_log_body($log_body)
    {
        return base64_encode(print_r($log_body, 1));
    }

    /**
     * @param $last_log_date
     * @return bool
     */
    private static function is_log_in_interval($last_log_date)
    {
        if ($last_log_date) {
            $log_date_stamp = strtotime($last_log_date);
            if (time() - self::$send_interval_minutes * 60 <= $log_date_stamp) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $log_body
     * @return bool
     */
    private static function check_log_in_file($log_body)
    {
        $check_data = self::read_last_log_check_file();
        $search_hash = self::get_log_hash($log_body);
        if (is_array($check_data) && array_key_exists($search_hash, $check_data)) {
            return $check_data[$search_hash];
        }

        return false;
    }

    /**
     * @param $aData
     * @return int
     */
    private static function save_last_log_check_file($aData)
    {
        $check_data = self::read_last_log_check_file();
        $key = self::get_log_hash(base64_decode($aData['DATA_DUMP']));

        if (is_array($check_data)) {
            $check_data[$key] = $aData['DATE'];
        } else {
            $check_data = array($key => $aData['DATE']);
        }

        return file_put_contents(DIR_FS_LOGFILES . self::LAST_LOG_CHECK_FILE, serialize($check_data));
    }

    /**
     * @return array|mixed
     */
    private static function read_last_log_check_file()
    {
        if (is_file(DIR_FS_LOGFILES . self::LAST_LOG_CHECK_FILE)) {
            return unserialize(file_get_contents(DIR_FS_LOGFILES . self::LAST_LOG_CHECK_FILE));
        }

        return array();
    }

    /**
     * @param $sInput
     * @return string
     */
    public static function get_log_hash($sInput)
    {
        return md5(self::prepare_log_body($sInput));
    }
}
