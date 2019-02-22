<?php

namespace App\IQSocketControlBundle\Connector;


interface Constants
{
    const DEFAULT_IP = '192.168.0.100';

    // SNMP
    const SNMP_PATH_ROOT               = '0.1.3.6.1.4.1.21287.16';
    const SNMP_PATH_SET_OUTPUT         = '0.1.3.6.1.4.1.21287.16.1.0';
    const SNMP_PATH_GET_OUTPUT         = '0.1.3.6.1.4.1.21287.16.1.0';
    const SNMP_PATH_SET_RESTART        = '0.1.3.6.1.4.1.21287.19.5.0';
    const SNMP_PATH_SET_CANCEL_RESTART = '0.1.3.6.1.4.1.21287.16.25.0';
    const SNMP_PATH_GET_ACTIVE_RULES   = '0.1.3.6.1.4.1.21287.16.6.0';

    // XML
    const XML_STATUS_URL = 'http://%s/status.xml';
    const XML_LOST_PACKETS_RATIO = 'at%d';
}