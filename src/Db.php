<?php
/**
 * Db.php
 * Created by RisingSun,ICEDOT
 * User: MarkSpace
 * Create on 2021/7/28 15:16
 */

namespace ms\core;

use ms\core\Error;
use ms\core\Log;

class Db
{
    private $pdo;
    private static $debug=true; //是否开启调试模式s
    private static $instance= []; //实例
    private static $instance_index='default_db';
    protected static $hosts; //支持多数据库
    private $table = ''; //临时储存数据表
    private $options = [
        'field'=>'*',
        'where'=>'',
        'alias'=>'',
        'order'=>'',
        'limit'=>'',
        'group'=>'',
        'having'=>'',
        'join'=>'',
        'joinp'=>'',
    ]; //连贯操作储存
    private $Db_Prefix = '';

    /**
     * 初始化设置数据库信息
     * @param $config
     */
    public static function setHosts($config=''){
        $config=Config::get('Database');
        return $config;
    }

    /**
     * 切换数据库
     */
    public static function changeDataBase($baseName='default_db'){
        self::$hosts=self::setHosts();
        if(empty(self::$hosts[$baseName])){
            return ('不存在此数据库配置信息');
        }
        self::$instance_index = $baseName;
    }

    /**
     * 创建连接
     * @return $Db
     */
    private static function createConnection()
    {
        try {
            self::$hosts=self::setHosts();
            if(empty(self::$hosts[self::$instance_index])){
                print('This database configuration does not exist');die;
            }
            $conf = self::$hosts[self::$instance_index];
            $Db = new self();

            if($conf['db_provider']=='mysql'){
            $dsn = "{$conf['db_provider']}:host={$conf['db_host']};dbname={$conf['db_name']};charset={$conf['db_charset']};port={$conf['db_port']}";
            $user = $conf['db_user'];
            $password = $conf['db_pwd'];
            $Db->pdo = new \PDO($dsn, $user, $password, array(\PDO::ATTR_PERSISTENT=>$conf['db_connection'])  /*持久性链接PDO::ATTR_PERSISTENT=>true*/ );
            }elseif($conf['db_provider']=='MSSQL'){
            $dsn = "odbc:Driver=".$conf['mssqldriver'].";Server=".$conf['db_host'].",".$conf['db_port'].";Database=".$conf['db_name'].";";
            $user = $conf['db_user'];
            $password = $conf['db_pwd'];
            $Db->pdo = new \PDO($dsn, $user, $password,array(\PDO::ATTR_PERSISTENT=>$conf['db_connection'])  /*持久性链接PDO::ATTR_PERSISTENT=>true*/ );
            }elseif($conf['db_provider']=='sqlite'){
            if (!file_exists($conf['path'])) {
            echo('本地数据库不存在，请初始化数据库！' ); 
            exit;
            } 
            $Db->pdo = new \PDO('sqlite:'.$conf['path']);
            if (!pdo){ echo '数据库连接失败'; exit;} 
            }
            $Db->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            return $Db;
            
        }catch(\PDOException $e){
            Error::echoSqlError($e);
        }
    }

    /**
     * 获取连接
     * @return mixed
     */
    private static function getInstance(){
        if(empty(self::$instance[self::$instance_index])){
            self::$instance[self::$instance_index] = self::createConnection();
        }
        return self::$instance[self::$instance_index];
    }

    /**
     * 选择数据表
     * @param $table_name
     * @return mixed|Db
     */
    public static function table($table_name)
    {
        
        // self::$hosts=self::setHosts();
        $Instance = self::getInstance();
        $Instance->table = $table_name;
        // $Instance->Db_Prefix = self::$hosts[self::$instance_index]['Db_Prefix'];
        $Instance->Db_Prefix = self::prefix(); 
        $Instance->options =[
            'field'=>'*',
            'where'=>'',
            'alias'=>'',
            'order'=>'',
            'limit'=>'',
            'group'=>'',
            'having'=>'',
            'join'=>'',
            'joinp'=>''
        ];
        return $Instance;
    }

    /**
     * 选择数据表
     * @param $table_name
     * @return mixed|Db
     */
    public static function name($table_name)
    {
        self::$hosts=self::setHosts();
        return self::table(self::prefix().$table_name);
    }

    /**
     * 获取数据表前缀
     * @param $table_name
     * @return mixed|Db
     */
    public static function prefix()
    {
        return self::$hosts[self::$instance_index]['db_prefix'];
    }

    /**
     * 原生sql语句
     * @param $query
     * @return mixed|Db
     */
    public static function query()
    {
        self::$hosts=self::setHosts();
        $Instance = self::getInstance();
        return $Instance;
    }

    /**
     * 开启事物
     */
    public static function startTrans(){
        $Instance = self::getInstance();
        try {
            return $Instance->pdo->beginTransaction();
        } catch (\PDOException $e) {
            // throw $e;
            // 服务端断开时重连一次
            if ($e->errorInfo[1] == 2006 || $e->errorInfo[1] == 2013) {
                $this->closeConnection();
                self::getInstance();
                return $Instance->pdo->beginTransaction();
            } else {
                throw $e;
            }
        }
    }

    /**
     * 提交事物
     */
    public static function commit()
    {
        return self::getInstance()->pdo->commit();
    }

    /**
     *回滚操作
     */
    public static function rollback()
    {
        return self::getInstance()->pdo->rollBack();
    }

    /**
     * 别名
     */
    public function alias($name){
        $this->options['alias'] = ' AS '.$name;
        return $this;
    }

    /**
     * 指定字段
     */
    public function field($name){
        $name=is_Array($name)?implode(',',$name):$name;
        $this->options['field'] = $name;
        return $this;
    }

    /**
     * 结果排序
     */
    public function order($name){
        $this->options['order'] = ' ORDER BY '.$name;
        return $this;
    }

    /**
     * 分页查询
     */
    public function limit($page=0,$limit=10){
        $this->options['limit'] = ' LIMIT '.$page.','.$limit;
        return $this;
    }

    /**
     * 分页查询进化版本
     */
    public function page($page=1,$limit=10){
        $this->options['limit'] = ' LIMIT '.(($page-1)*$limit).','.$limit;
        return $this;
    }

    /**
     * 分组查询
     */
    public function group($name){
        $this->options['group'] = ' GROUP BY '.$name;
        return $this;
    }

    /**
     * 用于配合group方法完成从分组的结果中筛选（通常是聚合条件）数据。
     */
    public function having($where = ''){
        if(empty($this->options['where'])){
            $this->options['having'] = " HAVING {$where} ";
        }else{
            $this->options['having'] .= " AND {$where} ";
        }
        return $this;
    }

    /**
     * 连表 自动携带表前缀
     */
    public function join($tablename,$condition,$type='INNER'){
        $tablename=trim($tablename,' ');
        $sql = " {$type} JOIN ".self::prefix()."{$tablename} ON {$condition} ";
        if(empty($this->options['join'])){
            $this->options['join'] = $sql;
        }else{
            $this->options['join'] .= $sql;
        }
        return $this;
    }

    /**
     * 连表 不携带表前缀
     */
    public function joinp($tablename,$condition,$type='INNER'){
        $sql = " {$type} JOIN {$tablename} ON {$condition} ";
        if(empty($this->options['join'])){
            $this->options['join'] = $sql;
        }else{
            $this->options['join'] .= $sql;
        }
        return $this;
    }

    /**
     * 连表 原生写法
     */
    public function joins($sql){
        $this->options['join'] = $sql;
        return $this;
    }

    /*
     * 处理空格 防注入转换
     */
    public function security($str){
        return trim(str_replace("'", "\'", $str));
    }

    /**
     * OR条件语句  注意不支持一条语句多次使用
     */
    public function whereOr($key = null, $factor = null, $val = null){
        if($key != null && $factor != null && $val != null){ //三项存在规则
            $val = $this->security($val); //防注入
            $where = " {$key} {$factor} '{$val}' ";
        } elseif (is_array($key)){ //一项是数组规则
            $where = '';
            foreach ($key as $k => $v) {
                if (is_array($v)) {
                    $v[2] = $this->security($v[2]); //防注入
                    if(strtoupper($v[1]) == 'IN'){
                        $where.= " {$v[0]} {$v[1]} ({$v[2]}) OR";
                    }elseif (strtoupper($v[1]) == 'BETWEEN'){
                        $where.= " {$v[0]} {$v[1]} {$v[2]} OR";
                    }else{
                        $where.= " {$v[0]} {$v[1]} '{$v[2]}' OR";
                    }
                } else {
                    $v = $this->security($v); //防注入
                    $where .= " {$k}='{$v}' OR";
                }
            }
            $where = trim(trim($where), 'OR');
        } elseif ($key != null && $factor != null && $val == null) { //两项存在规则
            $factor = $this->security($factor); //防注入
            $where = " {$key}='{$factor}' ";
        } elseif ($key != null && $factor == null && $val == null) { //一项非数组规则 无法保障数据安全 请勿使用此方式
            $where = $key;
        }else{
            exit('WHEREOR Invalid rule');
        }

        if(empty($this->options['where'])){
            $this->options['where'] = " WHERE ({$where}) ";
        }else{
            $this->options['where'] .= " OR ({$where}) ";
        }
        return $this;
    }

    /**
     * AND条件语句
     */
    public function where($key=null, $factor = null, $val = null){
        if(!$key){
            return $this;
        }
        if($key != null && $factor != null && $val != null){ //三项存在规则
            $val = $this->security($val); //防注入
            $where = " {$key} {$factor} '{$val}' ";
        } elseif (is_array($key)){ //一项是数组规则
            $where = '';
            foreach ($key as $k => $v) {
                if (is_array($v)) {
                    $v[2] = $this->security($v[2]); //防注入
                    if(strtoupper($v[1]) == 'IN'){
                        $where.= " {$v[0]} {$v[1]} ({$v[2]}) AND";
                    }elseif (strtoupper($v[1]) == 'BETWEEN'){
                        $where.= " {$v[0]} {$v[1]} {$v[2]} AND";
                    }else{
                        $where.= " {$v[0]} {$v[1]} '{$v[2]}' AND";
                    }
                } else {
                    $v = $this->security($v); //防注入
                    $where .= " {$k}='{$v}' AND";
                }
            }
            $where = trim(trim($where), 'AND');
        } elseif ($key != null && $factor != null && $val == null) { //两项存在规则
            $factor = $this->security($factor); //防注入
            $where = " {$key}='{$factor}' ";
        } elseif ($key != null && $factor == null && $val == null) { //一项非数组规则 无法保障数据安全 请勿使用此方式
            $where = $key;
        }else{
            exit('WHERE Invalid rule');
        }
        if(empty($this->options['where'])){
            $this->options['where'] = " WHERE {$where} ";
        }else{
            $this->options['where'] .= " AND {$where} ";
        }
        return $this;
    }

    /**
     * 执行列表查询
     */
    public function select(){
        $option = $this->options;
        $sql = "SELECT {$option['field']} FROM {$this->table} {$option['alias']} {$option['join']} {$option['where']} {$option['group']} {$option['having']} {$option['order']} {$option['limit']}";
        return $this->query_select($sql);
    }

    /**
     * 执行列表查询全部
     */
    public function findall(){
        $option = $this->options;
        $sql = "SELECT {$option['field']} FROM {$this->table} {$option['alias']} {$option['join']} {$option['where']} {$option['group']} {$option['having']} {$option['order']} ";
        return $this->query_select($sql);
    }

    /**
     * 执行获取单条记录
     */
    public function find(){
        $option = $this->options;
        $sql = "SELECT {$option['field']} FROM {$this->table} {$option['alias']} {$option['join']} {$option['where']} {$option['group']} {$option['having']}";
        return $this->query_find($sql);
    }

    /**
     * 执行单条记录字段查询 20220326
     */
    public function value($value){
        $option = $this->options;
        $sql = "SELECT {$value} FROM {$this->table} {$option['alias']} {$option['join']} {$option['where']} {$option['group']} {$option['having']}";
        if (!strpos($value, ',')) {
            // $data=$this->query_find($sql);
            return isset($this->query_find($sql)[$value]) ? $this->query_find($sql)[$value] : null;
        }else{
            return $this->query_find($sql);
        }
    }

    /**
     * 修改数据
     * 格式 $data = ['name'=>'王天佑',time=>'1234567890'];
     * @param $data
     */
    public function update($data){
        if (!is_array($data)) {
            exit('UPDATE Invalid rule');
        }
        $updateStr = '';
        foreach ($data as $k => $v) {
            $v = $this->security($v); //过滤非法字符
            if($updateStr){
                $updateStr .= ",`{$k}`='{$v}'";
            }else{
                $updateStr .= " `{$k}`='{$v}'";
            }
        }
        $option = $this->options;
        $sql = "UPDATE {$this->table} SET  {$updateStr} {$option['where']} ";
        return $this->exec($sql);
    }

    /**
     * 删除操作
     */
    public function delete(){
        $option = $this->options;
        $sql = "DELETE FROM {$this->table} {$option['where']} ";
        return $this->exec($sql);
    }

    /**
     * 插入操作
     * 格式 ['name'=>'王天佑',time=>'1234567890']
     */
    public function insert($data){
        if (!is_array($data)) {
            exit('INSERT Invalid rule');
        }
        $keyStr = '';
        $valStr = '';
        foreach ($data as $k => $v) {
            if($keyStr){
                $keyStr.= ",{$k}";
            }else{
                $keyStr.= "{$k}";
            }

            $v = $this->security($v); //过滤非法字符
            if($valStr){
                $valStr.= ",'{$v}'";
            }else{
                $valStr.= "'{$v}'";
            }


        }
        $sql = "INSERT INTO {$this->table} ({$keyStr}) VALUES ($valStr)";
        $res = $this->exec($sql);
        if($res){
            return $this->pdo->lastInsertId();
        }
        return $res;
    }

    /**
     * 聚合方法-统计数量，参数是要统计的字段名（可选）
     */
    public function count($field='*'){
        $option = $this->options;
        $sql = "SELECT COUNT('{$field}') as count FROM {$this->table} {$option['alias']} {$option['join']} {$option['where']} {$option['group']} {$option['having']} {$option['order']} {$option['limit']}";
        $res =  $this->query_find($sql);
        // print_r($res);die;
        if($res){
            return $res["count"];
        }
        return 0;
    }

    /**
     * 聚合方法-获取最大值，参数是要统计的字段名（必须）
     */
    public function max($field){
        $option = $this->options;
        $sql = "SELECT MAX({$field}) FROM {$this->table} {$option['alias']} {$option['join']} {$option['where']} {$option['group']} {$option['having']} {$option['order']} {$option['limit']}";
        $res =  $this->query_find($sql);
        if($res){
            return $res[0];
        }
        return 0;
    }

    /**
     * 聚合方法-获取最小值，参数是要统计的字段名（必须）
     */
    public function min($field){
        $option = $this->options;
        $sql = "SELECT MIN({$field}) FROM {$this->table} {$option['alias']} {$option['join']} {$option['where']} {$option['group']} {$option['having']} {$option['order']} {$option['limit']}";
        $res =  $this->query_find($sql);
        if($res){
            return $res[0];
        }
        return 0;
    }

    /**
     * 聚合方法-获取平均值，参数是要统计的字段名（必须）
     */
    public function avg($field){
        $option = $this->options;
        $sql = "SELECT AVG({$field}) FROM {$this->table} {$option['alias']} {$option['join']} {$option['where']} {$option['group']} {$option['having']} {$option['order']} {$option['limit']}";
        $res =  $this->query_find($sql);
        if($res){
            return $res[0];
        }
        return 0;
    }

    /**
     * 聚合方法-获取总分，参数是要统计的字段名（必须）
     */
    public function sum($field){
        $option = $this->options;
        if (!strpos($field, ',')) {
            $field = 'SUM('.$field.') as numsum';
        }else{
            $field=explode(',',$field);
            foreach ($field as $key => $value) {
                $field[$key] = 'SUM('.$value.') as '.$value;
            }
            $field = implode(', ',$field);
        }
        $sql = "SELECT {$field}  FROM {$this->table} {$option['alias']} {$option['join']} {$option['where']} {$option['group']} {$option['having']} {$option['order']} {$option['limit']}";
        $res =  $this->query_find($sql);
        if($res){
            if (!strpos($field, ',')){
              return  isset($res['numsum'])?$res['numsum']:0; 
            }else{
              return $res?$res:0;  
            }            
        }
        return 0;
    }

    /**
     * 执行PDO EXEC方法
     */
    public function exec($sql){
        try {
            return $this->pdo->exec($sql);
        } catch (\PDOException $e) {
            $this->toError($e,$sql);
        }
    }

    /**
     * 原生SQL单条数据获取
     * @param $sql
     * @return mixed
     */
    public function query_find($sql)
    {
        try {
            $sth = $this->pdo->prepare($sql.' LIMIT 1 ');
            $sth->execute();
            $bute=self::$hosts[self::$instance_index]['Db_bute'];
            if ($bute == 1) {
                $bute = \PDO::FETCH_ASSOC;
            } elseif ($bute == 2) {
                $bute = \PDO::FETCH_NUM;
            } elseif ($bute == 3 || !$bute) {
                $bute = \PDO::FETCH_BOTH;
            }
            $result = $sth->fetch($bute);
            unset($bute);
            return $result;
        } catch (PDOException $e) {
            // 服务端断开时重连一次
            if ($e->errorInfo[1] == 2006 || $e->errorInfo[1] == 2013) {
                $this->closeConnection();
                self::getInstance();
                try {
                    $sth = $this->pdo->prepare($sql.' LIMIT 1 ');
                    $sth->execute();
                    $bute=self::$hosts[self::$instance_index]['Db_bute'];
                    if ($bute == 1) {
                        $bute = \PDO::FETCH_ASSOC;
                    } elseif ($bute == 2) {
                        $bute = \PDO::FETCH_NUM;
                    } elseif ($bute == 3 || !$bute) {
                        $bute = \PDO::FETCH_BOTH;
                    }
                    $result = $sth->fetch($bute);
                    unset($bute);
                    return $result;
                } catch (PDOException $ex) {
                    // self::rollback();
                    throw $ex;
                }
            } else {
                // self::rollback();
                $this->toError($e,$sql);
            }

            // $this->toError($e,$sql);
        }
    }

    /**
     * 原生SQL查找多条数据
     * @param $sql
     * @return mixed
     */
    public function query_select($sql)
    {
        try {
            $sqlStartTime = Log::getMillisecond();
            // $this->pdo->beginTransaction();
            $sth = $this->pdo->prepare($sql);
            $sth->execute();
            $bute=self::$hosts[self::$instance_index]['db_bute'];
            if ($bute == 1) {
                $bute = \PDO::FETCH_ASSOC;
            } elseif ($bute == 2) {
                $bute = \PDO::FETCH_NUM;
            } elseif ($bute == 3 || !$bute) {
                $bute = \PDO::FETCH_BOTH;
            }
            $result = $sth->fetchAll($bute);
            unset($bute);
            $sqlEndTime = Log::getMillisecond();
            $this->showLog($sqlStartTime,$sqlEndTime,$sql);
            return $result;
        } catch (\PDOException $e) {
            // 服务端断开时重连一次
            if ($e->errorInfo[1] == 2006 || $e->errorInfo[1] == 2013) {
                $this->closeConnection();
                self::getInstance();
                try {
                    $sth = $this->pdo->prepare($sql.' LIMIT 1 ');
                    $sth->execute();
                    $bute=self::$hosts[self::$instance_index]['Db_bute'];
                    if ($bute == 1) {
                        $bute = \PDO::FETCH_ASSOC;
                    } elseif ($bute == 2) {
                        $bute = \PDO::FETCH_NUM;
                    } elseif ($bute == 3 || !$bute) {
                        $bute = \PDO::FETCH_BOTH;
                    }
                    $result = $sth->fetch($bute);
                    unset($bute);
                    return $result;
                } catch (PDOException $ex) {
                    // self::rollback();
                    throw $ex;
                }
            } else {
                // self::rollback();
                $this->toError($e,$sql);
            }
            // $this->toError($e,$sql);
        }
    }

    private function toError($e,$sql){
        if(self::$debug==true){
            Error::echoSqlError($e,$sql);
            $this->showerrLog($e);
        }else{
            $this->showerrLog($e->getMessage());
            echo $e->getMessage();/*die;*/
        }
    }

    public function __destruct()
    {
        // TODO: Implement __destruct() method.
        $this->pdo = null;
//        var_dump('PDO销毁了');
    }

    /**
    * 关闭连接
    */
    public function closeConnection()
    {
        $this->pdo = null;
    }

    /*
     * 记录sql日志
     * @err $e错误
    */  
    public function showLog($sqlStartTime,$sqlEndTime,$sql){
    $logdebug = Config::get('config')["logdebug"];
      if ($logdebug['SQL_LOG']) {
        unset($logdebug);
        Log::write(sprintf("SQL COSETIME=【%s】ms,SQL=【%s】", ($sqlEndTime - $sqlStartTime), $sql),Log::SQL,Log::FILE,RUN_PATH.'sql/'.date("y_m_d").".log");
      }
    }

    /*
     * 错误提示
     * @err $e错误
    */
    public function showerrLog($e){
    Log::write("SQL ERROR=" . json_encode($e), Log::ERROR,Log::FILE,RUN_PATH.'sql/'.date("y_m_d").".log");
    }
}
