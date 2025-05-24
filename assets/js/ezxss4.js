function request(action, data = {}) {
    return $.ajax({
        type: 'post',
        dataType: 'json',
        contentType: 'application/json',
        url: action,
        data: JSON.stringify(data)
    });
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
        request('/manage/dashboard/statistics', {
            page: location.pathname.split('/').pop().split('?')[0] === 'my' ? 'my' : 'dashboard'
        }).then(function (r) {
            $.each(r, function (key, value) {
                $('#' + key).html(value)
            })
        })
        
        var isAdmin = location.toString().split('/').pop() === 'my' ? 0 : 1

        pick_common($('#pick_common1').val(), 1, isAdmin)
        pick_common($('#pick_common2').val(), 2, isAdmin)
    }

    $(".delete-payload").click(function (e) {
        e.preventDefault()
        var id = $(this).attr('data-id')
        $(this).parent().parent().fadeOut('slow', function () { })
        request('/manage/users/deletepayload/' + id, {}).then(function (r) {})
    })

    $('#method').on('change', function () {
        $('.method-content').hide()
        $('#method-pick').hide()

        const alertId = parseInt(this.value)
        request('/manage/account/getAlertStatus', { alertId: alertId }).then(function (
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
        var isAdmin = location.toString().split('/').pop() === 'my' ? 0 : 1
        pick_common(this.value, 1, isAdmin)
    })

    $('#pick_common2').on('change', function () {
        var isAdmin = location.toString().split('/').pop() === 'my' ? 0 : 1
        pick_common(this.value, 2, isAdmin)
    })

    function pick_common(id, row, admin = 0) {
        $('#most_common' + row).empty()
        $('#toprow_common' + row).hide()
        $('#loading_common' + row).show()
        request('/manage/dashboard/mostCommon', {
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
        if(divId === 'pspider') {
            setSpider('0')
            return
        }
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

    $('#spider').on('change', function () {
        const method = $(this).val()
        setSpider(method)
    })

    function setSpider(method) {
        const id = window.location.pathname.split('/').pop()
        request('/manage/payload/spider/' + id, { method: method }).then(function (r) {
            window.location.href = window.location.href;
        })
    }

    $('#openGetChatId').click(function () {
        var bottoken = $('#telegram_bottoken').val()
        request('/manage/account/getchatid', { bottoken: bottoken }).then(function (r) {
            if (r.chatid) {
                $('#getChatId').modal('hide')
                $('#chatid').val(r.chatid)
            } else {
                $('#getChatId').modal('show')
                $('#getChatIdBody').html(r.error)
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
            const url = $(this).attr('url')
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
            const parent = $(this).parent().parent().parent().parent()
            parent.fadeOut('slow', function () { })
            const url = $(this).attr('url')
            request(url, { delete: '' }).then(function (r) { 
                parent.fadeOut('slow', function () { })
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
                const decodedConsole = decodeHTMLEntities(r.console);
                $('#console').val(decodedConsole);
            })
        }, 10000)
    }

    $('#extension-method').on('change', function() {
        if ($(this).val() === 'github') {
            $('#github-install-form').show();
            $('#custom-install-form').hide();
        } else {
            $('#github-install-form').hide();
            $('#custom-install-form').show();
        }
    });

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

        document.onselectstart = function () {
            return false
        }
    })

    if (document.getElementById("qrcode")) {
        var secret = document.getElementById("secret").value;
        var qrcode = new QRCode(document.getElementById("qrcode"), {
            "text": "otpauth://totp/ezXSS:ezXSS?secret=" + secret + "&issuer=ezXSS",
            "width": 300,
            "height": 300,
            "colorDark": "#ffffff",
            "colorLight": "#2c3256",
            "correctLevel": QRCode.CorrectLevel.H
        });
    }

    initExtensionsDropdown();

    $(document).on('click', '.copy-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const targetSelector = $(this).data('target');
        const targetElement = $(targetSelector);
        
        if (targetElement.length > 0) {
            const textToCopy = targetElement.val() || targetElement.text();
            
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(textToCopy).then(() => {
                    showCopyFeedback($(this), 'Copied!');
                }).catch(() => {
                    fallbackCopy(textToCopy, $(this));
                });
            } else {
                fallbackCopy(textToCopy, $(this));
            }
        }
    });
    
    function showCopyFeedback(button, message) {
        const originalText = button.html();
        const originalColor = button.css('background-color');
        
        button.html(message);
        button.css('background-color', '#2ecc71');
        
        setTimeout(() => {
            button.html(originalText);
            button.css('background-color', originalColor);
        }, 1000);
    }
    
    // Fallback copy function for older browsers
    function fallbackCopy(text, button) {
        const tempInput = document.createElement('textarea');
        tempInput.value = text;
        tempInput.style.position = 'fixed';
        tempInput.style.opacity = '0';
        tempInput.style.left = '-9999px';
        document.body.appendChild(tempInput);
        tempInput.select();
        tempInput.setSelectionRange(0, 99999);
        
        try {
            const successful = document.execCommand('copy');
            if (successful) {
                showCopyFeedback(button, 'Copied!');
            } else {
                showCopyFeedback(button, 'Failed');
            }
        } catch (err) {
            showCopyFeedback(button, 'Error');
        } finally {
            document.body.removeChild(tempInput);
        }
    }
})

function shortboost(str) {
    const replacements = {
        "ij": "ĳ", "bar": "㍴", "ov": "㍵", "na": "㎁", "ma": "㎃", "ka": "㎄", "kb": "㎅", "mb": "㎆", "gb": "㎇", "cal": "㎈", "kcal": "㎉", "pf": "㎊", "nf": "㎋", "mg": "㎎", "kg": "㎏", "hz": "㎐", "khz": "㎑", "mhz": "㎒", "ghz": "㎓", "thz": "㎔", "ml": "㎖", "dl": "㎗", "kl": "㎘", "fm": "㎙", "nm": "㎚", "mm": "㎜", "cm": "㎝", "km": "㎞", "mm2": "㎟", "cm2": "㎠", "m2": "㎡", "km2": "㎢", "mm3": "㎣", "cm3": "㎤", "m3": "㎥", "km3": "㎦", "pa": "㎩", "kpa": "㎪", "mpa": "㎫", "gpa": "㎬", "rad": "㎭", "ps": "㎰", "ns": "㎱", "ms": "㎳", "pv": "㎴", "nv": "㎵", "mv": "㎷", "kv": "㎸", "pw": "㎺", "nw": "㎻", "mw": "㎽", "kw": "㎾", "bq": "㏃", "cc": "㏄", "cd": "㏅", "db": "㏈", "gy": "㏉", "ha": "㏊", "hp": "㏋", "in": "㏌", "kk": "㏍", "kt": "㏏", "lm": "㏐", "ln": "㏑", "log": "㏒", "lx": "㏓", "mb": "㏔", "mil": "㏕", "mol": "㏖", "ph": "㏗", "pm": "㏘", "ppm": "㏙", "pr": "㏚", "sr": "㏛", "sv": "㏜", "wb": "㏝", "no": "№", "tm": "™", "tel": "℡", "rs": "₨", "sm": "℠", "gal": "㏿", "ff": "ﬀ", "fi": "ﬁ", "fl": "ﬂ", "ffi": "ﬃ", "ffl": "ﬄ", "st": "ﬅ", "dm": "㍷", "dm2": "㍸", "dm3": "㍹", "iu": "㍺", "pc": "㍶", "hg": "㋌", "erg": "㋍", "ev": "㋎", "ltd": "㋏", "hpa": "㍱", "da": "㍲", "pte": "㉐", "ix": "ⅸ", "ii": "ⅱ", "iii": "ⅲ", "iv": "ⅳ", "xi": "Ⅺ", "xii": "Ⅻ", "vi": "Ⅵ", "vii": "Ⅶ", "viii": "Ⅷ", "nj": "Ǌ", "dz": "Ǳ", "lj": "Ǉ", "10": "⑩", "11": "⑪", "12": "⑫", "13": "⑬", "14": "⑭", "15": "⑮", "16": "⑯", "17": "⑰", "18": "⑱", "19": "⑲", "20": "⑳", "21": "㉑", "22": "㉒", "23": "㉓", "24": "㉔", "25": "㉕", "26": "㉖", "27": "㉗", "28": "㉘", "29": "㉙", "30": "㉚", "31": "㉛", "32": "㉜", "33": "㉝", "34": "㉞", "35": "㉟", "36": "㊱", "37": "㊲", "38": "㊳", "39": "㊴", "40": "㊵", "41": "㊶", "42": "㊷", "43": "㊸", "44": "㊹", "45": "㊺", "46": "㊻", "47": "㊼", "48": "㊽", "49": "㊾", "50": "㊿"
    };

    const sortedKeys = Object.keys(replacements).sort((a, b) => b.length - a.length);

    for (const key of sortedKeys) {
        const regex = new RegExp(key, 'g')
        str = str.replace(regex, replacements[key])
    }

    return str
}

function decodeHTMLEntities(text) {
    if (!text) return '';
    const textarea = document.createElement('textarea');
    textarea.innerHTML = text;
    return textarea.value;
}

function initExtensionsDropdown() {
    const dropdown = $('.extensions-dropdown');
    if (dropdown.length === 0) return;

    const display = dropdown.find('.extensions-display');
    const dropdownList = dropdown.find('.extensions-dropdown-list');
    const hiddenInput = dropdown.find('#extensions-hidden');
    const tagsContainer = dropdown.find('.extensions-tags');
    const dropdownBtn = dropdown.find('.extensions-dropdown-btn');
    
    let selectedExtensions = [];
    let isOpen = false;

    function initializeFromCheckboxes() {
        dropdown.find('input[type="checkbox"]:checked').each(function() {
            const id = $(this).val();
            const name = $(this).data('name');
            const description = $(this).data('description');
            
            selectedExtensions.push({
                id: id,
                name: name,
                description: description
            });
        });
        updateDisplay();
        updateHiddenInput();
    }

    function updateDisplay() {
        tagsContainer.empty();
        
        if (selectedExtensions.length === 0) {
            dropdownBtn.removeClass('has-selections').addClass('empty');
            dropdownBtn.find('.dropdown-text').text('Select extensions...');
        } else {
            dropdownBtn.addClass('has-selections').removeClass('empty');
            
            selectedExtensions.forEach(function(ext) {
                const tag = $('<div class="extension-tag"></div>');
                const nameSpan = $('<span></span>').text(ext.name);
                const removeSpan = $('<span class="extension-tag-remove">×</span>')
                    .attr('data-id', ext.id)
                    .on('click', function(e) {
                        e.stopPropagation();
                        removeExtension(ext.id);
                    });
                
                tag.append(nameSpan).append(removeSpan);
                tagsContainer.append(tag);
            });
        }
    }

    function updateHiddenInput() {
        const ids = selectedExtensions.map(ext => ext.id);
        hiddenInput.val(ids.join(','));
    }

    function toggleDropdown() {
        isOpen = !isOpen;
        if (isOpen) {
            display.addClass('active');
            dropdownList.show();
        } else {
            display.removeClass('active');
            dropdownList.hide();
        }
    }

    function closeDropdown() {
        if (isOpen) {
            isOpen = false;
            display.removeClass('active');
            dropdownList.hide();
        }
    }

    function addExtension(id, name, description) {
        if (!selectedExtensions.find(ext => ext.id === id)) {
            selectedExtensions.push({ id, name, description });
            updateDisplay();
            updateHiddenInput();
        }
    }

    function removeExtension(id) {
        selectedExtensions = selectedExtensions.filter(ext => ext.id !== id);
        updateDisplay();
        updateHiddenInput();
        
        dropdown.find(`input[value="${id}"]`).prop('checked', false);
    }

    display.on('click', function(e) {
        if ($(e.target).closest('.extension-tag').length === 0) {
            e.stopPropagation();
            toggleDropdown();
        }
    });

    dropdown.on('change', 'input[type="checkbox"]', function() {
        const checkbox = $(this);
        const id = checkbox.val();
        const name = checkbox.data('name');
        const description = checkbox.data('description');
        
        if (checkbox.is(':checked')) {
            addExtension(id, name, description);
        } else {
            removeExtension(id);
        }
    });

    $(document).on('click', function(e) {
        if (!dropdown.is(e.target) && dropdown.has(e.target).length === 0) {
            closeDropdown();
        }
    });

    dropdownList.on('click', function(e) {
        e.stopPropagation();
    });

    initializeFromCheckboxes();
}