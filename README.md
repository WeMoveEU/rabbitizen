# Rabbitizen

An generic CiviCRM API function to consume messages from an AMQP queue and process them trough an arbitrary CiviCRM API action

The extension is licensed under [AGPL-3.0](LICENSE.txt).

## Requirements

* PHP v5.4+
* CiviCRM (5.0+)
* Composer

## Installation
### Installation (Web UI)

This extension has not yet been published for installation via the web UI.

### Installation (CLI, Zip)

Sysadmins and developers may download the `.zip` file for this extension and
install it with the command-line tool [cv](https://github.com/civicrm/cv).

```bash
cd <extension-dir>
cv dl eu.wemove.rabbitizen@https://github.com/WeMoveEU/eu.wemove.rabbitizen/archive/master.zip
```

### Installation (CLI, Git)

Sysadmins and developers may clone the [Git](https://en.wikipedia.org/wiki/Git) repo for this extension and
install it with the command-line tool [cv](https://github.com/civicrm/cv).

```bash
git clone https://github.com/WeMoveEU/eu.wemove.rabbitizen.git
cv en rabbitizen
```

### Install dependencies (amqp lib)

```bash
composer install
```

## Usage

c.f. API specs of Rabbitizen.consume

