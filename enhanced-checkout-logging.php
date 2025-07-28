<?php
// Enhanced Checkout Logging - Add to functions.php
add_action('wp_footer', function() {
    if (!is_checkout()) return;
    ?>
    <script>
    console.log('🔍 Enhanced checkout logging initialized');
    
    // Monitor WooCommerce checkout events
    jQuery(document.body).on('submit_checkout', function() {
        console.log('✅ submit_checkout event fired');
    });
    
    jQuery(document.body).on('checkout_error', function(event, data) {
        console.log('❌ checkout_error event fired:', data);
    });
    
    jQuery(document.body).on('updated_checkout', function() {
        console.log('🔄 updated_checkout event fired');
    });
    
    // Monitor form submission directly
    jQuery(document).on('submit', 'form.woocommerce-checkout', function(e) {
        console.log('📝 Form submit event triggered');
        console.log('📝 Form action:', this.action);
        console.log('📝 Form method:', this.method);
        
        // Check if form is valid
        var isValid = true;
        jQuery(this).find('[required]').each(function() {
            if (!jQuery(this).val()) {
                console.log('❌ Required field empty:', jQuery(this).attr('name'));
                isValid = false;
            }
        });
        console.log('📝 Form validation check:', isValid);
    });
    
    // Monitor button state changes
    var orderButton = jQuery('button[name="woocommerce_checkout_place_order"]');
    if (orderButton.length) {
        console.log('🔘 Order button found:', orderButton.length);
        
        // Check button attributes
        console.log('🔘 Button type:', orderButton.attr('type'));
        console.log('🔘 Button disabled:', orderButton.prop('disabled'));
        console.log('🔘 Button form:', orderButton.attr('form'));
        
        // Monitor button clicks more thoroughly
        orderButton.on('click', function(e) {
            console.log('🖱️ Button clicked - event details:');
            console.log('🖱️ Event type:', e.type);
            console.log('🖱️ Event target:', e.target);
            console.log('🖱️ Event currentTarget:', e.currentTarget);
            console.log('🖱️ Event defaultPrevented:', e.defaultPrevented);
            console.log('🖱️ Button disabled state:', jQuery(this).prop('disabled'));
            
            // Check if click is being prevented
            setTimeout(function() {
                console.log('🖱️ After click - form submission status check');
                var form = jQuery('form.woocommerce-checkout');
                console.log('🖱️ Form exists:', form.length > 0);
                console.log('🖱️ Form action:', form.attr('action'));
            }, 100);
        });
    }
    
    // Monitor for JavaScript errors
    window.addEventListener('error', function(e) {
        console.log('🚨 JavaScript error:', e.message);
        console.log('🚨 Error file:', e.filename);
        console.log('🚨 Error line:', e.lineno);
    });
    
    // Check WooCommerce checkout object
    if (typeof wc_checkout_params !== 'undefined') {
        console.log('⚙️ WooCommerce checkout params found');
        console.log('⚙️ AJAX URL:', wc_checkout_params.ajax_url);
        console.log('⚙️ Checkout URL:', wc_checkout_params.checkout_url);
    } else {
        console.log('⚠️ WooCommerce checkout params not found');
    }
    
    // Monitor for any AJAX requests
    jQuery(document).ajaxSend(function(event, xhr, settings) {
        if (settings.url.includes('checkout') || settings.url.includes('wc-ajax')) {
            console.log('📡 AJAX request:', settings.url);
            console.log('📡 AJAX method:', settings.type);
            console.log('📡 AJAX data:', settings.data);
        }
    });
    
    jQuery(document).ajaxComplete(function(event, xhr, settings) {
        if (settings.url.includes('checkout') || settings.url.includes('wc-ajax')) {
            console.log('📡 AJAX response received for:', settings.url);
            console.log('📡 Response status:', xhr.status);
        }
    });
    
    // Check for WooCommerce Blocks specific issues
    if (typeof wp !== 'undefined' && wp.data && wp.data.subscribe) {
        console.log('🧱 WooCommerce Blocks detected');
        wp.data.subscribe(function() {
            var checkoutState = wp.data.select('wc/store/checkout');
            if (checkoutState) {
                console.log('🧱 Checkout state updated');
                console.log('🧱 Has errors:', checkoutState.hasErrors());
                console.log('🧱 Is idle:', checkoutState.isIdle());
            }
        });
    }
    
    console.log('🔍 Enhanced logging setup complete');
    </script>
    <?php
});