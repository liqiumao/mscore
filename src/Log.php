<?php
/**
 * 日志操作类库
 * Created by RisingSun
 * User: MarkSpace 
 * Create on 2022/3/26 15:16
 */
namespace ms\core;

class Log {

    const ERROR = "ERROR";
    const DEBUG = "DEBUG";
    const SQL = "SQL";
    const NOTICE = "NOTICE";
    const API = "API";

    // 日志记录方式
    const SYSTEM = 0;
    const MAIL = 1;
    const TCP = 2;
    const FILE = 3;

    //日志信息
    static $logs = array();
    //日期格式
    static $format = '[ c ]';

    /**
     * 记录日志到内存中
     * @param string $message 日志内容
     * @param string $level 日志级别
     */
    public static function record($message,$level=self::DEBUG){
        $now = date(self::$format);
        self::$logs[]="{$now} {$level}: {$message}\r\n";
    }

    /**
     * 保存内存中的日志到文件
     * @param int $type 存储日志类型
     * @param string $file 写入文件位置
     * @param string $extra 额外参数
     */
    public static function save($type=self::FILE,$file='',$extra=""){
        if(empty($file)){
            $file = LOG_PATH.date("y_m_d").".log";
        }
        $dir = dirname($file);
        is_dir($dir) or (createFolders(dirname($dir)) and mkdir($dir, 0777));
        error_log(implode("",self::$logs),$type,$file,$extra);
        self::$logs=array();
    }

    /**
     * 直接输出日志
     * @param string $message 日志内容
     * @param string $level 日志输出级别
     * @param int $type 存储日志类型
     * @param string $file 写入文件位置
     * @param string $extra 额外参数
     */
    public static function write($message,$level=self::DEBUG,$type=self::FILE,$file='',$extra=''){
        if(empty($file)){
            $file = LOG_PATH.date("y_m_d").".log";
        }
        $dir = dirname($file);
        is_dir($dir) or (createFolders(dirname($dir)) and mkdir($dir, 0777));
        $now = date(self::$format);
        error_log("{$now} {$level}: {$message}\r\n",$type,$file,$extra);
    }

    /**
     * 直接输出日志
     * @param string $message 日志内容
     * @param string $level 日志输出级别
     * @param int $type 存储日志类型
     * @param string $file 写入文件位置
     * @param string $extra 额外参数
     */
    public static function filewrite($message,$file=''){
        if(empty($file)){
            $file = LOG_PATH.'err_log_'.date("y_m_d").".log";
        }
        $dir = dirname($file);
        is_dir($dir) or (createFolders(dirname($dir)) and mkdir($dir, 0777));
        $now = date(self::$format);
        file_put_contents($file,"{$now} : {$message}".PHP_EOL, FILE_APPEND);//写文件
       // error_log("{$now} : {$message}\r\n",$file);
    }

    /**
     * 获取当前时间毫秒数
     * @return float
    */
    public static function getMillisecond(){
        list($s1, $s2) = explode(' ', microtime());
        return (float)sprintf('%.0f', (floatval($s1) + floatval($s2)) * 1000);
    }

}