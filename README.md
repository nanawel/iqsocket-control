IQSocket Control
================

> Author: Anael O. nanawel@gmail.com

## Introduction

This tool helps you prevent erroneous socket restart from the IQSocket
because of lost ping responses by sending CANCEL RESTART commands via
SNMP to the device.

See http://www.iqtronic.com/wp-content/uploads/2015/07/User-manual-IQTS-IP200-v1_0r1.pdf

> :warning:
> HTTP authentication is currently **not** supported. Feel free to propose a PR.

## Requirements

- PHP 7.x with SNMP support (package `php-snmp` for Debian/Ubuntu,
same name under Archlinux)
- [Composer](https://getcomposer.org/download/)

## Installation

Install dependencies via Composer:
```
composer install
```

## Usage

> See also [Docker usage](#docker) below.

### Get status (XML or SNMP)

`iqsc:status [options] [--] <ip-address>...`

```
$ bin/console iqsc:status -h
Description:
  [IQSC] Get IQSocket status

Usage:
  iqsc:status [options] [--] <ip-address>...

Arguments:
  ip-address            IQSocket IP address or hostname [default: ["192.168.0.100"]]

Options:
  -s, --use-snmp        Use SNMP instead of XML
  -h, --help            Display this help message
```

Example:
```
$ bin/console iqsc:status 192.168.1.250
+---------------+-----------+--------------------+
| Device IP     | Key       | Value              |
+---------------+-----------+--------------------+
| 192.168.1.250 | devname   | IPSOCKET           |
| 192.168.1.250 | location  | Paris              |
| 192.168.1.250 | systimeup | 0days 0hrs 37mins  |
| 192.168.1.250 | systime   | 0days 0hrs 37mins  |
| 192.168.1.250 | fwver     | 1.0.4              |
| 192.168.1.250 | macaddr   | 00:19:51:10:13:87  |
| 192.168.1.250 | systemp   | 35.2               |
| 192.168.1.250 | lastevent | 0days 0hrs 26mins  |
| 192.168.1.250 | socket    | Turned ON          |
| 192.168.1.250 | rules     | 1                  |
| 192.168.1.250 | ip1       | 8.8.8.8            |
| 192.168.1.250 | evt1      | 0/                 |
| 192.168.1.250 | evs1      | 6                  |
| 192.168.1.250 | pl1       | 64/                |
| 192.168.1.250 | pr1       | 81/                |
| 192.168.1.250 | pt1       | 158/               |
| 192.168.1.250 | st1       | 48.7/              |
| 192.168.1.250 | at1       | 0                  |
| 192.168.1.250 | ip2       |                    |
| 192.168.1.250 | evt2      |                    |
| 192.168.1.250 | evs2      |                    |
| 192.168.1.250 | pl2       |                    |
| 192.168.1.250 | pr2       |                    |
| 192.168.1.250 | pt2       |                    |
| 192.168.1.250 | st2       |                    |
| 192.168.1.250 | at2       |                    |
| 192.168.1.250 | ip3       |                    |
| 192.168.1.250 | evt3      |                    |
| 192.168.1.250 | evs3      |                    |
| 192.168.1.250 | pl3       |                    |
| 192.168.1.250 | pr3       |                    |
| 192.168.1.250 | pt3       |                    |
| 192.168.1.250 | st3       |                    |
| 192.168.1.250 | at3       |                    |
+---------------+-----------+--------------------+
```

### Run watchdog

`iqsc:watchdog [options] [--] <ip-address>...`

```
$ bin/console iqsc:watchdog -h
Description:
  [IQSC] Run watchdog to prevent erroneous restarts

Usage:
  iqsc:watchdog [options] [--] <ip-address>...

Arguments:
  ip-address            IQSocket IP address or hostname [default: ["192.168.0.100"]]

Options:
  -d, --delay=DELAY     Polling delay [default: 30]
  -l, --loops=LOOPS     Max loops (0 for no limit) [default: 0]
  -h, --help            Display this help message
```

Example:
```
$ bin/console iqsc:watchdog -d30 192.168.1.250
Starting watchdog (see logs for details)
```

### Switch output ON/OFF

`iqsc:switch <status> <ip-address>...`

```
$ bin/console iqsc:switch -h
Description:
  [IQSC] Switch ON or OFF the output socket

Usage:
  iqsc:switch <status> <ip-address>...

Arguments:
  status                ON or OFF
  ip-address            IQSocket IP address or hostname [default: ["192.168.0.100"]]

Options:
  -h, --help            Display this help message
```

### Restart output

`iqsc:restart <ip-address>...`

```
$ bin/console iqsc:restart -h
Description:
  [IQSC] Restart IQSocket output socket

Usage:
  iqsc:restart <ip-address>...

Arguments:
  ip-address            IQSocket IP address or hostname [default: ["192.168.0.100"]]

Options:
  -h, --help            Display this help message
```

## Docker

You might prefer using a Docker container to run this tool.

To do so, just build the container using the provided [`Dockerfile`](Dockerfile):

```
docker build -t iqsocket-control .
```

Then run any of the commands above
- preferably using **ephemeral containers**: `--rm`
- with an **init wrapper** to handle syscalls (like Ctrl-C): `--init`
- using your **own user ID** to avoid running it as root: `--user $(id -u)`

```
docker run --rm --init --user $(id -u) iqsocket-control iqsc:<command> <options> <IP>
```

Examples:
- Get the status of device at 192.168.0.100
```
docker run --rm --init --user $(id -u) iqsocket-control iqsc:status 192.168.0.100
```

- Run watchdog on devices at 192.168.0.100 and 192.168.0.101
```
docker run --rm --init --user $(id -u) iqsocket-control iqsc:watchdog 192.168.0.100 192.168.0.101
```