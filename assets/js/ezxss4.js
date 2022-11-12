function request(action, data) {
    data["csrf"] = csrf;
    var post = $.ajax({
        type: "post",
        dataType: "json",
        url: action,
        data: data
    }).always(function (data, statusText, xhr) {
        if(statusText !== 'success') {
            location.reload();
        }
    });
    return post;
}

$(document).ready(function () {

    if (location.toString().split('/').pop() == 'dashboard') {
        request('/manage/api/statistics', {}).then(function (r) {
            $.each(r, function (key, value) {
                $("#" + key).html(value);
            });
        });
    }

    $("a[method='post']").click(function (e) {
        e.preventDefault();
        request($(this).attr('href'), {}).then(function (r) {
            location.reload();
        });
    });

    $('#method').on('change', function () {
        $('#method-content-1').hide();
        $('#method-content-2').hide();
        $('#method-content-3').hide();
        $('#method-content-4').hide();
        $('#method-disabled').hide();
        $('#method-loading').hide();

        var alertId = this.value;
        request('/manage/api/getAlertStatus', { alertId: alertId }).then(function (r) {
            if (r.enabled == 1) {
                $('#method-content-' + alertId).show();
            } else {
                $('#method-disabled').show();
            }
        });
    });

});