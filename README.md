# iTop-br-ad-data-collector

Synchronization of PCs from an Active Directory/LDAP Directory

---

## Overview

The `iTop-br-ad-data-collector` is a tool designed to synchronize PC objects from an Active Directory (AD) or LDAP directory into iTop. It automates the creation and updating of PCs based on data retrieved from the directory.

---

## Features

- Automatic creation and update of PCs in iTop based on LDAP data.
- Fully automated creation of Synchronization Data Sources in iTop.
- Compatibility with Windows Active Directory.
- Supports pagination for retrieving large sets of items when the LDAP server enforces a limit on search results.

---

## Technical Requirements

- **Web access**: The collector must have access to iTop's API.
- **LDAP access**: The collector requires access to the LDAP/Active Directory server.
- **Compatibility**: This collector is specifically designed for Windows Active Directory.

---

## Configuration

The collector comes with a sample configuration file: `collectors/params.distrib.xml`. You can modify this file to suit your environment.
Create a copy as `conf/params.local.xml`.

### Sample Configuration

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!-- conf/params.local.xml - your specific configuration parameters -->
<parameters>

    <itop_url>https://localhost/iTop</itop_url>
    <itop_login>admin</itop_login>
    <itop_password>admin</itop_password>
    <itop_token />
    <itop_login_mode />
    <contact_to_notify>john.doe@demo.com</contact_to_notify>
    <synchro_user>admin</synchro_user>

    <ldapuri>ldaps://ldapserver:port</ldapuri>
    <ldaplogin>CN=ITOP-LDAP,DC=company,DC=com</ldaplogin>
    <ldappassword>password</ldappassword>
    <page_size>500</page_size>

    <!-- Parameters for PC synchronization -->
    <pc_options type="hash">
        <ldapdn>DC=company,DC=com</ldapdn>
        <ldapfilter>(&amp;(objectClass=computer)(!(UserAccountControl:1.2.840.113556.1.4.803:=2)))</ldapfilter>
        <!-- use the value of whenCreated as move2production in iTop -->
        <synchronize_move2production>yes</synchronize_move2production>
        <default_org_id>Demo</default_org_id>
        <default_status>production</default_status>
        <default_type>&lt;NULL&gt;</default_type>
    </pc_options>

    <!-- this mapping is applied to the value from distinguishedName to extract the status of the PC-->
    <status_mapping type="array">
        <pattern>/.*/production</pattern>
    </status_mapping>

    <!-- this mapping is applied to the value from distinguishedName to extract the type of the PC-->
    <type_mapping type="array">
        <pattern>/laptop/laptop</pattern>
        <pattern>/desktop/notebook</pattern>
    </type_mapping>

    <os_family_mapping type="array">
    <!-- Syntax /pattern/replacement where:
      any delimiter can be used (not only /) but the delimiter cannot be present in the "replacement" string
      pattern is a RegExpr pattern
      replacement is a sprintf string in which:
          %1$s will be replaced by the whole matched text,
          %2$s will be replaced by the first match group, if any group is defined in the RegExpr
          %3$s will be replaced by the second matched group, etc...
    -->
        <pattern>/macOS/MacOS</pattern>
        <pattern>/Microsoft Windows/Windows</pattern>
        <pattern>/Windows XP Professional/Windows</pattern>
        <pattern>/Windows 7 (Pro|Enterprise|Business)/Windows</pattern>
        <pattern>/Windows 10 (Pro|Enterprise|Business)/Windows</pattern>
        <pattern>/Windows 11 (Pro|Enterprise|Business)/Windows</pattern>
        <pattern>/.*/%1$s</pattern>
    </os_family_mapping>

    <os_version_mapping type="array">
        <pattern>/5.1 \(2600\)/Windows XP, Service Pack 3</pattern>
        <pattern>/6.1 \(7601\)/Windows 7, Service Pack 1</pattern>
        <pattern>/10.0 \(1\d+\)/Windows 10</pattern>
        <pattern>/10.0 \(2\d+\)/Windows 11</pattern>
        <pattern>/.*/%1$s</pattern>
    </os_version_mapping>

    <prefix></prefix>
    <json_placeholders>
        <prefix>$prefix$</prefix>
        <pcs_data_table>synchro_data_$prefix$ldap_pcs</pcs_data_table>
        <full_load_interval>604800</full_load_interval> <!-- 7 days (in seconds): 7*24*60*60 -->
        <synchro_status>production</synchro_status>
        <delete_policy>update</delete_policy>
        <delete_policy_update>status:obsolete</delete_policy_update>
        <user_delete_policy>administrators</user_delete_policy>
    </json_placeholders>
</parameters>
```

### Key Configuration Parameters

| Parameter                         | Description                                                                                            | Example Value                                                                |
| --------------------------------- | ------------------------------------------------------------------------------------------------------ | ---------------------------------------------------------------------------- |
| `itop_url`                        | URL of the iTop instance.                                                                              | `https://localhost/iTop`                                                     |
| `ldapuri`                         | LDAP server URI, including protocol and port.                                                          | `ldaps://ldapserver:port`                                                    |
| `ldapdn`                          | Base DN to search objects from.                                                                        | `DC=company,DC=com`                                                          |
| `ldapfilter`                      | LDAP search filter for PC objects.                                                                     | `(&(objectClass=computer)(!(UserAccountControl:1.2.840.113556.1.4.803:=2)))` |
| **`synchronize_move2production`** | Use the `whenCreated` value to populate the "Move to production" field in iTop.                        | `yes` or `no`                                                                |
| `default_org_id`                  | Default organization for imported PCs.                                                                 | `Demo`                                                                       |
| `default_status`                  | Default status for imported PCs. Possible values: `stock`, `implementation`, `production`, `obsolete`. | `production`                                                                 |
| `default_type`                    | Default type for imported PCs. Possible values: `laptop`, `desktop`, `<NULL>`.                         | `<NULL>`                                                                     |

**Note**: (!(UserAccountControl:1.2.840.113556.1.4.803:=2)) is used to not collect deactivated objects.

---

### Additional Features

1. **Mapping Rules**
   - **Status Mapping**: Extracts PC status from the `distinguishedName`.
   - **Type Mapping**: Identifies PC type (`laptop`, `desktop`) based on patterns in the `distinguishedName`.
   - **OS Family Mapping**: Normalizes operating system names.
   - **OS Version Mapping**: Translates raw OS version data into human-readable names.

2. **Pagination**
   - Supports efficient retrieval of large datasets by splitting the search into pages (`page_size` parameter).

3. **Notification**
   - Notify a designated contact in case of issues or updates using the `contact_to_notify` parameter.

---

## Usage

1. Clone the repository: `git clone https://github.com/rudnerbjoern/iTop-br-ad-data-collector.git`
2. Create the `conf/params.local.xml` file to match your environment.
3. Run the collector `php exec.php`
