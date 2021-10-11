# EDG ERP

EDG ERP Module for Magento 2.

 - [Description](#markdown-header-description)
 - [How to use](#markdown-header-how-to-use)
 - [Tagmanager](#markdown-header-tagmanager)
 - [Styles](#markdown-header-styles)

## Description
This module is responsible for the order export. Orders will be exported by a cronjob or can be exported manually by running magerun2 sys:cron:run

## How to step over from Bold_PIM and Bold_PIMService to Edg_Erp and Edg_ErpService
Delete the Bold_PIM and Bold_PIMService from the app/code directory
- Run ```rm -rf app/code/Bold/PIM``` from the root of the project
- Run ```rm -rf app/code/Bold/PIMService``` from the root of the project

Install the latest versions of the Edg_Erp and Edg_ErpService modules by adding the modules and the corresponding repositories to the composer.json file
- ```“edg/module-erp-koppeling": “0.3.0”```
- ```“edg/module-erp-service": “0.1.1”```
- ```
  "edg-erp": {
      "type": "git",
      "url": "https://github.com/SysteembeheerEdg/Edg-Erp.git"
  },
  "edg-erp-service": {
      "type": "git",
      "url": "https://github.com/SysteembeheerEdg/Edg-Erp-Service.git"
  }

Install the new modules
- Run ```composer update edg/module-erp-koppeling edg/module-erp-service``` 
- Run ```php bin/magento setup:upgrade --keep-generated```

Now we have installed the latest versions of the Edg_Erp and Edg_ErpService modules. 
In case you have an extension module built you need to make it compatible with the new Edg_Erp and Edg_ErpService modules.

If this is the case then the next thing we need to do is make sure that modules that depended on either the Bold_PIM, Bold_PIMService or both 
need to be decoupled and made compatible with the newly installed Edg_Erp and Edg_ErpService modules. 
To check if there are modules that still depend on either the Bold_PIM, Bold_PIMService or both 
you can run ```php bin/magento setup:d:compile```. 

If this command successfully compiles the installation there are no (more) modules that depend on the deleted modules. If it doesn’t compile you’ll see something like:
- ```Fatal error:  Class 'Bold\PIM\Model\Convert\OrderToDataModel' not found in /home/experius/domains/edgkoppeling.nl.irn03.xpdev.nl/app/code/Edg/PIM/Model/Convert/OrderToDataModel.php on line 19```

If you already had the Bold_PIM and Bold_PIMService modules installed change the source_model of the bold_pim_articletype attribute by executing the following query:
- ```UPDATE `eav_attribute` SET `source_model` = 'Edg\\Erp\\Model\\SourceModel\\Eav\\ArticleType' WHERE attribute_code = 'bold_pim_articletype'```
- NOTE: This query needs double ```"\\"``` to make sure it is not seen as an escape character.

Now you should be ready to use the functionality :)

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

### Article Sync 

This module knows 3 ways for syncing the articles with Magento 

- Manually per article
- At 00:00 the full article list gets synchronized by a crontask based on all articles present in Magento 
- every 5 minutes the articles get synchronized by a crontask based on the queue 
- To test the article sync locally adjust the 'PIM environment tag(bold_orderexim/settings/environment_tag)' to 'educatheek_test'


