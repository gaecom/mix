<?php

namespace apps\daemon\commands;

use mix\console\Command;
use mix\console\ExitCode;
use mix\facades\Error;
use mix\facades\Input;
use mix\facades\Output;
use mix\process\Process;

/**
 * 这是一个单进程守护进程的范例
 * @author 刘健 <coder.liu@qq.com>
 */
class SingleCommand extends BaseCommand
{

    // 初始化事件
    public function onInitialize()
    {
        parent::onInitialize(); // TODO: Change the autogenerated stub
        // 获取程序名称
        $this->programName = Input::getCommandName();
        // 设置pidfile
        $this->pidFile = "/var/run/{$this->programName}.pid";
    }

    // 启动
    public function actionStart()
    {
        // 预处理
        if (!parent::actionStart()) {
            return ExitCode::UNSPECIFIED_ERROR;
        }
        // 修改进程名称
        Process::setName("mix-daemon: {$this->programName}");
        // 开始工作
        $this->startWork();
        // 返回退出码
        return ExitCode::OK;
    }

    // 开始工作
    public function startWork()
    {
        try {
            $this->work();
        } catch (\Exception $e) {
            // 处理异常
            Error::handleException($e);
            // 休息一会，避免 cpu 出现 100%
            sleep(1);
            // 重建流程
            $this->startWork();
        }
    }

    // 执行工作
    public function work()
    {
        // 模型内使用长连接版本的数据库组件，这样组件会自动帮你维护连接不断线
        $tableModel = new \apps\common\models\TableModel();
        // 循环执行任务
        while (true) {
            // 执行业务代码
            // ...
        }
    }

}
