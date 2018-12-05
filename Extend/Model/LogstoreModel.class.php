<?php
class LogstoreModel extends Model {
    
    static function Aliyun_Log_PHP_Client_Autoload($className) {
        $classPath = explode('_', $className);
        if ($classPath[0] == 'Aliyun') {
            if(count($classPath)>4)
                $classPath = array_slice($classPath, 0, 4);
                $filePath = VENDOR_PATH . implode('/', $classPath) . '.php';
                if (file_exists($filePath))
                    require_once($filePath);
        }
    }
    private static $isReg = false;
    public function __construct($tableName = '', $tablePrefix = '', $connection = '') {
        if (empty($connection)) {
            $connection = C('LOGSTORE_DSN');
        }
        if (empty($connection)) {
            $this->error = "LOGSTORE_DSN not configured";
        }
        if (empty($tableName)) {
            $this->error = "tablename is empty";
        }
        if (empty($tablePrefix)) {
            $tablePrefix = 'fdls_';
        }
        if (!self::$isReg) {
            spl_autoload_register('LogstoreModel::Aliyun_Log_PHP_Client_Autoload');
            self::$isReg = true;
        }
        parent::__construct($tableName, $tablePrefix, $connection);
    }
    
    public function __call($method,$args) {
        if(in_array(strtolower($method),array('order','limit'), true)) {
            // 连贯操作的实现
            $this->options[strtolower($method)] =   $args[0];
            return $this;
        }else{
            throw_exception(__CLASS__.':'.$method.L('_METHOD_NOT_EXIST_'));
            return;
        }
    }
    
    public function count() {
        if (empty($this->db)) {
            return false;
        }
        $options =  $this->_parseOptions($this->options);
        return $this->db->count($options);
    }
    
    public function select($options = array()) {
        try {
            if (empty($this->db)) {
                return false;
            }
            $options =  $this->_parseOptions($this->options);
           
            $data = $this->db->select($options);

            return $data;
            
        } catch (ThinkException $e){
            $this->error = $e->getMessage();
            return false;
        }
    }
    
    public function add($data = '', $options = array(), $replace = false) {
        return $this->batchAdd($data, $options);
    }
    
    public function batchAdd($data = '', $options = array()) {
        try{
            if (empty($this->db)) {
                return false;
            }
            if (empty($data)) {
                throw_exception("data is empty");
            }
            if (count($data) == count($data, 1)) {//一维数组
                $data = array($data);
            }
            
            $options =  $this->_parseOptions($this->options);
            return $this->db->batchAdd($options, $data);
            
        } catch (ThinkException $e){
            $this->error = $e->getMessage();
            return false;
        }
    }
    
}