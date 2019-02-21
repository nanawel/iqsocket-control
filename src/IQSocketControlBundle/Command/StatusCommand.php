<?php
namespace App\IQSocketControlBundle\Command;

use App\IQSocketControlBundle\Connector\IQSocket;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class StatusCommand extends \Symfony\Component\Console\Command\Command
{
    protected function configure()
    {
        $this->setName('iqsc:status')
            ->setDescription('[IQSC] Get IQSocket status')
            ->addOption(
                'use-snmp',
                's',
                InputOption::VALUE_NONE,
                'Use SNMP instead of XML'
            )
            ->addArgument(
                'ip-address',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'IQSocket IP address or hostname',
                ['192.168.0.100']
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $table = new Table($output);
        $table->setHeaders(['Device IP', 'Key', 'Value']);

        foreach ($input->getArgument('ip-address') as $ipAddress) {
            $connector = new IQSocket($ipAddress);

            $status = $input->getOption('use-snmp') ? $connector->getSnmpStatus() : $connector->getXmlStatus();
            foreach ($status as $key => $value) {
                $table->addRow([$ipAddress, $key, $value]);
            }

            $table->render();
        }
    }
}
