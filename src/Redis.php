<?php
/**
 * Redis 操作,支持 Master/Slave 的负载集群
 * author：LQM  RisingSun
 * Time:20220326
 */
namespace Mscore\Core;

use Redis as RedisBase;

class Redis
{
    /**
     * 对象实例数组
     * @var array
     */
    private static $_instance = [];
    // 是否使用 M/S 的读写集群方案
    private $_isUseCluster;
    // Slave 句柄标记
    private $_sn = 0;
    // 服务器连接句柄
    private $_linkHandle;
    private $Db_connection;
    private $Db_Host;
    private $Db_Port;
    private $Db_Pwd;
    private $Db_Expire;
    private $Db_Master;

    private $handler;
    protected static $cache; //支持多数据库

 
    /**
     * 构造函数
     *
     * @param boolean $_isUseCluster 是否采用 M/S 方案
    */
    public function __construct($connection,$host, $port, $auth, $select, $timeout, $debug = false, $isUseCluster = false)
    {
        $this->_isUseCluster=$isUseCluster;
        $this->Db_Host=$host;
        if(!$this->handler) {
            $this->handler = new RedisBase();
        }

        if($connection){
        $this->handler->pconnect($host, $port, $timeout);    
        }else{
        $this->handler->connect($host, $port, $timeout);   
        }

        if('' != $auth) {
        $this->handler->auth($this->Db_Pwd);
        }
        
        if(0 != $select) {
        $this->handler->select(intval($select));
        }
        $this->debug = $debug;
    }
     
    /*
     * 单例模式
     * @param 初始化：Redis::getInstance()
    */
    public static function getInstance($selects=null) {
        // 检测php环境
        if (!extension_loaded('redis')) {
            echo ('not support:redis');
            return false;
        }
        $config = array();
        $config=self::configget('redis')['redis_db'];
        $isUseCluster = $config['db_isusecluster'];
        $hosts = $config['db_host'];
        $ports = $config['db_port'];
        $pwds = $config['db_pwd'];
        if($isUseCluster){
            if(count($hosts) < 2 || $ports < 2) {
                echo ('config error： at least 2 host and 2 port needed');die;
            }
            foreach($hosts as $key => $host) {
                $port = !empty($ports[$key]) ? $ports[$key] : 6379;
                $pwd = !empty($pwds[$key]) ? $pwds[$key] : '';
                $select = intval($selects[$key]);
                if(!self::$_instance[$key] instanceof self) {
                    self::$_instance[$key] = new self($config['db_connection'],$host, $port, $pwd, $select, $config['db_timeout'], $config['db_debug'],$isUseCluster);
                }
            }
            return self::$_instance[0];
        }else{
            $selects = isset($config['db_select'][0])?$config['db_select'][0]:0; //根据配置文件获得默认库
            return self::$_instance[0] = new self($config['db_connection'],$hosts[0], $ports[0],isset($pwds[0])?$pwds[0]:'', $selects?intval($selects):0, $config['db_timeout'], $config['db_debug']);
        }
        
    }

    /*
     * 执行
     * @param exec 执行
    */
    public static function exec($meth='',$arr=[], Int $db=0) {
        if(empty($meth)){
            return $meth.'不能为空';
        }
        if(empty($arr)){
            return $arr.'不能为空';
        }
        if($meth){
           return self::getInstance($db)->runCommand($meth,$arr);
        }
    }  

    /**
     * 获取主服务器
     * @return mixed
     */
    public function master() {
        if($this->debug) {
//            echo 'i am master <br />';
        }
        return self::$_instance[0];
    }
    /**
     * 获取从服务器
     */
    public function slaves() {
        $slaves = [];
        for($i = 1; $i < count(self::$_instance); $i++) {
            $slaves[] = self::$_instance[$i];
        }
        return $slaves;
    }

    /**
     * 随机生成一台从服务器
     */
    public function oneSlave() {
        $slaves = $this->slaves();
        $count=count($slaves);
        $i= mt_rand(0,$count - 1);       
        if($this->debug) {
            echo 'i am slave '.$i.'<br />';
        }        
        return self::$_instance[$i];
    }

    /**
     * 执行命令
     */
    public function runCommand($command, $params) {
        $command=strtolower($command);
        try{
            $redis = $this->getByCommand($command);
            return call_user_func_array([$redis, $command], $params);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }

    }

    /**
     * 根据command命令来获取服务器
     * TODO::命令及格式
     * runCommand('set', [key, value]),runCommand('get', [key])
     * 写：set($key, $value)，setex($key, $expire, $value)，mset($arr),设置过期时间:expire($key, $time),删除缓存delete($key),值加加操作incr($key)\incrBy($key, $default)($default 操作时的默认值),值减减操作decr($key)\decrBy($key, $default),添空当前数据库flushDB(),将一个或多个值value插入到列表lpush($key, $value),移除并返回列表的第一个元素lpop($key),为哈希表中的字段赋值hset($name, $key, $value)，删除哈希表key中的一个或多个指定字段hdel($name, $key)
     * 读方法：1查询剩余过期时间：ttl($key)，2读缓存get($key),3返回所有(一个或多个)给定 key 的值mget($keys),返回哈希表中指定字段的值hget($name, $key)/hgetAll($name),返回列表中指定区间内的元素lrange($key, $start, $end),
     * 事务：用于标记一个事务块的开始multi(),用于执行所有事务块内的命令exec()
     * 注意：serialize/unserialize 所有对象的序列化与反序列化需要自己重新设定
     */
    protected function getByCommand($command) {
        $command=strtolower($command);
        $read_command = ['get', 'hget', 'mget', 'hmget', 'hgetall', 'lrange', 'ttl'];
        $write_command = ['set','setex','mset', 'hset', 'hmset', 'delete', 'del','incr','incrBy','flushDB','incr','incrBy','decr','decrBy','lpush','lpop','lrange','hdel','multi','exec','expire'];
        if(in_array($command, $read_command)) {     //读命令，随机返回一台读服务器
            if($this->_isUseCluster){
                return $this->oneSlave()->handler;
            }else{
                return $this->master()->handler;   
            }
        } elseif(in_array($command, $write_command)) {
            return $this->master()->handler;
        } else {
            echo ('The command is not supported:'.$command);
        }
    }
    /**
     *  获取配置文件的值
     *  @param $key 标识config文件名 配置文件里统一使用return array的形式
    */
    public static function configget($key,$path='/config/')
    {
        if (isset(self::$cache[$path][$key])) return self::$cache[$path][$key];
        $file = dirname(dirname(dirname(dirname(dirname(__FILE__))))). $path .$key. '.php';
        if (is_file($file)) {
            self::$cache[$path][$key] = include $file;
        } else {
            return false;
        }
        return self::$cache[$path][$key];
    }
        
}
