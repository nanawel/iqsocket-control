<?php

namespace App\IQSocketControlBundle\Connector;


use App\IQSocketControlBundle\Exception\ConnectionException;

class IQSocket implements Constants
{
    /** @var string */
    protected $ipAddress;

    /** @var string */
    protected $community;

    /** @var \SNMP */
    protected $snmp;

    /**
     * IQSocket constructor.
     *
     * @param string $ipAddress
     * @param string $community
     */
    public function __construct(
        $ipAddress = self::DEFAULT_IP,
        $community = 'public'
    ) {
        $this->ipAddress = $ipAddress;
        $this->community = $community;
    }

    /**
     * @return array
     * @throws ConnectionException
     */
    public function getXmlStatus() {
        $url = sprintf(self::XML_STATUS_URL, $this->ipAddress);
        try {
            $statusXml = file_get_contents($url);
        }
        catch (\Throwable $e) {
            throw new ConnectionException("Could not retrieve IQSocket status at $url.", 0, $e);
        }
        if (!is_string($statusXml)) {
            throw new ConnectionException("Could not retrieve IQSocket status at $url.");
        }

        $xml = new \SimpleXMLElement($statusXml);

        $return = [];
        foreach ($xml as $node) {
            $return[$node->getName()] = (string) $node;
        }

        return $return;
    }

    /**
     * @return array
     * @throws ConnectionException
     */
    public function getSnmpStatus() {
        try {
            $status = $this->getSnmp()->walk(self::SNMP_PATH_ROOT);
        }
        catch (\SNMPException $e) {
            throw new ConnectionException(
                "Could not retrieve IQSocket status via SNMP from {$this->ipAddress}.", 0, $e
            );
        }

        return $status;
    }

    /**
     * @param bool $on
     * @return $this
     * @throws ConnectionException
     */
    public function setOutput($on = true) {
        $this->snmpSet(self::SNMP_PATH_SET_OUTPUT, 's', $on ? '1': '0');

        return $this;
    }

    /**
     * @return bool
     * @throws ConnectionException
     */
    public function isOn() {
        return !!$this->snmpGet(self::SNMP_PATH_GET_OUTPUT);
    }

    /**
     * @return $this
     * @throws ConnectionException
     */
    public function restart() {
        $this->snmpSet(self::SNMP_PATH_SET_RESTART, 's', '1');

        return $this;
    }

    /**
     * @return $this
     * @throws ConnectionException
     */
    public function cancelRestart() {
        $this->snmpSet(self::SNMP_PATH_SET_CANCEL_RESTART, 's', '1');

        return $this;
    }

    /**
     * @return int[]
     * @throws ConnectionException
     */
    public function getActiveRules() {
        // TODO Is this the actual format returned?
        return explode(',', $this->snmpGet(self::SNMP_PATH_GET_ACTIVE_RULES));
    }

    /**
     * @param int $ruleNum
     * @return int
     * @throws ConnectionException
     */
    public function getPacketLossRatio($ruleNum = 1) {
        try {
            return $this->getXmlStatus()[sprintf(self::XML_LOST_PACKETS_RATIO, $ruleNum)];
        }
        catch (\SNMPException $e) {
            throw new ConnectionException(
                "Could not retrieve IQSocket packet loss ratio via SNMP from {$this->ipAddress}.", 0, $e
            );
        }
    }

    /**
     * @return \SNMP
     */
    protected function getSnmp(): \SNMP {
        if (! $this->snmp) {
            $this->snmp = new \SNMP(\SNMP::VERSION_1, $this->ipAddress, $this->community);
        }

        return $this->snmp;
    }

    /**
     * @return mixed
     * @throws ConnectionException
     */
    protected function snmpGet($objectId) {
        try {
            if (false === ($value = $this->getSnmp()->get($objectId))) {
                throw new ConnectionException(
                    sprintf('Could not retrieve IQSocket value "%s" via SNMP from %s.', $objectId, $this->ipAddress)
                );
            }

            $value = trim(preg_replace('/^[A-Z ]+: "?(.*?)"?$/', '$1', $value));
        }
        catch (\Throwable $e) {
            throw new ConnectionException(
                sprintf('Could not retrieve IQSocket value "%s" via SNMP from %s.', $objectId, $this->ipAddress),
                0,
                $e
            );
        }

        return $value;
    }

    /**
     * @return $this
     * @throws ConnectionException
     */
    protected function snmpSet($objectId, $type, $value): IQSocket {
        try {
            if (false === $this->getSnmp()->set($objectId, $type, $value)) {
                throw new \SNMPException('Could not set value.');
            }
        }
        catch (\Throwable $e) {
            throw new ConnectionException(
                sprintf('Could not set IQSocket value "%s" via SNMP from %s.', $objectId, $this->ipAddress),
                0,
                $e
            );
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getIpAddress(): string {
        return $this->ipAddress;
    }

    /**
     * @return string
     */
    public function getCommunity(): string {
        return $this->community;
    }
}