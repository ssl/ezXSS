/**
 * ezXSS JavaScript Module
 * Handles all client-side functionality for the ezXSS application
 */

// Utility functions
const Utils = {
    /**
     * Makes an AJAX request with proper error handling
     * @param {string} action - The URL endpoint
     * @param {Object} data - Data to send
     * @returns {Promise} jQuery promise
     */
    request(action, data = {}) {
        return $.ajax({
            type: 'post',
            dataType: 'json',
            contentType: 'application/json',
            url: action,
            data: JSON.stringify(data),
            timeout: 60000
        }).fail(function(xhr, status, error) {
            console.error('Request failed:', { action, status, error });
        });
    },

    /**
     * Safely copies text to clipboard with fallback
     * @param {string} text - Text to copy
     * @param {jQuery} button - Button element for feedback
     */
    copyToClipboard(text, button = null) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text).then(() => {
                if (button) this.showCopyFeedback(button, 'Copied!');
                return true;
            }).catch(() => {
                return this.fallbackCopy(text, button);
            });
        } else {
            return this.fallbackCopy(text, button);
        }
    },

    /**
     * Fallback copy method for older browsers
     * @param {string} text - Text to copy
     * @param {jQuery} button - Button element for feedback
     */
    fallbackCopy(text, button) {
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
            if (button) {
                this.showCopyFeedback(button, successful ? 'Copied!' : 'Failed');
            }
            return successful;
        } catch (err) {
            console.error('Copy failed:', err);
            if (button) this.showCopyFeedback(button, 'Error');
            return false;
        } finally {
            document.body.removeChild(tempInput);
        }
    },

    /**
     * Shows visual feedback for copy operations
     * @param {jQuery} button - Button element
     * @param {string} message - Message to show
     */
    showCopyFeedback(button, message) {
        const originalText = button.html();
        const originalColor = button.css('background-color');
        
        button.html(message);
        button.css('background-color', message === 'Copied!' ? '#2ecc71' : '#e74c3c');
        
        setTimeout(() => {
            button.html(originalText);
            button.css('background-color', originalColor);
        }, 1000);
    },

    /**
     * Decodes HTML entities safely
     * @param {string} text - Text to decode
     * @returns {string} Decoded text
     */
    decodeHTMLEntities(text) {
        if (!text) return '';
        const textarea = document.createElement('textarea');
        textarea.innerHTML = text;
        return textarea.value;
    },

    /**
     * Generates a secure random password
     * @param {number} length - Password length
     * @returns {string} Generated password
     */
    generatePassword(length = 20) {
        const charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()_+';
        let password = '';
        
        for (let i = 0; i < length; i++) {
            password += charset.charAt(Math.floor(Math.random() * charset.length));
        }
        
        return password;
    },

    /**
     * Debounce function to limit function calls
     * @param {Function} func - Function to debounce
     * @param {number} wait - Wait time in milliseconds
     * @returns {Function} Debounced function
     */
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
};

// Main application object
const EzXSS = {
    // Configuration
    config: {
        consoleUpdateInterval: 10000
    },

    // State management
    state: {
        lastChecked: null,
        isAdmin: false,
        currentPath: window.location.pathname
    },

    /**
     * Initialize the application
     */
    init() {
        this.bindEvents();
        this.initializePageSpecificFeatures();
        this.initializeExtensionsDropdown();
    },

    /**
     * Bind all event handlers
     */
    bindEvents() {
        // Navigation
        $('.left-nav-toggle').on('click', this.toggleMobileNav);

        // Dashboard
        if (this.isDashboardPage()) {
            this.initializeDashboard();
        }

        // Payload management
        $('.delete-payload').on('click', this.handlePayloadDelete);
        $('#payloadList').on('change', this.handlePayloadListChange);
        $('#payloadListReport').on('change', this.handlePayloadListReportChange);
        $('#payloadListSession').on('change', this.handlePayloadListSessionChange);

        // Alert methods
        $('#method').on('change', this.handleMethodChange);

        // Common data selection
        $('#pick_common1, #pick_common2').on('change', this.handleCommonDataChange);

        // Item removal
        $('.remove-item').on('click', this.handleItemRemoval);

        // Spider settings
        $('#spider').on('change', this.handleSpiderChange);

        // Telegram integration
        $('#openGetChatId').on('click', this.handleTelegramChatId);

        // Password generation
        $('.generate-password').on('click', this.handlePasswordGeneration);

        // Report management
        $('.delete-selected').on('click', this.handleBulkDelete);
        $('.archive-selected').on('click', this.handleBulkArchive);
        $(document).on('click', '.delete', this.handleReportDelete);
        $(document).on('click', '.archive', this.handleReportArchive);
        $(document).on('click', '.share', this.handleReportShare);

        // Session management
        $('.execute-selected').on('click', this.handleBulkExecute);
        $('.kill-selected').on('click', this.handleBulkKill);
        $('.persistent-delete-selected').on('click', this.handlePersistentBulkDelete);
        $('#execute').on('click', this.handleExecute);

        // Command textarea Ctrl+Enter functionality
        $('#command').on('keydown', this.handleCommandKeydown.bind(this));

        // Extension management
        $('#extension-method').on('change', this.handleExtensionMethodChange);
        $(document).on('change', '.extension-toggle', this.handleExtensionToggle);

        // UI interactions
        $('.render').on('click', this.handleDOMRender);
        $('.copycookies').on('click', this.handleCookiesCopy);
        $('#select-all').on('click', this.handleSelectAll);
        $('label').on('mousedown', this.handleShiftSelect.bind(this));
        $(document).on('click', '.copy-btn', this.handleCopyButton);

        // Script source copying
        this.initializeScriptSourceCopying();
    },

    /**
     * Initialize page-specific features
     */
    initializePageSpecificFeatures() {
        // Payload edit page
        if (this.isPayloadEditPage()) {
            this.initializeShortboost();
        }

        // Session page
        if (this.isSessionPage()) {
            this.startConsoleUpdates();
        }

        // 2FA QR code
        if (document.getElementById("qrcode")) {
            this.initializeQRCode();
        }
    },

    /**
     * Check if current page is dashboard
     */
    isDashboardPage() {
        return this.state.currentPath.includes('/dashboard');
    },

    /**
     * Check if current page is payload edit
     */
    isPayloadEditPage() {
        return /^\/manage\/payload\/edit\/\d+$/.test(this.state.currentPath);
    },

    /**
     * Check if current page is session
     */
    isSessionPage() {
        return this.state.currentPath.includes('/session');
    },

    /**
     * Initialize dashboard functionality
     */
    initializeDashboard() {
        Utils.request('/manage/dashboard/statistics', {
            page: this.state.currentPath.split('/').pop().split('?')[0] === 'my' ? 'my' : 'dashboard'
        }).then((data) => {
            $.each(data, (key, value) => {
                $('#' + key).html(value);
            });
        }).catch((error) => {
            console.error('Failed to load dashboard statistics:', error);
        });
        
        this.state.isAdmin = !this.state.currentPath.endsWith('/my');
        this.pickCommon($('#pick_common1').val(), 1, this.state.isAdmin);
        this.pickCommon($('#pick_common2').val(), 2, this.state.isAdmin);
    },

    /**
     * Initialize shortboost functionality
     */
    initializeShortboost() {
        const label = $('.shortboost');
        const originalDomain = label.attr('domain');
        const replacedDomain = this.shortboost(originalDomain);
        
        if (originalDomain !== replacedDomain) {
            label.text('shortboost!');
        }

        label.on('click', () => {
            $('.scriptsrc').each(function() {
                const currentValue = $(this).val();
                const newValue = currentValue.replace(
                    new RegExp(originalDomain, 'g'),
                    replacedDomain
                );
                $(this).val(newValue);
            });
        });
    },

    /**
     * Initialize script source copying
     */
    initializeScriptSourceCopying() {
        const inputs = document.querySelectorAll('.scriptsrc');
        inputs.forEach(input => {
            input.addEventListener('click', function() {
                Utils.copyToClipboard(this.value);
                
                this.classList.add('clicked');
                setTimeout(() => {
                    this.classList.remove('clicked');
                }, 400);
            });
        });
    },

    /**
     * Start console updates for session pages
     */
    startConsoleUpdates() {
        const updateConsole = () => {
            Utils.request(this.state.currentPath, { getconsole: '' })
                .then((data) => {
                    const decodedConsole = Utils.decodeHTMLEntities(data.console);
                    $('#console').val(decodedConsole);
                })
                .catch((error) => {
                    console.error('Failed to update console:', error);
                });
        };

        // Initial update
        updateConsole();
        
        // Set up interval
        setInterval(updateConsole, this.config.consoleUpdateInterval);
    },

    /**
     * Initialize QR code for 2FA
     */
    initializeQRCode() {
        const secret = document.getElementById("secret").value;
        new QRCode(document.getElementById("qrcode"), {
            text: `otpauth://totp/ezXSS:ezXSS?secret=${secret}&issuer=ezXSS`,
            width: 300,
            height: 300,
            colorDark: "#ffffff",
            colorLight: "#2c3256",
            correctLevel: QRCode.CorrectLevel.H
        });
    },

    // Event handlers
    toggleMobileNav() {
        $('#mobile-dropdown').slideToggle();
    },

    handlePayloadDelete(e) {
        e.preventDefault();
        const id = $(this).attr('data-id');
        const row = $(this).parent().parent();
        
        row.fadeOut('slow');
        Utils.request(`/manage/users/deletepayload/${id}`, {})
            .catch(() => {
                row.fadeIn('slow'); // Restore on error
            });
    },

    handlePayloadListChange() {
        if (this.value) {
            window.location.href = `/manage/payload/edit/${this.value}`;
        }
    },

    handlePayloadListReportChange() {
        const urlParams = new URLSearchParams(window.location.search);
        const addValue = urlParams.get('archive') === '1' ? '?archive=1' : '';
        
        if (this.value !== '0') {
            window.location.href = `/manage/reports/list/${this.value}${addValue}`;
        } else {
            window.location.href = `/manage/reports/all${addValue}`;
        }
    },

    handlePayloadListSessionChange() {
        if (this.value !== '0') {
            window.location.href = `/manage/persistent/list/${this.value}`;
        } else {
            window.location.href = '/manage/persistent/all';
        }
    },

    handleMethodChange() {
        $('.method-content').hide();
        $('#method-pick').hide();

        const alertId = parseInt(this.value);
        Utils.request('/manage/account/getAlertStatus', { alertId })
            .then((data) => {
                if (data.enabled === 1) {
                    $(`#method-content-${alertId}`).show();
                } else {
                    $('#method-disabled').show();
                }
            });
    },

    handleCommonDataChange() {
        const id = this.value;
        const row = this.id === 'pick_common1' ? 1 : 2;
        EzXSS.pickCommon(id, row, EzXSS.state.isAdmin);
    },

    handleItemRemoval() {
        const data = $(this).attr('data');
        const divId = $(this).attr('divid');
        
        if (divId === 'pspider') {
            EzXSS.setSpider('0');
            return;
        }
        
        const type = divId.charAt(0) === 'p' ? 'pages' :
                    divId.charAt(0) === 'w' ? 'whitelist' :
                    divId.charAt(0) === 'b' ? 'blacklist' : '';
        
        const id = window.location.href.split('/').pop();
        
        Utils.request(`/manage/payload/removeItem/${id}`, { data, type })
            .then(() => {
                $(`#${divId}`).fadeOut('slow');
            });
    },

    handleSpiderChange() {
        EzXSS.setSpider($(this).val());
    },

    handleTelegramChatId() {
        const bottoken = $('#telegram_bottoken').val();
        
        Utils.request('/manage/account/getchatid', { bottoken })
            .then((data) => {
                if (data.chatid) {
                    $('#getChatId').modal('hide');
                    $('#chatid').val(data.chatid);
                } else {
                    $('#getChatId').modal('show');
                    $('#getChatIdBody').html(data.error);
                }
            });
    },

    handlePasswordGeneration() {
        const password = Utils.generatePassword(20);
        $('#password').val(password);
        $('#password').attr('type', 'text');
        setTimeout(() => {
            $('#password').attr('type', 'password');
        }, 5000);
    },

    handleBulkDelete() {
        $("input[name='selected']:checked").each(function() {
            const id = $(this).val();
            const row = $(`#${id}`);
            
            Utils.request(`/manage/reports/delete/${id}`)
                .then(() => {
                    row.fadeOut('slow');
                })
                .catch(() => {
                    console.error(`Failed to delete report ${id}`);
                });
        });
    },

    handleBulkArchive() {
        $("input[name='selected']:checked").each(function() {
            const id = $(this).val();
            const row = $(`#${id}`);
            
            Utils.request(`/manage/reports/archive/${id}`)
                .then(() => {
                    row.fadeOut('slow');
                })
                .catch(() => {
                    console.error(`Failed to archive report ${id}`);
                });
        });
    },

    handleReportDelete() {
        const id = $(this).attr('report-id');
        
        Utils.request(`/manage/reports/delete/${id}`)
            .then(() => {
                if (window.location.href.includes('/view/')) {
                    window.location.href = '/manage/reports';
                } else {
                    $(`#${id}`).fadeOut('slow');
                }
            });
    },

    handleReportArchive() {
        const id = $(this).attr('report-id');
        
        Utils.request(`/manage/reports/archive/${id}`)
            .then(() => {
                $(`#${id}`).fadeOut('slow');
            });
    },

    handleReportShare() {
        $('#reportid').val($(this).attr('report-id'));
        $('#shareid').val(
            `https://${window.location.hostname}/manage/reports/share/${$(this).attr('share-id')}`
        );
    },

    handleBulkExecute() {
        const command = $('#command').val();
        
        $("input[name='selected']:checked").each(function() {
            const url = $(this).attr('url');
            Utils.request(url, { execute: '', command })
                .then(() => {
                    $('#command').val('');
                });
        });
    },

    handleBulkKill() {
        $("input[name='selected']:checked").each(function() {
            const url = $(this).attr('url');
            Utils.request(url, { kill: '' });
        });
    },

    handlePersistentBulkDelete() {
        $("input[name='selected']:checked").each(function() {
            const parent = $(this).parent().parent().parent().parent();
            const url = $(this).attr('url');
            
            parent.fadeOut('slow');
            Utils.request(url, { delete: '' })
                .catch(() => {
                    parent.fadeIn('slow'); // Restore on error
                });
        });
    },

    handleExecute() {
        const command = $('#command').val();
        
        Utils.request(window.location.pathname, { execute: '', command })
            .then(() => {
                $('#command').val('');
            });
    },

    handleCommandKeydown(e) {
        if (e.key === 'Enter' && e.ctrlKey) {
            e.preventDefault();
            $('#execute').click();
        }
    },

    handleExtensionMethodChange() {
        if ($(this).val() === 'github') {
            $('#github-install-form').show();
            $('#custom-install-form').hide();
        } else {
            $('#github-install-form').hide();
            $('#custom-install-form').show();
        }
    },

    handleExtensionToggle() {
        const extensionId = $(this).data('id');
        
        $.ajax({
            url: `/manage/extensions/toggle/${extensionId}`,
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({})
        }).fail((xhr, status, error) => {
            console.error('Failed to toggle extension:', error);
            // Revert checkbox state on error
            $(this).prop('checked', !$(this).prop('checked'));
        });
    },

    handleDOMRender() {
        try {
            const domContent = $('#dom').val();
            const byteCharacters = unescape(encodeURIComponent(domContent));
            const byteArrays = [];

            for (let offset = 0; offset < byteCharacters.length; offset += 1024) {
                const slice = byteCharacters.slice(offset, offset + 1024);
                const byteNumbers = new Array(slice.length);
                
                for (let i = 0; i < slice.length; i++) {
                    byteNumbers[i] = slice.charCodeAt(i);
                }

                byteArrays.push(new Uint8Array(byteNumbers));
            }

            const blob = new Blob(byteArrays, { type: 'text/html' });
            const blobUrl = URL.createObjectURL(blob);

            window.open(blobUrl, '_blank');
        } catch (error) {
            console.error('Failed to render DOM:', error);
            alert('Failed to render DOM content');
        }
    },

    handleCookiesCopy() {
        try {
            const cookiesText = $('#cookies').text();
            const origin = $(this).attr('report-origin');
            const split = cookiesText.split('; ');
            const cookiesArray = [];

            split.forEach((value, index) => {
                const [cookieName, cookieValue] = value.split('=');
                
                if (cookieName && cookieValue) {
                    cookiesArray.push({
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
                    });
                }
            });

            const json = JSON.stringify(cookiesArray, null, 2);
            Utils.copyToClipboard(json);
        } catch (error) {
            console.error('Failed to copy cookies:', error);
            alert('Failed to copy cookies');
        }
    },

    handleSelectAll(event) {
        const isChecked = this.checked;
        $(':checkbox').prop('checked', isChecked);
    },

    handleShiftSelect(e) {
        const checkbox = $(`#${$(this).attr('for')}`);

        if (!this.state.lastChecked) {
            this.state.lastChecked = checkbox[0];
            return;
        }

        if (e.shiftKey) {
            const checkboxes = $('input[type="checkbox"]');
            const start = checkboxes.index(checkbox);
            const end = checkboxes.index(this.state.lastChecked);

            checkboxes
                .slice(Math.min(start, end), Math.max(start, end) + 1)
                .prop('checked', this.state.lastChecked.checked);
        }

        this.state.lastChecked = checkbox[0];

        // Prevent text selection
        document.onselectstart = () => false;
        setTimeout(() => {
            document.onselectstart = null;
        }, 100);
    },

    handleCopyButton(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const targetSelector = $(this).data('target');
        const targetElement = $(targetSelector);
        
        if (targetElement.length > 0) {
            const textToCopy = targetElement.val() || targetElement.text();
            Utils.copyToClipboard(textToCopy, $(this));
        }
    },

    // Utility methods
    pickCommon(id, row, admin = 0) {
        const commonElement = $(`#most_common${row}`);
        const topRowElement = $(`#toprow_common${row}`);
        const loadingElement = $(`#loading_common${row}`);
        
        commonElement.empty();
        topRowElement.hide();
        loadingElement.show();
        
        Utils.request('/manage/dashboard/mostCommon', {
            id: parseInt(id),
            row,
            admin
        }).then((data) => {
            loadingElement.hide();
            topRowElement.show();
            
            if (data.length > 0) {
                data.forEach((value) => {
                    const values = Object.values(value);
                    $('<tr>')
                        .append(
                            $('<td>').text(values[0]),
                            $('<td>').text(values[1])
                        )
                        .appendTo(commonElement);
                });
            } else {
                topRowElement.hide();
                commonElement.text('No reports data found');
            }
        }).catch(() => {
            loadingElement.hide();
            commonElement.text('Failed to load data');
        });
    },

    setSpider(method) {
        const id = window.location.pathname.split('/').pop();
        
        Utils.request(`/manage/payload/spider/${id}`, { method })
            .then(() => {
                window.location.reload();
            })
            .catch((error) => {
                console.error('Failed to set spider method:', error);
            });
    },

    /**
     * Shortboost function for domain compression
     * @param {string} str - String to compress
     * @returns {string} Compressed string
     */
    shortboost(str) {
        const replacements = {
            "ij": "ĳ", "bar": "㍴", "ov": "㍵", "na": "㎁", "ma": "㎃", "ka": "㎄", "kb": "㎅", "mb": "㎆", "gb": "㎇", "cal": "㎈", "kcal": "㎉", "pf": "㎊", "nf": "㎋", "mg": "㎎", "kg": "㎏", "hz": "㎐", "khz": "㎑", "mhz": "㎒", "ghz": "㎓", "thz": "㎔", "ml": "㎖", "dl": "㎗", "kl": "㎘", "fm": "㎙", "nm": "㎚", "mm": "㎜", "cm": "㎝", "km": "㎞", "mm2": "㎟", "cm2": "㎠", "m2": "㎡", "km2": "㎢", "mm3": "㎣", "cm3": "㎤", "m3": "㎥", "km3": "㎦", "pa": "㎩", "kpa": "㎪", "mpa": "㎫", "gpa": "㎬", "rad": "㎭", "ps": "㎰", "ns": "㎱", "ms": "㎳", "pv": "㎴", "nv": "㎵", "mv": "㎷", "kv": "㎸", "pw": "㎺", "nw": "㎻", "mw": "㎽", "kw": "㎾", "bq": "㏃", "cc": "㏄", "cd": "㏅", "db": "㏈", "gy": "㏉", "ha": "㏊", "hp": "㏋", "in": "㏌", "kk": "㏍", "kt": "㏏", "lm": "㏐", "ln": "㏑", "log": "㏒", "lx": "㏓", "mb": "㏔", "mil": "㏕", "mol": "㏖", "ph": "㏗", "pm": "㏘", "ppm": "㏙", "pr": "㏚", "sr": "㏛", "sv": "㏜", "wb": "㏝", "no": "№", "tm": "™", "tel": "℡", "rs": "₨", "sm": "℠", "gal": "㏿", "ff": "ﬀ", "fi": "ﬁ", "fl": "ﬂ", "ffi": "ﬃ", "ffl": "ﬄ", "st": "ﬅ", "dm": "㍷", "dm2": "㍸", "dm3": "㍹", "iu": "㍺", "pc": "㍶", "hg": "㋌", "erg": "㋍", "ev": "㋎", "ltd": "㋏", "hpa": "㍱", "da": "㍲", "pte": "㉐", "ix": "ⅸ", "ii": "ⅱ", "iii": "ⅲ", "iv": "ⅳ", "xi": "Ⅺ", "xii": "Ⅻ", "vi": "Ⅵ", "vii": "Ⅶ", "viii": "Ⅷ", "nj": "Ǌ", "dz": "Ǳ", "lj": "Ǉ", "10": "⑩", "11": "⑪", "12": "⑫", "13": "⑬", "14": "⑭", "15": "⑮", "16": "⑯", "17": "⑰", "18": "⑱", "19": "⑲", "20": "⑳", "21": "㉑", "22": "㉒", "23": "㉓", "24": "㉔", "25": "㉕", "26": "㉖", "27": "㉗", "28": "㉘", "29": "㉙", "30": "㉚", "31": "㉛", "32": "㉜", "33": "㉝", "34": "㉞", "35": "㉟", "36": "㊱", "37": "㊲", "38": "㊳", "39": "㊴", "40": "㊵", "41": "㊶", "42": "㊷", "43": "㊸", "44": "㊹", "45": "㊺", "46": "㊻", "47": "㊼", "48": "㊽", "49": "㊾", "50": "㊿"
        };

        const sortedKeys = Object.keys(replacements).sort((a, b) => b.length - a.length);

        for (const key of sortedKeys) {
            const regex = new RegExp(key, 'g');
            str = str.replace(regex, replacements[key]);
        }

        return str;
    },

    /**
     * Initialize extensions dropdown functionality
     */
    initializeExtensionsDropdown() {
        const dropdown = $('.extensions-dropdown');
        if (dropdown.length === 0) return;

        const ExtensionsDropdown = {
            dropdown,
            display: dropdown.find('.extensions-display'),
            dropdownList: dropdown.find('.extensions-dropdown-list'),
            hiddenInput: dropdown.find('#extensions-hidden'),
            tagsContainer: dropdown.find('.extensions-tags'),
            dropdownBtn: dropdown.find('.extensions-dropdown-btn'),
            selectedExtensions: [],
            isOpen: false,

            init() {
                this.bindEvents();
                this.initializeFromCheckboxes();
            },

            bindEvents() {
                this.display.on('click', (e) => {
                    if ($(e.target).closest('.extension-tag').length === 0) {
                        e.stopPropagation();
                        this.toggleDropdown();
                    }
                });

                this.dropdown.on('change', 'input[type="checkbox"]', (e) => {
                    const checkbox = $(e.target);
                    const id = checkbox.val();
                    const name = checkbox.data('name');
                    const description = checkbox.data('description');
                    
                    if (checkbox.is(':checked')) {
                        this.addExtension(id, name, description);
                    } else {
                        this.removeExtension(id);
                    }
                });

                $(document).on('click', (e) => {
                    if (!this.dropdown.is(e.target) && this.dropdown.has(e.target).length === 0) {
                        this.closeDropdown();
                    }
                });

                this.dropdownList.on('click', (e) => {
                    e.stopPropagation();
                });
            },

            initializeFromCheckboxes() {
                this.dropdown.find('input[type="checkbox"]:checked').each((index, element) => {
                    const $element = $(element);
                    const id = $element.val();
                    const name = $element.data('name');
                    const description = $element.data('description');
                    
                    this.selectedExtensions.push({ id, name, description });
                });
                
                this.updateDisplay();
                this.updateHiddenInput();
            },

            updateDisplay() {
                this.tagsContainer.empty();
                
                if (this.selectedExtensions.length === 0) {
                    this.dropdownBtn.removeClass('has-selections').addClass('empty');
                    this.dropdownBtn.find('.dropdown-text').text('Select extensions...');
                } else {
                    this.dropdownBtn.addClass('has-selections').removeClass('empty');
                    
                    this.selectedExtensions.forEach((ext) => {
                        const tag = $('<div class="extension-tag"></div>');
                        const nameSpan = $('<span></span>').text(ext.name);
                        const removeSpan = $('<span class="extension-tag-remove">×</span>')
                            .attr('data-id', ext.id)
                            .on('click', (e) => {
                                e.stopPropagation();
                                this.removeExtension(ext.id);
                            });
                        
                        tag.append(nameSpan).append(removeSpan);
                        this.tagsContainer.append(tag);
                    });
                }
            },

            updateHiddenInput() {
                const ids = this.selectedExtensions.map(ext => ext.id);
                this.hiddenInput.val(ids.join(','));
            },

            toggleDropdown() {
                this.isOpen = !this.isOpen;
                if (this.isOpen) {
                    this.display.addClass('active');
                    this.dropdownList.show();
                } else {
                    this.display.removeClass('active');
                    this.dropdownList.hide();
                }
            },

            closeDropdown() {
                if (this.isOpen) {
                    this.isOpen = false;
                    this.display.removeClass('active');
                    this.dropdownList.hide();
                }
            },

            addExtension(id, name, description) {
                if (!this.selectedExtensions.find(ext => ext.id === id)) {
                    this.selectedExtensions.push({ id, name, description });
                    this.updateDisplay();
                    this.updateHiddenInput();
                }
            },

            removeExtension(id) {
                this.selectedExtensions = this.selectedExtensions.filter(ext => ext.id !== id);
                this.updateDisplay();
                this.updateHiddenInput();
                
                this.dropdown.find(`input[value="${id}"]`).prop('checked', false);
            }
        };

        ExtensionsDropdown.init();
    }
};

// Initialize when document is ready
$(document).ready(() => {
    EzXSS.init();
});

// Legacy function exports for backward compatibility
function request(action, data = {}) {
    return Utils.request(action, data);
}

function shortboost(str) {
    return EzXSS.shortboost(str);
}

function decodeHTMLEntities(text) {
    return Utils.decodeHTMLEntities(text);
}

function initExtensionsDropdown() {
    EzXSS.initializeExtensionsDropdown();
}