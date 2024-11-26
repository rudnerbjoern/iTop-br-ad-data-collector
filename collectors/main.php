<?php

require_once(APPROOT . 'collectors/src/LDAPCollector.class.inc.php');
require_once(APPROOT . 'collectors/src/iTopPCLDAPCollector.class.inc.php');

Orchestrator::AddRequirement('1.0.0', 'ldap'); // LDAP support is required to run this collector

$iRank = 1;
Orchestrator::AddCollector($iRank++, iTopPCLDAPCollector::class);
