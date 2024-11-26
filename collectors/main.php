<?php

// Initialize collection plan
require_once(APPROOT . 'collectors/src/LDAPCollectionPlan.class.inc.php');
require_once(APPROOT . 'core/orchestrator.class.inc.php');

Orchestrator::AddRequirement('1.0.0', 'ldap'); // LDAP support is required to run this collector
Orchestrator::UseCollectionPlan('LDAPCollectionPlan');
