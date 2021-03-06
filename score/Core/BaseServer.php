<?php
namespace Swoolefy\Core;

class BaseServer {

	/**
	 * $server
	 * @var null
	 */
	public static $server = null;

	/**
	 * $_startTime 进程启动时间
	 * @var int
	 */
	protected static $_startTime = 0;

	/**
	 * $_tasks 线上正在运行的任务
	 * @var null
	 */
	public static $_table_tasks = [
		'table_ticker' => [
			'size' => 8096,
			'fields'=> [
				['tick_tasks','string',8096]
			]
		],
		'table_after' => [
			'size' => 8096,
			'fields'=> [
				['after_tasks','string',8096]
			]
		],
	];
	/**
	 * __construct
	 */
	public function __construct() {
		// check extensions
		self::checkVersion();
		// check is run on cli
		self::checkSapiEnv();
		// set timeZone
		self::setTimeZone(); 
		// create table
		self::createTables();
		// record start time
		self::$_startTime = date('Y-m-d H:i:s',strtotime('now'));
	}

	/**
	 * checkVersion 检查是否安装基础扩展
	 * @return   void
	 */
	public static function checkVersion() {
		if(version_compare(phpversion(), '5.6.0', '<')) {
			throw new \Exception("php version must be > 5.6.0,we suggest use php7.0+ version", 1);
		}

		if(!extension_loaded('swoole')) {
			throw new \Exception("you are not install swoole extentions,please install it where version >= 1.9.17 or >=2.0.5 from https://github.com/swoole/swoole-src", 1);
		}

		if(!extension_loaded('swoole_serialize')) {
			throw new \Exception("you are not install swoole_serialize extentions,please install it where from https://github.com/swoole/swoole_serialize", 1);
		}

		if(!extension_loaded('pcntl')) {
			throw new \Exception("you are not install pcntl extentions,please install it", 1);
		}

		if(!extension_loaded('posix')) {
			throw new \Exception("you are not install posix extentions,please install it", 1);
		}

		if(!extension_loaded('zlib')) {
			throw new \Exception("you are not install zlib extentions,please install it", 1);
		}
	}

	/**
	 * setMasterProcessName 设置主进程名称
	 * @param    $master_process_name
	 */
	public static function setMasterProcessName($master_process_name) {

		swoole_set_process_name($master_process_name);
	}

	/**
	 * setManagerProcessName 设置管理进程的名称
	 * @param    $manager_process_name
	 */
	public static function setManagerProcessName($manager_process_name) {
		swoole_set_process_name($manager_process_name);
	}

	/**
	 * setWorkerProcessName设置worker进程名称
	 * @param    $worker_process_name
	 * @param    $worker_id          
	 * @param    $worker_num         
	 */
	public static function setWorkerProcessName($worker_process_name, $worker_id, $worker_num=1) {
		// 设置worker的进程
		if($worker_id >= $worker_num) {
            swoole_set_process_name($worker_process_name."-task".$worker_id);
        }else {
            swoole_set_process_name($worker_process_name."-worker".$worker_id);
        }

	}

	/**
	 * startInclude设置需要在workerstart启动时加载的配置文件
	 * @param    $includes 
	 * @return   void
	 */
	public static function startInclude() {
		$includeFiles = isset(static::$config['include_files']) ? static::$config['include_files'] : [];
		if($includeFiles) {
			foreach($includeFiles as $filePath) {
				include_once $filePath;
			}
		}
	}

	/**
	 * setWorkerUserGroup 设置worker进程的工作组，默认是root
	 * @param    $worker_user
	 */
	public static function setWorkerUserGroup($worker_user=null) {
		if(!isset(static::$setting['user'])) {
			if($worker_user) {
				$userInfo = posix_getpwnam($worker_user);
				if($userInfo) {
					posix_setuid($userInfo['uid']);
					posix_setgid($userInfo['gid']);
				}
			}
		}
	}

	/**
	 * filterFaviconIcon google浏览器会自动发一次请求/favicon.ico,在这里过滤掉
	 * @return  void
	 */
	public static function filterFaviconIcon($request,$response) {
		if($request->server['path_info'] == '/favicon.ico' || $request->server['request_uri'] == '/favicon.ico') {
            return $response->end();
       	}
	}

	/**
	 * getStartTime 服务启动时间
	 * @return   time
	 */
	public static function getStartTime() {
		return self::$_startTime;
	}

	/**
	 * getConfig 获取服务的全部配置
	 * @return   array
	 */
	public static function getConfig() {
		static::$config['setting'] = self::getSetting();
		return static::$config;
	}
	/**
	 * getSetting 获取swoole的配置项
	 * @return   array
	 */
	public static function getSetting() {
		return static::$setting;
	}

	/**
	 * getSwooleVersion
	 * @return   string
	 */
	public static function getSwooleVersion() {
    	return swoole_version();
    }

	/**
	 * getLastError 返回最后一次的错误代码
	 * @return   int
	 */
	public static function getLastError() {
		return self::$server->getLastError();
	}

	/**
	 * getLastErrorMsg
	 * @return   string
	 */
	public static function getLastErrorMsg() {
		$code = swoole_errno();
		return swoole_strerror($code);
	}

	/**
	 * getLocalIp
	 * @return   string
	 */
	public static function getLocalIp() {
		return swoole_get_local_ip();	
	}

	/**
	 * getLocalMac 获取本机mac地址
	 * @return   array
	 */
	public static function getLocalMac() {
		return swoole_get_local_mac();
	}

	/**
	 * getStatus 获取swoole的状态信息
	 * @return   array
	 */
	public static function getStats() {
		return self::$server->stats();
	}

	/**
	 * setTimeZone 设置时区
	 * @return   void
	 */
	public static function setTimeZone() {
		if(isset(static::$config['time_zone'])) {
			date_default_timezone_set(static::$config['time_zone']);
		}
	}

	/**
	 * clearCache
	 * @return  void
	 */
	public static function clearCache() {
		if(function_exists('apc_clear_cache')){
        	apc_clear_cache();
    	}
	    if(function_exists('opcache_reset')){
	        opcache_reset();
	    }
	}

	/**
	 * getIncludeFiles
	 * @return   void
	 */
	public static function getIncludeFiles($dir='Http') {
		$dir = ucfirst($dir);
		$filePath = __DIR__.'/../'.$dir.'/'.$dir.'_'.'includes.json';
		$includes = get_included_files();
		if(is_file($filePath)) {
			@unlink($filePath);	
		}
		@file_put_contents($filePath, json_encode($includes));
		@chmod($filePath,0766);
	}

	/**
	 * createTables 默认创建定时器任务的内存表    
	 * @return  void
	 */
	public static function createTables() {
			if(!isset(static::$config['table']) || !is_array(static::$config['table'])) {
				static::$config['table'] = [];
			}

			if(isset(static::$config['table_tick_task']) && static::$config['table_tick_task'] == true) {
				$tables = array_merge(self::$_table_tasks,static::$config['table']);
			}else {
				$tables = static::$config['table'];
			}
			
			foreach($tables as $k=>$row) {
				$table = new \swoole_table($row['size']);
				foreach($row['fields'] as $p=>$field) {
					switch($field[1]) {
						case 'int':
							$table->column($field[0],\swoole_table::TYPE_INT,(int)$field[2]);
						break;
						case 'string':
							$table->column($field[0],\swoole_table::TYPE_STRING,(int)$field[2]);
						break;
						case 'float':
							$table->column($field[0],\swoole_table::TYPE_FLOAT,(int)$field[2]);
						break;
					}
				}
				$table->create();
				self::$server->$k = $table; 
			}
	}

	/**
	 * checkSapiEnv
	 * @return void
	 */
	public static function checkSapiEnv() {
        // Only for cli.
        if (php_sapi_name() != "cli") {
            throw new \Exception("only run in command line mode \n", 1);
        }
    }



}