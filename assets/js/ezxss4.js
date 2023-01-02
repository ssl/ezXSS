function request(action, data = {}) {
    data['csrf'] = csrf;
    return $.ajax({
        type: 'post',
        dataType: 'json',
        url: action,
        data: data
    }).always(function (data, statusText, xhr) {
        if (statusText !== 'success') {
            //window.location.href = window.location.href;
        }
    });
}

$(document).ready(function () {

    if (location.toString().split('/')[4] === 'dashboard') {
        request('/manage/api/statistics', { page: location.toString().split('/').pop() }).then(function (r) {
            $.each(r, function (key, value) {
                $('#' + key).html(value);
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
        $('.method-content').hide();
        $('#method-pick').hide();

        const alertId = this.value;
        request('/manage/api/getAlertStatus', { alertId: alertId }).then(function (r) {
            if (r.enabled == 1) {
                $('#method-content-' + alertId).show();
            } else {
                $('#method-disabled').show();
            }
        });
    });

    $('#payloadList').on('change', function () {
        window.location.href = '/manage/payload/edit/' + this.value;
    });

    $('#payloadListReport').on('change', function () {
        const urlParams = new URLSearchParams(window.location.search);
        const addValue = urlParams.get('archive') == '1' ? '?archive=1' : '';
        if (this.value !== '0') {
            window.location.href = '/manage/reports/list/' + this.value + addValue;
        } else {
            window.location.href = '/manage/reports/all' + addValue;
        }
    });

    $(".remove-item").click(function () {
        const data = $(this).attr('data');
        const divId = $(this).attr('divid');
        const type = (divId.charAt(0) === "p" ? "pages" : (divId.charAt(0) === "w" ? "whitelist" : (divId.charAt(0) === "b" ? "blacklist" : "")));
        const id = window.location.href.split("/").length > 0 ? window.location.href.split("/")[window.location.href.split("/").length - 1] : "";
        request('/manage/payload/removeItem/' + id, { data: data, type: type }).then(function (r) {
            $('#' + divId).fadeOut("slow", function () { });
        });

    });

    $("#openGetChatId").click(function () {
        var bottoken = $("#telegram_bottoken").val();
        request("/manage/api/getchatid", { bottoken: bottoken }).then(function (r) {
            if (r.echo.startsWith('chatId:')) {
                $('#getChatId').modal('hide');
                $("#chatid").val(r.echo.replace('chatId:', ''));
            } else {
                $('#getChatId').modal('show');
                $("#getChatIdBody").html(r.echo);
            }
        });
    });

    $(".generate-password").click(function () {
        var password = "";
        const possible = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()_+";

        for (var i = 0; i < 20; i++) {
            password += possible.charAt(Math.floor(Math.random() * possible.length));
        }

        $("#password").val(password);
    });

    $(".delete-selected").click(function () {
        $.each($("input[name='selected']:checked"), function () {
            request("/manage/reports/delete/" + $(this).val()).then(function (r) {
                $("#" + $(this).val()).fadeOut("slow", function () { });
            });
        });
    });

    $(".archive-selected").click(function () {
        $.each($("input[name='selected']:checked"), function () {
            request("/manage/reports/archive/" + $(this).val()).then(function (r) {
                $("#" + $(this).val()).fadeOut("slow", function () { });
            });
        });
    });

    $(".delete").click(function () {
        var id = $(this).attr('report-id');
        request("/manage/reports/delete/" + id).then(function (r) {
            if (window.location.href.indexOf('/view/') !== -1) {
                window.location.href = '/manage/reports';
            } else {
                window.location.href = window.location.href;
            }
        });
    });

    $(".archive").click(function () {
        var id = $(this).attr('report-id');
        request("/manage/reports/archive/" + id).then(function (r) {
            $("#" + id).fadeOut("slow", function () { });
        });
    });

    $(".share").click(function () {
        $('#reportid').val($(this).attr('report-id'));
        $('#shareid').val("https://" + window.location.hostname + "/manage/reports/share/" + $(this).attr('share-id'));
    });

    $(".render").click(function () {
        const byteCharacters = unescape(encodeURIComponent($('#dom').val()));
        const byteArrays = [];

        for (let offset = 0; offset < byteCharacters.length; offset += 1024) {
            const slice = byteCharacters.slice(offset, offset + 1024);

            const byteNumbers = new Array(slice.length);
            for (let i = 0; i < slice.length; i++) {
                byteNumbers[i] = slice.charCodeAt(i);
            }

            const byteArray = new Uint8Array(byteNumbers);

            byteArrays.push(byteArray);
        }

        const blob = new Blob(byteArrays, { type: 'text/html' });
        const blobUrl = URL.createObjectURL(blob);

        window.open(blobUrl, '_blank');
    });

    $(".copycookies").click(function () {
        var split = $("#cookies").text().split('; ');
        var origin = $(this).attr('report-origin');

        var json = '[';
        $.each(split, function (index, value) {
            var cookieData = value.split('=');
            var cookieName = cookieData[0];
            var cookieValue = cookieData[1];

            json += '{"domain":"' + origin + '","expirationDate":' + (Date.now() / 1000 + 31556926) + ',"hostOnly":true,"httpOnly":false,"name":"' + cookieName + '","path":"/","sameSite":"unspecified","secure":false,"session":false,"storeId": "0","value":"' + cookieValue + '","id":"' + (index + 1) + '"},';
        });
        json = json.substring(0, json.length - 1) + ']';

        var $temp = $("<input>");
        $("body").append($temp);
        $temp.val(json).select();
        document.execCommand("copy");
        $temp.remove();
    });

    $('#select-all').click(function (event) {
        if (this.checked) {
            $(':checkbox').each(function () {
                this.checked = true;
            });
        } else {
            $(':checkbox').each(function () {
                this.checked = false;
            });
        }
    });

    var lastChecked = null;
    const checkboxes = $('.chkbox');
    checkboxes.click(function (e) {
        if (!lastChecked) {
            lastChecked = this;
            return;
        }
        if (e.shiftKey) {
            const start = checkboxes.index(this);
            const end = checkboxes.index(lastChecked);
            checkboxes.slice(Math.min(start, end), Math.max(start, end) + 1).prop('checked', this.checked);
        }
        lastChecked = this;
    });
});