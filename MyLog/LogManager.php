<?php

namespace Xes\Service\Log;

class LogManager
{
	static private $logId;

    static private $instance;
    
    private $config = array(
                'logDir'  => '/tmp/',       // 默认日志路径
                'yewu'    => 'default',     // 默认业务名
                'level'   => self::DEBUG,   // 默认日志级别
                'multi'   => false,         // 一次请求记录不同业务日志时，是否分为不同日志文件
            );

    static private $memory = false;
    static private $time = false;

    const DEBUG     = 100;
    const INFO      = 200;
    const NOTICE    = 250;
    const WARNING   = 300;
    const ERROR     = 400;
    const CRITICAL  = 500;
    const ALERT     = 550;
    const EMERGENCY = 600;

    static protected $levels = array(
        100 => 'DEBUG',
		200 => 'INFO',
        250 => 'NOTICE',
        300 => 'WARNING',
        400 => 'ERROR',
        500 => 'CRITICAL',
        550 => 'ALERT',
        600 => 'EMERGENCY',
    );

    private function __construct() {}


    /**
     * @brief config 读取配置参数并记录开始的内存使用量及时间
     *
     * @Param $config
     */
    public function config($config = array())
    {

        // 将传入的配置参数与默认配置合并
        $this->config = array_merge($this->config, (array)$config);

        // 检测配置的级别参数是否合法，非法则默认为self::DEBUG级别
        if(!$this->checkLevel($this->config['level'])) {
            $this->config['level'] = self::DEBUG;
        }

        // 日志路径结尾若不是斜杠，若不是，则添加
        if(!empty($this->config['logDir'])) {
            $this->config['logDir'] = rtrim($this->config['logDir'], "\t\n\r\0\x0B\/") . '/';
        }
    }


    /*
     * @brief 获取当前的Unix时间戳和微秒数
     */
    public function getMicrotime()
    {
        list($usec, $sec) = explode(" ", microtime());
        $usec = substr($usec, 2,6);
        return $sec . $usec;
    }


    /**
     * 获取实例
     * @return object LogManager实例
     */
    static public function getInstance()
    {
    	if (empty(self::$instance) || !self::$instance instanceof LogManager) {
    		self::$instance = new LogManager();
    	}

    	// 初始化
        self::$instance->init();
    	return self::$instance;
    }


    /**
     * 初始化logid， 内存，时间
     */
    protected function init()
    {
    	$this->getLogId();
    	self::$memory = empty(self::$memory) ? memory_get_usage() : self::$memory;
        self::$time = empty(self::$time) ? $this->getMicrotime() : self::$time;
        register_shutdown_function($this->shutdown());
    }

    /**
     *  shutdown 释放文件句柄
     */
     protected function shutdown()
     {
        fclose($this->fh);
     }


    /**
     * @brief getLogId 获取logid
     */
    public function getLogId()
    {
        if (!empty(self::$logId)) {
            return self::$logId;
        }

        if (!empty($_REQUEST['LOGID'])) {
            self::$logId = trim($_REQUEST['LOGID']);
            return self::$logId;
        }

        // logid = 时间戳 + 微秒 + ip2long + 4位随机数
        $microtime = $this->getMicrotime();
        $remote_addr = empty($_SERVER['REMOTE_ADDR']) ? '127.0.0.1' : $_SERVER['REMOTE_ADDR'];
        self::$logId = $microtime . ip2long($remote_addr) . mt_rand(1000,9999);
        return self::$logId;
    }


    /**
     * @brief write  记录日志
     *
     * @Param $level int    日志级别
     * @Param $msg   string 日志信息
     * @Param $file  string 产生日志的文件
     * @Param $line  int    产生日志的行号
     * @Param $method string 产生日志的方法名
     * @Param $yewu string  业务名
     */
    public function write($level, $msg='', $file='-', $line='-', $method='-', $yewu='')
    {
    	if (!$this->checkLevel($level)) {
    		$level = self::ERROR;
        	$msg = '日志级别非法';
            $this->write($level, $msg, $file, $line, $method, $yewu);
    		return false;
    	}

    	$fileName = $this->getFileName($level, $yewu);

    	$content = $this->getFormatContent($level, $msg, $file, $line, $method, $yewu);

    	file_put_contents($fileName, $content, FILE_APPEND);
    }


    /**
     * 记录不同级别日志
     *
     * @Param $name string 级别名
     * @Param $arguments array 参数：日志信息、产生日志的文件、行号、方法、子业务名
     */
    public function __call($name, $arguments)
    {

        $levelName = strtoupper($name);

		$level  = $this->getLevel($levelName);
		$msg    = empty($arguments[0]) ? '' : $arguments[0];
		$file   = empty($arguments[1]) ? '-' : $arguments[1];
		$line   = empty($arguments[2]) ? '-' : $arguments[2];
		$method = empty($arguments[3]) ? '-' : $arguments[3];
		$yewu   = empty($arguments[4]) ? '' : $arguments[4];

		if (!$level) {
			$level = self::ERROR;
        	$msg = '方法' . $name . '不存在';
            $this->write($level, $msg, $file, $line, $method, $yewu);
    		return false;
		}

        $this->write($level, $msg, $file, $line, $method, $yewu);
    }


    /**
     * 根据级别名称获取对应数值
     * @param  string  $levelName   级别名
     * @return int     $level       级别对应数值
     */
    protected function getLevel($levelName)
    {
    	if (!$this->checkLevelName($levelName)) {
    		return false;
    	}

    	return array_search($levelName, self::$levels);
    }

    /**
     * 根据级别数值获取对应名称
     * @param  int     $level       级别对应数值
     * @return string  $levelName   级别名
     */
    protected function getLevelName($level)
    {
    	if (!$this->checkLevel($level)) {
    		return false;
    	}
    	return self::$levels[$level];
    }


    /**
     * 检测级别是否合法
     * @param  int     $level       级别对应数值
     * @return bool        
     */
    protected function checkLevel($level)
    {
    	return isset(self::$levels[$level]);
    }


    /**
     * 检测级别名称是否合法
     * @param  string  $levelName   级别名
     * @return bool
     */
    protected function checkLevelName($levelName)
    {
    	return in_array($levelName, self::$levels);
    }



    /**
     * 获取日志文件名，级别大于error的，为ERROR日志，其他为INFO日志
     * @param  int     $level      日志级别
     * @param  string  $yewu       write方法传入的业务名
     * @return string  $fileName   全路径日志文件名
     */
    protected function getFileName($level, $yewu)
    {
    	$fileLevel = $level < self::ERROR ? 'INFO' : 'ERROR';
        
        if (!$this->config['multi'] || empty($yewu)) {
            return $this->config['logDir'] . $this->config['yewu'] . '_' . $fileLevel . '.log';
        }
        return $this->config['logDir'] . $yewu . '_' . $fileLevel . '.log';
    }

    /**
     * 格式化日志内容
     *
     * @Param $level  int    日志级别
     * @Param $msg    string 日志信息
     * @Param $file   string 产生日志的文件
     * @Param $line   int    产生日志的行号
     * @Param $method string 产生日志的方法名
     * @Param $yewu   string 业务名
     */
    protected function getFormatContent($level, $msg, $file, $line, $method, $yewu)
    {
		$content                   = array();
		$content['datetime']       = '[' . date("Y-m-d H:i:s") . ']';
		$content['yewu']           = empty($yewu) ? $this->config['yewu'] : $yewu;
		$content['level']          = $this->getLevelName($level);
		$content['logid']          = $this->getLogId();
		$content['hostname']       = gethostname();
		$content['remote_addr']    = empty($_SERVER['REMOTE_ADDR']) ? '127.0.0.1' : $_SERVER['REMOTE_ADDR'];
		$content['request_method'] = empty($_SERVER['REQUEST_METHOD']) ? 'CLI' : $_SERVER['REQUEST_METHOD'];
		$content['position']       = $file . ':' . $line . ' ' . $method;
		$content['time']           = ($this->getMicrotime() - self::$time) / 1000;
		$content['memory']         = memory_get_usage() - self::$memory;
		$content['msg']            = $msg;

    	return implode(' ', $content) . "\n";
    }
}
