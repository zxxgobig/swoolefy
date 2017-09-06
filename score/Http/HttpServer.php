<?php
namespace Swoolefy\Http;
include_once "../../vendor/autoload.php";

use Swoole\Http\Server as httpServer;
use Swoolefy\Core\Base;
use Swoolefy\Core\Swfy;
use Swoole\Http\Request;
use Swoole\Http\Response;

class Webserver extends Base {

	/**
	 * $config
	 * @var null
	 */
	public static $config = null;
	/**
	 * $webserver
	 * @var null
	 */
	public $webserver = null;

	/**
	 * $conf
	 * @var array
	 */
	public static $setting = [
		'reactor_num' => 1, //reactor thread num
		'worker_num' => 2,    //worker process num
		'max_request' => 10000,
		'daemonize' => 0,
		'log_file' => __DIR__.'/log.txt',
	];

	/**
	 * $App
	 * @var null
	 */
	public static $App = null;

	/**
	 * $startctrl
	 * @var null
	 */
	public $startctrl = null;

	/**
	 * __construct
	 * @param array $config
	 */
	public function __construct(array $config=[]) {

		self::$config = array_merge(
					include(__DIR__.'/config.php'),
					$config
			);

		parent::__construct();

		self::$setting = array_merge(self::$setting, self::$config['setting']);

		self::$server = $this->webserver = new httpServer(self::$config['host'], self::$config['port']);

		$this->webserver->set(self::$setting);

		// 初始化启动类
		$startClass = isset(self::$config['start_init']) ? self::$config['start_init'] : 'Swoolefy\\Http\\StartInit';
		$this->startctrl = new $startClass;
	}

	public function start() {
		/**
		 * start回调
		 */
		$this->webserver->on('Start',function(HttpServer $server) {
			// 重新设置进程名称
			self::setMasterProcessName(self::$config['master_process_name']);
			// 启动的初始化函数
			$this->startctrl->start($server);
		});
		/**
		 * managerstart回调
		 */
		$this->webserver->on('ManagerStart',function(HttpServer $server) {
			// 重新设置进程名称
			self::setManagerProcessName(self::$config['manager_process_name']);
			// 启动的初始化函数
			$this->startctrl->managerStart($server);
		});

		/**
		 * 启动worker进程监听回调，设置定时器
		 */
		$this->webserver->on('WorkerStart',function(HttpServer $server, $worker_id){
			// 启动时提前加载文件
			$includeFiles = isset(self::$config['include_files']) ? self::$config['include_files'] : [];
			self::startInclude($includeFiles);
			// 重新设置进程名称
			self::setWorkerProcessName(self::$config['worker_process_name'], $worker_id, self::$setting['worker_num']);
			// 设置worker工作的进程组
			self::setWorkerUserGroup(self::$config['www_user']);
			// 初始化整个应用对象
			self::$App = swoole_pack(self::$config['application_index']::getInstance($config=[]));
			// 启动的初始化函数
			$this->startctrl->workerStart($server,$worker_id);
		});

		/**
		 * worker进程停止回调函数
		 */
		$this->webserver->on('WorkerStop',function(HttpServer $server, $worker_id) {
			// worker停止的触发函数
			$this->startctrl->workerStop($server,$worker_id);
		});

		/**
		 * 接受http请求
		 */
		$this->webserver->on('request',function(Request $request, Response $response) {
			// google浏览器会自动发一次请求/favicon.ico,在这里过滤掉
			if($request->server['path_info'] == '/favicon.ico' || $request->server['request_uri'] == '/favicon.ico') {
            		return $response->end();
       		}
       		// 超全局变量server
       		Swfy::$server = $this->webserver;
			swoole_unpack(self::$App)->run($request, $response);
		});

		$this->webserver->start();
	}

}

$websock = new Webserver();
$websock->start();