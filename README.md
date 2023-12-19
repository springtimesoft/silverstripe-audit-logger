# Silverstripe Audit Logger

This module leverages much of the functionality of [silverstripe/auditor](https://github.com/silverstripe/silverstripe-auditor), however replaces the `silverstripe/auditor::AuditLogger` with `springtimesoft/silverstripe-audit-logger::AuditLogger` to write the log to `public/assets/audit.log` rather than the system log.

Once a day it will truncate the log automatically to only keep logs from the last 30 days. This can be set to `0` if no truncation is required. See [configuration](#configuration) below.


## Installation

```shell
composer require springtimesoft/silverstripe-audit-logger
```


## Requirements

- [silverstripe/auditor](https://github.com/silverstripe/silverstripe-auditor) (automatically imported)


## Usage

Please refer to [silverstripe/auditor](https://github.com/silverstripe/silverstripe-auditor) for usage.


## Configuration

The following default values can be updated via your yaml configuration:

```yaml
Springtimesoft\AuditLogger\AuditFactory:
  auditLog: ../public/assets/audit.log
  logLevel: info
  keepForDays: 30
```
