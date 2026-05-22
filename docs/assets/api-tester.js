(function () {
  var SECURE_STORAGE_KEY = "smartadmin.docs.secure.v1";
  var SECURE_KEY_STORAGE_KEY = "smartadmin.docs.secure.key.v1";
  var DEFAULT_STATE = {
    baseUrl: "",
    token: "",
    username: "admin",
    password: "admin",
    passwordTransport: "server-rsa",
    rememberCredential: true,
    updatedAt: "",
  };

  var PASSWORD_PURPOSES = {
    login: "system.auth.login.password",
    changeOld: "system.auth.password.old_password",
    changeNew: "system.auth.password.new_password",
    userCreate: "system.user.create.password",
    userUpdate: "system.user.update.password",
    userReset: "system.user.reset_password.password",
  };

  var secureState = normalizeSecureState({});
  var secureReady = loadSecureState();
  var activeControls = null;

  function getTesterConfig() {
    return window.$docsify && window.$docsify.apiTester && typeof window.$docsify.apiTester === "object"
      ? window.$docsify.apiTester
      : {};
  }

  function getDefaultBaseUrl() {
    var configured = getTesterConfig().defaultBaseUrl;
    var location = window.location || {};
    var protocol = location.protocol || "http:";
    var host = location.hostname || "127.0.0.1";

    if (configured) {
      return String(configured).replace(/\/+$/, "");
    }

    // 本地文档通常由 composer docs:serve 暴露在 18100，后端 Hyperf 默认监听 9501。
    if (/^(localhost|127\.0\.0\.1|0\.0\.0\.0)$/i.test(host)) {
      return protocol + "//" + host + ":9501";
    }

    return (location.origin || "http://127.0.0.1:9501").replace(/\/+$/, "");
  }

  function cleanPath(path) {
    var value = String(path || "").replace(/[?#].*$/, "");
    if (/^https?:\/\//i.test(value)) {
      try {
        return new URL(value).pathname;
      } catch (error) {
        return value;
      }
    }
    return value;
  }

  function inferAuthEntry(path) {
    return /^\/project(?:\/|$)/.test(cleanPath(path)) ? "project" : "system";
  }

  function resolveAuthEntry(controls, path) {
    var selected = controls && controls.authEntry ? controls.authEntry.value : "auto";
    if (selected === "system" || selected === "project") {
      return selected;
    }
    return inferAuthEntry(path);
  }

  function authEntryLabel(entry) {
    return entry === "project" ? "Project 应用入口" : "System 后台入口";
  }

  function authRoutes(entry) {
    return entry === "project"
      ? {
        login: "/project/account/auth/login",
        logout: "/project/account/auth/logout",
        password: "/project/account/auth/password",
        passwordCrypto: "/project/account/auth/password-crypto",
      }
      : {
        login: "/system/auth/login",
        logout: "/system/auth/logout",
        password: "/system/auth/password",
        passwordCrypto: "/system/auth/password-crypto",
      };
  }

  function storageGet(key, fallback) {
    try {
      var value = localStorage.getItem(key);
      return value === null ? fallback : value;
    } catch (error) {
      return fallback;
    }
  }

  function storageSet(key, value) {
    try {
      localStorage.setItem(key, value);
      return true;
    } catch (error) {
      return false;
    }
  }

  function storageRemove(key) {
    try {
      localStorage.removeItem(key);
    } catch (error) {
      // 浏览器隐私模式或存储被禁用时，不阻断接口调试主流程。
    }
  }

  function safeJson(value, fallback) {
    if (!value || !String(value).trim()) {
      return fallback;
    }
    return JSON.parse(normalizeJsonc(value));
  }

  function normalizeJsonc(value) {
    return stripTrailingCommas(stripJsonComments(String(value || "")));
  }

  function stripTrailingCommas(value) {
    return value.replace(/,\s*([}\]])/g, "$1");
  }

  function stripJsonComments(value) {
    var output = "";
    var inString = false;
    var quote = "";
    var escaped = false;

    for (var index = 0; index < value.length; index += 1) {
      var char = value[index];
      var next = value[index + 1];

      if (inString) {
        output += char;
        if (escaped) {
          escaped = false;
        } else if (char === "\\") {
          escaped = true;
        } else if (char === quote) {
          inString = false;
          quote = "";
        }
        continue;
      }

      if (char === "\"" || char === "'") {
        inString = true;
        quote = char;
        output += char;
        continue;
      }

      if (char === "/" && next === "/") {
        while (index < value.length && !["\n", "\r"].includes(value[index])) {
          index += 1;
        }
        output += value[index] || "";
        continue;
      }

      if (char === "/" && next === "*") {
        index += 2;
        while (index < value.length && !(value[index] === "*" && value[index + 1] === "/")) {
          index += 1;
        }
        index += 1;
        continue;
      }

      output += char;
    }

    return output;
  }

  function formatJson(value) {
    if (value === undefined || value === null || value === "") {
      return "";
    }
    return JSON.stringify(value, null, 2);
  }

  function tryFormatResponse(text) {
    if (!text) {
      return "";
    }
    try {
      return JSON.stringify(JSON.parse(text), null, 2);
    } catch (error) {
      return text;
    }
  }

  function formatResponseBody(result) {
    var output = result.parsed !== null ? JSON.stringify(result.parsed, null, 2) : tryFormatResponse(result.text);
    return String(output || "").trim() ? output : "(响应体为空)";
  }

  function parseResponseJson(text) {
    if (!text) {
      return null;
    }
    try {
      return JSON.parse(text);
    } catch (error) {
      return null;
    }
  }

  function createNode(tag, className, text) {
    var node = document.createElement(tag);
    if (className) {
      node.className = className;
    }
    if (text !== undefined) {
      node.textContent = text;
    }
    return node;
  }

  function createField(label, input) {
    var field = createNode("label", "api-tester__field");
    field.appendChild(createNode("span", "api-tester__label", label));
    field.appendChild(input);
    return field;
  }

  function createInput(type, value, placeholder) {
    var input = document.createElement("input");
    input.type = type;
    input.value = value || "";
    if (placeholder) {
      input.placeholder = placeholder;
    }
    return input;
  }

  function createTextarea(value, placeholder) {
    var textarea = document.createElement("textarea");
    textarea.value = value || "";
    if (placeholder) {
      textarea.placeholder = placeholder;
    }
    return textarea;
  }

  function createCheckbox(label, checked) {
    var wrapper = createNode("label", "api-tester__check");
    var input = document.createElement("input");
    input.type = "checkbox";
    input.checked = checked;
    wrapper.appendChild(input);
    wrapper.appendChild(createNode("span", "", label));
    return { wrapper: wrapper, input: input };
  }

  function createPasswordTransportSelect(value) {
    var select = document.createElement("select");
    var options = [
      ["server-rsa", "服务端 RSA-OAEP 加密"],
      ["body", "使用 Body 中已有加密对象"],
    ];

    options.forEach(function (item) {
      var option = document.createElement("option");
      option.value = item[0];
      option.textContent = item[1];
      option.selected = item[0] === value;
      select.appendChild(option);
    });

    return select;
  }

  function createAuthEntrySelect(value) {
    var select = document.createElement("select");
    var options = [
      ["auto", "自动识别入口"],
      ["system", "System 后台"],
      ["project", "Project 应用"],
    ];

    options.forEach(function (item) {
      var option = document.createElement("option");
      option.value = item[0];
      option.textContent = item[1];
      option.selected = item[0] === value;
      select.appendChild(option);
    });

    return select;
  }

  function hasCrypto() {
    return Boolean(window.crypto && window.crypto.subtle && window.TextEncoder && window.TextDecoder);
  }

  function bytesToBase64(bytes) {
    var binary = "";
    var chunkSize = 8192;
    for (var index = 0; index < bytes.length; index += chunkSize) {
      binary += String.fromCharCode.apply(null, bytes.slice(index, index + chunkSize));
    }
    return btoa(binary);
  }

  function base64ToBytes(value) {
    var binary = atob(value);
    var bytes = new Uint8Array(binary.length);
    for (var index = 0; index < binary.length; index += 1) {
      bytes[index] = binary.charCodeAt(index);
    }
    return bytes;
  }

  async function getSecureKey() {
    if (!hasCrypto()) {
      throw new Error("当前浏览器不支持 Web Crypto，无法启用加密缓存");
    }

    var rawKey = storageGet(SECURE_KEY_STORAGE_KEY, "");
    if (!rawKey) {
      var keyBytes = new Uint8Array(32);
      window.crypto.getRandomValues(keyBytes);
      rawKey = bytesToBase64(keyBytes);
      storageSet(SECURE_KEY_STORAGE_KEY, rawKey);
    }

    return window.crypto.subtle.importKey(
      "raw",
      base64ToBytes(rawKey),
      { name: "AES-GCM" },
      false,
      ["encrypt", "decrypt"]
    );
  }

  async function encryptState(state) {
    var key = await getSecureKey();
    var iv = new Uint8Array(12);
    window.crypto.getRandomValues(iv);
    var payload = new TextEncoder().encode(JSON.stringify(state));
    var encrypted = await window.crypto.subtle.encrypt({ name: "AES-GCM", iv: iv }, key, payload);

    return JSON.stringify({
      version: 1,
      alg: "AES-GCM",
      iv: bytesToBase64(iv),
      data: bytesToBase64(new Uint8Array(encrypted)),
    });
  }

  async function decryptState(payload) {
    var parsed = JSON.parse(payload);
    var key = await getSecureKey();
    var decrypted = await window.crypto.subtle.decrypt(
      { name: "AES-GCM", iv: base64ToBytes(parsed.iv) },
      key,
      base64ToBytes(parsed.data)
    );
    return JSON.parse(new TextDecoder().decode(decrypted));
  }

  function normalizeSecureState(value) {
    var passwordTransport = ["server-rsa", "body"].includes(String(value.passwordTransport || ""))
      ? String(value.passwordTransport)
      : DEFAULT_STATE.passwordTransport;

    return {
      baseUrl: String(value.baseUrl || DEFAULT_STATE.baseUrl || getDefaultBaseUrl()),
      token: String(value.token || DEFAULT_STATE.token),
      username: String(value.username || DEFAULT_STATE.username),
      password: String(value.password || DEFAULT_STATE.password),
      passwordTransport: passwordTransport,
      rememberCredential: value.rememberCredential !== false,
      updatedAt: String(value.updatedAt || ""),
    };
  }

  async function persistSecureState() {
    secureState.updatedAt = new Date().toISOString();
    var encrypted = await encryptState(secureState);
    storageSet(SECURE_STORAGE_KEY, encrypted);
  }

  // 首次加载只读取当前加密缓存，并把默认测试账号写入加密缓存。
  async function loadSecureState() {
    var stored = {};
    var encrypted = storageGet(SECURE_STORAGE_KEY, "");

    if (encrypted) {
      try {
        stored = await decryptState(encrypted);
      } catch (error) {
        storageRemove(SECURE_STORAGE_KEY);
      }
    }

    secureState = normalizeSecureState(stored);

    try {
      await persistSecureState();
    } catch (error) {
      // 加密缓存不可用时只保留内存态，避免把 Token 或密码降级明文存储。
    }

    return secureState;
  }

  async function persistFromControls(controls) {
    secureState.baseUrl = controls.base.value.trim();
    secureState.token = controls.token.value.trim();
    secureState.passwordTransport = controls.passwordTransport.value;
    secureState.rememberCredential = controls.rememberCredential.checked;
    secureState.username = controls.rememberCredential.checked ? controls.username.value.trim() : DEFAULT_STATE.username;
    secureState.password = controls.rememberCredential.checked ? controls.password.value : DEFAULT_STATE.password;

    try {
      await persistSecureState();
      return "";
    } catch (error) {
      return "加密缓存不可用，当前配置只在本次页面会话中生效";
    }
  }

  async function clearMemory(controls) {
    storageRemove(SECURE_STORAGE_KEY);
    storageRemove(SECURE_KEY_STORAGE_KEY);
    secureState = normalizeSecureState({});
    controls.base.value = secureState.baseUrl;
    controls.token.value = "";
    controls.username.value = secureState.username;
    controls.password.value = secureState.password;
    controls.passwordTransport.value = secureState.passwordTransport;
    controls.rememberCredential.checked = true;
    controls.status.textContent = "已清除本地加密缓存，账号密码恢复为默认 admin/admin";
    controls.status.classList.remove("api-tester__error");
    controls.output.textContent = "";
  }

  function extractTokenValue(payload) {
    if (!payload) {
      return "";
    }

    if (typeof payload === "string") {
      return payload.trim();
    }

    if (typeof payload !== "object") {
      return "";
    }

    var candidates = [payload.data && payload.data.token, typeof payload.data === "string" ? payload.data : ""];

    return String(candidates.find(function (item) {
      return typeof item === "string" && item.trim();
    }) || "").trim();
  }

  function updateTokenValue(token, controls) {
    secureState.token = token || "";
    if (controls && controls.token) {
      controls.token.value = secureState.token;
    }
  }

  async function updateTokenFromResponse(parsed, controls) {
    var token = extractTokenValue(parsed);
    if (!token) {
      return "";
    }

    updateTokenValue(token, controls);
    await persistFromControls(controls);
    return "Token 已自动更新";
  }

  function normalizeQuery(query) {
    if (Array.isArray(query)) {
      return query.reduce(function (carry, item) {
        if (item && item.name) {
          carry[item.name] = item.value === undefined ? "" : item.value;
        }
        return carry;
      }, {});
    }
    return query && typeof query === "object" ? query : {};
  }

  function buildUrl(baseUrl, path, query) {
    var trimmedBase = String(baseUrl || "").replace(/\/+$/, "");
    var trimmedPath = String(path || "");
    if (!trimmedBase && !/^https?:\/\//i.test(trimmedPath)) {
      throw new Error("缺少 Base URL，请填写例如 " + getDefaultBaseUrl());
    }
    var url = /^https?:\/\//i.test(trimmedPath)
      ? new URL(trimmedPath)
      : new URL(trimmedBase + "/" + trimmedPath.replace(/^\/+/, ""));

    Object.keys(query).forEach(function (key) {
      var value = query[key];
      if (value !== undefined && value !== null && value !== "") {
        url.searchParams.set(key, String(value));
      }
    });

    return url.toString();
  }

  function isLoginPath(path) {
    var pathOnly = cleanPath(path);
    return pathOnly === authRoutes("system").login || pathOnly === authRoutes("project").login;
  }

  function isLogoutPath(path) {
    var pathOnly = cleanPath(path);
    return pathOnly === authRoutes("system").logout || pathOnly === authRoutes("project").logout;
  }

  function isUnauthorized(result) {
    return result.response.status === 401 || Number(result.businessCode) === 401;
  }

  function isBusinessSuccess(result) {
    return result.response.ok && (result.businessCode === null || Number(result.businessCode) === 200);
  }

  function revealResult(controls, isError) {
    if (!controls.result) {
      return;
    }

    controls.result.classList.remove("api-tester__result--empty", "api-tester__result--loading", "api-tester__result--error");
    if (isError) {
      controls.result.classList.add("api-tester__result--error");
    }

    requestAnimationFrame(function () {
      controls.result.scrollIntoView({ behavior: "smooth", block: "nearest" });
    });
  }

  function hasCredentials(controls) {
    return Boolean(controls.username.value.trim() && controls.password.value);
  }

  function pemToBytes(pem) {
    var base64 = String(pem || "")
      .replace(/-----BEGIN PUBLIC KEY-----/g, "")
      .replace(/-----END PUBLIC KEY-----/g, "")
      .replace(/\s+/g, "");
    return base64ToBytes(base64);
  }

  async function importServerPublicKey(pem) {
    if (!hasCrypto()) {
      throw new Error("当前浏览器不支持 Web Crypto，无法执行 RSA-OAEP 密码加密");
    }

    return window.crypto.subtle.importKey(
      "spki",
      pemToBytes(pem),
      { name: "RSA-OAEP", hash: "SHA-1" },
      false,
      ["encrypt"]
    );
  }

  async function fetchPasswordCryptoParameters(controls, count, path) {
    var entry = resolveAuthEntry(controls, path);
    var url = buildUrl(controls.base.value.trim(), authRoutes(entry).passwordCrypto, { count: count });
    var response = await fetch(url, {
      method: "GET",
      headers: { "Accept": "application/json" },
    });
    var text = await response.text();
    var parsed = parseResponseJson(text);
    var businessCode = parsed && Object.prototype.hasOwnProperty.call(parsed, "code") ? parsed.code : null;
    var data = parsed && parsed.data && typeof parsed.data === "object" ? parsed.data : parsed;

    if (!response.ok || (businessCode !== null && Number(businessCode) !== 200)) {
      throw new Error("获取 " + authEntryLabel(entry) + " 密码加密参数失败：" + ((parsed && parsed.info) || ("HTTP " + response.status)));
    }
    if (!data || data.alg !== "RSA-OAEP" || data.hash !== "SHA-1" || !data.public_key || !Array.isArray(data.nonces)) {
      throw new Error("密码加密参数响应格式无效");
    }
    if (data.nonces.length < count) {
      throw new Error("密码加密 nonce 数量不足");
    }

    return data;
  }

  async function encryptPasswordValue(publicKey, kid, nonce, purpose, value) {
    var payload = JSON.stringify({
      v: 1,
      purpose: purpose,
      nonce: nonce,
      ts: Math.floor(Date.now() / 1000),
      value: String(value || ""),
    });
    var encrypted = await window.crypto.subtle.encrypt(
      { name: "RSA-OAEP" },
      publicKey,
      new TextEncoder().encode(payload)
    );

    return {
      kid: kid,
      nonce: nonce,
      ciphertext: bytesToBase64(new Uint8Array(encrypted)),
    };
  }

  function passwordPurposeMap(path, body) {
    var pathOnly = cleanPath(path);
    var fields = {};
    if (isLoginPath(pathOnly)) {
      fields.password = PASSWORD_PURPOSES.login;
      return fields;
    }
    if (pathOnly === authRoutes("system").password || pathOnly === authRoutes("project").password) {
      fields.old_password = PASSWORD_PURPOSES.changeOld;
      fields.new_password = PASSWORD_PURPOSES.changeNew;
      return fields;
    }
    if (/\/system\/user\/create$/.test(pathOnly) && Object.prototype.hasOwnProperty.call(body, "password")) {
      fields.password = PASSWORD_PURPOSES.userCreate;
      return fields;
    }
    if (/\/system\/user\/update\/[^/]+$/.test(pathOnly) && Object.prototype.hasOwnProperty.call(body, "password")) {
      fields.password = PASSWORD_PURPOSES.userUpdate;
      return fields;
    }
    if (/\/system\/user\/reset-password\/[^/]+$/.test(pathOnly)) {
      fields.password = PASSWORD_PURPOSES.userReset;
      return fields;
    }

    return fields;
  }

  async function encryptPasswordFieldsForRequest(controls, path, body) {
    var purposes = passwordPurposeMap(path, body);
    var fields = Object.keys(purposes).filter(function (field) {
      return typeof body[field] === "string";
    });
    if (fields.length === 0) {
      return body;
    }

    var params = await fetchPasswordCryptoParameters(controls, fields.length, path);
    var publicKey = await importServerPublicKey(params.public_key);
    for (var index = 0; index < fields.length; index += 1) {
      var field = fields[index];
      body[field] = await encryptPasswordValue(publicKey, params.kid, params.nonces[index], purposes[field], body[field]);
    }

    return body;
  }

  function applyAuthorizationHeader(headers, token) {
    var key = Object.prototype.hasOwnProperty.call(headers, "Authorization") ? "Authorization" : "authorization";
    var value = headers.Authorization || headers.authorization || "";

    if (token && (!value || /<token>/i.test(value))) {
      delete headers.authorization;
      headers.Authorization = "Bearer " + token;
      return headers;
    }

    if (!token && /<token>/i.test(value)) {
      delete headers[key];
    }

    return headers;
  }

  async function prepareRequestBody(config, controls) {
    var bodyText = controls.body.value.trim();
    if (["GET", "HEAD"].includes(controls.method.value.toUpperCase()) || !bodyText) {
      return bodyText;
    }

    var pathOnly = cleanPath(controls.path.value);
    var isPasswordPath = isLoginPath(pathOnly)
      || pathOnly === authRoutes("system").password
      || pathOnly === authRoutes("project").password
      || /\/system\/user\/create$/.test(pathOnly)
      || /\/system\/user\/update\/[^/]+$/.test(pathOnly)
      || /\/system\/user\/reset-password\/[^/]+$/.test(pathOnly);
    if (!isPasswordPath) {
      return bodyText;
    }

    var body = safeJson(bodyText || "{}", {});
    if (isLoginPath(pathOnly)) {
      body.username = controls.username.value.trim();
      if (controls.passwordTransport.value !== "body") {
        body.password = controls.password.value;
      }
    }

    if (controls.passwordTransport.value === "server-rsa") {
      body = await encryptPasswordFieldsForRequest(controls, pathOnly, body);
      return formatJson(body).trim();
    }

    return formatJson(body).trim();
  }

  // 非登录接口缺少 Token 或 Token 失效时，复用缓存账号自动登录并回填 Bearer Token。
  async function loginWithCredentials(controls) {
    if (!hasCredentials(controls)) {
      throw new Error("缺少用户名或密码，无法自动登录生成 Token");
    }

    var baseUrl = controls.base.value.trim();
    var entry = resolveAuthEntry(controls, controls.path.value);
    var routes = authRoutes(entry);
    if (!baseUrl) {
      throw new Error("缺少 Base URL，无法自动登录生成 Token");
    }

    var url = buildUrl(baseUrl, routes.login, {});
    var loginBody = await encryptPasswordFieldsForRequest(controls, routes.login, {
      username: controls.username.value.trim(),
      password: controls.password.value,
    });

    var startedAt = performance.now();
    var response = await fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(loginBody),
    });
    var elapsed = Math.round(performance.now() - startedAt);
    var text = await response.text();
    var parsed = parseResponseJson(text);
    var businessCode = parsed && Object.prototype.hasOwnProperty.call(parsed, "code") ? parsed.code : null;
    var businessInfo = parsed && Object.prototype.hasOwnProperty.call(parsed, "info") ? parsed.info : "";
    var success = response.ok && (businessCode === null || Number(businessCode) === 200);

    if (!success) {
      throw new Error(authEntryLabel(entry) + "自动登录失败：" + (businessInfo || ("HTTP " + response.status)));
    }

    var token = extractTokenValue(parsed);
    if (!token) {
      throw new Error(authEntryLabel(entry) + "自动登录成功但响应中未识别到 Token");
    }

    updateTokenValue(token, controls);
    await persistFromControls(controls);

    return {
      elapsed: elapsed,
      token: token,
      message: authEntryLabel(entry) + "自动登录成功",
    };
  }

  async function performRequest(config, controls) {
    var method = controls.method.value.toUpperCase();
    var headers = safeJson(controls.headers.value, {});
    var query = normalizeQuery(safeJson(controls.query.value, {}));
    var token = controls.token.value.trim();
    var bodyText = await prepareRequestBody(config, controls);
    var url = buildUrl(controls.base.value.trim(), controls.path.value.trim(), query);
    var request = { method: method, headers: applyAuthorizationHeader(headers, token) };

    if (!["GET", "HEAD"].includes(method) && bodyText) {
      request.body = bodyText;
      if (!request.headers["Content-Type"] && !request.headers["content-type"]) {
        request.headers["Content-Type"] = "application/json";
      }
    }

    var startedAt = performance.now();
    var response = await fetch(url, request);
    var elapsed = Math.round(performance.now() - startedAt);
    var text = await response.text();
    var parsed = parseResponseJson(text);

    return {
      response: response,
      elapsed: elapsed,
      text: text,
      parsed: parsed,
      url: url,
      businessCode: parsed && Object.prototype.hasOwnProperty.call(parsed, "code") ? parsed.code : null,
      businessInfo: parsed && Object.prototype.hasOwnProperty.call(parsed, "info") ? parsed.info : "",
    };
  }

  async function sendRequest(config, controls) {
    var method = controls.method.value.toUpperCase();
    var loginPath = isLoginPath(controls.path.value);
    var authEntry = resolveAuthEntry(controls, controls.path.value);
    var statusParts = [];
    var cacheMessage = "";

    controls.button.disabled = true;
    controls.status.textContent = "请求中...";
    controls.status.classList.remove("api-tester__error");
    controls.output.textContent = "";
    controls.result.classList.remove("api-tester__result--empty", "api-tester__result--error");
    controls.result.classList.add("api-tester__result--loading");

    try {
      cacheMessage = await persistFromControls(controls);

      if (!loginPath && !controls.token.value.trim() && hasCredentials(controls)) {
        controls.status.textContent = "正在自动登录生成 Token...";
        statusParts.push((await loginWithCredentials(controls)).message);
      }

      var result = await performRequest(config, controls);
      var retried = false;

      // SmartAdmin 业务失败通常仍是 HTTP 200，401 需要同时检查 HTTP 状态和 body.code。
      if (!loginPath && isUnauthorized(result) && hasCredentials(controls)) {
        updateTokenValue("", controls);
        await persistFromControls(controls);
        controls.status.textContent = "Token 已失效，正在自动登录后重试...";
        statusParts.push("Token 已失效，已自动登录重试");
        await loginWithCredentials(controls);
        result = await performRequest(config, controls);
        retried = true;
      }

      if (isBusinessSuccess(result)) {
        var tokenMessage = await updateTokenFromResponse(result.parsed, controls);
        if (isLogoutPath(controls.path.value)) {
          updateTokenValue("", controls);
          await persistFromControls(controls);
          tokenMessage = "Token 已清理";
        }
        if (tokenMessage) {
          statusParts.push(tokenMessage);
        }
      } else if (isUnauthorized(result)) {
        updateTokenValue("", controls);
        await persistFromControls(controls);
        statusParts.push("Token 已失效并清理");
      }

      statusParts.unshift("HTTP " + result.response.status + " " + result.response.statusText);
      statusParts.push(authEntryLabel(authEntry));
      if (result.businessCode !== null) {
        statusParts.push("业务 code " + result.businessCode);
      }
      if (result.businessInfo) {
        statusParts.push(String(result.businessInfo));
      }
      if (retried) {
        statusParts.push("已重试 1 次");
      }
      if (cacheMessage) {
        statusParts.push(cacheMessage);
      }
      statusParts.push(method, result.elapsed + "ms", result.url);

      controls.status.textContent = statusParts.join(" · ");
      controls.output.textContent = formatResponseBody(result);
      var failed = !isBusinessSuccess(result);
      if (failed) {
        controls.status.classList.add("api-tester__error");
      }
      revealResult(controls, failed);
    } catch (error) {
      controls.status.textContent = "请求失败：" + (error && error.message ? error.message : String(error));
      controls.status.classList.add("api-tester__error");
      controls.output.textContent = "请检查 Base URL、CORS、HTTPS 证书、接口路径、Token、用户名和密码。";
      revealResult(controls, true);
    } finally {
      controls.button.disabled = false;
    }
  }

  function ensureModal() {
    var overlay = document.querySelector(".api-tester-modal");
    if (overlay) {
      return overlay;
    }

    overlay = createNode("div", "api-tester-modal");
    var dialog = createNode("div", "api-tester-modal__dialog");
    var bar = createNode("div", "api-tester-modal__bar");
    var title = createNode("strong", "api-tester-modal__title", "在线调试");
    var close = createNode("button", "api-tester-modal__close", "×");
    var content = createNode("div", "api-tester-modal__content");

    close.type = "button";
    close.setAttribute("aria-label", "关闭调试层");
    close.title = "关闭调试层";
    close.addEventListener("click", closeModal);
    bar.appendChild(title);
    bar.appendChild(close);
    dialog.appendChild(bar);
    dialog.appendChild(content);
    overlay.appendChild(dialog);
    document.body.appendChild(overlay);

    return overlay;
  }

  function closeModal() {
    var overlay = document.querySelector(".api-tester-modal");
    if (!overlay) {
      return;
    }
    overlay.classList.remove("api-tester-modal--open");
    document.body.classList.remove("api-tester-modal-open");
  }

  function buildControls(config) {
    var method = String(config.method || "GET").toUpperCase();
    var initialEntry = config.authEntry || "auto";
    var inferredEntry = inferAuthEntry(config.path || "");
    var panel = createNode("section", "api-tester");
    var header = createNode("div", "api-tester__header");
    var title = createNode("h3", "api-tester__title", config.name || config.title || "接口测试");
    var meta = createNode("div", "api-tester__meta");
    var workspace = createNode("div", "api-tester__workspace");
    var body = createNode("div", "api-tester__body");
    var row1 = createNode("div", "api-tester__row");
    var row2 = createNode("div", "api-tester__row api-tester__row--three");
    var row3 = createNode("div", "api-tester__row api-tester__row--three");
    var row4 = createNode("div", "api-tester__row");
    var credentialHint = createNode("div", "api-tester__credential-hint", "调试器会按登录入口选择密码加密参数：System 使用 /system/auth/password-crypto，Project 使用 /project/account/auth/password-crypto；缓存使用 AES-GCM 加密，但不抵御同源 XSS。");
    var bodyUsername = config.body && typeof config.body === "object" && typeof config.body.username === "string" ? config.body.username : "";
    var defaultUsername = inferredEntry === "project" ? "project_user" : "admin";
    var initialUsername = bodyUsername && secureState.username === DEFAULT_STATE.username ? bodyUsername : (secureState.username || defaultUsername);
    var baseInput = createInput("url", secureState.baseUrl || config.baseUrl || getDefaultBaseUrl(), getDefaultBaseUrl());
    var pathInput = createInput("text", config.path || "", "/system/auth/login");
    var methodSelect = document.createElement("select");
    var authEntrySelect = createAuthEntrySelect(initialEntry);
    var tokenInput = createInput("password", secureState.token || "", "自动维护，可留空");
    var usernameInput = createInput("text", initialUsername, defaultUsername);
    var passwordInput = createInput("password", secureState.password, "admin");
    var passwordTransportSelect = createPasswordTransportSelect(config.passwordTransport || secureState.passwordTransport);
    var rememberCredential = createCheckbox("加密记住账号密码", secureState.rememberCredential);
    var headersText = createTextarea(formatJson(config.headers || {}), "{\n  \"Content-Type\": \"application/json\"\n}");
    var queryText = createTextarea(formatJson(config.query || {}), "{\n  \"page\": 1\n}");
    var bodyText = createTextarea(formatJson(config.body || ""), "{\n  \"username\": \"admin\"\n}");
    var actions = createNode("div", "api-tester__actions");
    var button = createNode("button", "api-tester__button", "发送请求");
    var clearButton = createNode("button", "api-tester__button api-tester__button--secondary", "清除记忆");
    var hint = createNode("span", "api-tester__hint", "Base URL、Token、账号密码会使用 AES-GCM 加密后缓存到 localStorage。");
    var result = createNode("div", "api-tester__result api-tester__result--empty");
    var resultHeader = createNode("div", "api-tester__result-header");
    var resultTitle = createNode("strong", "api-tester__result-title", "响应结果");
    var copyButton = createNode("button", "api-tester__result-copy", "复制");
    var status = createNode("div", "api-tester__status", "等待请求");
    var output = createNode("pre", "api-tester__output");

    ["GET", "POST", "PUT", "PATCH", "DELETE", "HEAD"].forEach(function (item) {
      var option = document.createElement("option");
      option.value = item;
      option.textContent = item;
      option.selected = item === method;
      methodSelect.appendChild(option);
    });

    tokenInput.className = "api-tester__token";
    usernameInput.className = "api-tester__username";
    passwordInput.className = "api-tester__password";
    authEntrySelect.className = "api-tester__auth-entry";
    passwordTransportSelect.className = "api-tester__password-transport";
    headersText.classList.add("api-tester__textarea--headers");
    queryText.classList.add("api-tester__textarea--query");
    bodyText.classList.add("api-tester__textarea--body");
    button.type = "button";
    clearButton.type = "button";
    copyButton.type = "button";

    meta.appendChild(createNode("span", "api-tester__method", method));
    meta.appendChild(createNode("span", "api-tester__path", config.path || ""));
    header.appendChild(title);
    header.appendChild(meta);
    row1.appendChild(createField("Base URL", baseInput));
    row1.appendChild(createField("Path", pathInput));
    row2.appendChild(createField("Method", methodSelect));
    row2.appendChild(createField("登录入口", authEntrySelect));
    row2.appendChild(createField("Bearer Token", tokenInput));
    row3.appendChild(createField("Username", usernameInput));
    row3.appendChild(createField("Password", passwordInput));
    row3.appendChild(createField("登录密码传输方式", passwordTransportSelect));
    row4.appendChild(rememberCredential.wrapper);
    row4.appendChild(credentialHint);
    actions.appendChild(button);
    actions.appendChild(clearButton);
    actions.appendChild(hint);
    resultHeader.appendChild(resultTitle);
    resultHeader.appendChild(copyButton);
    result.appendChild(resultHeader);
    result.appendChild(status);
    result.appendChild(output);

    body.appendChild(row1);
    body.appendChild(row2);
    body.appendChild(row3);
    body.appendChild(row4);
    body.appendChild(createField("Headers JSON", headersText));
    body.appendChild(createField("Query JSON", queryText));
    body.appendChild(createField("Body JSON / Raw", bodyText));
    body.appendChild(actions);
    panel.appendChild(header);
    workspace.appendChild(body);
    workspace.appendChild(result);
    panel.appendChild(workspace);

    activeControls = {
      base: baseInput,
      path: pathInput,
      method: methodSelect,
      authEntry: authEntrySelect,
      token: tokenInput,
      username: usernameInput,
      password: passwordInput,
      passwordTransport: passwordTransportSelect,
      rememberCredential: rememberCredential.input,
      headers: headersText,
      query: queryText,
      body: bodyText,
      button: button,
      result: result,
      status: status,
      output: output,
      copy: copyButton,
    };

    [baseInput, tokenInput, usernameInput, passwordInput, authEntrySelect, passwordTransportSelect].forEach(function (input) {
      input.addEventListener("input", function () {
        persistFromControls(activeControls);
      });
      input.addEventListener("change", function () {
        persistFromControls(activeControls);
      });
    });
    rememberCredential.input.addEventListener("change", function () {
      persistFromControls(activeControls);
    });
    clearButton.addEventListener("click", function () {
      clearMemory(activeControls);
    });
    copyButton.addEventListener("click", function () {
      var text = output.textContent || "";
      if (!text) {
        status.textContent = "暂无响应内容可复制";
        return;
      }
      if (!navigator.clipboard || !navigator.clipboard.writeText) {
        status.textContent = "当前浏览器不支持剪贴板复制，请手动选择响应内容";
        return;
      }
      navigator.clipboard.writeText(text).then(function () {
        copyButton.textContent = "已复制";
        window.setTimeout(function () {
          copyButton.textContent = "复制";
        }, 1400);
      }).catch(function () {
        status.textContent = "复制失败，请手动选择响应内容";
      });
    });
    button.addEventListener("click", function () {
      sendRequest(config, activeControls);
    });

    return panel;
  }

  async function openModal(config) {
    await secureReady;
    var overlay = ensureModal();
    var content = overlay.querySelector(".api-tester-modal__content");
    content.innerHTML = "";
    content.appendChild(buildControls(config));
    overlay.classList.add("api-tester-modal--open");
    document.body.classList.add("api-tester-modal-open");
  }

  // api-test 代码块只渲染为调试按钮，真实表单统一放到弹窗中，避免长页面被表单淹没。
  function renderBlock(pre) {
    if (pre.dataset.apiTesterRendered === "1") {
      return;
    }

    var code = pre.querySelector("code") || pre;
    var config;
    try {
      config = safeJson(code.textContent, {});
    } catch (error) {
      var failed = createNode("div", "api-tester-trigger api-tester-trigger--error", "api-test 配置不是合法 JSON：" + error.message);
      pre.replaceWith(failed);
      return;
    }

    pre.dataset.apiTesterRendered = "1";

    var method = String(config.method || "GET").toUpperCase();
    var wrapper = createNode("div", "api-tester-trigger");
    var summary = createNode("div", "api-tester-trigger__summary");
    var button = createNode("button", "api-tester-trigger__button", "在线调试");

    button.type = "button";
    summary.appendChild(createNode("span", "api-tester__method", method));
    summary.appendChild(createNode("code", "api-tester-trigger__path", config.path || ""));
    wrapper.appendChild(summary);
    wrapper.appendChild(button);
    button.addEventListener("click", function () {
      openModal(config);
    });

    pre.replaceWith(wrapper);
  }

  function renderApiTester() {
    document.querySelectorAll('pre[data-lang="api-test"], pre code.lang-api-test').forEach(function (node) {
      renderBlock(node.matches("pre") ? node : node.closest("pre"));
    });
  }

  function install(hook) {
    hook.doneEach(renderApiTester);
  }

  window.$docsify = window.$docsify || {};
  window.$docsify.plugins = [].concat(install, window.$docsify.plugins || []);
})();
