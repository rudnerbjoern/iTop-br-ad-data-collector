<?php

class iTopPCLDAPCollector extends LDAPCollector
{

    protected $idx;

    /**
     * @var LookupTable For the OS Family / OS Version lookup
     */
    protected $oOSVersionLookup;

    /**
     * @var MappingTable Mapping table for the OS Families
     */
    static protected $oOSFamilyMappings = null;

    /**
     * @var MappingTable Mapping table for OS versions
     */
    static protected $oOSVersionMappings = null;

    /**
     * @var MappingTable Mapping table for status
     */
    static protected $oStatusMappings = null;

    protected $sLDAPDN;
    protected $sLDAPFilter;
    protected $sSynchronizeMove2Production;
    protected $aPCFields;
    protected $aPCDefaults;

    protected $aPCs;

    public function __construct()
    {
        parent::__construct();
        // let's read the configuration parameters
        // set the defaults
        $aLocalPCDefaults = [
            'synchronize_move2production' => 'no',
            'default_org_id' => 'Demo',
            'default_status' => 'production',
            'ldapdn' => 'DC=company,DC=com',
            'ldapfilter' => '(&(objectClass=computer)(!(UserAccountControl:1.2.840.113556.1.4.803:=2)))',
        ];
        $aPCOptions = Utils::GetConfigurationValue('pc_options', []);

        // Konfigurationswerte abrufen und mit Standardwerten kombinieren
        $aPCOptions = array_merge($aLocalPCDefaults, $aPCOptions);

        // Attribute direkt setzen
        $this->sSynchronizeMove2Production = $aPCOptions['synchronize_move2production'];
        $this->aPCDefaults = [
            'org_id' => $aPCOptions['default_org_id'],
            'status' => $aPCOptions['default_status'],
        ];
        $this->sLDAPDN = $aPCOptions['ldapdn'];
        $this->sLDAPFilter = $aPCOptions['ldapfilter'];

        $aLocalPCFields =  array(
            'primary_key' => 'objectsid',
            'name' => 'name',
            'dn' => 'distinguishedname',
            'osfamily_id' => 'operatingsystem',
            'osversion_id' => 'operatingsystemversion',
            'move2production' =>  'whencreated',
        );

        $this->aPCFields = array_merge($aLocalPCFields, Utils::GetConfigurationValue('pc_fields', array('primary_key' => 'objectsid')));
        $this->aPCs = array();
        $this->idx = 0;

        // Safety check
        if (!array_key_exists('primary_key', $this->aPCFields)) {
            Utils::Log(LOG_ERR, "PCs: You MUST specify a mapping for the field:'primary_key'");
        }
        if (!array_key_exists('name', $this->aPCFields)) {
            Utils::Log(LOG_ERR, "PCs: You MUST specify a mapping for the field:'name'");
        }

        // For debugging dump the mapping and default values
        $sMapping = '';
        foreach ($this->aPCFields as $sAttCode => $sField) {
            if (array_key_exists($sAttCode, $this->aPCDefaults)) {
                $sDefaultValue = ", default value: '{$this->aPCDefaults[$sAttCode]}'";
            } else {
                $sDefaultValue = '';
            }
            $sMapping .= "   iTop '$sAttCode' is filled from LDAP '$sField' $sDefaultValue\n";
        }
        foreach ($this->aPCDefaults as $sAttCode => $sDefaultValue) {
            if (!array_key_exists($sAttCode, $this->aPCFields)) {
                $sMapping .= "   iTop '$sAttCode' is filled with the constant value '$sDefaultValue'\n";
            }
        }
        Utils::Log(LOG_DEBUG, "PCs: Mapping of the fields:\n$sMapping");
    }

    /*
    public function AttributeIsOptional($sAttCode)
    {
        if (in_array($sAttCode, array(
            'brand_id',
            'model_id',
            'description',
            'business_criticity',
            'location_id',
            'bcm_rpo',
            'bcm_rto',
            'bcm_mtd',
            'rm_confidentiality',
            'rm_integrity',
            'rm_availability',
            'rm_authenticity',
            'rm_nonrepudiation',
            'system_landscape',
            'patchgroup_id',
            'patchmethod_id',
            'patchreboot_id',
            'backupmethod',
            'backupdescription',
            'tickets_list',
            'costcenter_id',
            'workstation_id'
        ))) return true;

        return parent::AttributeIsOptional($sAttCode);
    }
*/

    protected function GetData()
    {
        $aAttributes = array_values($this->aPCFields);
        $aList = $this->Search($this->sLDAPDN, $this->sLDAPFilter, $aAttributes);

        if ($aList !== false) {
            $iNumberOfPCs = count($aList) - 1;
            Utils::Log(LOG_INFO, "(PCs) Number of entries found on LDAP: " . $iNumberOfPCs);
        }
        return $aList;
    }

    /**
     * Helper method to extract the OSFamily information from the PC object
     * according to the 'os_family_mapping' mapping taken from the configuration
     * @param String $sRawValue
     * @return string The mapped OS Family or an empty string if nothing matches the extraction rules
     */
    static public function GetOSFamily($sRawValue)
    {
        if (self::$oOSFamilyMappings === null) {
            self::$oOSFamilyMappings =  new MappingTable('os_family_mapping');
        }
        $value = self::$oOSFamilyMappings->MapValue($sRawValue, '');

        return $value;
    }

    /**
     * Helper method to extract the Version information from the PC object
     * according to the 'os_version_mapping' mapping taken from the configuration
     * @param String $sRawValue
     * @return string The mapped OS Version or the original value if nothing matches the extraction rules
     */
    static public function GetOSVersion($sRawValue)
    {
        if (self::$oOSVersionMappings === null) {
            self::$oOSVersionMappings =  new MappingTable('os_version_mapping');
        }
        $value = self::$oOSVersionMappings->MapValue($sRawValue, $sRawValue); // Keep the raw value by default

        return $value;
    }

    /**
     * Helper method to extract the status information from the PC object
     * according to the 'status_mapping' mapping taken from the configuration
     * @param String $sRawValue
     * @return string The mapped OS Version or the original value if nothing matches the extraction rules
     */
    static public function GetStatus($sRawValue)
    {
        if (self::$oStatusMappings === null) {
            self::$oStatusMappings =  new MappingTable('status_mapping');
        }
        $value = self::$oStatusMappings->MapValue($sRawValue, $sRawValue); // Keep the raw value by default

        return $value;
    }

    public function Prepare()
    {
        if (! $aData = $this->GetData()) return false;

        foreach ($aData as $aPC) {
            if (isset($aPC[$this->aPCFields['primary_key']][0]) && $aPC[$this->aPCFields['primary_key']][0] != "") {
                $aValues = array();

                // Primary key must be the first column
                $sPrimaryKey = "";
                if ($this->aPCFields['primary_key'] == "objectsid") {
                    $sPrimaryKey = $this->BinaryToSID($aPC[$this->aPCFields['primary_key']][0]);
                } else {
                    $sPrimaryKey = $aPC[$this->aPCFields['primary_key']][0];
                }

                $aValues['primary_key'] = $sPrimaryKey;

                // First set the default values (as well as the constant values for fields which are not collected)
                foreach ($this->aPCDefaults as $sFieldCode => $sDefaultValue) {
                    $aValues[$sFieldCode] = $sDefaultValue;
                }

                // Then read the actual values (if any)
                foreach ($this->aPCFields as $sFieldCode => $sLDAPAttribute) {
                    if ($sFieldCode == 'primary_key') continue; // Already processed, must be the first column

                    $sDefaultValue = isset($this->aPCDefaults[$sFieldCode]) ? $this->aPCDefaults[$sFieldCode] : '';
                    $sFieldValue = isset($aPC[$sLDAPAttribute][0]) ? $aPC[$sLDAPAttribute][0] : $sDefaultValue;

                    $aValues[$sFieldCode] = $sFieldValue;
                }

                // Process mapping of status from dn
                if (isset($aValues['dn'])) {
                    $sStatus = static::GetStatus($aValues['dn']);
                    $aValues['status'] = $sStatus;
                    unset($aValues['dn']);
                }

                if ($this->sSynchronizeMove2Production == "yes") {
                    if (isset($aValues['move2production'])) {
                        $aValues['move2production'] = $this->FormatDate($aValues['move2production']);
                    }
                } else {
                    unset($aValues['move2production']);
                }

                $this->aPCs[] = $aValues;
            }
        }
        return true;
    }

    public function Fetch()
    {
        if ($this->idx < count($this->aPCs)) {
            $aData = $this->aPCs[$this->idx];
            $this->idx++;

            if ($aData !== false) {
                // Then process each collected OS
                $aData['osfamily_id'] = static::GetOSFamily($aData['osfamily_id'], '');
                $aData['osversion_id'] = static::GetOSVersion($aData['osversion_id'], '');
            }


            return $aData;
        }
        return false;
    }





    protected function MustProcessBeforeSynchro()
    {
        // We must reprocess the CSV data obtained from ad
        // to lookup the OSFamily/OSVersion in iTop
        return true;
    }

    protected function InitProcessBeforeSynchro()
    {
        // Retrieve the identifiers of the OSVersion since we must do a lookup based on two fields: Family + Version
        // which is not supported by the iTop Data Synchro... so let's do the job of an ETL
        $this->oOSVersionLookup = new LookupTable('SELECT OSVersion', array('osfamily_id_friendlyname', 'name'));
    }

    protected function ProcessLineBeforeSynchro(&$aLineData, $iLineIndex)
    {
        // Process each line of the CSV
        $this->oOSVersionLookup->Lookup($aLineData, array('osfamily_id', 'osversion_id'), 'osversion_id', $iLineIndex);
    }
}
