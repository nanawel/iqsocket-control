<?php
namespace App\IQSocketControlBundle\Command;

use App\IQSocketControlBundle\Connector\IQSocket;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class RestartCommand extends \Symfony\Component\Console\Command\Command
{
    protected function configure()
    {
        $this->setName('iqsc:restart')
            ->setDescription('[IQSC] Restart IQSocket output socket')
            ->addArgument(
                'ip-address',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'IQSocket IP address or hostname',
                [IQSocket::DEFAULT_IP]
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $table = new Table($output);
        $table->setHeaders(['Device IP', 'Key', 'Value']);

        foreach ($input->getArgument('ip-address') as $ipAddress) {
            $connector = new IQSocket($ipAddress);
            $connector->restart();
        }
    }
}
