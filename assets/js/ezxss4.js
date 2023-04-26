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

    $('.left-nav-toggle').click(function () {
        $('#mobile-dropdown').slideToggle();
    });

    if (location.toString().split('/')[4] === 'dashboard') {
        request('/manage/api/statistics', { page: location.toString().split('/').pop() }).then(function (r) {
            $.each(r, function (key, value) {
                $('#' + key).html(value);
            });
        });

        var isMy = (location.toString().split('/').pop() === 'my') ? 0 : 1;

        pick_common($('#pick_common1').val(), 1, isMy);
        pick_common($('#pick_common2').val(), 2, isMy);
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

    $('#pick_common1').on('change', function () {
        var isMy = (location.toString().split('/').pop() === 'my') ? 0 : 1;
        pick_common(this.value, 1, isMy);
    });

    $('#pick_common2').on('change', function () {
        var isMy = (location.toString().split('/').pop() === 'my') ? 0 : 1;
        pick_common(this.value, 2, isMy);
    });

    function pick_common(id, row, admin = 0) {
        $('#most_common' + row).empty();
        $('#toprow_common' + row).hide();
        $('#loding_common' + row).show();
        request('/manage/api/getMostCommon', { id: id, row: row, admin: admin }).then(function (r) {
            $('#loding_common' + row).hide();
            $('#toprow_common' + row).show();
            if (r.length > 0) {
                $.each(r, function (key, value) {
                    $('<tr>').append(
                        $('<td>').text(Object.values(value)[0]),
                        $('<td>').text(Object.values(value)[1])
                    ).appendTo('#most_common' + row);
                });
            } else {
                $('#toprow_common' + row).hide();
                $('#most_common' + row).text('No reports data found');
            }
        });
    }

    $('#payloadListReport').on('change', function () {
        const urlParams = new URLSearchParams(window.location.search);
        const addValue = urlParams.get('archive') == '1' ? '?archive=1' : '';
        if (this.value !== '0') {
            window.location.href = '/manage/reports/list/' + this.value + addValue;
        } else {
            window.location.href = '/manage/reports/all' + addValue;
        }
    });

    $('#payloadListSession').on('change', function () {
        if (this.value !== '0') {
            window.location.href = '/manage/persistent/list/' + this.value;
        } else {
            window.location.href = '/manage/persistent/all';
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
            const id = $(this).val();
            request("/manage/reports/delete/" + id).then(function (r) {
                $("#" + id).fadeOut("slow", function () { });
            });
        });
    });

    $(".archive-selected").click(function () {
        $.each($("input[name='selected']:checked"), function () {
            const id = $(this).val();
            request("/manage/reports/archive/" + id).then(function (r) {
                $("#" + id).fadeOut("slow", function () { });
            });
        });
    });

    $(".delete").click(function () {
        var id = $(this).attr('report-id');
        request("/manage/reports/delete/" + id).then(function (r) {
            if (window.location.href.indexOf('/view/') !== -1) {
                window.location.href = '/manage/reports';
            } else {
                $("#" + id).fadeOut("slow", function () { });
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

    $('#execute').click(function () {
        command = $('#command').val();
        request(window.location.pathname, { 'execute': '', command: command }).then(function (r) {
            $('#command').val('');
        });
    });

    if (location.toString().split('/')[5] === 'session') {
        window.setInterval(function () {
            request(window.location.pathname, { 'getconsole': '' }).then(function (r) {
                $('#console').val(r.console);
            });
        }, 10000);
    }

    if (location.toString().split('/')[4] === "persistent") {
        var startTime = new Date();
        setInterval(function () {
            var elapsedTime = new Date() - startTime;
            var seconds = Math.round(elapsedTime / 1000);
            $("#last").text(seconds + "s ago");
        }, 1000);
        setInterval(function () {
            location.reload();
        }, 60000);
    }

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

    var lastChecked;

    $('label').on('mousedown', function (e) {
        // Find the checkbox associated with the clicked label
        var checkbox = $('#' + $(this).attr('for'));

        if (!lastChecked) {
            lastChecked = checkbox[0];
            return;
        }

        if (e.shiftKey) {
            var start = $('input[type="checkbox"]').index(checkbox);
            var end = $('input[type="checkbox"]').index(lastChecked);

            $('input[type="checkbox"]').slice(Math.min(start, end), Math.max(start, end) + 1).each(function () {
                this.checked = lastChecked.checked;
            });
        }

        lastChecked = checkbox[0];

        // Prevent text selection
        document.onselectstart = function () { return false; };
    });
});