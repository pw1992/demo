<?php

class MultiProgress2{
    const FILE_ENGINE = 'file';
    const REDIS_ENGINE = 'redis';
    const PROGRESS_ENGINE = 'progress';

    private $processNum = 0;
    private $function = false;
    private $callback = false;

    private $queueFile = false;
    private $queueEmploy = false;

    private $progressQueue = false;

    private $redis = false;
    public $redisListKey = 'multiProgress:queue';
    public $redisIp = '127.0.0.1';
    public $redisPort = '6379';
    public $redisDB = 1;
    public $redisPassword = 'root';


    public function __construct($processNum, $function, $callback = false, $queueEngine = 'file'){
        if ($processNum < 1) {
            echo "开启的进程数量不能低于1个；\n";
            exit();
        }
        $this->processNum = $processNum;
        $this->function = $function;
        $this->callback = $callback;
        $this->queueEngine = $queueEngine;
    }


    public function __destruct(){
        switch ($this->queueEngine){
            case static::FILE_ENGINE:
                $this->deleteQueueFile();
                break;
            case static::REDIS_ENGINE:
                $this->disconnectRedis();
                break;
            case static::PROGRESS_ENGINE:
                $this->deleteProgressQueue();
                break;
        }
    }


    private function deleteQueueFile(){
        try{
            unlink($this->queueFile);
            unlink($this->queueEmploy);
        }catch (Exception $err) {
            $processId = getmypid();
            echo "pid:$processId  ->队列文件已删除！\n";
        }
    }


    private function disconnectRedis(){
        try{
            $this->redis->del($this->redisListKey);
            $this->redis->close();
        }catch (Exception $err) {
            print_r($err->getMessage()."\n");
        }
    }


    private function deleteProgressQueue(){
        try{
            \msg_remove_queue($this->progressQueue);
        }catch (Exception $err){
            print_r($err->getMessage()."\n");
        }
    }


    private function createQueueFile(){
        $processId = getmypid();
        if (strtoupper(substr(PHP_OS,0,3))==='WIN'){
            //Windows
            $path = dirname(__FILE__)."\\";
        }else{
            //Linux
            $path = "/tmp/";
        }
        $this->queueFile = $path."multi_progress_queue_$processId";
        $this->queueEmploy = $path."multi_progress_queue_employ_$processId";
        file_put_contents($this->queueFile, "");
        file_put_contents($this->queueEmploy, "");
    }


    private function connectRedis(){
        $redis = new \Redis();
        try{
            $redis->connect($this->redisIp, $this->redisPort, 1);
        }catch (Exception $err){
            print_r("Redis connect error\n");
            exit();
        }
        if ($this->redisPassword){
            try{
                $redis->auth($this->redisPassword);
            }catch (Exception $err){
                print_r("Redis Auth Error\n");
                exit();
            }
        }

        try{
            $redis->ping();
        }catch (Exception $err){
            print_r($err->getMessage()."\n");
            exit();
        }
        $redis->select($this->redisDB);
        $this->redis = $redis;
    }


    private function createProgressQueue(){
        //生成一个消息队列的key
        $msg_key = ftok(__FILE__, 'a');
        //产生一个消息队列
        $this->progressQueue = \msg_get_queue($msg_key, 0666);

        //检测一个队列是否存在 ,返回boolean值
        if (!msg_queue_exists($msg_key)){
            print_r("Process queue does not exist");
            exit();
        }
    }


    private function putFileQueue($args){
        if (!$this->queueFile)
            $this->createQueueFile();
        file_put_contents($this->queueFile, json_encode($args)."\n", FILE_APPEND);
    }


    private function putRedisQueue($args){
        if (!$this->redis)
            $this->connectRedis();
        $this->redis->rPush($this->redisListKey, json_encode($args));
    }


    private function putProgressQueue($args){
        if (!$this->progressQueue)
            $this->createProgressQueue();
        return \msg_send($this->progressQueue,1, json_encode($args));
    }


    private function popFileQueue(){
        $parameter = false;
        $pid = getmypid();
        do{
            if (!is_file($this->queueFile) || !is_file($this->queueEmploy))
                break;

            if (file_get_contents($this->queueEmploy) == 0) {
                file_put_contents($this->queueEmploy, $pid);
                $data = file_get_contents($this->queueFile);
                if (!$data)
                    break;

                $data = explode("\n", trim($data));
                $parameter = $data[0];
                unset($data[0]);
                file_put_contents($this->queueFile, implode("\n", $data));
                $parameter = json_decode($parameter, true);
                break;
            }
            sleep(rand(1,3));
        }while(true);
        file_put_contents($this->queueEmploy, 0);
        return $parameter;
    }


    private function popRedisQueue(){
        try{
            $parameter = $this->redis->lPop($this->redisListKey);
            if ($parameter)
                $parameter = json_decode($parameter, true);
        }catch (Exception $err){
            $parameter = false;
        }
        return $parameter;
    }


    private function popProgressQueue(){
        $status = msg_stat_queue($this->progressQueue);
        if ($status['msg_qnum'] < 1)
            return false;

        $parameter = false;
        \msg_receive($this->progressQueue, 0, $msg_type, 1024, $parameter);
        if (!$parameter)
            return false;
        return json_decode($parameter, true);
    }


    public function put(){
        //获取传递参数的个数
        $count = func_num_args();
        //遍历参数
        $args = [];
        for ($i = 0; $i < $count; $i++) {
            //获取参数
            $args[] = func_get_arg($i);
        }

        switch ($this->queueEngine){
            case static::FILE_ENGINE:
                $this->putFileQueue($args);
                break;
            case static::REDIS_ENGINE:
                $this->putRedisQueue($args);
                break;
            case static::PROGRESS_ENGINE:
                $this->putProgressQueue($args);
                break;
        }
    }


    public function get(){
        switch ($this->queueEngine){
            case static::FILE_ENGINE:
                return $this->popFileQueue();
                break;
            case static::REDIS_ENGINE:
                return $this->popRedisQueue();
                break;
            case static::PROGRESS_ENGINE:
                return $this->popProgressQueue();
                break;
        }
        return false;
    }


    private function createThread($processNum) {
        for ($process = 1; $process <= $processNum; $process++) {
            $pid = \pcntl_fork();
            if ($pid == -1) {
                die("could not fork\n");
            } elseif ($pid) {
                sleep(1);
            } else {
                $function = $this->function;
                $callback = $this->callback;
                do{
                    $parameter = $this->get();
                    if (!$parameter)
                        break;

                    try{
                        $response = $function(...$parameter);
                        $status = true;
                    }catch (\Exception $err){
                        $status = false;
                        echo "Function Error: {$err->getMessage()}\n";
                        var_dump($err->getMessage());
                    }


                    if ($status && $callback) {
                        try{
                            $callback(...$response);
                        }catch (\Exception $err ){
                            echo "Callback Error\n";
                        }
                    }

                }while(true);

                exit();
            }
        }

        // 等待进程结束
        while (pcntl_waitpid(0, $status) != -1) {
            $status = pcntl_wexitstatus($status);
        }
    }


    public function run(){
        $this->createThread($this->processNum);
    }
}



