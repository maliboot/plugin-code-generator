<?php

declare(strict_types=1);

namespace MaliBoot\PluginCodeGenerator\Adapter\Console;

use Hyperf\Command\Command as HyperfCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class PluginGenCurdConsole extends HyperfCommand
{
    public function __construct()
    {
        parent::__construct('plugin:gen-curd');
    }

    public function configure(): void
    {
        parent::configure();
        $this->setDescription('Create a new plugin curd');
        $this->addArgument('plugin', InputArgument::REQUIRED, '插件名称');
        $this->addArgument('table', InputArgument::REQUIRED, '表名称');

        $this->addOption('class', null, InputOption::VALUE_OPTIONAL, '类名称');
        $this->addOption('name', null, InputOption::VALUE_OPTIONAL, '业务名称');
        $this->addOption('pool', null, InputOption::VALUE_OPTIONAL, '数据库连接池', 'default');
        $this->addOption('path', null, InputOption::VALUE_OPTIONAL, '生成文件的路径');
        $this->addOption('prefix', null, InputOption::VALUE_OPTIONAL, '数据库前缀', '');
        $this->addOption('force', null, InputOption::VALUE_OPTIONAL, '是否强制覆盖', false);
        $this->addOption('table-mapping', 'M', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, '表映射关系');
        $this->addOption('property-case', null, InputOption::VALUE_OPTIONAL, '要使用哪个属性案例，0：蛇形案例，1：骆驼案例。');
        $this->addOption('cn-name', null, InputOption::VALUE_OPTIONAL, '中文业务名称');
        $this->addOption('platform', null, InputOption::VALUE_OPTIONAL, '平台', 'admin');
        $this->addOption('enable-domain-model', null, InputOption::VALUE_OPTIONAL, '是否支持DDD架构', 'false');
        $this->addOption('enable-query-command', null, InputOption::VALUE_OPTIONAL, '是否支持读写分离架构', 'false');
    }

    public function handle(): void
    {
        $commonFieldArguments = [
            'plugin' => $this->input->getArgument('plugin'),
            'table' => $this->input->getArgument('table'),
            '--pool' => $this->input->getOption('pool') ?? null,
            '--path' => $this->input->getOption('path') ?? null,
            '--prefix' => $this->input->getOption('prefix') ?? null,
        ];

        $commonArguments = $commonFieldArguments + [
            '--class' => $this->input->getOption('class') ?? null,
            '--name' => $this->input->getOption('name') ?? null,
            '--force' => $this->input->getOption('force') ?? false,
            '--enable-query-command' => $this->input->getOption('enable-query-command'),
            '--enable-domain-model' => $this->input->getOption('enable-domain-model'),
        ];

        $platform = $this->input->getOption('platform');
        $curdArguments = array_merge($commonArguments, ['--method' => 'curd']);

        if ($commonArguments['--enable-domain-model']) {
            $this->call('plugin:gen-model', $commonArguments);
        }

        $this->call('plugin:gen-do', $commonFieldArguments);

        $this->call('plugin:gen-vo', $commonArguments);
        $this->call('plugin:gen-command', $curdArguments);
        $this->call('plugin:gen-repo', $commonArguments);
        $this->call('plugin:gen-executor', $curdArguments + ['--platform' => $platform]);
        $this->call('plugin:gen-controller', $commonArguments + ['--platform' => $platform]);
        $this->call('plugin:gen-api', $commonArguments);
        $this->call('plugin:gen-rpc', $commonArguments);
        //        $this->call('plugin:gen-service', $commonArguments);
    }
}
