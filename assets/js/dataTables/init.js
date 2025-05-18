$(document).ready(function () {
    $.fn.dataTable.ext.errMode = 'none';
    
    const commonSettings = {
        scrollX: false,
        autoWidth: true,
        responsive: true,
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            paginate: {
                first: "«",
                previous: "‹",
                next: "›",
                last: "»"
            }
        }
    };

    const truncateContent = (data, type, row, column) => {
        if (type === 'display' && data) {
            if (['uri', 'ip', 'payload', 'shorturi', 'description'].includes(column)) {
                return `<div class="truncate-cell" title="${data}">${data}</div>`;
            }
        }
        return data;
    };

    new DataTable('#reports', {
        ...commonSettings,
        "ajax": {
            "url": "/manage/reports/data",
            "contentType": "application/json",
            "type": "POST",
            "data": function (d) {
                d.id = window.location.pathname.split('/').filter(Boolean).pop() === 'all' ? 0 : window.location.pathname.split('/').filter(Boolean).pop();
                d.archive = (window.location.search.indexOf('archive=1') !== -1 ? 1 : 0);
                return JSON.stringify(d);
            }
        },
        columns: [
            {
                className: 'dt-body-left',
                data: 'shareid',
                orderable: false,
                render: function (data, type, row) {
                    return `<div class="action-column">
                        <label class="reports-checkbox-label" for="chk_` + row.id + `">
                            <input class="chkbox" type="checkbox" name="selected"
                                value="`+ row.id + `" id="chk_` + row.id + `"
                                report-id="`+ row.id + `">
                            <span class="reports-checkbox-custom rectangular"></span>
                        </label>
                        <div class="btn-group btn-view" role=group>
                            <a href="/manage/reports/view/`+ row.id + `" class="btn pretty-border">View</a>
                            <div class=btn-group role=group>
                                <button type=button
                                    class="btn btn-default dropdown-toggle pretty-border"
                                    data-toggle=dropdown aria-haspopup=true aria-expanded=true>
                                    <span class=caret></span>
                                </button>
                                <div class=dropdown-backdrop></div>
                                <ul class=dropdown-menu>
                                    <li><a class="share" report-id="`+ row.id + `" share-id="` + data + `" data-toggle="modal" data-target="#shareModal">Share</a></li>
                                    <li><a class="delete" report-id="`+ row.id + `">Delete</a></li>
                                    <li><a class="archive" report-id="`+ row.id + `">Archive</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>`;
                }
            },
            { data: 'id' },
            { 
                data: 'uri',
                className: 'truncate-cell',
                render: function(data, type, row) {
                    return truncateContent(data, type, row, 'uri');
                }
            },
            { 
                data: 'ip',
                className: 'truncate-cell',
                render: function(data, type, row) {
                    return truncateContent(data, type, row, 'ip');
                }
            },
            { data: 'browser' },
            { 
                data: 'payload',
                className: 'truncate-cell',
                render: function(data, type, row) {
                    return truncateContent(data, type, row, 'payload');
                }
            },
            { data: 'last' }
        ],
        columnDefs: [{ targets: 2, className: "truncate" }],
        createdRow: function (row, data, dataIndex) {
            $(row).attr('id', data.id);
        },
        order: [[1, 'desc']],
        dom: '<"top"lipf>rt',
        drawCallback: function(settings) {
            var api = this.api();
            var hasData = api.data().any();
            $('.with-bar').toggle(hasData);
        }
    });

    var dataTablePersistent = new DataTable('#persistent', {
        ...commonSettings,
        "ajax": {
            "url": "/manage/persistent/sessions",
            "contentType": "application/json",
            "type": "POST",
            "data": function (d) {
                d.id = window.location.pathname.split('/').filter(Boolean).pop() === 'all' ? 0 : window.location.pathname.split('/').filter(Boolean).pop();
                return JSON.stringify(d);
            }
        },
        columns: [
            {
                className: 'dt-body-left persistent-column',
                data: 'clientid',
                orderable: false,
                render: function (data, type, row) {
                    return `<div class="action-column">
                        <label class="reports-checkbox-label" for="chk_` + row.id + `">
                            <input class="chkbox" type="checkbox" name="selected"
                                value="` + row.id + `" id="chk_` + row.id + `"
                                url="/manage/persistent/session/` + data + `~` + row.origin + `">
                            <span class="reports-checkbox-custom rectangular" style="top:7px"></span>
                        </label>
                        <a href="/manage/persistent/session/` + data + `~` + row.origin + `">` + data + `</a>
                    </div>`;
                }
            },
            { data: 'browser' },
            { 
                data: 'ip',
                className: 'truncate-cell',
                render: function(data, type, row) {
                    return truncateContent(data, type, row, 'ip');
                }
            },
            { 
                data: 'shorturi',
                className: 'truncate-cell',
                render: function(data, type, row) {
                    return truncateContent(data, type, row, 'shorturi');
                }
            },
            { 
                data: 'payload',
                className: 'truncate-cell',
                render: function(data, type, row) {
                    return truncateContent(data, type, row, 'payload');
                }
            },
            { data: 'requests' },
            { 
                data: 'last',
                render: function (data, type, row, meta) {
                    if (type === 'sort') {
                        return row.time;
                    }
                    return data;
                }
            },
        ],
        order: [[6, 'desc']],
        dom: '<"top"lipf>rt',
        drawCallback: function(settings) {
            var api = this.api();
            var hasData = api.data().any();
            $('.with-bar').toggle(hasData);
        }
    });

    if (location.toString().split('/')[4] === "persistent") {
        var elapsedTime = 0;
        function updateTimer() {
            setInterval(function () {
                elapsedTime += 1;
                $("#last").text(elapsedTime + "s ago");
            }, 1000);
        }
        updateTimer();
        setInterval(function () {
            dataTablePersistent.ajax.reload(null, false);
            elapsedTime = 0;
        }, 120000);
    }

    new DataTable('#logs', {
        ...commonSettings,
        "ajax": {
            "url": "/manage/logs/data",
            "contentType": "application/json",
            "type": "POST",
            "data": function (d) {
                return JSON.stringify(d);
            }
        },
        columns: [
            { 
                data: 'user',
                render: function(data, type, row) {
                    return truncateContent(data, type, row, 'user');
                }
            },
            { 
                data: 'description',
                className: 'truncate-cell',
                render: function(data, type, row) {
                    return truncateContent(data, type, row, 'description');
                }
            },
            { 
                data: 'ip',
                className: 'truncate-cell',
                render: function(data, type, row) {
                    return truncateContent(data, type, row, 'ip');
                }
            },
            { 
                data: 'date',
                render: function (data, type, row, meta) {
                    if (type === 'sort') {
                        return row.time;
                    }
                    return truncateContent(data, type, row, 'date');
                }
            },
        ],
        order: [[3, 'desc']],
        dom: '<"top"lipf>rt',
    });

    new DataTable('#users', {
        ...commonSettings,
        "ajax": {
            "url": "/manage/users/data",
            "contentType": "application/json",
            "type": "POST",
            "data": function (d) {
                return JSON.stringify(d);
            }
        },
        columns: [
            { 
                data: 'id',
                render: function(data, type, row) {
                    return truncateContent(data, type, row, 'id');
                }
            },
            { 
                data: 'username',
                render: function(data, type, row) {
                    return truncateContent(data, type, row, 'username');
                }
            },
            { 
                data: 'rank',
                render: function(data, type, row) {
                    return truncateContent(data, type, row, 'rank');
                }
            },
            { 
                data: 'payloads',
                render: function(data, type, row) {
                    return truncateContent(data, type, row, 'payloads');
                }
            },
            { 
                data: 'id',
                orderable: false,
                render: function (data, type, row) {
                    return `<a href="/manage/users/edit/` + data + `">Edit</a>`;
                }
            },
            { 
                data: 'id',
                orderable: false,
                render: function (data, type, row) {
                    return `<a href="/manage/users/delete/` + data + `">Delete</a>`;
                }
            },
        ],
        order: [[0, 'asc']],
        scrollY: false,
        scrollX: false,
        dom: '<"top"lipf>rt',
    });

    $('#simple-table').DataTable({
        ...commonSettings,
        columnDefs: [
            { orderable: true, targets: [0] }
        ],
        "pageLength": 25,
        order: [[0, 'desc']],
        dom: '<"top"lipf>rt',
    });

    if ($('#user-logs').length) {
        new DataTable('#user-logs', {
            ...commonSettings,
            "ajax": {
                "url": "/manage/logs/users",
                "contentType": "application/json",
                "type": "POST",
                "data": function (d) {
                    d.id = window.location.pathname.split('/').filter(Boolean).pop();
                    return JSON.stringify(d);
                }
            },
            columns: [
                { 
                    data: 'description',
                    render: function(data, type, row) {
                        return truncateContent(data, type, row, 'description');
                    }
                },
                { 
                    data: 'ip',
                    render: function(data, type, row) {
                        return truncateContent(data, type, row, 'ip');
                    }
                },
                { 
                    data: 'date',
                    render: function (data, type, row, meta) {
                        if (type === 'sort') {
                            return row.time;
                        }
                        return truncateContent(data, type, row, 'date');
                    }
                }
            ],
            columnDefs: [
                { targets: 1, className: "truncate" }
            ],
            order: [[2, 'desc']],
            dom: '<"top"pf>rt',
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50], [5, 10, 25, 50]],
            pagingType: "simple_numbers",
            scrollX: false,
            autoWidth: false,
        });
    }
});