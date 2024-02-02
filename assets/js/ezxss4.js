function request(action, data = {}) {
    if(action.startsWith('/manage/api/')) {
        return $.ajax({
            type: 'post',
            dataType: 'json',
            contentType: 'application/json',
            url: action,
            data: JSON.stringify(data)
        });
    } else {
        data.csrf = csrf;
        return $.ajax({
            type: 'post',
            dataType: 'json',
            url: action,
            data: data
        });
    }
}


$(document).ready(function () {
    $('.left-nav-toggle').click(function () {
        $('#mobile-dropdown').slideToggle()
    })

    if (window.location.pathname.match(/^\/manage\/payload\/edit\/\d+$/)) {
        var label = $('.shortboost')
        var originalDomain = label.attr('domain')
        var replacedDomain = shortboost(originalDomain)
        if (originalDomain !== replacedDomain) {
            label.text('shortboost!')
        }

        label.on('click', function () {
            $('.scriptsrc').each(function () {
                let currentValue = $(this).val()
                let newValue = currentValue.replace(
                    new RegExp(originalDomain, 'g'),
                    replacedDomain
                )
                $(this).val(newValue)
            })
        })
    }

    const inputs = document.querySelectorAll('.scriptsrc')
    inputs.forEach(input => {
        input.addEventListener('click', function () {
            const textToCopy = this.value
            const tempInput = document.createElement('input')
            tempInput.value = textToCopy
            document.body.appendChild(tempInput)
            tempInput.select()
            document.execCommand('copy')
            document.body.removeChild(tempInput)

            this.classList.add('clicked')
            setTimeout(() => {
                this.classList.remove('clicked')
            }, 400)
        })
    })

    if (location.toString().split('/')[4] === 'dashboard') {
        request('/manage/api/statistics', {
            page: location.toString().split('/').pop()
        }).then(function (r) {
            $.each(r, function (key, value) {
                $('#' + key).html(value)
            })
        })
        
        var isMy = location.toString().split('/').pop() === 'my' && location.toString().split('/').pop() !== 'index' ? 0 : 1

        pick_common($('#pick_common1').val(), 1, isMy)
        pick_common($('#pick_common2').val(), 2, isMy)
    }

    $("a[method='post']").click(function (e) {
        e.preventDefault()
        var currentUrl = window.location.href
        request($(this).attr('href'), {}).then(function (r) {
            window.location.href = currentUrl
        })
    })

    $('#method').on('change', function () {
        $('.method-content').hide()
        $('#method-pick').hide()

        const alertId = parseInt(this.value)
        request('/manage/api/getAlertStatus', { alertId: alertId }).then(function (
            r
        ) {
            if (r.enabled === 1) {
                $('#method-content-' + alertId).show()
            } else {
                $('#method-disabled').show()
            }
        })
    })

    $('#payloadList').on('change', function () {
        window.location.href = '/manage/payload/edit/' + this.value
    })

    $('#pick_common1').on('change', function () {
        var isMy = location.toString().split('/').pop() === 'my' ? 0 : 1
        pick_common(this.value, 1, isMy)
    })

    $('#pick_common2').on('change', function () {
        var isMy = location.toString().split('/').pop() === 'my' ? 0 : 1
        pick_common(this.value, 2, isMy)
    })

    function pick_common(id, row, admin = 0) {
        $('#most_common' + row).empty()
        $('#toprow_common' + row).hide()
        $('#loading_common' + row).show()
        request('/manage/api/getMostCommon', {
            id: parseInt(id),
            row: row,
            admin: admin
        }).then(function (r) {
            $('#loading_common' + row).hide()
            $('#toprow_common' + row).show()
            if (r.length > 0) {
                $.each(r, function (key, value) {
                    $('<tr>')
                        .append(
                            $('<td>').text(Object.values(value)[0]),
                            $('<td>').text(Object.values(value)[1])
                        )
                        .appendTo('#most_common' + row)
                })
            } else {
                $('#toprow_common' + row).hide()
                $('#most_common' + row).text('No reports data found')
            }
        })
    }

    $('#payloadListReport').on('change', function () {
        const urlParams = new URLSearchParams(window.location.search)
        const addValue = urlParams.get('archive') == '1' ? '?archive=1' : ''
        if (this.value !== '0') {
            window.location.href = '/manage/reports/list/' + this.value + addValue
        } else {
            window.location.href = '/manage/reports/all' + addValue
        }
    })

    $('#payloadListSession').on('change', function () {
        if (this.value !== '0') {
            window.location.href = '/manage/persistent/list/' + this.value
        } else {
            window.location.href = '/manage/persistent/all'
        }
    })

    $('.remove-item').click(function () {
        const data = $(this).attr('data')
        const divId = $(this).attr('divid')
        const type =
            divId.charAt(0) === 'p'
                ? 'pages'
                : divId.charAt(0) === 'w'
                    ? 'whitelist'
                    : divId.charAt(0) === 'b'
                        ? 'blacklist'
                        : ''
        const id =
            window.location.href.split('/').length > 0
                ? window.location.href.split('/')[
                window.location.href.split('/').length - 1
                ]
                : ''
        request('/manage/payload/removeItem/' + id, {
            data: data,
            type: type
        }).then(function (r) {
            $('#' + divId).fadeOut('slow', function () { })
        })
    })

    $('#openGetChatId').click(function () {
        var bottoken = $('#telegram_bottoken').val()
        request('/manage/api/getchatid', { bottoken: bottoken }).then(function (r) {
            if (r.echo.startsWith('chatId:')) {
                $('#getChatId').modal('hide')
                $('#chatid').val(r.echo.replace('chatId:', ''))
            } else {
                $('#getChatId').modal('show')
                $('#getChatIdBody').html(r.echo)
            }
        })
    })

    $('.generate-password').click(function () {
        var password = ''
        const possible =
            'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()_+'

        for (var i = 0; i < 20; i++) {
            password += possible.charAt(Math.floor(Math.random() * possible.length))
        }

        $('#password').val(password)
    })

    $('.delete-selected').click(function () {
        $.each($("input[name='selected']:checked"), function () {
            const id = $(this).val()
            request('/manage/reports/delete/' + id).then(function (r) {
                $('#' + id).fadeOut('slow', function () { })
            })
        })
    })

    $('.archive-selected').click(function () {
        $.each($("input[name='selected']:checked"), function () {
            const id = $(this).val()
            request('/manage/reports/archive/' + id).then(function (r) {
                $('#' + id).fadeOut('slow', function () { })
            })
        })
    })

    $(document).on('click', '.delete', function () {
        var id = $(this).attr('report-id')
        request('/manage/reports/delete/' + id).then(function (r) {
            if (window.location.href.indexOf('/view/') !== -1) {
                window.location.href = '/manage/reports'
            } else {
                $('#' + id).fadeOut('slow', function () { })
            }
        })
    })

    $(document).on('click', '.archive', function () {
        var id = $(this).attr('report-id')
        request('/manage/reports/archive/' + id).then(function (r) {
            $('#' + id).fadeOut('slow', function () { })
        })
    })

    $(document).on('click', '.share', function () {
        $('#reportid').val($(this).attr('report-id'))
        $('#shareid').val(
            'https://' +
            window.location.hostname +
            '/manage/reports/share/' +
            $(this).attr('share-id')
        )
    })

    $('.execute-selected').click(function () {
        $.each($("input[name='selected']:checked"), function () {
            const id = $(this).val()
            const url = $(this).attr('url')
            console.log(id)
            command = $('#command').val()
            request(url, { execute: '', command: command }).then(function (r) {
                $('#command').val('')
            })
        })
    })

    $('.kill-selected').click(function () {
        $.each($("input[name='selected']:checked"), function () {
            const url = $(this).attr('url')
            request(url, { kill: '' }).then(function (r) {
                $('#command').val('')
            })
        })
    })

    $('.persistent-delete-selected').click(function () {
        $.each($("input[name='selected']:checked"), function () {
            const url = $(this).attr('url')
            request(url, { delete: '' }).then(function (r) {
                $('#command').val('')
            })
        })
    })

    $('#execute').click(function () {
        command = $('#command').val()
        request(window.location.pathname, { execute: '', command: command }).then(
            function (r) {
                $('#command').val('')
            }
        )
    })

    if (location.toString().split('/')[5] === 'session') {
        window.setInterval(function () {
            request(window.location.pathname, { getconsole: '' }).then(function (r) {
                $('#console').val(r.console)
            })
        }, 10000)
    }

    $('.render').click(function () {
        const byteCharacters = unescape(encodeURIComponent($('#dom').val()))
        const byteArrays = []

        for (let offset = 0; offset < byteCharacters.length; offset += 1024) {
            const slice = byteCharacters.slice(offset, offset + 1024)

            const byteNumbers = new Array(slice.length)
            for (let i = 0; i < slice.length; i++) {
                byteNumbers[i] = slice.charCodeAt(i)
            }

            const byteArray = new Uint8Array(byteNumbers)

            byteArrays.push(byteArray)
        }

        const blob = new Blob(byteArrays, { type: 'text/html' })
        const blobUrl = URL.createObjectURL(blob)

        window.open(blobUrl, '_blank')
    })

    $('.copycookies').click(function () {
        var split = $('#cookies').text().split('; ')
        var origin = $(this).attr('report-origin')

        var cookiesArray = []

        $.each(split, function (index, value) {
            var cookieData = value.split('=')
            var cookieName = cookieData[0]
            var cookieValue = cookieData[1]

            var cookieObject = {
                domain: origin,
                expirationDate: Date.now() / 1000 + 31556926,
                hostOnly: true,
                httpOnly: false,
                name: cookieName,
                path: '/',
                sameSite: 'no_restriction',
                secure: false,
                session: false,
                storeId: 'firefox-default',
                partitionKey: null,
                firstPartyDomain: '',
                value: cookieValue,
                id: index + 1
            }

            cookiesArray.push(cookieObject)
        })

        var json = JSON.stringify(cookiesArray)

        var $temp = $('<input>')
        $('body').append($temp)
        $temp.val(json).select()
        document.execCommand('copy')
        $temp.remove()
    })

    $('#select-all').click(function (event) {
        if (this.checked) {
            $(':checkbox').each(function () {
                this.checked = true
            })
        } else {
            $(':checkbox').each(function () {
                this.checked = false
            })
        }
    })

    var lastChecked

    $('label').on('mousedown', function (e) {
        // Find the checkbox associated with the clicked label
        var checkbox = $('#' + $(this).attr('for'))

        if (!lastChecked) {
            lastChecked = checkbox[0]
            return
        }

        if (e.shiftKey) {
            var start = $('input[type="checkbox"]').index(checkbox)
            var end = $('input[type="checkbox"]').index(lastChecked)

            $('input[type="checkbox"]')
                .slice(Math.min(start, end), Math.max(start, end) + 1)
                .each(function () {
                    this.checked = lastChecked.checked
                })
        }

        lastChecked = checkbox[0]

        // Prevent text selection
        document.onselectstart = function () {
            return false
        }
    })
})

function shortboost(str) {
    const replacements = {
        "ij": "ĳ", "bar": "㍴", "ov": "㍵", "na": "㎁", "ma": "㎃", "ka": "㎄", "kb": "㎅", "mb": "㎆", "gb": "㎇", "cal": "㎈", "kcal": "㎉", "pf": "㎊", "nf": "㎋", "mg": "㎎", "kg": "㎏", "hz": "㎐", "khz": "㎑", "mhz": "㎒", "ghz": "㎓", "thz": "㎔", "ml": "㎖", "dl": "㎗", "kl": "㎘", "fm": "㎙", "nm": "㎚", "mm": "㎜", "cm": "㎝", "km": "㎞", "mm2": "㎟", "cm2": "㎠", "m2": "㎡", "km2": "㎢", "mm3": "㎣", "cm3": "㎤", "m3": "㎥", "km3": "㎦", "pa": "㎩", "kpa": "㎪", "mpa": "㎫", "gpa": "㎬", "rad": "㎭", "ps": "㎰", "ns": "㎱", "ms": "㎳", "pv": "㎴", "nv": "㎵", "mv": "㎷", "kv": "㎸", "pw": "㎺", "nw": "㎻", "mw": "㎽", "kw": "㎾", "bq": "㏃", "cc": "㏄", "cd": "㏅", "db": "㏈", "gy": "㏉", "ha": "㏊", "hp": "㏋", "in": "㏌", "kk": "㏍", "kt": "㏏", "lm": "㏐", "ln": "㏑", "log": "㏒", "lx": "㏓", "mb": "㏔", "mil": "㏕", "mol": "㏖", "ph": "㏗", "pm": "㏘", "ppm": "㏙", "pr": "㏚", "sr": "㏛", "sv": "㏜", "wb": "㏝", "no": "№", "tm": "™", "tel": "℡", "rs": "₨", "sm": "℠", "gal": "㏿", "ff": "ﬀ", "fi": "ﬁ", "fl": "ﬂ", "ffi": "ﬃ", "ffl": "ﬄ", "st": "ﬅ", "dm": "㍷", "dm2": "㍸", "dm3": "㍹", "iu": "㍺", "pc": "㍶", "hg": "㋌", "erg": "㋍", "ev": "㋎", "ltd": "㋏", "hpa": "㍱", "da": "㍲", "pte": "㉐", "ix": "ⅸ", "ii": "ⅱ", "iii": "ⅲ", "iv": "ⅳ", "xi": "Ⅺ", "xii": "Ⅻ", "vi": "Ⅵ", "vii": "Ⅶ", "viii": "Ⅷ", "nj": "Ǌ", "dz": "Ǳ", "lj": "Ǉ", "10": "⑩", "11": "⑪", "12": "⑫", "13": "⑬", "14": "⑭", "15": "⑮", "16": "⑯", "17": "⑰", "18": "⑱", "19": "⑲", "20": "⑳", "21": "㉑", "22": "㉒", "23": "㉓", "24": "㉔", "25": "㉕", "26": "㉖", "27": "㉗", "28": "㉘", "29": "㉙", "30": "㉚", "31": "㉛", "32": "㉜", "33": "㉝", "34": "㉞", "35": "㉟", "36": "㊱", "37": "㊲", "38": "㊳", "39": "㊴", "40": "㊵", "41": "㊶", "42": "㊷", "43": "㊸", "44": "㊹", "45": "㊺", "46": "㊻", "47": "㊼", "48": "㊽", "49": "㊾", "50": "㊿"
    };

    for (let key in replacements) {
        const regex = new RegExp(key, 'g')
        str = str.replace(regex, replacements[key])
    }

    return str
}