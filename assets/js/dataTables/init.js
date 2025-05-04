$(document).ready(function () {
    $.fn.dataTable.ext.errMode = 'none';
    
    new DataTable('#reports', {
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
                width: 110,
                className: 'dt-body-left',
                data: 'shareid',
                orderable: false,
                render: function (data, type, row) {
                    return `<label class="reports-checkbox-label" for="chk_` + row.id + `">
                        <input class="chkbox" type="checkbox" name="selected"
                            value="`+ row.id + `" id="chk_` + row.id + `"
                            report-id="`+ row.id + `">
                        <span class="reports-checkbox-custom rectangular"></span>
                    </label>
                    <div class="btn-group btn-view" style="width:100px;max-width:100px;" role=group>
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
                    </div>`;
                }
            },
            { data: 'id' },
            { data: 'uri' },
            { data: 'ip' },
            { data: 'browser' },
            { data: 'payload' },
            { data: 'last' }
        ],
        columnDefs: [{ targets: 2, className: "truncate" }],
        createdRow: function (row, data, dataIndex) {
            $(row).attr('id', data.id);
            var td = $(row).find(".truncate");
            td.attr("title", td.html());
        },
        order: [[1, 'desc']],
        dom: '<"top"lipf>rt',
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search reports...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            paginate: {
                first: "«",
                previous: "‹",
                next: "›",
                last: "»"
            }
        },
        // Show with-bar only when data is present
        drawCallback: function(settings) {
            var api = this.api();
            var hasData = api.data().any();
            $('.with-bar').toggle(hasData);
        }
    });

    var dataTablePersistent = new DataTable('#persistent', {
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
                width: 110,
                className: 'dt-body-left persistent-column',
                data: 'clientid',
                orderable: false,
                render: function (data, type, row) {
                    return `<label class="reports-checkbox-label" for="chk_` + row.id + `">
                    <input class="chkbox" type="checkbox" name="selected"
                        value="` + row.id + `" id="chk_` + row.id + `"
                        url="/manage/persistent/session/` + data + `~` + row.origin + `">
                    <span class="reports-checkbox-custom rectangular" style="top:7px"></span>
                </label>
                <a href="/manage/persistent/session/` + data + `~` + row.origin + `">` + data + `</a>`;
                }
            },
            { data: 'browser', },
            { data: 'ip' },
            { data: 'shorturi' },
            { data: 'payload' },
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
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search sessions...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            paginate: {
                first: "«",
                previous: "‹",
                next: "›",
                last: "»"
            }
        },
        // Show with-bar only when data is present
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
        "ajax": {
            "url": "/manage/logs/data",
            "contentType": "application/json",
            "type": "POST",
            "data": function (d) {
                return JSON.stringify(d);
            }
        },
        columns: [
            { data: 'user', },
            { data: 'description', },
            { data: 'ip', },
            { 
                data: 'date',
                render: function (data, type, row, meta) {
                    if (type === 'sort') {
                        return row.time;
                    }
                    return data;
                }
            },
        ],
        order: [[3, 'desc']],
        dom: '<"top"lipf>rt',
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search logs...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            paginate: {
                first: "«",
                previous: "‹",
                next: "›",
                last: "»"
            }
        }
    });

    new DataTable('#users', {
        "ajax": {
            "url": "/manage/users/data",
            "contentType": "application/json",
            "type": "POST",
            "data": function (d) {
                return JSON.stringify(d);
            }
        },
        columns: [
            { data: 'id', },
            { data: 'username', },
            { data: 'rank', },
            { data: 'payloads', },
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
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search users...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            paginate: {
                first: "«",
                previous: "‹",
                next: "›",
                last: "»"
            }
        }
    });

    $('#simple-table').DataTable({
        columnDefs: [
            { orderable: false, targets: [0] }
        ],
        "pageLength": 25,
        order: [[0, 'desc']],
        dom: '<"top"lipf>rt',
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
    });

    // Initialize user logs DataTable if the element exists
    if ($('#user-logs').length) {
        new DataTable('#user-logs', {
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
                        if (type === 'display') {
                            return '<div style="word-wrap: break-word; word-break: break-word;">' + data + '</div>';
                        }
                        return data;
                    }
                },
                { 
                    data: 'ip',
                    render: function(data, type, row) {
                        if (type === 'display') {
                            return '<div class="truncate" title="' + data + '">' + data + '</div>';
                        }
                        return data;
                    }
                },
                { 
                    data: 'date',
                    render: function (data, type, row, meta) {
                        if (type === 'sort') {
                            return row.time;
                        }
                        return data;
                    }
                }
            ],
            columnDefs: [
                { targets: 1, className: "truncate" }
            ],
            createdRow: function (row, data, dataIndex) {
                var tds = $(row).find(".truncate");
                tds.each(function() {
                    $(this).attr("title", $(this).html());
                });
            },
            order: [[2, 'desc']],
            dom: '<"top"pf>rt',
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50], [5, 10, 25, 50]],
            pagingType: "simple_numbers",
            scrollX: false,
            autoWidth: false,
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search logs...",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ entries",
                paginate: {
                    first: "«",
                    previous: "‹",
                    next: "›",
                    last: "»"
                }
            }
        });
    }
});