define([
    'jquery','domReady'
    ], function ($,domReady) {
        "use strict";
        var enhanced_ecommerce = function () {
            this.tvc_get_impression = function(tvc_cat_products){
                var t_send_threshold=0;
                var t_prod_pos=0;
                var t_json_length=Object.keys(tvc_cat_products).length;
                for(var t_item in tvc_cat_products) {
                    t_send_threshold++;
                        var tvc_br =  tvc_cat_products[t_item].manufacturer_name;
					
                        ga("ec:addImpression", {   
                                "id"      : tvc_cat_products[t_item].tvc_id,
                                "name"    : tvc_cat_products[t_item].tvc_name,
                                "category": tvc_cat_products[t_item].tvc_c,
                                "price"   : tvc_cat_products[t_item].tvc_p,
                                "list"    : "Category Page",
                                "position": parseInt(t_item) + 1
                            });
                        if(t_json_length > 6 ){
                            if((t_send_threshold % 6 )==0){
                                t_json_length=t_json_length - 6;
                                ga("send", "event", "Enhanced-Ecommerce","load","product_impression_cp" , {"nonInteraction": 1});  
                            }
                        }
                        else{
                            t_json_length--;
                            if(t_json_length==0){
                                    ga("send", "event", "Enhanced-Ecommerce","load", "product_impression_cp", {"nonInteraction": 1});  
                            }
                        }
                }
                
            }
            this.tvc_impr_click = function(tvc_cat_products){
                jQuery('.product-item-photo, .product-item-link').on('click',function(){
                    var t_ID = jQuery(this).siblings().find('.price-final_price').attr('data-product-id');
                
                    for(var t_item in tvc_cat_products){
                        if(tvc_cat_products[t_item].tvc_id == t_ID){
                            ga("ec:addProduct", {
                                "id"       : tvc_cat_products[t_item].tvc_id,    
                                "name"     : tvc_cat_products[t_item].tvc_name,
                                "category" : tvc_cat_products[t_item].tvc_c,
                                "position" : parseInt(t_item)
                            });
                                ga("ec:setAction", "click",{'list':'Category Page'});
                                ga("send", "event", "Enhanced-Ecommerce", "click","product_click", {"nonInteraction": 1});
                        }
                    }
                });
            }
            this.tvc_pro_detail = function(tvc_products){
                ga("ec:addProduct", {
                    "id"       : tvc_products.tvc_id,    
                    "name"     : tvc_products.tvc_name,
                    "category" : tvc_products.tvc_c,
                    "price"    : tvc_products.tvc_p,
                });
                    ga("ec:setAction", "detail",{'list':tvc_products.tvc_list});
                    ga("send", "event", "Enhanced-Ecommerce", "load","product_detail", {"nonInteraction": 1});
            }
            this.tvc_add_to_cart = function(add_product){
                jQuery('#product-addtocart-button').on("click",function(){
                    ga("ec:addProduct", {
                        "id"       : add_product.tvc_id,    
                        "name"     : add_product.tvc_name,
                        "category" : add_product.tvc_c,
                        "price"    : add_product.tvc_p,
                        "quantity" : jQuery('#qty').val()
                    });
                    ga("ec:setAction", "add", {'list':add_product.tvc_list} );
                    ga("send", "event", "Enhanced-Ecommerce", "add_to_cart","product_add_to_cart", {"nonInteraction": 1});
                });
            }
            this.tvc_remove_cart = function(remove_items){
                jQuery('.action-delete').on("click",function(){
                    var t_id = jQuery(this).attr('data-post');
                    t_id = jQuery.parseJSON(t_id);
                    var tvc_quantity =  jQuery('.input-text.qty').val();
                    for(var t_item in remove_items){
                        if(remove_items[t_item].tvc_i == t_id.data.id){
                            ga("ec:addProduct", {
                                "id"       : remove_items[t_item].tvc_id,    
                                "name"     : remove_items[t_item].tvc_name,
                                "category" : remove_items[t_item].tvc_c,
                                "price"    : remove_items[t_item].tvc_p,
                                "quantity" : tvc_quantity
                            });
                        }
                    }
                    ga("ec:setAction", "remove");
                    ga("send", "event", "Enhanced-Ecommerce", "click","product_remove_from_cart", {"nonInteraction": 1});
                });
               
            }
			this.tvc_checkout_steps = function(tvc_checkout_prod, tvc_login_flag){
				var tvc_obj = this;
				if( tvc_login_flag &&
				   performance.navigation.redirectCount == 0 &&
				   performance.navigation.type == 0
				){
					this.call_step(tvc_checkout_prod,1);
					this.call_step(tvc_checkout_prod,2);
				}
				else{
					if(performance.navigation.redirectCount == 0 && performance.navigation.type == 0) {
						this.call_step(tvc_checkout_prod,1);
					}
					domReady(function () {
						jQuery('#customer-email').live("change",function(){
							tvc_obj.call_step(tvc_checkout_prod,2);
						});
						
					});
				}
				domReady(function () {
					jQuery('button[data-role="opc-continue"]').live("click",function(){
						tvc_obj.call_step(tvc_checkout_prod,3);
					});
					jQuery('.checkout').live("click",function(){
						tvc_obj.call_step(tvc_checkout_prod,4);
					});
				});	
				
			}
			this.call_step = function(tvc_checkout_prod,step){
				for(var t_item in tvc_checkout_prod) {
					ga("ec:addProduct", {
							"id"       : tvc_checkout_prod[t_item].tvc_id,    
							"name"     : tvc_checkout_prod[t_item].tvc_name,
							"category" : tvc_checkout_prod[t_item].tvc_c,
							"price"    : tvc_checkout_prod[t_item].tvc_p,
							"quantity" : tvc_checkout_prod[t_item].tvc_q
					});
				}
				ga("ec:setAction","checkout",{"step": step});
				ga("send", "event", "Enhanced-Ecommerce","load","checkout_step_"+step,{"nonInteraction": 1});
			}
            this.tvc_transaction_call = function(tvc_oo,tvc_oc){
                for(var i in tvc_oo)
                {
                    ga("ec:addProduct",{
                        "id"       : tvc_oo[i].tvc_id,
                        "name"     : tvc_oo[i].tvc_name,
                        "category" : tvc_oo[i].tvc_c,
                        "price"    : tvc_oo[i].tvc_p,
                        "quantity" : tvc_oo[i].tvc_Qty
                    });
                }
                ga("ec:setAction","purchase",{
                    "id"         : tvc_oc.tvc_id,
                    "affiliation": tvc_oc.tvc_affiliate,
                    "revenue"    : tvc_oc.tvc_revenue,
                    "tax"        : tvc_oc.tvc_tt,
                    "shipping"   : tvc_oc.tvc_ts
                });
                ga("set", "dimension2", tvc_oc.tvc_payment);
                ga("set", "dimension3", tvc_oc.tvc_shipping);
                ga("send", "event", "Enhanced-Ecommerce","load","purchase",{"nonInteraction": 1});
                ga("set", "dimension2", null);
                ga("set", "dimension3", null);
            }
        };
        return enhanced_ecommerce;
    });