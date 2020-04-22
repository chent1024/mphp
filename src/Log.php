<?php
namespace mphp;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\FirePHPHandler;

class Log {
    protected $logger;
    /**
    DEBUG = 100;
    INFO = 200;
    NOTICE = 250;
    WARNING = 300;
    ERROR = 400;
    CRITICAL = 500;
    ALERT = 550;
    EMERGENCY = 600;
     * @var
     */
    protected $level = 100;
    public function init($conf = 'app.log')
    {
        $config = config($conf);
        $path = $config['path'];
        $level = $config['level'];
        if(!$config){
            $path = $config['path'];
            $level = $this->level;
        }
        $file = $path.DIRECTORY_SEPARATOR.date('Y-m-d').'.log';

        $this->logger = new Logger('mphp');
        $this->logger->pushHandler(new StreamHandler($file, $level));
        $this->logger->pushHandler(new FirePHPHandler());
    }

  public function __call($name, $arguments)
  {
      if(!method_exists($this->logger, $name)){
          throw new \Exception("Cannot find method Logger->{$name}.");
      }
      return $this->logger->$name(...$arguments);
  }

}