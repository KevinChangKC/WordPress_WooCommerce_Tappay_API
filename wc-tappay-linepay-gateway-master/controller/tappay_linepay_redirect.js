jQuery(function() {
    var wc_tappay_redirect = {
        initialize: function () {
            // setup direct method api
            console.log('redirect initialize');
            TPDirect.redirect(tappay_params.url); 
        },
    };

    // override default place order button
    jQuery(document).ready(function() {
        console.log('redirect');
        wc_tappay_redirect.initialize();
    });
});
