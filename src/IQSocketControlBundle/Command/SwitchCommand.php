<?php
namespace App\IQSocketControlBundle\Command;

use App\IQSocketControlBundle\Connector\IQSocket;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class SwitchCommand extends \Symfony\Component\Console\Command\Command
{
    const ON  = 'on';
    const OFF = 'off';

    protected function configure()
    {
        $this->setName('iqsc:switch')
            ->setDescription('[IQSC] Switch ON or OFF the output socket')
            ->addArgument(
                'status',
                InputArgument::REQUIRED,
                'ON or OFF'
            )
            ->addArgument(
                'ip-address',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'IQSocket IP address or hostname',
                [IQSocket::DEFAULT_IP]
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        switch (strtolower($status = $input->getArgument('status'))) {
            case self::OFF:
                $status = false;
                break;

            case self::ON:
                $status = true;
                break;

            default:
                $stderr = $output instanceof ConsoleOutputInterface
                    ? $output->getErrorOutput()
                    : $output;
                $stderr->writeln(sprintf('<error>Invalid value "%s"</error>', $status));
                return 1;
        }

        foreach ($input->getArgument('ip-address') as $ipAddress) {
            $connector = new IQSocket($ipAddress);
            $connector->setOutput($status);
        }
    }
}
