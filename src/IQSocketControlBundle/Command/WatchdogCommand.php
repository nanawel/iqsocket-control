<?php
namespace App\IQSocketControlBundle\Command;

use App\IQSocketControlBundle\Connector\IQSocket;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;


class WatchdogCommand extends \Symfony\Component\Console\Command\Command
{
    const DEFAULT_POLLING_DELAY = 30;

    /** @var \Psr\Log\LoggerInterface */
    protected $logger;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        ?string $name = null
    ) {
        parent::__construct($name);
        $this->logger = $logger;
    }

    protected function configure()
    {
        $this->setName('iqsc:watchdog')
            ->setDescription('[IQSC] Run watchdog to prevent erroneous restarts')
            ->addOption(
                'delay',
                'd',
                InputOption::VALUE_REQUIRED,
                'Polling delay',
                self::DEFAULT_POLLING_DELAY
            )
            ->addOption(
                'loops',
                'l',
                InputOption::VALUE_REQUIRED,
                'Max loops (0 for no limit)',
                0
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
        $connectors = $this->initConnectors($input->getArgument('ip-address'));

        ini_set('max_execution_time', 0);

        $output->writeln('Starting watchdog (see logs for details)');
        $this->runLoop($connectors, $input->getOption('delay'), $input->getOption('loops'));
    }

    /**
     * @param IQSocket[] $connectors
     * @param int $delay
     * @param int $maxLoops
     */
    protected function runLoop(array $connectors, $delay, $maxLoops) {
        $this->logger->notice(sprintf('Starting watchdog with %d device(s)', count($connectors)));

        if ($maxLoops = max(0, $maxLoops)) {
            $loops = 0;
            $this->logger->notice(sprintf('%d loops max', $maxLoops));
        }
        while(true) {
            foreach ($connectors as $connector) {
                try {
                    $ruleFound = false;
                    foreach ([1, 2, 3] as $ruleNum) {
                        if (strlen($host = $connector->getXmlStatus()["ip$ruleNum"])) {
                            $ruleFound = true;
                            $this->logger->info(sprintf(
                                'Checking host %s for device %s (rule #%d)',
                                $host,
                                $connector->getIpAddress(),
                                $ruleNum
                            ));
                            if ($ratio = $connector->getLostPacketRatio($ruleNum)) {
                                $this->logger->notice('Lost packet ratio = ' . $ratio);
                                if ($this->ping($host)) {
                                    $this->logger->info(sprintf(
                                        'Host %s is available, restart cancellation signal sent.',
                                        $host
                                    ));
                                    $connector->cancelRestart();
                                }
                                else {
                                    $this->logger->notice(sprintf(
                                        'Host %s *does* seem unavailable, restart cancellation signal skipped.',
                                        $host
                                    ));
                                }
                            }
                            else {
                                $this->logger->debug('Lost packet ratio = 0, good!');
                            }
                            break;
                        }
                    }
                    if (!$ruleFound) {
                        $this->logger->warning('No active rule found for device ' . $connector->getIpAddress());
                    }
                }
                catch (\Throwable $e) {
                    $this->logger->error(sprintf(
                        'Error while attempting to control device at %s (%s)',
                        $connector->getIpAddress(),
                        $e->getMessage()
                    ), ['exception' => $e]);
                }
            }

            if ($maxLoops && !$loops--) {
                $this->logger->info(sprintf('Loops limit reached, exiting.'));
                break;
            }
            $this->logger->info(sprintf('Sleeping for %d seconds...', $delay));
            sleep($delay);
        }
    }

    /**
     * @param string[] $ipAddresses
     */
    protected function initConnectors(array $ipAddresses) {
        $connectors = [];
        foreach ($ipAddresses as $ipAddress) {
            $connectors[$ipAddress] = new IQSocket($ipAddress);
        }

        return $connectors;
    }

    /**
     * @param string $host
     * @param int $count
     * @return bool
     */
    protected function ping($host, $count = 1) {
        $this->logger->debug("Pinging host $host $count time(s)...");
        $process = new Process(['/bin/ping', '-c', $count, $host]);

        return $process->run() === 0;
    }
}
