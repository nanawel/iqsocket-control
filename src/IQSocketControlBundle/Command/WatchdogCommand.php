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

    const RULES_EVALUATION_AND = 'AND';
    const RULES_EVALUATION_OR  = 'OR';
    const RULES_EVALUATIONS  = [
        self::RULES_EVALUATION_AND,
        self::RULES_EVALUATION_OR
    ];
    const DEFAULT_RULES_EVALUATION = self::RULES_EVALUATION_AND;

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
                'rules-eval',
                'E',
                InputOption::VALUE_REQUIRED,
                sprintf(
                    'Rules evaluation (%s), as configured on the device.',
                    implode(', ', self::RULES_EVALUATIONS)
                ),
                self::DEFAULT_RULES_EVALUATION
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
                [IQSocket::DEFAULT_IP]
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $connectors = $this->initConnectors($input->getArgument('ip-address'));

        ini_set('max_execution_time', 0);

        $ruleEvaluation = strtoupper($input->getOption('rules-eval'));
        if (!in_array($ruleEvaluation, self::RULES_EVALUATIONS)) {
            throw new \InvalidArgumentException('Invalid value for rule-eval.');
        }

        $output->writeln('Starting watchdog (see logs for details)');
        $this->runLoop(
            $connectors,
            $input->getOption('delay'),
            $ruleEvaluation,
            $input->getOption('loops')
        );
    }

    /**
     * @param IQSocket[] $connectors
     * @param int $delay
     * @param string $rulesEvaluation
     * @param int $maxLoops
     */
    protected function runLoop(array $connectors, $delay, $rulesEvaluation, $maxLoops) {
        $this->logger->notice(sprintf('Starting watchdog with %d device(s)', count($connectors)));

        if ($maxLoops = max(0, $maxLoops)) {
            $loops = 0;
            $this->logger->notice(sprintf('%d loops max', $maxLoops));
        }
        while(true) {
            foreach ($connectors as $connector) {
                try {
                    $ruleHosts = $connector->getActiveRuleHosts();

                    if (!$ruleHosts) {
                        $this->logger->error('No active rule found for device ' . $connector->getIpAddress());
                        continue;
                    }

                    $successHosts = 0;
                    $packetLossFound = 0;
                    foreach ($ruleHosts as $ruleNum => $host) {
                        $this->logger->info(sprintf(
                            'Checking host %s for device %s (rule #%d)',
                            $host,
                            $connector->getIpAddress(),
                            $ruleNum
                        ));
                        if ($ratio = $connector->getPacketLossRatio($ruleNum)) {
                            $packetLossFound++;
                            $this->logger->notice(sprintf('Packet loss ratio = %d for host %s.', $ratio, $host));
                            if ($this->ping($host)) {
                                $successHosts++;
                                $this->logger->info(sprintf('Host %s is available.', $host));
                            }
                            else {
                                $this->logger->warning(sprintf('Host %s *does* seem unavailable.', $host));
                            }
                        }
                        else {
                            $this->logger->debug(sprintf('Packet loss ratio = 0 for host %s.', $host));
                        }
                    }
                    if (!$packetLossFound) {
                        $this->logger->debug(sprintf(
                            'No packet loss detected for device %s, good!',
                            $connector->getIpAddress()
                        ));
                        continue;
                    }

                    $shouldRestart = false;
                    // At least one host failed and we needed ANY rule to match => should restart
                    if ($successHosts < count($ruleHosts) && $rulesEvaluation == self::RULES_EVALUATION_OR) {
                        $shouldRestart = true;
                    }
                    // No host succeeded and we needed ALL rules to match => should restart
                    elseif ($successHosts == 0 && $rulesEvaluation == self::RULES_EVALUATION_AND) {
                        $shouldRestart = true;
                    }

                    if (!$shouldRestart) {
                        $this->logger->info(sprintf(
                            'Given ruleset did not match (evaluation = %s), sending restart cancellation signal.',
                            strtoupper($rulesEvaluation)
                        ));
                        $connector->cancelRestart();
                    }
                }
                catch (\Throwable $e) {
                    $this->logger->critical(sprintf(
                        'Error while attempting to access device at %s (%s)',
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
