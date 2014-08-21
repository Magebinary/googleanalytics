var GoogleAnalyticEnhancedECommerce = {


    //sssss
    setCurrency: function(Currency) {

        ga('set', '&cu', Currency);

    },

    add: function(Product, Order, Impression) {
        var Products = {};
        var Orders = {};

        ProductFieldObject = ['id', 'name', 'category', 'brand', 'variant', 'price', 'quantity', 'coupon', 'list', 'position', 'dimension1'];
        OrderFieldObject = ['id', 'affiliation', 'revenue', 'tax', 'shipping', 'coupon', 'list', 'step', 'option'];

        if (Product != null) {
            for (var key in Product) {
                for (i = 0; i < ProductFieldObject.length; i++) {
                    if (key.toLowerCase() == ProductFieldObject[i]) {
                        Products[key.toLowerCase()] = Product[key];
                    }
                }

            }
        }

        if (Order != null) {

            for (var key in Order) {
                for (i = 0; i < OrderFieldObject.length; i++) {
                    if (key.toLowerCase() == OrderFieldObject[i]) {
                        Orders[key.toLowerCase()] = Order[key];
                    }
                }

            }

            //console.log(Orders);

        }



        if (Impression == true) {
            ga('ec:addImpression', Products);
        } else {
            ga('ec:addProduct', Products);

        }


        //console.log("Added Product Details:")
        //console.log(Products);


    },

    addProductDetailView: function(Product) {
        this.add(Product);
        ga('ec:setAction', 'detail');
        ga('send', 'pageview');
    },

    addToCart: function(Product) {


        this.add(Product);

        ga('ec:setAction', 'add');
        ga('send', 'event', 'UX', 'click', 'Add to Cart'); // Send data using an event.

    },

    removeFromCart: function(Product) {

        this.add(Product);

        ga('ec:setAction', 'remove');
        ga('send', 'event', 'UX', 'click', 'Remove From cart'); // Send data using an event.

    },


    addProductImpression: function(Product) {
		//console.log(Product);
        this.add(Product,'',true);
        ga('send', 'pageview');

    },

	/**
	id, type, affiliation, revenue, tax, shipping and coupon.
	**/


    refundByOrderId: function(Order) {
    
    /**
	Refund an entire transaction.
	**/

        ga('ec:setAction', 'refund', {
            'id': Order.Id // Transaction ID is only required field for full refund.
        });
    },


    refundByProduct: function(Product,Order) {
    
    /**
	 Refund a single product.	
	**/
        this.add(Product);

        ga('ec:setAction', 'refund', {
            'id': Order.Id, // Transaction ID is required for partial refund.
        });
		

    },

    addProductClick: function(Product) {

    //console.log(Product);
	
	$('a[href$="'+Product.url+'"]').on("click", function(event) {
        
		GoogleAnalyticEnhancedECommerce.add(Product);
        ga('ec:setAction', 'click', {
            list: Product.list
        });
 
        ga('send', 'event', 'Product Click', 'click', Product.list, {
            'hitCallback': function() {
                return !ga.loaded;
            }
        });
	
	});

   },

    addPurchase: function(Product, Order) {

        this.add(Product);
        ga('ec:setAction', 'purchase', Order);
        ga('send', 'pageview');

    },

    addCheckout: function(Product) {

        this.add(Product);

        /***
        ga('ec:setAction','checkout',{
            'step': 1
            //'option':'Visa'
        });
***/

        ga('ec:setAction','checkout');

        ga('send', 'pageview');

    }







}