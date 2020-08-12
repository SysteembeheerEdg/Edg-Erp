require([
    'Magento_Ui/js/modal/confirm',
    'mage/translate'
], function (confirm, $t) {
    'use strict';

    function pimSyncConfirm(url) {
        confirm({
            content: $t('Are you sure you want to synchronize product data with PIM/Progress?'),
            actions: {
                confirm: function () {
                    location.href = url;
                }
            }
        });
    }
    
    window.pimSyncConfirm = pimSyncConfirm;
});
