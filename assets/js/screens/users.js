window.EZ = window.EZ || {};
(function (EZ) {
  "use strict";

  var dom = EZ.dom, util = EZ.util;

  var usersDirty = false;

  var BEHAVIOUR_CAPS = ["payload.behaviour.persistent", "payload.behaviour.spider", "payload.behaviour.beacon",
    "payload.behaviour.customjs", "payload.behaviour.pages", "payload.behaviour.filters", "payload.behaviour.extensions"];
  var CAPS = ["payload.create", "payload.delete", "payload.share", "payload.viewall",
    "payload.behaviour"].concat(BEHAVIOUR_CAPS,
    ["report.view", "report.viewall", "report.share", "session.view", "session.viewall", "session.interact", "user.manage", "user.roles", "settings.manage", "apikey.manage", "log.view"]);
  var PRESETS = {
    admin: "*",
    user: ["payload.create", "payload.delete", "report.view", "report.share",
      "session.view", "session.interact", "apikey.manage",
      "payload.behaviour"].concat(BEHAVIOUR_CAPS)
  };
  var ROLES = ["admin", "user"];

  var CAP_LABEL = {
    "payload.create": "Create payloads", "payload.delete": "Delete payloads", "payload.share": "Share payloads",
    "payload.viewall": "View all payloads",
    "payload.behaviour": "Advanced payload behaviour",
    "payload.behaviour.persistent": "Persistent mode", "payload.behaviour.spider": "Page spider",
    "payload.behaviour.beacon": "CSP-bypass beacons", "payload.behaviour.customjs": "Custom JavaScript",
    "payload.behaviour.pages": "Extract extra pages", "payload.behaviour.filters": "Allow / deny filters",
    "payload.behaviour.extensions": "Extensions",
    "report.view": "View reports", "report.viewall": "View all reports", "report.share": "Share reports",
    "session.view": "View sessions", "session.viewall": "View all sessions", "session.interact": "Live console & proxy",
    "user.manage": "Manage users", "user.roles": "Manage roles & permissions",
    "settings.manage": "Manage settings", "apikey.manage": "Manage API keys", "log.view": "View audit log"
  };
  var CAP_GROUPS = [
    { label: "Payloads", caps: ["payload.create", "payload.delete", "payload.share", "payload.viewall"] },
    { label: "Advanced payload behaviour", master: "payload.behaviour", caps: BEHAVIOUR_CAPS },
    { label: "Reports", caps: ["report.view", "report.viewall", "report.share"] },
    { label: "Sessions", caps: ["session.view", "session.viewall", "session.interact"] },
    { label: "Account", caps: ["apikey.manage"] },
    { label: "Administration", caps: ["user.manage", "user.roles", "settings.manage", "log.view"] }
  ];

  function el(id) { return EZ.dom.byId(id); }
  function panel(name) { return document.querySelector('[data-users-panel="' + name + '"]'); }
  function decodePerms(p) {
    if (!p) { return {}; }
    if (typeof p === "string") { try { return JSON.parse(p) || {}; } catch (e) { return {}; } }
    return (typeof p === "object") ? p : {};
  }
  var rolesData = null;
  function setRoles(res) {
    rolesData = {};
    ((res && res.data && res.data.roles) || []).forEach(function (r) { rolesData[r.name] = r; });
    return rolesData;
  }
  function loadRoles() {
    return EZ.api.get("/users/roles", { noGlobalError: true }).then(setRoles).catch(function () { rolesData = null; });
  }
  function roleNames() { return rolesData ? Object.keys(rolesData) : ROLES.slice(); }
  function roleSlug(raw) {
    return String(raw || "").toLowerCase().replace(/[^a-z0-9_-]+/g, "-").replace(/^-+|-+$/g, "");
  }
  function presetMap(role) {
    var m = {};
    if (role === "admin") { CAPS.forEach(function (c) { m[c] = true; }); return m; }
    if (rolesData && rolesData[role]) { CAPS.forEach(function (c) { m[c] = !!rolesData[role].capabilities[c]; }); return m; }
    var preset = PRESETS[role];
    CAPS.forEach(function (c) { m[c] = (preset === "*") || (!!preset && preset.indexOf(c) !== -1); });
    return m;
  }
  function effective(user) {
    var role = user.role || "user";
    if (role === "admin") { var all = {}; CAPS.forEach(function (c) { all[c] = true; }); return all; }
    var m = presetMap(role), ov = decodePerms(user.permissions);
    Object.keys(ov).forEach(function (c) { if (CAPS.indexOf(c) !== -1) { m[c] = !!ov[c]; } });
    return m;
  }
  function roleTag(role) {
    var cls = role === "admin" ? "tag tag--muted" : "tag";
    return '<span class="' + cls + '">' + util.escapeHtml(role) + "</span>";
  }
  function capsCell(user) {
    var role = user.role || "user";
    if (role === "admin") { return '<span class="faint">-</span>'; }
    var preset = presetMap(role), eff = effective(user), diff = [];
    CAPS.forEach(function (c) {
      var inPreset = !!preset[c], has = !!eff[c];
      if (has && !inPreset) { diff.push("+" + c); }
      else if (!has && inPreset) { diff.push("\u2212" + c); }
    });
    if (!diff.length) { return '<span class="faint">Default</span>'; }
    return '<span class="tag" title="' + util.escapeHtml(diff.join(", ")) + '">Custom</span>';
  }
  function twofaTag(user) {
    return user.twofa_enabled
      ? '<span class="tag tag--success">On</span>'
      : '<span class="tag tag--muted">Off</span>';
  }
  function userNameCell(user) {
    var name = util.escapeHtml(user.username);
    return user.banned
      ? name + ' <span class="tag tag--danger">Banned</span>'
      : name;
  }
  function fmtDate(s) {
    if (!s) { return "-"; }
    var d = new Date(String(s).replace(" ", "T"));
    if (isNaN(d.getTime())) { return util.escapeHtml(String(s)); }
    return d.toLocaleDateString([], { year: "numeric", month: "short", day: "numeric" });
  }

  var USERS_PER_PAGE = 25;
  var usersState = { page: 1, search: "" };

  function renderUsersTab() {
    var p = panel("users");
    if (!p) { return; }
    usersState = { page: 1, search: EZ.util.readInitialSearch() };
    p.innerHTML =
      '<div class="toolbar">' +
        '<div class="search">' +
          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5" stroke-linecap="round"/></svg>' +
          '<input class="input" id="users-search" type="search" placeholder="Search users...">' +
        "</div>" +
        '<div class="toolbar__group">' +
          '<button class="btn" id="users-roles" type="button">Roles</button>' +
          '<button class="btn btn--primary" id="users-add" type="button">Add user</button>' +
        "</div>" +
      "</div>" +
      '<div class="tablewrap"><table class="data">' +
        "<thead><tr><th>User</th><th>Role</th><th>Access</th><th>2FA</th><th>Created</th></tr></thead>" +
        '<tbody id="users-tbody"><tr><td colspan="5" class="faint">' + EZ.ui.loading() + '</td></tr></tbody>' +
      "</table></div>" +
      '<div class="pagination" id="users-pagination"></div>';

    dom.on(el("users-add"), "click", function () { openUserModal(null); });
    dom.on(el("users-roles"), "click", openRolesModal);
    var searchBox = el("users-search");
    if (searchBox) { searchBox.value = usersState.search; }
    dom.on(searchBox, "input", util.debounce(function () {
      usersState.search = searchBox.value.trim();
      usersState.page = 1;
      loadUsers();
    }, 300));

    loadUsers();
  }

  function loadUsers() {
    var tb = el("users-tbody");
    if (tb) { tb.innerHTML = '<tr><td colspan="5" class="faint">' + EZ.ui.loading() + '</td></tr>'; }
    var q = "?page=" + usersState.page + "&per_page=" + USERS_PER_PAGE +
      (usersState.search ? "&search=" + encodeURIComponent(usersState.search) : "");
    EZ.api.get("/users" + q).then(function (res) {
      paintUsers(res.data || []);
      paintUsersPagination((res.meta && res.meta.pagination) || {});
    }).catch(function () {
      if (tb) { tb.innerHTML = '<tr><td colspan="5" class="faint">Could not load users.</td></tr>'; }
    });
  }

  function paintUsersPagination(meta) {
    var box = el("users-pagination");
    if (!box) { return; }
    EZ.ui.renderPager(box, meta, function (p) { usersState.page = p; loadUsers(); });
  }

  function paintUsers(rows) {
    var tb = el("users-tbody");
    if (!tb) { return; }
    if (!rows.length) {
      tb.innerHTML = '<tr><td colspan="5" class="faint">' +
        (usersState.search ? "No users match." : "No users yet.") + "</td></tr>";
      return;
    }
    tb.innerHTML = rows.map(function (u) {
      return '<tr data-edit-user="' + u.id + '">' +
        '<td class="cell-strong">' + userNameCell(u) + "</td>" +
        "<td>" + roleTag(u.role) + "</td>" +
        "<td>" + capsCell(u) + "</td>" +
        "<td>" + twofaTag(u) + "</td>" +
        '<td class="faint">' + fmtDate(u.created_at) + "</td>" +
        "</tr>";
    }).join("");
    dom.qsa("[data-edit-user]", tb).forEach(function (tr) {
      dom.on(tr, "click", function () { openUserModal(tr.getAttribute("data-edit-user")); });
    });
  }

  function permSwitch(cap, map, disabled) {
    return '<span class="switch"><input type="checkbox" data-cap="' + cap + '"' +
      (cap === "payload.behaviour" ? " data-behaviour-master" : "") +
      (map[cap] ? " checked" : "") + (disabled ? " disabled" : "") + '><span class="switch__track"></span></span>';
  }
  function permRow(cap, map, disabled) {
    return '<label class="permrow"><span class="permrow__label">' + util.escapeHtml(CAP_LABEL[cap] || cap) + "</span>" +
      permSwitch(cap, map, disabled) + "</label>";
  }
  function capEditor(map, disabled) {
    return '<div id="um-caps" class="permgroups">' + CAP_GROUPS.map(function (g) {
      if (g.master) {
        return '<div class="permgroup permgroup--master" data-behaviour-group>' +
          '<label class="permrow permrow--master"><span class="permrow__label">' + util.escapeHtml(g.label) + "</span>" +
            permSwitch(g.master, map, disabled) + "</label>" +
          '<div class="permgroup__children">' + g.caps.map(function (c) { return permRow(c, map, disabled); }).join("") + "</div>" +
        "</div>";
      }
      return '<div class="permgroup"><div class="permgroup__title">' + util.escapeHtml(g.label) + "</div>" +
        g.caps.map(function (c) { return permRow(c, map, disabled); }).join("") + "</div>";
    }).join("") + "</div>";
  }
  function permBlock(role, map, expand) {
    var isAdmin = role === "admin";
    return '<div class="field" style="margin-top:var(--space-md)">' +
        '<div class="permhead"><label class="field-label" for="um-role" style="margin:0">Role</label>' +
          roleSelectHTML(role) +
          '<button class="btn btn--sm" type="button" id="um-perm-toggle" aria-expanded="' + (expand ? "true" : "false") + '"' +
            (isAdmin ? " hidden" : "") + ">Customize permissions</button>" +
        "</div>" +
        '<p class="field-hint" id="um-admin-note"' + (isAdmin ? "" : " hidden") + ">Admins always hold every permission.</p>" +
      "</div>" +
      '<div id="um-perms"' + (expand && !isAdmin ? "" : " hidden") + ' style="margin-top:var(--space-sm)">' +
        capEditor(map, isAdmin) +
      "</div>";
  }
  function capChecked(i) {
    var prev = i.getAttribute("data-prev");
    return prev !== null ? prev === "1" : i.checked;
  }
  function readCapsIn(root) {
    var m = {};
    dom.qsa("input[data-cap]", root).forEach(function (i) { m[i.getAttribute("data-cap")] = capChecked(i); });
    return m;
  }
  function readCaps() { return readCapsIn(el("um-caps")); }
  function syncBehaviourMaster() {
    var master = dom.qs("[data-behaviour-master]"), group = dom.qs("[data-behaviour-group]");
    if (!master || !group) { return; }
    var off = !master.checked;
    dom.qsa(".permgroup__children input[data-cap]", group).forEach(function (i) {
      if (off) {
        if (i.getAttribute("data-prev") === null) { i.setAttribute("data-prev", i.checked ? "1" : "0"); }
        i.checked = false;
        i.disabled = true;
      } else {
        var prev = i.getAttribute("data-prev");
        if (prev !== null) { i.checked = prev === "1"; i.removeAttribute("data-prev"); }
        i.disabled = master.disabled;
      }
    });
    group.classList.toggle("is-off", off);
  }
  function syncCapsToPreset(role) {
    var disabled = role === "admin";
    var map = disabled ? presetMap("admin") : presetMap(role);
    dom.qsa("#um-caps input[data-cap]").forEach(function (i) {
      i.checked = !!map[i.getAttribute("data-cap")];
      i.disabled = disabled;
    });
    syncBehaviourMaster();
    var toggle = el("um-perm-toggle"), perms = el("um-perms"), note = el("um-admin-note");
    if (toggle) { toggle.hidden = disabled; toggle.setAttribute("aria-expanded", "false"); }
    if (perms) { perms.hidden = true; }
    if (note) { note.hidden = !disabled; }
  }
  function wirePermBlock(onCapChange) {
    var toggle = el("um-perm-toggle");
    if (toggle) {
      dom.on(toggle, "click", function () {
        var perms = el("um-perms");
        var open = perms.hidden;
        perms.hidden = !open;
        toggle.setAttribute("aria-expanded", String(open));
      });
    }
    var master = dom.qs("[data-behaviour-master]");
    if (master) { dom.on(master, "change", function () { syncBehaviourMaster(); if (onCapChange) { onCapChange(); } }); }
  }
  function hasOverrides(user) {
    if ((user.role || "user") === "admin") { return false; }
    var preset = presetMap(user.role || "user"), eff = user.capabilities || effective(user);
    return CAPS.some(function (c) { return !!eff[c] !== !!preset[c]; });
  }
  function overridesFor(role) {
    if (role === "admin") { return {}; }
    var preset = presetMap(role), cur = readCaps(), ov = {};
    CAPS.forEach(function (c) { if (!!cur[c] !== !!preset[c]) { ov[c] = !!cur[c]; } });
    return ov;
  }
  function umMsg(text, isError) { EZ.ui.fieldMsg(el("um-msg"), text, isError); }

  var GEN_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12a9 9 0 1 1-2.6-6.3M21 4.5V9h-4.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  function pwField(id, placeholder) {
    return '<div class="copyfield" style="flex:1 1 auto"><input class="input" id="' + id + '" type="password" autocomplete="new-password"' +
      (placeholder ? ' placeholder="' + placeholder + '"' : "") + ">" +
      '<button class="copyfield__btn" type="button" data-genpw="' + id + '" aria-label="Generate password" title="Generate a strong password">' + GEN_ICON + "</button></div>";
  }
  function genPassword(len) {
    len = len || 16;
    var chars = "abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%^&*-_=+";
    var out = "", c = window.crypto, i;
    if (c && c.getRandomValues) {
      var a = new Uint32Array(len);
      c.getRandomValues(a);
      for (i = 0; i < len; i++) { out += chars.charAt(a[i] % chars.length); }
    } else {
      for (i = 0; i < len; i++) { out += chars.charAt(Math.floor(Math.random() * chars.length)); }
    }
    return out;
  }
  function wireGenPw(root) {
    dom.qsa("[data-genpw]", root || document).forEach(function (b) {
      dom.on(b, "click", function () {
        var inp = el(b.getAttribute("data-genpw"));
        if (!inp) { return; }
        inp.value = genPassword(16);
        inp.type = "text";
        inp.focus();
        inp.dispatchEvent(new Event("input", { bubbles: true }));
      });
    });
  }

  function roleSelectHTML(selected) {
    var names = roleNames();
    if (selected && names.indexOf(selected) === -1) { names = names.concat([selected]); }
    return '<select class="input" id="um-role" style="max-width:220px">' + names.map(function (r) {
      return '<option value="' + util.escapeHtml(r) + '"' + (r === selected ? " selected" : "") + ">" + util.escapeHtml(r) + "</option>";
    }).join("") + "</select>";
  }

  function openUserModal(id) {
    var body = el("user-modal-body"), title = el("user-modal-title");
    if (!body) { return; }
    var rd = el("role-defaults-body"); if (rd) { rd.innerHTML = ""; }
    if (id == null) {
      title.textContent = "Add user";
      body.innerHTML =
        '<div class="field"><label class="field-label" for="um-username">Username</label><input class="input" id="um-username" autocapitalize="none" spellcheck="false"></div>' +
        '<div class="field" style="margin-top:var(--space-md)"><label class="field-label" for="um-password">Password</label>' + pwField("um-password") + "</div>" +
        permBlock("user", presetMap("user"), false) +
        '<p class="field-hint" id="um-msg" hidden></p>' +
        '<div style="display:flex;gap:var(--space-sm);justify-content:flex-end;margin-top:var(--space-lg)">' +
          '<button class="btn btn--ghost" type="button" data-close-modal>Cancel</button>' +
          '<button class="btn btn--primary" type="button" id="um-create">Create user</button>' +
        "</div>";
      dom.on(el("um-role"), "change", function () { syncCapsToPreset(this.value); });
      syncCapsToPreset("user");
      wirePermBlock();
      wireGenPw(body);
      dom.on(el("um-create"), "click", createUser);
      EZ.ui.openModal("user-modal");
      var u = el("um-username"); if (u) { u.focus(); }
    } else {
      usersDirty = false;
      var onClose = function () { if (usersDirty) { usersDirty = false; loadUsers(); } };
      document.body.style.cursor = "progress";
      var done = function () { document.body.style.cursor = ""; };
      EZ.api.get("/users/get/" + encodeURIComponent(id)).then(function (res) {
        renderEditUser(res.data || {});
        EZ.ui.openModal("user-modal", onClose);
      }).catch(function () {
        title.textContent = "User";
        body.innerHTML = '<p class="field-hint">Could not load this user.</p>';
        EZ.ui.openModal("user-modal", onClose);
      }).then(done, done);
    }
  }

  function renderEditUser(user) {
    var body = el("user-modal-body"), title = el("user-modal-title");
    var self = (EZ.state.user || {}).id;
    var isSelf = String(user.id) === String(self);
    title.textContent = user.username || "User";
    var effMap = user.capabilities || effective(user);

    var canEditUsername = util.can("user.manage");
    var usernameField = canEditUsername
      ? '<div class="field"><label class="field-label" for="um-username">Username</label>' +
          '<div class="copyfield"><input class="input" id="um-username" value="' + util.escapeHtml(user.username || "") + '" readonly autocapitalize="none" spellcheck="false">' +
          '<button class="copyfield__btn" type="button" id="um-username-edit" aria-label="Edit username" title="Edit username">' +
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 20h9" stroke-linecap="round"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" stroke-linecap="round" stroke-linejoin="round"/></svg>' +
          "</button></div></div>"
      : '<div class="field"><label class="field-label">Username</label><input class="input" value="' + util.escapeHtml(user.username || "") + '" disabled></div>';

    body.innerHTML =
      usernameField +
      (isSelf ? "" :
        '<div class="field" style="margin-top:var(--space-md)"><label class="field-label" for="um-newpw">Reset password</label>' +
          '<div class="inputrow">' + pwField("um-newpw", "New password") + '<button class="btn btn--primary" type="button" id="um-reset">Reset</button></div></div>') +
      '<hr class="um-divider">' +
      permBlock(user.role || "user", effMap, hasOverrides(user)) +
      '<hr class="um-divider">' +
      (isSelf
        ? '<div class="notice"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 8v5m0 3h.01" stroke-linecap="round"/><circle cx="12" cy="12" r="9"/></svg><span>This is your own account. Change your password and 2FA on the <a class="link" href="#" data-screen="account">Account</a> screen.</span></div>'
        : '<div class="listrow"><div class="listrow__main"><div class="cell-strong">Banned</div>' +
            '<div class="faint" style="font-size:var(--text-xs)">A banned user cannot sign in and their alerts are suppressed.</div></div>' +
            '<label class="switch"><input type="checkbox" id="um-banned"' + (user.banned ? " checked" : "") + '><span class="switch__track"></span></label></div>' +
          '<div class="dangerzone"><div class="dangerzone__text"><div class="cell-strong">Delete user</div>' +
            '<div class="faint" style="font-size:var(--text-xs)">Permanently removes this account and its access.</div></div>' +
            '<button class="btn btn--sm" type="button" id="um-delete">Delete</button></div>') +
      '<p class="field-hint" id="um-msg" hidden></p>' +
      '<div style="display:flex;gap:var(--space-sm);justify-content:flex-end;margin-top:var(--space-lg)">' +
        '<button class="btn btn--ghost" type="button" data-close-modal>Close</button>' +
      "</div>";

    if (canEditUsername) {
      var unameInput = el("um-username"), unameEdit = el("um-username-edit");
      var savingUsername = false;
      if (unameEdit && unameInput) {
        dom.on(unameEdit, "click", function () {
          unameInput.readOnly = false;
          unameInput.focus();
          unameInput.select();
        });
        var commitUsername = function () {
          if (unameInput.readOnly || savingUsername) { return; }
          var name = unameInput.value.trim();
          if (name === "" || name === (user.username || "")) {
            unameInput.value = user.username || "";
            unameInput.readOnly = true;
            umMsg("");
            return;
          }
          savingUsername = true;
          saveUser(user.id, { username: name }, function () {
            user.username = name;
            unameInput.value = name;
            unameInput.readOnly = true;
            savingUsername = false;
            title.textContent = name;
            umMsg("Username updated.", false);
          }, function (err) {
            savingUsername = false;
            umMsg((err && err.message) || "Could not update the username.", true);
            unameInput.focus();
          });
        };
        dom.on(unameInput, "blur", commitUsername);
        dom.on(unameInput, "keydown", function (e) {
          if (e.key === "Enter") { e.preventDefault(); unameInput.blur(); }
          else if (e.key === "Escape") { unameInput.value = user.username || ""; unameInput.readOnly = true; umMsg(""); }
        });
      }
    }

    wirePermBlock();
    wireGenPw(body);

    if (isSelf) {
      el("um-role").disabled = true;
      dom.qsa("#um-caps input").forEach(function (i) { i.disabled = true; });
    } else {
      dom.on(el("um-role"), "change", function () {
        var role = this.value;
        saveUser(user.id, { role: role }, function () {
          user.role = role;
          syncCapsToPreset(role);
        });
      });
      dom.qsa("#um-caps input[data-cap]").forEach(function (i) {
        dom.on(i, "change", function () {
          syncBehaviourMaster();
          var role = el("um-role").value;
          saveUser(user.id, { permissions: overridesFor(role) }, null);
        });
      });
      dom.on(el("um-reset"), "click", function () {
        var pw = (el("um-newpw") || {}).value || "";
        if (!pw) { umMsg("Enter a new password.", true); return; }
        saveUser(user.id, { password: pw }, function () { el("um-newpw").value = ""; umMsg("Password reset.", false); });
      });
      dom.on(el("um-delete"), "click", function () { deleteUser(user); });
      dom.on(el("um-banned"), "change", function () {
        var checked = this.checked;
        saveUser(user.id, { banned: checked ? 1 : 0 }, function () {
          user.banned = checked ? 1 : 0;
          umMsg(checked ? "User banned." : "User unbanned.", false);
        }, function (err) {
          var box = el("um-banned");
          if (box) { box.checked = !checked; }
          umMsg((err && err.message) || "Could not update ban state.", true);
        });
      });
    }
  }

  function saveUser(id, body, ok, fail) {
    EZ.api.post("/users/update/" + encodeURIComponent(id), body).then(function () {
      EZ.ui.flashSaved();
      if (ok) { ok(); }
      usersDirty = true;
    }).catch(function (err) {
      if (fail) { fail(err); } else { umMsg(err.message || "Update failed.", true); }
    });
  }

  var rolePrefsDirty = false;
  function rdMsg(text) { var m = el("rd-msg"); if (m) { m.hidden = !text; m.textContent = text || ""; } }

  function openRolesModal() {
    var um = el("user-modal-body"); if (um) { um.innerHTML = ""; }
    var body = el("role-defaults-body");
    if (!body) { return; }
    var t = el("role-defaults-title"); if (t) { t.textContent = "Roles"; }
    rolePrefsDirty = false;
    EZ.ui.openModal("role-defaults-modal", function () { if (rolePrefsDirty) { rolePrefsDirty = false; loadRoles().then(loadUsers); } });
    renderRolesList();
  }

  function renderRolesList() {
    var body = el("role-defaults-body");
    body.innerHTML = EZ.ui.loading();
    loadRoles().then(function () {
      var map = rolesData || {};
      var rows = Object.keys(map).map(function (name) {
        var r = map[name];
        var right = r.locked
          ? '<span class="faint" style="font-size:var(--text-xs)">All permissions</span>'
          : '<button class="btn btn--sm" type="button" data-role-edit="' + util.escapeHtml(name) + '">Edit</button>' +
            (r.deletable ? ' <button class="btn btn--sm" type="button" data-role-del="' + util.escapeHtml(name) + '">Delete</button>' : "");
        return '<div class="rolerow"><div class="rolerow__main"><div class="cell-strong">' + util.escapeHtml(name) + "</div>" +
          (r.builtin ? '<div class="faint" style="font-size:var(--text-xs)">Built-in</div>' : "") + "</div>" +
          '<div class="rolerow__act">' + right + "</div></div>";
      }).join("");
      body.innerHTML =
        '<p class="field-hint" style="margin-bottom:var(--space-md)">Roles set the starting permissions for the users on them. A user with customized permissions keeps their own. Admin always holds everything.</p>' +
        '<div class="rolelist">' + rows + "</div>" +
        '<div class="inputrow" style="margin-top:var(--space-md)"><input class="input" id="role-new-name" placeholder="New role name" autocapitalize="none" spellcheck="false"><button class="btn btn--primary" type="button" id="role-new-btn">Create role</button></div>' +
        '<p class="field-hint" id="rd-msg" hidden></p>';
      dom.qsa("[data-role-edit]", body).forEach(function (b) { dom.on(b, "click", function () { openRoleEditor(b.getAttribute("data-role-edit")); }); });
      dom.qsa("[data-role-del]", body).forEach(function (b) { dom.on(b, "click", function () { deleteRole(b.getAttribute("data-role-del")); }); });
      dom.on(el("role-new-btn"), "click", createRole);
      dom.on(el("role-new-name"), "keydown", function (e) { if (e.key === "Enter") { createRole(); } });
    }, function () { body.innerHTML = '<p class="field-hint">Could not load roles.</p>'; });
  }

  function openRoleEditor(name) {
    var body = el("role-defaults-body");
    var caps = (rolesData && rolesData[name]) ? rolesData[name].capabilities : presetMap(name);
    body.innerHTML =
      '<div class="permhead"><button class="btn btn--sm" type="button" id="role-back">&larr; Roles</button>' +
        '<span class="cell-strong" style="margin-left:var(--space-sm)">' + util.escapeHtml(name) + "</span></div>" +
      '<div style="margin-top:var(--space-md)">' + capEditor(caps, false) + "</div>" +
      '<p class="field-hint" id="rd-msg" hidden></p>';
    syncBehaviourMaster();
    dom.on(el("role-back"), "click", renderRolesList);
    dom.qsa("#role-defaults-body input[data-cap]").forEach(function (i) {
      dom.on(i, "change", function () { syncBehaviourMaster(); saveRole(name); });
    });
  }

  function saveRole(name) {
    var caps = readCapsIn(el("role-defaults-body"));
    EZ.api.post("/users/roles/" + encodeURIComponent(name), { permissions: caps }).then(function (res) {
      setRoles(res); rolePrefsDirty = true; EZ.ui.flashSaved();
    }).catch(function (err) { rdMsg((err && err.message) || "Could not save."); });
  }

  function createRole() {
    var inp = el("role-new-name"); var raw = (inp && inp.value || "").trim();
    if (!raw) { if (inp) { inp.focus(); } return; }
    EZ.api.post("/users/roles", { name: raw, permissions: presetMap("user") }).then(function (res) {
      setRoles(res); rolePrefsDirty = true; openRoleEditor(roleSlug(raw));
    }).catch(function (err) { rdMsg((err && err.message) || "Could not create the role."); });
  }

  function deleteRole(name) {
    EZ.ui.confirm({
      title: "Delete role", danger: true, confirmLabel: "Delete",
      message: "Delete role “" + name + "”? Its users move to the user role."
    }).then(function (ok) {
      if (!ok) { return; }
      EZ.api.del("/users/roles/" + encodeURIComponent(name)).then(function (res) {
        setRoles(res); rolePrefsDirty = true; EZ.ui.toast("Role deleted.", "success"); renderRolesList();
      }).catch(function (err) { EZ.ui.toast((err && err.message) || "Could not delete the role.", "error"); });
    });
  }

  function createUser() {
    var username = (el("um-username") || {}).value.trim();
    var password = (el("um-password") || {}).value || "";
    var role = (el("um-role") || {}).value || "user";
    if (!username || !password) { umMsg("Username and password are required.", true); return; }
    var ov = overridesFor(role);
    var body = { username: username, password: password, role: role };
    if (Object.keys(ov).length) { body.permissions = ov; }
    umMsg("");
    EZ.api.post("/users", body).then(function () {
      EZ.ui.closeModalById("user-modal");
      EZ.ui.toast("User created.", "success");
      var searchBox = el("users-search");
      if (searchBox) { searchBox.value = ""; }
      usersState.search = "";
      usersState.page = 1;
      var q = "?page=1&per_page=" + USERS_PER_PAGE;
      EZ.api.get("/users" + q).then(function (res) {
        var meta = (res.meta && res.meta.pagination) || {};
        var pages = meta.pages ? parseInt(meta.pages, 10) : 1;
        usersState.page = pages > 0 ? pages : 1;
        loadUsers();
      }).catch(function () { loadUsers(); });
    }).catch(function (err) { umMsg(err.message || "Could not create the user.", true); });
  }

  function deleteUser(user) {
    EZ.ui.confirm({
      title: "Delete user", danger: true, confirmLabel: "Delete",
      message: "Delete user \u201c" + (user.username || "") + "\u201d? This cannot be undone."
    }).then(function (ok) {
      if (!ok) { return; }
      EZ.api.del("/users/delete/" + encodeURIComponent(user.id)).then(function () {
        EZ.ui.closeModalById("user-modal");
        EZ.ui.toast("User deleted.", "success");
        loadUsers();
      }).catch(function (err) { umMsg(err.message || "Could not delete the user.", true); });
    });
  }

  EZ.router.register("users", {
    mount: function () {
      var pu = panel("users");
      if (!pu) { return; }
      if (util.can("user.manage")) { loadRoles().then(renderUsersTab, renderUsersTab); }
      else { pu.innerHTML = EZ.ui.lockMsg(); }
    },
    resetState: function () {
      rolesData = null;
      usersState = { page: 1, search: "" };
      usersDirty = false;
    }
  });

})(window.EZ);
