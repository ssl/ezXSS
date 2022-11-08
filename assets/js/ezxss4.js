function request(action, data) {
    data["csrf"] = csrf;
    return $.ajax({
        type: "post",
        dataType: "json",
        url: action,
        data: data
    });
}

$(document).ready(function() {

    if(location.toString().split('/').pop() == 'dashboard') {
        request('/manage/dashboard/statistics', {}).then(function(r) {
            $.each( r, function( key, value ) {
                $("#"+key).html(value);
            });
        });
    }

    $("a[method='post']").click(function(e) {
        e.preventDefault();
        request($(this).attr('href'), {}).then(function(r) {
            location.reload();
        });
        
    });

});