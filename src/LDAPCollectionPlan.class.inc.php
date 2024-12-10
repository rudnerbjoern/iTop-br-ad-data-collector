<?php

class LDAPCollectionPlan extends CollectionPlan
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    public function Init(): void
    {
        parent::Init();
    }

    /**
     * @inheritdoc
     */
    public function AddCollectorsToOrchestrator(): bool
    {
        Utils::Log(LOG_INFO, "---------- LDAP Collectors to launched ----------");

        return parent::AddCollectorsToOrchestrator();
    }
}
