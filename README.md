# EDG ERP

EDG ERP Module for Magento 2.

 - [Description](#markdown-header-description)
 - [How to use](#markdown-header-how-to-use)
 - [Tagmanager](#markdown-header-tagmanager)
 - [Styles](#markdown-header-styles)

## Description
This module is responsible for the order export. Orders will be exported by a cronjob or can be exported manually by running magerun2 sys:cron:run

## How to use
- Delete the Bold_PIM and Bold_PIMService from the app/code directory
- Install the latest versions of the Edg_Erp and Edg_ErpService modules
- In case you have an extension module built you need to make it compatible with the new Edg_Erp and Edg_ErpService modules
- If you already had the Bold PIM and Bold PIM modules installed change the source_model of the bold_pim_articletype attribute by executing
```UPDATE `eav_attribute` SET `source_model` = 'Edg\\Erp\\Model\\SourceModel\\Eav\\ArticleType' WHERE attribute_code = 'bold_pim_articletype'```
This query needs double '\\' to make sure it is not seen as an escape character

#### Order export Configuration for staging environment
- SET ```Stores > Configuration > EDG PIM > Bold PIM Integration > Module Settings > Enable UploadOrders (export)``` to 'Yes'
- SET ```Stores > Configuration > EDG PIM > Bold PIM Integration > Module Settings > PIM environment tag``` to 'educatheek_m2'
- SET ```Stores > Configuration > EDG PIM > Bold PIM Integration > Module Settings > Export Order Type``` to educatheek_m2_order
- Make sure that for testing purposes the increment id is 9 digits long and does not contain a prefix like "staging_" in staging_100366209.

These settings are necessary for recognition of the environment when exporting orders, when the tag and type are not recognized by the environment, the orders will not be exported.

#### Run order export command
- run ```magerun2 sys:cron:run``` and give in the number of the task ```bold_pim_orderpush```

#### See which orders will be exported
- ```SELECT `main_table`.* FROM `sales_order` AS `main_table` WHERE (`status` IN('pending', 'pending', 'processing')) AND (`pim_is_exported` = '0')```

#### Order export logging
- In ```var/log/bold_pim/info.log``` the order export log files can be found
