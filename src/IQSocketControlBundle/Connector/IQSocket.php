<?php

namespace App\IQSocketControlBundle\Connector;


use App\IQSocketControlBundle\Exception\ConnectionException;

class IQSocket
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
     */
    public function __construct(
        $ipAddress,
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
        $url = "http://{$this->ipAddress}/status.xml";
        $statusXml = file_get_contents($url);
        if (!is_string($statusXml)) {
            throw new ConnectionException("Could not retrieve status at $url.");
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
            $status = $this->getSnmp()->walk('0.1.3.6.1.4.1.21287.16');
        }
        catch (\SNMPException $e) {
            throw new ConnectionException("Could not retrieve status via SNMP from {$this->ipAddress}.", 0, $e);
        }

        return $status;
    }

    /**
     * @param bool $on
     * @return $this
     * @throws ConnectionException
     */
    public function setOutput($on = true) {
        try {
            if (false === $this->getSnmp()->set('0.1.3.6.1.4.1.21287.16.1.0', 's', $on ? '1': '0')) {
                throw new \SNMPException('Could not set value.');
            }
        }
        catch (\SNMPException $e) {
            throw new ConnectionException("Could not set output status via SNMP on {$this->ipAddress}.", 0, $e);
        }

        return $this;
    }

    /**
     * @return bool
     * @throws ConnectionException
     */
    public function isOn() {
        try {
            if (false === ($isOn = $this->getSnmp()->get('0.1.3.6.1.4.1.21287.16.1.0'))) {
                throw new \SNMPException('Could not get value.');
            }
        }
        catch (\SNMPException $e) {
            throw new ConnectionException("Could not get output status via SNMP on {$this->ipAddress}.", 0, $e);
        }

        return !!$isOn;
    }

    /**
     * @return $this
     * @throws ConnectionException
     */
    public function cancelRestart() {
        try {
            if (false === $this->getSnmp()->set('0.1.3.6.1.4.1.21287.16.25.0', 's', '1')) {
                throw new \SNMPException('Could not set value.');
            }
        }
        catch (\SNMPException $e) {
            throw new ConnectionException("Could not cancel restart via SNMP on {$this->ipAddress}.", 0, $e);
        }

        return $this;
    }

    /**
     * @param int $ruleNum
     * @return int
     * @throws ConnectionException
     */
    public function getLostPacketRatio($ruleNum = 1) {
        try {
            return $this->getXmlStatus()['at' . $ruleNum];
        }
        catch (\SNMPException $e) {
            throw new ConnectionException("Could not retrieve status via SNMP from {$this->ipAddress}.", 0, $e);
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