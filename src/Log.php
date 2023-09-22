<?php
/**
 * 日志操作类库
 * Created by RisingSun
 * User: MarkSpace 
 * Create on 2022/3/26 15:16
 */
namespace Mscore\Core;

class Log {

    /**
     * 直接输出日志
     * @param string $message 日志内容
     * @param string $level 日志输出级别
     * @param int $type 存储日志类型
     * @param string $file 写入文件位置
     * @param string $extra 额外参数
     */
    public static function write($message,$file=''){
        if(empty($file)){
            return false;
        }
        // 日志内容
        if(is_file($file)){
            unlink($file);
            file_put_contents($file,'');
        }
        $logcontent = $message."\r\n";       
        file_put_contents($file, $logcontent, FILE_APPEND | LOCK_EX);
        unset($logcontent);
        return true;
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