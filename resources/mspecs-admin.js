jQuery(function($){
    $(document).on('click', '.mspecs-actions-wrapper button', function(e){
        var $button = $(this);

        // if(confirm('Please confirm that you would like to:\n\n' + $button.text().trim() + '\n')){
            $button.addClass('loading');

            var action = $button.attr('data-action');
            var nonce = $button.attr('data-nonce');

            $.post(mspecs_admin.ajax_url, {
                action: 'mspecs_admin_action',
                actionId: action,
                nonce: nonce,
            })
            .done(function(res){
                // TODO: Handle success
            })
            .fail(function(){
                // TODO: Handle failure
            })
            .always(function(){
                $button.removeClass('loading');
            });
            $('#lal_add_fa').parents().eq(3).show();   

        // }
    });
    
    $('.hide_on_load').parent().parent().toggleClass('hide');
    $('#api_auth_toggle').click(function(e) {
        $('.basic_auth').parent().parent().toggleClass('hide');
        $('.token_auth').parent().parent().toggleClass('hide');
    });
});