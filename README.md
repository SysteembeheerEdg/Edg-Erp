# Bold PIM

Bold PIM Module for Magento 2.

 - [Description](#markdown-header-description)
 - [How to use](#markdown-header-how-to-use)
 - [Tagmanager](#markdown-header-tagmanager)
 - [Styles](#markdown-header-styles)

## Description
This module is responsible for the order export. Orders will be exported by a cronjob or can be exported manually by running magerun2 sys:cron:run

## How to use

#### Order export Configuration for staging environment
- Set ```Stores > Configuration > EDG PIM > Bold PIM Integration > Module Settings > Enable UploadOrders (export)``` to 'Yes'
- Set ```Stores > Configuration > EDG PIM > Bold PIM Integration > Module Settings > PIM environment tag``` to 'educatheek_m2'
- SET ```Stores > Configuration > EDG PIM > Bold PIM Integration > Module Settings > Export Order Type``` to educatheek_m2_order

These settings are necessary for recognition of the environment when exporting orders, when the tag and type are not recognized by the environment, the orders will not be exported.

#### Run order export command
- run ```magerun2 sys:cron:run``` and give in the number of the task ```bold_pim_orderpush```

#### See which orders will be exported
- ```SELECT `main_table`.* FROM `sales_order` AS `main_table` WHERE (`status` IN('pending', 'pending', 'processing')) AND (`pim_is_exported` = '0')```

#### Order export logging
- In ```var/log/bold_pim/info.log``` the order export log files can be found
